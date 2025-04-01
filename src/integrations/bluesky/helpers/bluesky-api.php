<?php

namespace Uncanny_Automator\Integrations\Bluesky;

use Exception;
use WP_Error;
use Uncanny_Automator\Api_Server;

class Bluesky_API {

	/**
	 * Helpers
	 *
	 * @var Bluesky_Helpers
	 */
	protected $helpers;

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/bluesky';

	/**
	 * __construct
	 *
	 * @param  mixed $helpers
	 *
	 * @return void
	 */
	public function __construct( $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * API request.
	 *
	 * @param array $body
	 * @param array $action_data
	 * @param bool  $include_credentials
	 *
	 * @return array
	 * @throws Exception
	 */
	public function api_request( $body, $action_data = null, $include_credentials = true ) {

		// Append credentials to the body.
		if ( $include_credentials ) {
			$body['credentials'] = $this->get_api_request_credentials();
		}

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;
	}

	/**
	 * Get API request credentials.
	 *
	 * @return string - JSON encoded credentials
	 * @throws Exception - If server credentials are invalid
	 */
	private function get_api_request_credentials() {

		$data                           = $this->helpers->get_credentials();
		$credentials                    = array();
		$credentials['handle']          = $data['handle'];
		$credentials['vault_signature'] = $data['vault_signature'];

		return wp_json_encode( $credentials );
	}

	/**
	 * Check for errors.
	 *
	 * @param mixed $response
	 *
	 * @return void
	 * @throws Exception
	 */
	private function check_for_errors( $response ) {

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

	/**
	 * Get API endpoint.
	 *
	 * @return string
	 */
	public function get_api_endpoint() {
		return self::API_ENDPOINT;
	}
}
