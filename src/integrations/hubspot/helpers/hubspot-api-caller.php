<?php

namespace Uncanny_Automator\Integrations\HubSpot;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class HubSpot_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property HubSpot_App_Helpers $helpers
 */
class HubSpot_Api_Caller extends Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string - The prepared credentials JSON string.
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		if ( empty( $credentials['hubspot_id'] ) || empty( $credentials['vault_signature'] ) ) {
			throw new Exception( esc_html_x( 'Invalid credentials', 'HubSpot', 'uncanny-automator' ) );
		}

		return wp_json_encode(
			array(
				'vault_signature' => $credentials['vault_signature'],
				'hubspot_id'      => $credentials['hubspot_id'],
			)
		);
	}

	/**
	 * Check for errors in API response.
	 *
	 * @param array $response
	 * @param array $args
	 * @return void
	 *
	 * @throws Exception
	 */
	public function check_for_errors( $response, $args = array() ) {

		if ( isset( $response['data']['status'] ) && 'error' === $response['data']['status'] ) {

			$message = $this->extract_error_message( $response );

			throw new Exception( esc_html( $message ) );
		}

		if ( 200 !== intval( $response['statusCode'] ) ) {
			throw new Exception(
				sprintf(
					// translators: %s: API status code
					esc_html_x( 'API returned an error: %s', 'HubSpot', 'uncanny-automator' ),
					absint( $response['statusCode'] )
				),
				absint( $response['statusCode'] )
			);
		}
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Extract error message from API response.
	 *
	 * @param  array $response
	 * @return string
	 */
	public function extract_error_message( $response ) {

		$message = esc_html_x( 'API returned an error:', 'HubSpot', 'uncanny-automator' ) . $response['statusCode'];

		if ( ! empty( $response['data']['message'] ) ) {
			$message = $response['data']['message'] . "\n";
		}

		if ( ! empty( $response['data']['validationResults'] ) ) {

			foreach ( $response['data']['validationResults'] as $result ) {

				if ( ! empty( $result['error'] ) ) {
					$message .= '(' . $result['error'] . ')';
				}

				if ( ! empty( $result['name'] ) ) {
					$message .= ' Field: ' . $result['name'];
				}

				if ( ! empty( $result['message'] ) ) {
					$message .= "\n" . $result['message'] . ')';
				}
			}
		}

		return $message;
	}

	/**
	 * Get token info from API.
	 *
	 * @return array|false
	 */
	public function get_connected_account_info() {
		try {
			$response = $this->api_request( 'access_token_info' );
			return $response['data'] ?? false;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Create or update a contact.
	 *
	 * @param  mixed $properties
	 * @param  bool $update
	 * @param  mixed $action_data
	 * @return array
	 */
	public function create_contact( $properties, $update = true, $action_data = null ) {

		$params = array(
			'action'     => $update ? 'create_or_update_contact' : 'create_contact',
			'properties' => wp_json_encode( $properties ),
		);

		$response = $this->api_request( $params, $action_data );

		return $response;
	}

	/**
	 * Add contact to segment.
	 *
	 * @param  mixed $list_id
	 * @param  mixed $email
	 * @param  mixed $action_data
	 * @return array
	 */
	public function add_contact_to_list( $list_id, $email, $action_data ) {

		if ( empty( $email ) ) {
			throw new Exception( esc_html_x( 'Email is missing', 'HubSpot', 'uncanny-automator' ) );
		}

		if ( empty( $list_id ) ) {
			throw new Exception( esc_html_x( 'Segment is missing', 'HubSpot', 'uncanny-automator' ) );
		}

		$params = array(
			'action' => 'add_contact_to_list',
			'email'  => $email,
			'list'   => $list_id,
		);

		return $this->api_request( $params, $action_data );
	}

	/**
	 * Remove contact from segment.
	 *
	 * @param  mixed $list_id
	 * @param  mixed $email
	 * @param  mixed $action_data
	 * @return array
	 */
	public function remove_contact_from_list( $list_id, $email, $action_data ) {

		if ( empty( $email ) ) {
			throw new Exception( esc_html_x( 'Email is missing', 'HubSpot', 'uncanny-automator' ) );
		}

		if ( empty( $list_id ) ) {
			throw new Exception( esc_html_x( 'Segment is missing', 'HubSpot', 'uncanny-automator' ) );
		}

		$params = array(
			'action' => 'remove_contact_from_list',
			'email'  => $email,
			'list'   => $list_id,
		);

		return $this->api_request( $params, $action_data );
	}
}
