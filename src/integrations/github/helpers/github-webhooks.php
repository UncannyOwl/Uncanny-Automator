<?php

namespace Uncanny_Automator\Integrations\Github;

use Exception;
use Uncanny_Automator\App_Integrations\App_Webhook_Manager;
use Uncanny_Automator\App_Integrations\App_Webhooks;
use WP_REST_Request;

/**
 * Class Github_Webhooks
 *
 * @package Uncanny_Automator\Integrations\Github
 *
 * @property Github_Api_Caller  $api
 * @property Github_App_Helpers $helpers
 */
class Github_Webhooks extends App_Webhooks {

	use App_Webhook_Manager;

	////////////////////////////////////////////////////////////
	// Manager option name override
	////////////////////////////////////////////////////////////

	/**
	 * Override the trait-default option name to preserve the legacy storage key.
	 *
	 * @return string
	 */
	public function get_webhook_manager_option_name() {
		return 'github_webhooks_manager';
	}

	/**
	 * In-flight legacy shape migration for sites connected before the canonical
	 * `{id, name, hook_id, events, meta}` shape was introduced. Detected by the
	 * presence of a flat top-level `owner` (canonical lives under `meta`).
	 *
	 * NOTE: required only for the legacy migration pre Automator Platform; once
	 * webhook management moves to Automator Platform this override goes away
	 * along with the integration-side option storage.
	 *
	 * @return array
	 */
	protected function read_webhook_manager_option() {
		$stored = automator_get_option( $this->get_webhook_manager_option_name(), array() );

		if ( empty( $stored ) || ! is_array( $stored ) ) {
			return $stored;
		}

		$first = reset( $stored );
		if ( ! is_array( $first ) || ! array_key_exists( 'owner', $first ) ) {
			return $stored;
		}

		$migrated = array();
		foreach ( $stored as $key => $entry ) {
			$migrated[ $key ] = is_array( $entry ) ? $this->canonicalize_legacy_entry( $entry ) : $entry;
		}

		automator_update_option( $this->get_webhook_manager_option_name(), $migrated );

		return $migrated;
	}

	/**
	 * Convert a single pre-canonical GitHub entry into the canonical shape by
	 * folding the flat owner/admin/endpoint keys into the `meta` bag.
	 *
	 * @param array $entry Pre-canonical stored entry.
	 *
	 * @return array
	 */
	private function canonicalize_legacy_entry( $entry ) {
		return array(
			'id'      => isset( $entry['id'] ) ? (string) $entry['id'] : '',
			'name'    => $entry['name'] ?? '',
			'hook_id' => $entry['hook_id'] ?? null,
			'events'  => isset( $entry['events'] ) && is_array( $entry['events'] ) ? $entry['events'] : array(),
			'meta'    => array(
				'owner'        => $entry['owner'] ?? null,
				'admin'        => $entry['admin'] ?? false,
				'endpoint'     => $entry['endpoint'] ?? null,
				'secret'       => $entry['secret'] ?? null,
				'url'          => $entry['url'] ?? null,
				'connected_at' => $entry['connected_at'] ?? null,
			),
		);
	}

	////////////////////////////////////////////////////////////
	// App_Webhook_Manager trait abstract implementations
	////////////////////////////////////////////////////////////

	/**
	 * Fetch the user's GitHub repositories.
	 *
	 * @param bool $force_refresh When true, bypass the helper-side cache on
	 *                            the repos API call.
	 *
	 * @return array Raw repo resources for `format_resource_for_storage()`.
	 */
	public function fetch_resources( $force_refresh = false ) {
		$repos = $this->api->get_user_repos( $force_refresh );
		if ( empty( $repos ) ) {
			return array();
		}

		usort(
			$repos,
			function ( $a, $b ) {
				$cmp = strcmp( $a['owner'], $b['owner'] );
				return 0 !== $cmp ? $cmp : strcmp( $a['name'], $b['name'] );
			}
		);

		return $repos;
	}

