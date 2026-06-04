<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;
use Uncanny_Automator\App_Integrations\App_Webhook_Manager;
use Uncanny_Automator\App_Integrations\App_Webhooks;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Asana_Webhooks
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_Api_Caller  $api
 * @property Asana_App_Helpers $helpers
 */
class Asana_Webhooks extends App_Webhooks {

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
		return 'asana_webhooks_manager';
	}

	/**
	 * In-flight legacy shape migration for sites connected before the canonical
	 * `{id, name, hook_id, events, meta}` shape was introduced. Detected by the
	 * presence of a flat top-level `workspace_id` (canonical lives under `meta`).
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
		if ( ! is_array( $first ) || ! array_key_exists( 'workspace_id', $first ) ) {
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
	 * Convert a single pre-canonical Asana entry into the canonical shape by
	 * folding the flat workspace + endpoint keys into the `meta` bag.
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
				'workspace_id'   => $entry['workspace_id'] ?? null,
				'workspace_name' => $entry['workspace_name'] ?? null,
				'endpoint'       => $entry['endpoint'] ?? null,
				'secret'         => $entry['secret'] ?? null,
				'url'            => $entry['url'] ?? null,
				'connected_at'   => $entry['connected_at'] ?? null,
			),
		);
	}

	////////////////////////////////////////////////////////////
	// App_Webhook_Manager trait abstract implementations
	////////////////////////////////////////////////////////////

	/**
	 * Fetch projects across every workspace the user has access to.
	 *
	 * @param bool $force_refresh When true, bypass the helper-side cache on
	 *                            both the workspaces and projects API calls.
	 *
	 * @return array Raw project resources for `format_resource_for_storage()`.
	 */
	public function fetch_resources( $force_refresh = false ) {
		$workspaces = $this->api->get_user_workspaces( $force_refresh );
		if ( empty( $workspaces ) ) {
			return array();
		}

		$resources = array();
		foreach ( $workspaces as $workspace ) {
			$projects = $this->api->get_workspace_projects( $workspace['value'], $force_refresh );
			if ( empty( $projects ) ) {
				continue;
			}
			foreach ( $projects as $project ) {
				$resources[] = array(
					'id'             => $project['value'],
					'name'           => $project['text'],
					'workspace_id'   => $workspace['value'],
					'workspace_name' => $workspace['text'],
				);
			}
		}

		usort(
			$resources,
			function ( $a, $b ) {
				$cmp = strcmp( $a['workspace_name'], $b['workspace_name'] );
				return 0 !== $cmp ? $cmp : strcmp( $a['name'], $b['name'] );
			}
		);

		return $resources;
	}

	/**
	 * Normalize a raw project resource into the canonical-shape entry.
	 *
	 * @param array $resource Raw project resource from `fetch_resources()`.
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
				'workspace_id'   => $resource['workspace_id'],
				'workspace_name' => $resource['workspace_name'],
				'endpoint'       => $this->get_webhook_endpoint(),
				'secret'         => null,
				'url'            => add_query_arg( array( 'pid' => (string) $resource['id'] ), $this->get_webhook_url() ),
				'connected_at'   => null,
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Webhook subscription CRUD (called by the settings trait)
	////////////////////////////////////////////////////////////

	/**
	 * Create a webhook subscription for a project.
	 *
	 * @param string $project_id Canonical resource ID.
	 * @param array  $events     Selected event values.
	 *
	 * @return array Updated canonical-shape entry.
	 *
	 * @throws Exception If the project is not in the manager config or the API call fails.
	 */
	public function create_webhook( $project_id, $events ) {
		$entry = $this->require_entry( $project_id );

		// First-time enable gate — if no project is currently subscribed, flip
		// the integration-level webhooks-enabled flag on so the handler registers.
		$webhooks_enabled = $this->get_webhooks_enabled_status();
		if ( ! $webhooks_enabled ) {
			$this->store_webhooks_enabled_status( true );
		}

		try {
			$hook = $this->api->do_create_webhook( $project_id, $events, $entry['meta']['url'] );
		} catch ( Exception $e ) {
			// Roll back the enabled flag on first-create failure.
			if ( ! $webhooks_enabled ) {
				$this->store_webhooks_enabled_status( false );
			}
			throw $e;
		}

		$entry['hook_id']              = $hook['hook_id'] ?? null;
		$entry['events']               = $events;
		$entry['meta']['secret']       = $hook['secret'] ?? null;
		$entry['meta']['connected_at'] = time();

		$this->update_resource_in_manager_config( $project_id, $entry );

		return $entry;
	}

	/**
	 * Update an existing webhook subscription's events.
	 *
	 * @param string $project_id Canonical resource ID.
	 * @param array  $events     Selected event values.
	 *
	 * @return array Updated canonical-shape entry.
	 *
	 * @throws Exception If the project is not in the manager config, the API call fails,
	 *                   or the events match the current subscription.
	 */
	public function update_webhook( $project_id, $events ) {
		$entry = $this->require_entry( $project_id );

		$existing = $entry['events'] ?? array();
		$proposed = $events;
		sort( $existing );
		sort( $proposed );
		if ( $proposed === $existing ) {
			throw new Exception( esc_html_x( 'No changes to the webhook events.', 'Asana', 'uncanny-automator' ) );
		}

		$this->api->do_update_webhook( $project_id, $events, $entry['hook_id'] );

		$entry['events'] = $events;
		$this->update_resource_in_manager_config( $project_id, $entry );

		return $entry;
	}

	/**
	 * Delete a webhook subscription.
	 *
	 * @param string $project_id Canonical resource ID.
	 *
	 * @return array Updated canonical-shape entry.
	 *
	 * @throws Exception If the project is not in the manager config or the API call fails.
	 */
	public function delete_webhook( $project_id ) {
		$entry = $this->require_entry( $project_id );

		$this->api->do_delete_webhook( $project_id, $entry['hook_id'] );

		$entry['hook_id']              = null;
		$entry['events']               = array();
		$entry['meta']['secret']       = null;
		$entry['meta']['connected_at'] = null;

		$this->update_resource_in_manager_config( $project_id, $entry );

		return $entry;
	}

	/**
	 * Get the canonical entry for a project, throwing when missing.
	 *
	 * @param string $project_id Canonical resource ID.
	 *
	 * @return array
	 *
	 * @throws Exception If the project is not in the manager config.
	 */
	protected function require_entry( $project_id ) {
		$config = $this->get_webhook_manager_config();
		$entry  = $config[ $project_id ] ?? null;
		if ( empty( $entry ) ) {
			throw new Exception( esc_html_x( 'Project not found.', 'Asana', 'uncanny-automator' ) );
		}
		return $entry;
	}

	////////////////////////////////////////////////////////////
	// Project config accessor used by validation + processor
	////////////////////////////////////////////////////////////

	/**
	 * Get the canonical entry for a project, or an empty array if missing.
	 *
	 * @param string $project_id Canonical resource ID.
	 *
	 * @return array
	 */
	public function get_project_config( $project_id ) {
		$config = $this->get_webhook_manager_config();
		return $config[ $project_id ] ?? array();
	}

	////////////////////////////////////////////////////////////
	// App_Webhooks abstract overrides — Asana-specific validation + processing
	////////////////////////////////////////////////////////////

	/**
	 * Validate the webhook.
	 * Override to handle Asana-specific validation (handshake + signature).
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool|WP_REST_Response
	 *
	 * @throws Exception If the webhook is invalid.
	 */
	protected function validate_webhook( $request ) {

		// Check if this is a handshake request.
		$secret = $request->get_header( 'x_hook_secret' );
		if ( ! empty( $secret ) ) {
			return $this->handle_webhook_creation_handshake( $secret );
		}

		// Validate we have data.
		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			throw new Exception( esc_html_x( 'Webhook not accepted', 'Asana', 'uncanny-automator' ) );
		}

		// Retrieve the project ID from the URL param: pid=<project_gid>
		$project_id     = $request->get_param( 'pid' );
		$project_config = ! empty( $project_id ) ? $this->get_project_config( $project_id ) : null;

		if ( empty( $project_config ) ) {
			throw new Exception( esc_html_x( 'Project is not configured for webhooks on this site', 'Asana', 'uncanny-automator' ) );
		}

		// Validate the webhook signature.
		$secret = $project_config['meta']['secret'] ?? null;
		if ( empty( $secret ) || ! $this->validate_asana_signature( $request, $secret ) ) {
			throw new Exception( esc_html_x( 'Webhook verification failed', 'Asana', 'uncanny-automator' ) );
		}

		return true;
	}

	/**
	 * Process the webhook request.
	 * Override to handle Asana-specific event processing with deduplication.
	 *
	 * @param string $action_name   The action name.
	 * @param array  $action_params The action parameters array.
	 *
	 * @return void
	 */
	protected function process_webhook_request( $action_name, $action_params ) {
		$data       = $action_params[0] ?? null;
		$project_id = $data['pid'] ?? null;

		if ( ! empty( $data ) ) {
			$this->process_webhook_events( $data, $project_id );
		}
	}

	/**
	 * Handle the webhook creation handshake by echoing the secret header back.
	 *
	 * @param string $secret The secret value sent in `X-Hook-Secret`.
	 *
	 * @return WP_REST_Response
	 */
	private function handle_webhook_creation_handshake( $secret ) {
		$response = new WP_REST_Response();
		$response->set_status( 200 );
		$response->header( 'X-Hook-Secret', $secret );
		return $response;
	}

	/**
	 * Validate the Asana webhook signature via HMAC SHA256 against `X-Hook-Signature`.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @param string          $secret  The webhook secret captured during handshake.
	 *
	 * @return bool
	 */
	private function validate_asana_signature( $request, $secret ) {
		$signature = $request->get_header( 'X-Hook-Signature' );
		if ( empty( $signature ) ) {
			return false;
		}

		$payload            = $request->get_body();
		$expected_signature = hash_hmac( 'sha256', $payload, $secret );

		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Hand off webhook events to the dedicated processor for grouping + dispatch.
	 *
	 * @param array  $data       The webhook payload.
	 * @param string $project_id The project ID.
	 *
	 * @return void
	 */
	private function process_webhook_events( $data, $project_id ) {
		if ( empty( $data['events'] ) || ! is_array( $data['events'] ) ) {
			return;
		}

		if ( empty( $project_id ) ) {
			return;
		}

		$project_config = $this->get_project_config( $project_id );
		if ( empty( $project_config ) ) {
			return;
		}

		$processor = new Asana_Webhook_Processor();
		$processor->process_events( $data['events'], $project_config );
	}

	////////////////////////////////////////////////////////////
	// Token data resolution used by triggers
	////////////////////////////////////////////////////////////

	/**
	 * Resolve user-token data from a webhook event payload.
	 *
	 * @param array $event_data The event data passed to triggers.
	 *
	 * @return array Array with `value`, `text`, `email` keys.
	 */
	public function get_user_token_data( $event_data ) {
		$user_id      = $event_data['user_id'] ?? null;
		$workspace_id = $event_data['workspace_id'] ?? null;

		$user = array(
			'value' => $user_id,
			'text'  => '-',
			'email' => '-',
		);

		if ( ! empty( $user_id ) && ! empty( $workspace_id ) ) {
			$option        = $this->helpers->get_workspace_user_option( $workspace_id, $user_id );
			$user['text']  = $option['text'] ?? '-';
			$user['email'] = $option['email'] ?? '-';
		} else {
			$user['text'] = esc_html_x( 'System generated', 'Asana', 'uncanny-automator' );
		}

		return $user;
	}
}
