<?php

namespace Uncanny_Automator\Integrations\ClickUp;

use Exception;

/**
 * Class ClickUp_Api_Caller
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 *
 * @property ClickUp_App_Helpers $helpers
 */
class ClickUp_Api_Caller extends \Uncanny_Automator\App_Integrations\Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set custom properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// ClickUp uses access_token directly, not the standard 'credentials' key.
		$this->set_credential_request_key( 'access_token' );

		// ClickUp can be slow — set a generous default timeout.
		$this->set_request_timeout( 45 );

		// Register ClickUp-specific error messages.
		$this->register_error_messages(
			array(
				'OAUTH_025'      => esc_html_x( 'OAuth token has expired. Please reconnect your ClickUp account.', 'ClickUp', 'uncanny-automator' ),
				'OAUTH_027'      => esc_html_x( 'OAuth token is invalid. Please reconnect your ClickUp account.', 'ClickUp', 'uncanny-automator' ),
				'Team not found' => esc_html_x( 'The ClickUp team/workspace was not found.', 'ClickUp', 'uncanny-automator' ),
			)
		);
	}

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * ClickUp uses a direct access_token, not vault_signature.
	 *
	 * @param array $credentials The raw credentials from storage.
	 * @param array $args Additional arguments.
	 *
	 * @return string The access token.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return $credentials['access_token'] ?? '';
	}

	/**
	 * Check for ClickUp-specific errors in the response.
	 *
	 * @param array $response The API response.
	 * @param array $args     Additional arguments.
	 *
	 * @return void
	 * @throws Exception If an error is detected.
	 */
	public function check_for_errors( $response, $args = array() ) {
		// Check for HTTP error responses (4xx and 5xx).
		if ( isset( $response['statusCode'] ) && $response['statusCode'] >= 400 ) {
			// Check for ClickUp's ECODE/err format.
			$error_code    = $response['data']['ECODE'] ?? '';
			$error_message = $response['data']['err'] ?? '';

			if ( ! empty( $error_code ) || ! empty( $error_message ) ) {
				throw new Exception(
					sprintf(
						/* translators: %1$s: Status code, %2$s: Error code, %3$s: Error message */
						esc_html_x( 'ClickUp API has responded with a status code: %1$s and with an error %2$s: %3$s', 'ClickUp', 'uncanny-automator' ),
						absint( $response['statusCode'] ),
						esc_html( ! empty( $error_code ) ? $error_code : 'UNKNOWN_ERR_CODE' ),
						esc_html( ! empty( $error_message ) ? $error_message : 'No error message specified.' )
					),
					absint( $response['statusCode'] )
				);
			}

			// Fall back to parent error handling for standard error format.
			$this->handle_400_error( $response, $args );
		}

		// Check for API server error format.
		if ( ! empty( $response['error'] ) ) {
			throw new Exception(
				sprintf(
					/* translators: %1$s: Error type, %2$s: Error description */
					esc_html_x( 'Uncanny Automator API has responded with error %1$s: %2$s', 'ClickUp', 'uncanny-automator' ),
					esc_html( $response['error']['type'] ?? 'Unknown' ),
					esc_html( $response['error']['description'] ?? 'Unknown error' )
				),
				absint( $response['statusCode'] ?? 500 )
			);
		}
	}

	////////////////////////////////////////////////////////////
	// Data fetching methods
	////////////////////////////////////////////////////////////

	/**
	 * Get team workspaces.
	 *
	 * @return array
	 */
	public function get_team_workspaces() {
		try {
			$response = $this->api_request( 'get_authorized_teams' );
			$teams    = array();

			foreach ( $response['data']['teams'] ?? array() as $team ) {
				$teams[] = array(
					'value' => (string) $team['id'],
					'text'  => $team['name'],
				);
			}

			return $teams;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get spaces for a team.
	 *
	 * @param string $team_id The team ID.
	 *
	 * @return array
	 */
	public function get_spaces( $team_id ) {
		if ( empty( $team_id ) ) {
			return array();
		}

		try {
			$response = $this->api_request(
				array(
					'action'  => 'get_spaces',
					'team_id' => $team_id,
				)
			);

			$spaces = array();

			foreach ( $response['data']['spaces'] ?? array() as $space ) {
				$spaces[] = array(
					'value' => (string) $space['id'],
					'text'  => $space['name'],
				);
			}

			return $spaces;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get folders for a space.
	 *
	 * @param string $space_id The space ID.
	 *
	 * @return array Raw folder objects from the ClickUp API.
	 */
	public function get_folders( $space_id ) {
		if ( empty( $space_id ) ) {
			return array();
		}

		try {
			$response = $this->api_request(
				array(
					'action'   => 'get_folders',
					'space_id' => $space_id,
				)
			);

			return $response['data']['folders'] ?? array();
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get lists for a folder.
	 *
	 * @param string $folder_id The folder ID (may contain SPACE_ID flag).
	 *
	 * @return array
	 */
	public function get_lists( $folder_id ) {
		if ( empty( $folder_id ) ) {
			return array();
		}

		try {
			$response = $this->api_request(
				array(
					'action'    => 'get_lists',
					'folder_id' => $folder_id,
				)
			);

			$lists = array();

			foreach ( $response['data']['lists'] ?? array() as $list ) {
				$lists[] = array(
					'value' => (string) $list['id'],
					'text'  => $list['name'],
				);
			}

			return $lists;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get space statuses.
	 *
	 * @param string $space_id The space ID.
	 *
	 * @return array Formatted for dropdown.
	 */
	public function get_space_statuses( $space_id ) {
		if ( empty( $space_id ) ) {
			return array();
		}

		try {
			$response = $this->api_request(
				array(
					'action'   => 'get_space_members',
					'space_id' => $space_id,
				)
			);

			$statuses = array();

			foreach ( $response['data']['statuses'] ?? array() as $status ) {
				$status_name = strtoupper( $status['status'] );
				$statuses[]  = array(
					'value' => $status_name,
					'text'  => $status_name,
				);
			}

			return $statuses;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get list members formatted for dropdown.
	 *
	 * @param string $list_id The list ID.
	 *
	 * @return array
	 */
	public function get_list_members( $list_id ) {
		if ( empty( $list_id ) ) {
			return array();
		}

		try {
			$response = $this->api_request(
				array(
					'action'  => 'get_list_members',
					'list_id' => $list_id,
				)
			);

			$members = array();

			foreach ( $response['data']['members'] ?? array() as $member ) {
				if ( ! empty( $member['id'] ) ) {
					$members[] = array(
						'value' => (string) $member['id'],
						'text'  => sprintf( '%s (%s)', $member['username'], $member['email'] ),
					);
				}
			}

			return $members;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get tasks in a list.
	 *
	 * @param string $list_id The list ID.
	 *
	 * @return array
	 */
	public function get_list_tasks( $list_id ) {
		if ( empty( $list_id ) ) {
			return array();
		}

		try {
			$response = $this->api_request(
				array(
					'action'  => 'task_list',
					'list_id' => $list_id,
				)
			);

			$tasks = array();

			foreach ( $response['data']['tasks'] ?? array() as $task ) {
				$tasks[] = array(
					'value' => (string) $task['id'],
					'text'  => $task['name'],
				);
			}

			return $tasks;
		} catch ( Exception $e ) {
			return array();
		}
	}
}
