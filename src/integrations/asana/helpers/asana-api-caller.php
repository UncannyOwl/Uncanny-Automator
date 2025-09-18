<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Class Asana_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Asana_App_Helpers $helpers
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
}
