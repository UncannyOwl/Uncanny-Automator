<?php

namespace Uncanny_Automator\Integrations\Twilio;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Twilio_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Twilio_App_Helpers $helpers
 */
class Twilio_Api_Caller extends Api_Caller {

	/**
	 * Custom API request method for Twilio API Authenticated requests.
	 * This allows us to still utilize all the other mapping etc with the abstract api_request method.
	 *
	 * @param array $body
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function twilio_api_request( $body, $action_data = null ) {
		$credentials = $this->helpers->get_credentials();

		if ( empty( $credentials ) ) {
			throw new Exception( esc_html_x( 'Twilio is not connected', 'Twilio', 'uncanny-automator' ) );
		}

		// Convert string to action array.
		if ( is_string( $body ) ) {
			$body = array( 'action' => $body );
		}

		// Add credentials to body as legacy format requires.
		$body['account_sid'] = $credentials['account_sid'];
		$body['auth_token']  = $credentials['auth_token'];

		// Set flag to exclude manual credential population.
		$args = array(
			'exclude_credentials' => true,
		);

		return $this->api_request( $body, $action_data, $args );
	}

	/**
	 * Check for errors in response
	 *
	 * @param array $response
	 * @param array $args Optional additional arguments
	 * @throws Exception
	 */
	public function check_for_errors( $response, $args = array() ) {

		if ( 200 !== $response['statusCode'] ) {

			// First check for a straight error being returned.
			// API Proxy uses curl client and is responding differently with this integration.
			$error = $response['error'] ?? '';
			if ( ! empty( $error ) ) {
				throw new Exception( esc_html( $error ) );
			}

			// Fallback to data message.
			$error_message = isset( $response['data']['message'] )
				? $response['data']['message']
				// translator: %d is the status code
				: sprintf( esc_html_x( 'API request failed with status %d', 'Twilio', 'uncanny-automator' ), absint( $response['statusCode'] ) );

			throw new Exception( esc_html( $error_message ) );
		}
	}

	/**
	 * Get account info
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_account_info() {
		$response = $this->twilio_api_request( 'account_info' );

		return $response['data'] ?? array();
	}

	/**
	 * Validate phone number exists in Twilio account
	 *
	 * @param string $phone_number
	 * @return array
	 * @throws Exception
	 */
	public function validate_phone_number_in_account( $phone_number ) {
		$response = $this->twilio_api_request(
			array(
				'action'       => 'validate_phone_number',
				'phone_number' => $phone_number,
			)
		);

		return $response['data'] ?? array();
	}

	/**
	 * Send SMS
	 *
	 * @param string $to
	 * @param string $body
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function send_sms( $to, $body, $action_data = null ) {
		// Get the from number.
		$credentials = $this->helpers->get_credentials();
		$from        = $credentials['phone_number'];

		if ( empty( $from ) ) {
			throw new Exception( esc_html_x( 'Twilio phone number is missing.', 'Twilio', 'uncanny-automator' ) );
		}

		// Validate the to number.
		$to = $this->helpers->validate_phone_number( $to );
		if ( ! $to ) {
			throw new Exception( esc_html_x( 'To number is not valid.', 'Twilio', 'uncanny-automator' ) );
		}

		$response = $this->twilio_api_request(
			array(
				'action' => 'send_sms',
				'from'   => $from,
				'to'     => $to,
				'body'   => $body,
			),
			$action_data
		);

		return $response['data'];
	}
}
