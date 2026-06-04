<?php

namespace Uncanny_Automator\Integrations\Github;

use Exception;

/**
 * Class Github_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Github_Api_Caller $api
 * @property Github_Webhooks $webhooks
 */
class Github_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	/**
	 * The option name for the repos.
	 *
	 * @var string
	 */
	const REPO_OPTION = 'automator_github_repos';

	/**
	 * Repository field action meta key.
	 *
	 * @var string
	 */
	const ACTION_REPO_META_KEY = 'GITHUB_REPO';

	/**
	 * Issue or Pull Request field action meta key.
	 *
	 * @var string
	 */
	const ACTION_ISSUE_PR_META_KEY = 'GITHUB_ISSUE_PR';

	/**
	 * Get account info.
	 *
	 * @return array
	 */
	public function get_account_info() {
		$credentials = $this->get_credentials();
		return $credentials['user'] ?? array();
	}

	////////////////////////////////////////////////////////////
	// Recipe UI helper methods
	////////////////////////////////////////////////////////////

	/**
	 * Get repository option config.
	 *
	 * @param string $option_code
	 * @param string $event
	 *
	 * @return array
	 */
	public function get_repo_option_config( $option_code ) {
		return array(
			'input_type'      => 'select',
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Repository', 'GitHub', 'uncanny-automator' ),
			'required'        => true,
			'options'         => array(),
			'options_show_id' => false,
			'relevant_tokens' => array(),
			'remote_data'     => $this->remote_data_load_config( 'repos' ),
		);
	}

	/**
	 * Fetch the user's repositories, prepended with an empty placeholder when
	 * more than one repo exists so the dropdown can show "Select a repository".
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_repos( $request ): array {
		$repos   = $this->api->get_user_repos( $request->is_refresh() );
		$options = $this->format_repo_options( $repos );

		if ( count( $repos ) > 1 ) {
			$empty = array(
				'value' => '',
				'text'  => esc_html_x( 'Select a repository', 'GitHub', 'uncanny-automator' ),
			);
			array_unshift( $options, $empty );
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Format repo options.
	 *
	 * @param array $repos
	 *
	 * @return array
	 */
	private function format_repo_options( $repos ) {
		return array_map(
			function ( $repo ) {
				return array(
					'value' => $repo['owner'] . '/' . $repo['name'],
					'text'  => $repo['owner'] . ' : ' . $repo['name'],
				);
			},
			$repos
		);
	}

	/**
	 * Get server ID from $_POST.
	 *
	 * @param string $meta_key
	 *
	 * @return array
	 */
	public function get_repo_from_ajax( $meta_key = self::ACTION_REPO_META_KEY ) {
		$values = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();

		$full_repo = isset( $values[ $meta_key ] )
			? sanitize_text_field( wp_unslash( $values[ $meta_key ] ) )
			: '';

		return ! empty( $full_repo )
			? $this->get_repo_parts( $full_repo )
			: array();
	}

	/**
	 * Resolve the {owner, name} repo parts from a remote-data request's selected
	 * repo value. Mirrors {@see self::get_repo_from_ajax()} for the new framework.
	 *
	 * @param Remote_Data_Request $request  The remote-data request.
	 * @param string              $meta_key Meta key holding the `owner/name` value.
	 *
	 * @return array {owner, name} or empty when no repo is selected.
	 */
	private function get_repo_from_request( $request, $meta_key = self::ACTION_REPO_META_KEY ) {
		$full_repo = $request->get_field_value( $meta_key );
		return ! empty( $full_repo ) ? $this->get_repo_parts( $full_repo ) : array();
	}

	/**
	 * Get repository info from parsed.
	 *
	 * @param array $parsed
	 * @param string $meta_key
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_repo_parts_from_parsed( $parsed, $meta_key = self::ACTION_REPO_META_KEY ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Repository is required.', 'GitHub', 'uncanny-automator' ) );
		}

		$full_repo = sanitize_text_field( $parsed[ $meta_key ] );

		return $this->get_repo_parts( $full_repo );
	}

	/**
	 * Extract owner and repo name from full repository name.
	 *
	 * @param string $repo_name The full repository name (format: owner/repo-name).
	 *
	 * @return array
	 */
	public function get_repo_parts( $repo_name ) {
		$parts = explode( '/', $repo_name );
		// GitHub API guarantees owner/repo format with no slashes in owner or repo names
		// so this will always be exactly 2 parts the way we store and utilize.
		return array(
			'owner' => $parts[0] ?? '',
			'name'  => $parts[1] ?? '',
		);
	}

	/**
	 * Get issue or pull request option config.
	 *
	 * @return array
	 */
	public function get_issue_or_pr_option_config( $option_code, $repo_meta_key ) {
		return array(
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Issue or Pull Request', 'GitHub', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => array(),
			'relevant_tokens' => array(),
			'remote_data'     => $this->remote_data_parent_config( 'repo_issues_and_prs', array( $repo_meta_key ) ),
		);
	}

	/**
	 * Get issue or pull request number from parsed data.
	 *
	 * @param array $parsed
	 * @param string $option_code
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_issue_or_pr_number_from_parsed( $parsed, $option_code ) {
		$number = $parsed[ $option_code ] ?? '';

		if ( empty( $number ) ) {
			throw new Exception( esc_html_x( 'Issue or Pull Request is required.', 'GitHub', 'uncanny-automator' ) );
		}

		return $number;
	}

	/**
	 * Get label option config.
	 *
	 * @param string $option_code
	 * @param string $repo_meta_key
	 * @param bool $is_adding Whether this is for adding (true) or removing (false) labels
	 *
	 * @return array
	 */
	public function get_label_option_config( $option_code, $repo_meta_key, $is_adding = true ) {
		return array(
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Label name', 'GitHub', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => array(),
			'options_show_id' => false,
			'relevant_tokens' => array(),
			'remote_data'     => $this->remote_data_parent_config( 'repo_labels', array( $repo_meta_key ) ),
			'description'     => $is_adding
				? esc_html_x( 'To create a new label enter it as a custom value', 'GitHub', 'uncanny-automator' )
				: esc_html_x( 'Select an existing label to remove', 'GitHub', 'uncanny-automator' ),
		);
	}

	/**
	 * Get comment option config.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function get_comment_option_config( $option_code ) {
		return array(
			'input_type'      => 'textarea',
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Comment', 'GitHub', 'uncanny-automator' ),
			'required'        => true,
			'relevant_tokens' => array(),
			'description'     => esc_html_x( 'Supports GitHub flavored markdown.', 'GitHub', 'uncanny-automator' ),
		);
	}

	/**
	 * Get comment from parsed.
	 *
	 * @param array $parsed
	 * @param string $option_code
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_comment_from_parsed( $parsed, $option_code ) {
		if ( ! isset( $parsed[ $option_code ] ) ) {
			throw new Exception( esc_html_x( 'Comment is required.', 'GitHub', 'uncanny-automator' ) );
		}

		return sanitize_textarea_field( $parsed[ $option_code ] );
	}

	/**
	 * Fetch tags for the selected repository.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_repo_tags( $request ): array {
		$repo_parts = $this->get_repo_from_request( $request );
		$tags       = empty( $repo_parts )
			? array()
			: $this->api->get_repo_tags( $repo_parts['name'], $repo_parts['owner'], $request->is_refresh() );

		return $this->remote_data_success( $tags );
	}

	/**
	 * Get label from parsed.
	 *
	 * @param array $parsed
	 * @param string $option_code
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_label_from_parsed( $parsed, $option_code ) {
		$label = isset( $parsed[ $option_code ] ) ? sanitize_text_field( $parsed[ $option_code ] ) : '';

		if ( empty( $label ) ) {
			throw new Exception( esc_html_x( 'Label is required.', 'GitHub', 'uncanny-automator' ) );
		}

		return $label;
	}

	/**
	 * Fetch a 50/50 mix of issues and pull requests for the selected repo,
	 * each prefixed with `[Issue]` / `[PR]`, capped at 1000 combined items.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_repo_issues_and_prs( $request ): array {
		$repo_parts = $this->get_repo_from_request( $request );

		if ( empty( $repo_parts ) ) {
			return $this->remote_data_success( array() );
		}

		$issues = $this->api->get_repo_issues( $repo_parts['name'], $repo_parts['owner'], $request->is_refresh() );
		$prs    = $this->api->get_repo_pull_requests( $repo_parts['name'], $repo_parts['owner'], $request->is_refresh() );

		$issues = $this->prefix_issue_pr_options( $issues, '[Issue]' );
		$prs    = $this->prefix_issue_pr_options( $prs, '[PR]' );

		return $this->remote_data_success(
			$this->merge_arrays_with_equal_distribution( $issues, $prs, 1000 )
		);
	}

	/**
	 * Merge two arrays with equal distribution and a cap.
	 *
	 * @param array $a First array
	 * @param array $b Second array
	 * @param int $limit Maximum total items
	 *
	 * @return array Merged array with equal distribution
	 */
	public function merge_arrays_with_equal_distribution( $a, $b, $limit = 1000 ) {
		$count_a = count( $a );
		$count_b = count( $b );

		// If we're under the cap, just merge.
		if ( $count_a + $count_b <= $limit ) {
			return array_merge( $a, $b );
		}

		// Aim for an equal split; if one side is short, give the surplus to the other.
		$share_a = intdiv( $limit, 2 );
		$take_a  = min( $count_a, $share_a );
		$take_b  = min( $count_b, $limit - $take_a );

		// If B couldn't fill its share, let A use the remainder (and vice versa).
		if ( $take_a + $take_b < $limit ) {
			$remain = $limit - ( $take_a + $take_b );
			if ( $take_a < $count_a ) {
				$take_a = min( $count_a, $take_a + $remain );
			} elseif ( $take_b < $count_b ) {
				$take_b = min( $count_b, $take_b + $remain );
			}
		}

		// Return the merged array with equal distribution.
		return array_merge( array_slice( $a, 0, $take_a ), array_slice( $b, 0, $take_b ) );
	}

	/**
	 * Prefix issue/PR options.
	 *
	 * @param array $options
	 * @param string $prefix
	 *
	 * @return array
	 */
	private function prefix_issue_pr_options( $options, $prefix ) {
		// Bail if no options or options is not an array.
		if ( empty( $options ) || ! is_array( $options ) ) {
			return array();
		}
		return array_map(
			function ( $option ) use ( $prefix ) {
				return array(
					'value' => $option['value'],
					'text'  => $prefix . ' ' . $option['text'],
				);
			},
			$options
		);
	}

	/**
	 * Fetch branches for the selected repository.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_repo_branches( $request ): array {
		$repo_parts = $this->get_repo_from_request( $request );
		$branches   = empty( $repo_parts )
			? array()
			: $this->api->get_repo_branches( $repo_parts['name'], $repo_parts['owner'], $request->is_refresh() );

		return $this->remote_data_success( $branches );
	}

	/**
	 * Fetch labels for the selected repository.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_repo_labels( $request ): array {
		$repo_parts = $this->get_repo_from_request( $request );
		$labels     = empty( $repo_parts )
			? array()
			: $this->api->get_repo_labels( $repo_parts['name'], $repo_parts['owner'], $request->is_refresh() );

		return $this->remote_data_success( $labels );
	}

	////////////////////////////////////////////////////////////
	// Webhook trigger UI helper methods
	////////////////////////////////////////////////////////////

	/**
	 * Get repository options.
	 *
	 * @param string $event
	 *
	 * @return array
	 */
	public function get_webhook_repo_options( $event = 'all' ) {
		$webhook_config = $this->webhooks->get_webhook_manager_config();
		$options        = array();
		foreach ( $webhook_config as $repo_id => $repo ) {
			// Ensure the webhook has been connected.
			if ( empty( $repo['events'] ) || empty( $repo['hook_id'] ) ) {
				continue;
			}

			// Ensure the event is in the webhook's events.
			if ( 'all' !== $event && ! in_array( $event, $repo['events'], true ) ) {
				continue;
			}

			$options[] = array(
				'text'  => ( $repo['meta']['owner'] ?? '' ) . ' : ' . $repo['name'],
				'value' => $repo_id,
			);
		}

		return $options;
	}

	/**
	 * Get repository option config.
	 *
	 * @param string $option_code
	 * @param string $event
	 *
	 * @return array
	 */
	public function get_webhook_repo_option_config( $option_code, $event ) {
		return array(
			'input_type'      => 'select',
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Repository', 'GitHub', 'uncanny-automator' ),
			'required'        => true,
			'options'         => $this->get_webhook_repo_options( $event ),
			'options_show_id' => false,
			'relevant_tokens' => array(),
		);
	}

	/**
	 * Get event options.
	 *
	 * @return array
	 */
	public function get_event_options() {
		$events = array(
			array(
				'text'  => esc_html_x( 'Push', 'GitHub', 'uncanny-automator' ),
				'value' => 'push',
			),
			array(
				'text'  => esc_html_x( 'Pull request', 'GitHub', 'uncanny-automator' ),
				'value' => 'pull_request',
			),
			array(
				'text'  => esc_html_x( 'Issues', 'GitHub', 'uncanny-automator' ),
				'value' => 'issues',
			),
			array(
				'text'  => esc_html_x( 'Issue comment', 'GitHub', 'uncanny-automator' ),
				'value' => 'issue_comment',
			),
			array(
				'text'  => esc_html_x( 'Pull request review', 'GitHub', 'uncanny-automator' ),
				'value' => 'pull_request_review',
			),
			array(
				'text'  => esc_html_x( 'Release', 'GitHub', 'uncanny-automator' ),
				'value' => 'release',
			),

			array(
				'text'  => esc_html_x( 'Delete', 'GitHub', 'uncanny-automator' ),
				'value' => 'delete',
			),
			array(
				'text'  => esc_html_x( 'Fork', 'GitHub', 'uncanny-automator' ),
				'value' => 'fork',
			),
			array(
				'text'  => esc_html_x( 'Star', 'GitHub', 'uncanny-automator' ),
				'value' => 'star',
			),
			array(
				'text'  => esc_html_x( 'Commit comment', 'GitHub', 'uncanny-automator' ),
				'value' => 'commit_comment',
			),
			array(
				'text'  => esc_html_x( 'Workflow run', 'GitHub', 'uncanny-automator' ),
				'value' => 'workflow_run',
			),
		);

		/**
		 * Filter GitHub webhook events.
		 *
		 * @link https://docs.github.com/en/developers/webhooks-and-events/webhooks/webhook-events-and-payloads
		 *
		 * @param array $events
		 * @property text - The text to display for the event.
		 * @property value - The value to use for the event.
		 *
		 * @return array
		 *
		 * @example:
		 * add_filter( 'automator_github_pro_webhook_events', function( $events ) {
		 *     $events[] = array(
		 *         'text'  => esc_html_x( 'Team member added', 'GitHub', 'uncanny-automator' ),
		 *         'value' => 'team_add',
		 *     );
		 *     return $events;
		 * } );
		 */
		$events = apply_filters( 'automator_github_pro_webhook_events', $events );

		return $events;
	}

	/**
	 * Get event action options.
	 *
	 * @return array
	 */
	/**
	 * Fetch the available event-action options for the selected `EVENT_TYPE`,
	 * prepended with the "Any action" sentinel (`-1`).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_event_actions( $request ): array {
		$actions = $this->get_event_actions_options( $request->get_field_value( 'EVENT_TYPE' ) );
		$options = array(
			array(
				'text'  => esc_html_x( 'Any action', 'GitHub', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		if ( ! empty( $actions ) ) {
			$options = array_merge( $options, $actions );
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Get event actions options by event type.
	 *
	 * @param string $event
	 *
	 * @return array
	 */
	public function get_event_actions_options( $event ) {

		// Bail if empty or invalid type.
		if ( empty( $event ) || ! is_string( $event ) ) {
			return array();
		}

		// Build filterable event type action options.
		switch ( $event ) {
			case 'release':
				$actions = array(
					array(
						'text'  => esc_html_x( 'Published', 'GitHub', 'uncanny-automator' ),
						'value' => 'published',
					),
					array(
						'text'  => esc_html_x( 'Unpublished', 'GitHub', 'uncanny-automator' ),
						'value' => 'unpublished',
					),
					array(
						'text'  => esc_html_x( 'Created', 'GitHub', 'uncanny-automator' ),
						'value' => 'created',
					),
					array(
						'text'  => esc_html_x( 'Edited', 'GitHub', 'uncanny-automator' ),
						'value' => 'edited',
					),
					array(
						'text'  => esc_html_x( 'Deleted', 'GitHub', 'uncanny-automator' ),
						'value' => 'deleted',
					),
					array(
						'text'  => esc_html_x( 'Prereleased', 'GitHub', 'uncanny-automator' ),
						'value' => 'prereleased',
					),
					array(
						'text'  => esc_html_x( 'Released', 'GitHub', 'uncanny-automator' ),
						'value' => 'released',
					),
				);
				break;
			case 'workflow_run':
				$actions = array(
					array(
						'text'  => esc_html_x( 'Completed', 'GitHub', 'uncanny-automator' ),
						'value' => 'completed',
					),
					array(
						'text'  => esc_html_x( 'Requested', 'GitHub', 'uncanny-automator' ),
						'value' => 'requested',
					),
					array(
						'text'  => esc_html_x( 'In progress', 'GitHub', 'uncanny-automator' ),
						'value' => 'in_progress',
					),
				);
				break;
			default:
				$actions = array();
				break;
		}

		/**
		 * Filter GitHub webhook event type actions.
		 *
		 * @link https://docs.github.com/en/developers/webhooks-and-events/webhooks/webhook-events-and-payloads
		 *
		 * @param array $actions
		 * @property text - The text to display for the event.
		 * @property value - The value to use for the event.
		 *
		 * @return array
		 *
		 * @example:
		 * add_filter( 'automator_github_pro_webhook_event_actions', function( $actions, $event ) {
		 *     if ( 'workflow_run' === $event ) {
		 *         $actions[] = array(
		 *             'text'  => esc_html_x( 'Completed', 'GitHub', 'uncanny-automator' ),
		 *             'value' => 'completed',
		 *         );
		 *     }
		 *     return $actions;
		 * }, 10, 2 );
		 * } );
		 */
		return apply_filters( 'automator_github_pro_webhook_event_actions', $actions, $event );
	}
}