	/**
	 * Normalize a raw repo resource into the canonical-shape entry.
	 *
	 * @param array $resource Raw repo resource from `fetch_resources()`.
	 *
	 * @return array
	 */
	public function format_resource_for_storage( $resource ) {
		return array(
			'id'      => (string) $resource['id'],
			'name'    => $resource['name'],
			'hook_id' => null,
			'events'  => array(),
			'meta'    => array(
				'owner'        => $resource['owner'],
				'admin'        => $resource['admin'] ?? false,
				'endpoint'     => $this->get_webhook_endpoint(),
				'secret'       => $this->get_webhook_key( true ),
				'url'          => $this->get_webhook_url(),
				'connected_at' => null,
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Webhook subscription CRUD (called by the settings trait)
	////////////////////////////////////////////////////////////

	/**
	 * Create a webhook subscription for a repository.
	 *
	 * @param string $repo_id Canonical resource ID.
	 * @param array  $events  Selected event values.
	 *
	 * @return array Updated canonical-shape entry.
	 *
	 * @throws Exception If the repo is not in the manager config or the API call fails.
	 */
	public function create_webhook( $repo_id, $events ) {
		$entry = $this->require_entry( $repo_id );

		$hook = $this->api->do_create_webhook(
			$entry['meta']['owner'],
			$entry['name'],
			$entry['meta']['url'],
			$entry['meta']['secret'],
			$events
		);

		$entry['hook_id']              = $hook['hook_id'] ?? null;
		$entry['events']               = $events;
		$entry['meta']['connected_at'] = time();

		$this->update_resource_in_manager_config( $repo_id, $entry );

		return $entry;
	}

	/**
	 * Update an existing webhook subscription's events.
	 *
	 * @param string $repo_id Canonical resource ID.
	 * @param array  $events  Selected event values.
	 *
	 * @return array Updated canonical-shape entry.
	 *
	 * @throws Exception If the repo is not in the manager config, the API call fails,
	 *                   or the events match the current subscription.
	 */
	public function update_webhook( $repo_id, $events ) {
		$entry = $this->require_entry( $repo_id );

		$existing = $entry['events'] ?? array();
		$proposed = $events;
		sort( $existing );
		sort( $proposed );
		if ( $proposed === $existing ) {
			throw new Exception( esc_html_x( 'No changes to the webhook events.', 'GitHub', 'uncanny-automator' ) );
		}

		$this->api->do_update_webhook(
			$entry['meta']['owner'],
			$entry['name'],
			$entry['meta']['url'],
			$entry['meta']['secret'],
			$events,
			$entry['hook_id']
		);

		$entry['events'] = $events;
		$this->update_resource_in_manager_config( $repo_id, $entry );

		return $entry;
	}

	/**
	 * Delete a webhook subscription.
	 *
	 * @param string $repo_id Canonical resource ID.
	 *
	 * @return array Updated canonical-shape entry.
	 *
	 * @throws Exception If the repo is not in the manager config or the API call fails.
	 */
	public function delete_webhook( $repo_id ) {
		$entry = $this->require_entry( $repo_id );

		$this->api->do_delete_webhook(
			$entry['meta']['owner'],
			$entry['name'],
			$entry['hook_id']
		);

		$entry['hook_id']              = null;
		$entry['events']               = array();
		$entry['meta']['connected_at'] = null;

		$this->update_resource_in_manager_config( $repo_id, $entry );

		return $entry;
	}

	/**
	 * Get the canonical entry for a repo, throwing when missing.
	 *
	 * @param string $repo_id Canonical resource ID.
	 *
	 * @return array
	 *
	 * @throws Exception If the repo is not in the manager config.
	 */
	protected function require_entry( $repo_id ) {
		$config = $this->get_webhook_manager_config();
		$entry  = $config[ $repo_id ] ?? null;
		if ( empty( $entry ) ) {
			throw new Exception( esc_html_x( 'Repo not found.', 'GitHub', 'uncanny-automator' ) );
		}
		return $entry;
	}

	////////////////////////////////////////////////////////////
	// Repo config accessor used by validation + triggers
	////////////////////////////////////////////////////////////

	/**
	 * Get the canonical entry for a repo, or an empty array if missing.
	 *
	 * @param string $repo_id Canonical resource ID.
	 *
	 * @return array
	 */
	public function get_repo_config( $repo_id ) {
		$config = $this->get_webhook_manager_config();
		return $config[ $repo_id ] ?? array();
	}

	////////////////////////////////////////////////////////////
	// App_Webhooks abstract overrides — GitHub-specific validation + processing
	////////////////////////////////////////////////////////////

	/**
	 * Validate the webhook request.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool
	 * @throws Exception If the webhook authorization is invalid.
	 */
	protected function validate_webhook( $request ) {

		$event   = $request->get_header( 'X-GitHub-Event' );
		$hook_id = $request->get_header( 'X-GitHub-Hook-ID' );
		$repo_id = $request->get_header( 'X-GitHub-Hook-Installation-Target-ID' );
		$config  = $this->get_repo_config( $repo_id );

		// If the repo is not configured, return false.
		if ( empty( $config ) ) {
			throw new Exception( esc_html_x( 'Repo not configured for Automator', 'GitHub', 'uncanny-automator' ) );
		}

		// If the hook ID is not the same as the configured hook ID, return false.
		if ( (string) $hook_id !== (string) $config['hook_id'] ) {
			throw new Exception( esc_html_x( 'Hook ID does not match configured hook ID', 'GitHub', 'uncanny-automator' ) );
		}

		// Validate the GitHub signature.
		$this->validate_github_signature( $request, $config['meta']['secret'] ?? '' );

		// Check for the initial webhook creation event ping event.
		if ( 'ping' === $event ) {
			// Throw exception with success message to validate but stop processing.
			throw new Exception( esc_html_x( 'Ping event received', 'GitHub', 'uncanny-automator' ) );
		}

		// If the event is not the same as the configured event, return false.
		if ( ! in_array( $event, $config['events'], true ) ) {
			throw new Exception( esc_html_x( 'Event does not match allowed events', 'GitHub', 'uncanny-automator' ) );
		}

		return true;
	}

	/**
	 * Set the shutdown data.
	 * - Override to use structured webhook data.
	 *
	 * @param WP_REST_Request $request The WP_REST_Request object.
	 *
	 * @return array
	 */
	protected function set_shutdown_data( $request ) {
		return array(
			'action_name'   => $this->get_do_action_name(),
			'action_params' => array(
				$this->get_decoded_request_body(), // First param: GitHub payload
				$request->get_header( 'X-GitHub-Event' ), // Second param: event type
			),
		);
	}

	/**
	 * Validate GitHub webhook signature.
	 *
	 * @param WP_REST_Request $request
	 * @param string $secret
	 *
	 * @return void
	 * @throws Exception
	 */
	private function validate_github_signature( $request, $secret ) {
		// Secret should always be set in the config.
		if ( empty( $secret ) ) {
			throw new Exception( esc_html_x( 'Secret is required', 'GitHub', 'uncanny-automator' ) );
		}

		// Validate the request signature.
		$body      = $request->get_body();
		$signature = $request->get_header( 'X-Hub-Signature-256' );

		if ( empty( $signature ) ) {
			throw new Exception( esc_html_x( 'Unauthorized request signature', 'GitHub', 'uncanny-automator' ) );
		}

		$expected_signature = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			throw new Exception( esc_html_x( 'Invalid request signature', 'GitHub', 'uncanny-automator' ) );
		}
	}

	////////////////////////////////////////////////////////////
	// Recipe trigger validation helpers.
	////////////////////////////////////////////////////////////

	/**
	 * Check if webhook matches a specific repository
	 *
	 * @param array $data
	 * @param string|int $repo_id
	 *
	 * @return bool
	 */
	public function webhook_matches_repository( $data, $repo_id ) {
		$webhook_repo_id = $data['repository']['id'] ?? null;
		return (string) $webhook_repo_id === (string) $repo_id;
	}

	/**
	 * Check if webhook matches a specific action
	 *
	 * @param array $data
	 * @param string $action
	 *
	 * @return bool
	 */
	public function webhook_matches_action( $data, $action ) {
		$data_action = $data['action'] ?? null;
		return $action === $data_action;
	}

	/**
	 * Get formatted rich text content from GitHub webhook payload
	 *
	 * Handles GitHub-specific content like issue bodies, pull request descriptions,
	 * and release notes that contain markdown formatting. Converts markdown to HTML
	 * for proper rendering in email templates and other HTML contexts.
	 *
	 * @param array  $data The webhook payload data
	 * @param string $path Dot notation path (e.g., 'issue.body', 'pull_request.body')
	 * @param array  $options Optional formatting options
	 *
	 * @return string The formatted HTML content
	 */
	public function get_rich_text_value( $data, $path, $options = array() ) {
		// Get the raw value first using the parent's method
		$raw_value = $this->get_payload_value( $data, $path );

		if ( empty( $raw_value ) || ! is_string( $raw_value ) ) {
			return '';
		}

		// Use the markdown parser service to convert the content
		$parser          = new \Uncanny_Automator\Services\Markdown\Markdown_Parser();
		$formatted_value = $parser->parse( $raw_value, $options );

		/**
		 * Filter the final GitHub rich text HTML output.
		 *
		 * Allows advanced users to make final modifications to the processed
		 * HTML content before it's returned as a token value.
		 *
		 * @param string $formatted_value The processed HTML content
		 * @param string $raw_value       The original markdown content
		 * @param string $path            The data path being processed
		 * @param array  $data            The full webhook payload data
		 * @param array  $options         The formatting options used
		 *
		 * @return string Modified HTML content
		 */
		$formatted_value = apply_filters( 'automator_github_rich_text_html', $formatted_value, $raw_value, $path, $data );

		return $formatted_value;
	}
}
