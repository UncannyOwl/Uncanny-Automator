<?php

namespace Uncanny_Automator\Integrations\Github;

use Exception;

/**
 * Class Github_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Github_Api_Caller $api
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
			'ajax'            => array(
				'endpoint' => 'automator_github_get_repo_options',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get repo options AJAX.
	 *
	 * @return void
	 */
	public function get_repo_options_ajax() {
		Automator()->utilities->verify_nonce();
		$repos   = $this->api->get_user_repos( $this->is_ajax_refresh() );
		$options = $this->format_repo_options( $repos );

		// Add empty option if more than one repo.
		if ( count( $repos ) > 1 ) {
			$empty = array(
				'value' => '',
				'text'  => esc_html_x( 'Select a repository', 'GitHub', 'uncanny-automator' ),
			);
			array_unshift( $options, $empty );
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
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
			'ajax'            => array(
				'endpoint'      => 'automator_github_get_repo_issues_and_pr_options',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $repo_meta_key ),
			),
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
			'ajax'            => array(
				'endpoint'      => 'automator_github_get_repo_label_options',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $repo_meta_key ),
			),
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
	 * Get tag options AJAX.
	 *
	 * @return void
	 */
	public function get_repo_tag_options_ajax() {
		Automator()->utilities->verify_nonce();
		$repo_parts = $this->get_repo_from_ajax();
		$tags       = empty( $repo_parts )
			? array()
			: $this->api->get_repo_tags( $repo_parts['name'], $repo_parts['owner'], $this->is_ajax_refresh() );

		wp_send_json(
			array(
				'success' => true,
				'options' => $tags,
			)
		);
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
	 * Get repo issues and pull requests options AJAX.
	 *
	 * @return void
	 */
	public function get_repo_issues_and_pr_options_ajax() {
		Automator()->utilities->verify_nonce();
		$repo_parts = $this->get_repo_from_ajax();

		if ( empty( $repo_parts ) ) {
			wp_send_json(
				array(
					'success' => true,
					'options' => array(),
				)
			);
		}

		// Get both issues and pull requests
		$issues = $this->api->get_repo_issues( $repo_parts['name'], $repo_parts['owner'], $this->is_ajax_refresh() );
		$prs    = $this->api->get_repo_pull_requests( $repo_parts['name'], $repo_parts['owner'], $this->is_ajax_refresh() );

		// Format issues and PRs with prefixes
		$issues = $this->prefix_issue_pr_options( $issues, '[Issue]' );
		$prs    = $this->prefix_issue_pr_options( $prs, '[PR]' );

		// Merge with equal distribution and 1000 limit.
		$combined_options = $this->merge_arrays_with_equal_distribution( $issues, $prs, 1000 );

		wp_send_json(
			array(
				'success' => true,
				'options' => $combined_options,
			)
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
	 * Get repo branches options AJAX.
	 *
	 * @return void
	 */
	public function get_repo_branches_options_ajax() {
		Automator()->utilities->verify_nonce();
		$repo_parts = $this->get_repo_from_ajax();
		$branches   = empty( $repo_parts )
			? array()
			: $this->api->get_repo_branches( $repo_parts['name'], $repo_parts['owner'], $this->is_ajax_refresh() );

		wp_send_json(
			array(
				'success' => true,
				'options' => $branches,
			)
		);
	}

	/**
	 * Get repo labels options AJAX.
	 *
	 * @return void
	 */
	public function get_repo_label_options_ajax() {
		Automator()->utilities->verify_nonce();
		$repo_parts = $this->get_repo_from_ajax();
		$labels     = empty( $repo_parts )
			? array()
			: $this->api->get_repo_labels( $repo_parts['name'], $repo_parts['owner'], $this->is_ajax_refresh() );

		wp_send_json(
			array(
				'success' => true,
				'options' => $labels,
			)
		);
	}
}
