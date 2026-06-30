<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Class Asana_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Webhooks $webhooks
 */
class Asana_Api_Caller extends \Uncanny_Automator\App_Integrations\Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return array - The prepared credentials for user or Bot requests.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return wp_json_encode(
			array(
				'vault_signature' => $credentials['vault_signature'],
				'asana_id'        => $credentials['asana_id'],
			)
		);
	}

	/**
	 * Check for errors.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs
	 */
	public function check_for_errors( $response, $args = array() ) {
		if ( isset( $response['statusCode'] ) && 400 === $response['statusCode'] ) {
			$this->handle_400_error( $response, $args );
		}

		// Check for errors in the response data.
		if ( isset( $response['data']['errors'] ) && is_array( $response['data']['errors'] ) ) {
			$error_messages = array();

			foreach ( $response['data']['errors'] as $error ) {
				if ( isset( $error['message'] ) ) {
					$error_messages[] = $error['message'];
				}
			}

			if ( ! empty( $error_messages ) ) {
				$error_message = implode( '; ', $error_messages );
				throw new Exception( 'Asana API Error: ' . esc_html( $error_message ), 400 );
			}
		}
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Get user workspaces.
	 *
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_user_workspaces( $refresh = false ) {

		$key = $this->helpers->get_const( 'WORKSPACE_OPTION' );

		$workspaces = automator_get_option( $key );

		if ( empty( $workspaces ) || $refresh ) {
			$response   = $this->api_request( 'get_user_workspaces' );
			$workspaces = $response['data']['workspaces'] ?? array();
			if ( empty( $workspaces ) ) {
				automator_delete_option( $key );
				return array();
			}

			automator_update_option( $key, $workspaces );
		}

		return $workspaces;
	}

	/**
	 * Get workspace projects.
	 *
	 * @param string $workspace_id
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_workspace_projects( $workspace_id, $refresh = false ) {
		if ( empty( $workspace_id ) ) {
			return array();
		}

		return $this->get_workspace_data(
			'ASANA_PROJECTS_' . $workspace_id,
			array(
				'action'       => 'get_workspace_projects',
				'workspace_id' => $workspace_id,
			),
			'projects',
			$refresh
		);
	}

	/**
	 * Get project tasks.
	 *
	 * @param string $project_id
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_project_tasks( $project_id, $refresh = false ) {
		if ( empty( $project_id ) ) {
			return array();
		}

		return $this->get_workspace_data(
			'ASANA_TASKS_' . $project_id,
			array(
				'action'     => 'get_project_tasks',
				'project_id' => $project_id,
			),
			'tasks',
			$refresh
		);
	}

	/**
	 * Get workspace tags.
	 *
	 * @param string $workspace_id
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_workspace_tags( $workspace_id, $refresh = false ) {
		if ( empty( $workspace_id ) ) {
			return array();
		}

		return $this->get_workspace_data(
			'ASANA_TAGS_' . $workspace_id,
			array(
				'action'       => 'get_workspace_tags',
				'workspace_id' => $workspace_id,
			),
			'tags',
			$refresh
		);
	}

	/**
	 * Get workspace users.
	 *
	 * @param string $workspace_id
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_workspace_users( $workspace_id, $refresh = false ) {
		if ( empty( $workspace_id ) ) {
			return array();
		}

		return $this->get_workspace_data(
			'ASANA_USERS_' . $workspace_id,
			array(
				'action'       => 'get_workspace_users',
				'workspace_id' => $workspace_id,
			),
			'users',
			$refresh,
			'users' // Encryption type key to use for encryption/decryption.
		);
	}

	/**
	 * Get project custom fields.
	 *
	 * @param string $project_id
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_project_custom_fields( $project_id, $refresh = false ) {
		if ( empty( $project_id ) ) {
			return array();
		}

		// If refresh is requested, check if we're within the rate limit window.
		if ( $refresh ) {
			$transient_key = 'asana_custom_fields_refresh_' . $project_id;
			$last_refresh  = get_transient( $transient_key );
			if ( false !== $last_refresh ) {
				// Still within rate limit window, ignore refresh request.
				$refresh = false;
			} else {
				// Set transient to prevent excessive refresh requests (2 minutes).
				set_transient( $transient_key, time(), 2 * MINUTE_IN_SECONDS );
			}
		}

		return $this->get_workspace_data(
			'ASANA_CUSTOM_FIELDS_' . $project_id,
			array(
				'action'     => 'get_project_custom_fields',
				'project_id' => $project_id,
			),
			'custom_fields',
			$refresh
		);
	}

	/**
	 * Issue the remote `create_webhook` action against Asana.
	 * Pure API I/O — config persistence is owned by `Asana_Webhooks` via the manager trait.
	 *
	 * @param string $project_id The ID of the project (resource).
	 * @param array  $events     The events to subscribe to.
	 * @param string $target_url The webhook target URL the manager will receive events on.
	 *
	 * @return array {
	 *     hook_id: string,
	 *     secret:  string|null,
	 * }
	 *
	 * @throws Exception When events are empty or the API rejects the request.
	 */
	public function do_create_webhook( $project_id, $events, $target_url ) {

		if ( empty( $events ) ) {
			throw new Exception( esc_html_x( 'Please select at least one event to subscribe your webhook to.', 'Asana', 'uncanny-automator' ) );
		}

		$args = array(
			'action'   => 'create_webhook',
			'resource' => $project_id,
			'target'   => $target_url,
			'filters'  => wp_json_encode( $this->convert_events_to_filters( $events ) ),
		);

		$response = $this->api_request( $args );

		// Platform normalizes a successful vendor response to 200; accept it alongside Asana's native 201 Created.
		if ( 201 !== $response['statusCode'] && 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html( $response['data']['message'] ?? '' ) );
		}

		return array(
			'hook_id' => $response['data']['data']['gid'] ?? null,
			'secret'  => $response['data']['X-Hook-Secret'] ?? null,
		);
	}

	/**
	 * Issue the remote `update_webhook` action against Asana — change the events the remote hook fires on.
	 * Pure API I/O — config persistence is owned by `Asana_Webhooks` via the manager trait.
	 *
	 * @param string $project_id The ID of the project (resource).
	 * @param array  $events     The events to subscribe to.
	 * @param string $hook_id    The remote webhook ID.
	 *
	 * @return void
	 *
	 * @throws Exception When events are empty or the API rejects the request.
	 */
	public function do_update_webhook( $project_id, $events, $hook_id ) {

		if ( empty( $events ) ) {
			throw new Exception( esc_html_x( 'Please select at least one event to subscribe your webhook to.', 'Asana', 'uncanny-automator' ) );
		}

		$args = array(
			'action'     => 'update_webhook',
			'webhook_id' => $hook_id,
			'filters'    => wp_json_encode( $this->convert_events_to_filters( $events ) ),
		);

		$response = $this->api_request( $args );
		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html( $response['data']['message'] ) );
		}
	}

	/**
	 * Issue the remote `delete_webhook` action against Asana.
	 * Pure API I/O — config persistence is owned by `Asana_Webhooks` via the manager trait.
	 *
	 * @param string $project_id The ID of the project (resource).
	 * @param string $hook_id    The remote webhook ID.
	 *
	 * @return void
	 *
	 * @throws Exception When the API rejects the request.
	 */
	public function do_delete_webhook( $project_id, $hook_id ) {

		$args = array(
			'action'     => 'delete_webhook',
			'webhook_id' => $hook_id,
		);

		$response = $this->api_request( $args );

		if ( 200 !== $response['statusCode'] ) {
			$message = $response['data']['message'] ?? esc_html_x( 'Unknown error :', 'Asana', 'uncanny-automator' ) . ' ' . $response['statusCode'];
			throw new Exception( esc_html( $message ) );
		}
	}

	/*
	 * --------------------------------------------------------------------
	 * LSP-compat shims for pre-7.3.0 Uncanny_Automator_Pro
	 * --------------------------------------------------------------------
	 * The block below is dead-code-by-design. It exists so that PHP 8+ accepts
	 * the class declaration when pre-7.3.0 Pro's `Asana_Pro_Api_Caller` (which
	 * extends this class) overrides `create_webhook`, `update_webhook`, and
	 * `delete_webhook` at its narrower arity. Without these signature stubs
	 * on the parent, PHP raises an LSP "must be compatible" fatal at class
	 * declaration time and the site refuses to boot, blocking the user from
	 * updating Pro from wp-admin.
	 *
	 * When old Pro is active, Pro's override runs and the bodies below are
	 * never reached. When Pro is updated to 7.3.0+, the override disappears
	 * and Free no longer calls these names (it uses `do_create_webhook` /
	 * `do_update_webhook` / `do_delete_webhook` above), so the bodies are
	 * still unreachable.
	 *
	 * Safe to delete this entire block once Pro ≥ 7.3.0 is the supported floor.
	 * --------------------------------------------------------------------
	 */

	/**
	 * LSP-compat shim. See block comment above. Do not invoke directly.
	 *
	 * @param string $project_id Unused.
	 * @param array  $events     Unused.
	 *
	 * @return void
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function create_webhook( $project_id, $events ) {
		throw new \BadMethodCallException( 'Asana_Api_Caller::create_webhook is a pre-7.3.0 Pro LSP-compat stub; use do_create_webhook() instead.' );
	}

	/**
	 * LSP-compat shim. See block comment above. Do not invoke directly.
	 *
	 * @param string $project_id Unused.
	 * @param array  $events     Unused.
	 *
	 * @return void
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function update_webhook( $project_id, $events ) {
		throw new \BadMethodCallException( 'Asana_Api_Caller::update_webhook is a pre-7.3.0 Pro LSP-compat stub; use do_update_webhook() instead.' );
	}

	/**
	 * LSP-compat shim. See block comment above. Do not invoke directly.
	 *
	 * @param string $project_id Unused.
	 *
	 * @return void
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function delete_webhook( $project_id ) {
		throw new \BadMethodCallException( 'Asana_Api_Caller::delete_webhook is a pre-7.3.0 Pro LSP-compat stub; use do_delete_webhook() instead.' );
	}

	/**
	 * Get task.
	 *
	 * @param string $task_id The task ID.
	 *
	 * @return array
	 */
	public function get_task( $task_id ) {
		if ( empty( $task_id ) ) {
			return array();
		}

		try {
			$args = array(
				'action'  => 'get_task',
				'task_id' => $task_id,
			);

			$response = $this->api_request( $args );

			if ( 200 !== $response['statusCode'] ) {
				return array();
			}

			return $response['data']['data'] ?? array();
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get workspace cached data with fallback to API.
	 *
	 * @param string $option_key The option key to use
	 * @param array $body The API request body
	 * @param string|null $data_key The key in response data (null for direct data)
	 * @param bool $refresh Whether to refresh the cache
	 * @param string|null $encryption_key Whether the data is encrypted
	 *
	 * @return array
	 */
	private function get_workspace_data( $option_key, $body, $data_key = null, $refresh = false, $encryption_key = null ) {
		// Check if the data requires encryption.
		$encrypted = ! empty( $encryption_key ) && is_string( $encryption_key );
		// Get the cached option for the workspace data.
		$cached_data = automator_get_option( $option_key, array() );

		// If we have the data cached and not refreshing, return it.
		if ( ! $refresh && ! empty( $cached_data ) ) {
			// If the data is encrypted, decrypt it.
			return $encrypted
				? $this->helpers->decrypt_data( $cached_data, $option_key, $encryption_key )
				: $cached_data;
		}

		// Get the data from the API.
		try {
			$response = $this->api_request( $body );

			if ( 200 !== $response['statusCode'] ) {
				return array();
			}

			$data = $data_key
				? ( $response['data'][ $data_key ] ?? array() )
				: ( $response['data'] ?? array() );

			if ( ! empty( $data ) ) {
				// Encrypt data if flagged.
				$option_data = $encrypted
					? $this->helpers->encrypt_data( $data, $option_key, $encryption_key )
					: $data;

				automator_update_option( $option_key, $option_data, false );
			}

			return $data;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Convert events to Asana webhook filters.
	 *
	 * @param array $events The events array.
	 *
	 * @return array
	 */
	private function convert_events_to_filters( $events ) {
		$filters = array();

		foreach ( $events as $event ) {
			switch ( $event ) {
				case 'task.added':
					$filters[] = array(
						'resource_type' => 'task',
						'action'        => 'added',
					);
					break;
				case 'task.changed':
					$filters[] = array(
						'resource_type' => 'task',
						'action'        => 'changed',
						'fields'        => array( 'name', 'notes', 'due_on', 'custom_fields' ),
					);
					break;
				case 'story.added':
					$filters[] = array(
						'resource_type' => 'story',
						'action'        => 'added',
					);
					break;
				case 'task.status_changed':
					$filters[] = array(
						'resource_type'    => 'task',
						'resource_subtype' => 'approval',
						'action'           => 'changed',
					);
					break;
			}
		}

		return $filters;
	}
}
