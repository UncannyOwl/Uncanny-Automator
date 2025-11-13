<?php

namespace Uncanny_Automator\Integrations\Bluesky;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;
use WP_Error;
/**
 * Bluesky_API_Caller class
 *
 * @package Uncanny_Automator
 *
 * @property Bluesky_App_Helpers $helpers
 */
class Bluesky_API_Caller extends Api_Caller {

	////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////

	/**
	 * Prepare request credentials.
	 *
	 * @param array $credentials
	 * @param array $args
	 *
	 * @return array
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		$prepared                    = array();
		$prepared['handle']          = $credentials['handle'];
		$prepared['vault_signature'] = $credentials['vault_signature'];
		return wp_json_encode( $prepared );
	}

	////////////////////////////////////////////////////
	// Bluesky specific methods
	////////////////////////////////////////////////////

	/**
	 * Authenticate the user and store vault credentials.
	 *
	 * @param string $username
	 * @param string $app_password
	 *
	 * @return void
	 * @throws Exception If the user is not authenticated
	 */
	public function authenticate_user( $username, $app_password ) {

		$response = $this->bluesky_request(
			array(
				'action'       => 'authenticate',
				'username'     => $username,
				'app_password' => $app_password,
			),
			null,
			false
		);

		$data = isset( $response['data'] ) ? $response['data'] : array();
		if ( empty( $data ) ) {
			throw new Exception( esc_html_x( 'Invalid response please refresh the page and try again.', 'Bluesky', 'uncanny-automator' ) );
		}

		$this->helpers->store_credentials( $data );
	}

	/**
	 * Bluesky request.
	 *
	 * @param array $body
	 * @param array $action_data
	 * @param bool  $include_credentials
	 *
	 * @return array
	 */
	public function bluesky_request( $body, $action_data = null, $include_credentials = true ) {
		$args = array();

		if ( ! $include_credentials ) {
			$args['exclude_credentials'] = true;
		}

		return $this->api_request( $body, $action_data, $args );
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

		$message    = '';
		$error_code = '';

		if ( isset( $response['error'] ) ) {
			$error_code = $response['error'];
			$message    = $error_code;

			if ( isset( $response['message'] ) ) {
				$message .= ' : ' . $response['message'];
			}
		}

		if ( isset( $response['data'] ) && isset( $response['data']['error'] ) ) {
			$error_code = $response['data']['error'];
			$message    = $error_code;
			if ( isset( $response['data']['message'] ) ) {
				$message .= ' : ' . $response['data']['message'];
			}
		}

		// Check if the error code is an authorization error.
		if ( $this->is_authorization_error( $error_code ) ) {
			// Disconnect the user.
			$this->helpers->remove_credentials();
			$message .= ' ' . sprintf(
				// translators: %s: Settings page URL.
				esc_html_x( 'Please [reconnect your Bluesky account](%s).', 'BlueSky', 'uncanny-automator' ),
				$this->helpers->get_settings_page_url()
			);
		}

		if ( ! empty( $message ) ) {
			throw new Exception( esc_html( $message ) );
		}
	}

	/**
	 * Check if the error code is an authorization error.
	 *
	 * @param string $error_code
	 *
	 * @return bool
	 */
	public function is_authorization_error( $error_code ) {

		if ( empty( $error_code ) ) {
			return false;
		}

		$codes = array(
			'AccountTakedown',
			'AuthenticationRequired',
			'ExpiredToken',
			'InvalidToken',
		);

		return in_array( $error_code, $codes, true );
	}
}
