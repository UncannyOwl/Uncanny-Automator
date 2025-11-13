<?php
namespace Uncanny_Automator\Integrations\Campaign_Monitor;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Campaign_Monitor_Api_Caller
 *
 * @package Uncanny_Automator
 */
class Campaign_Monitor_Api_Caller extends Api_Caller {

	/**
	 * Invalid token error.
	 *
	 * @var string
	 */
	const INVALID_TOKEN_ERROR = 'invalid_token';

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Register error patterns.
		$this->register_error_messages(
			array(
				self::INVALID_TOKEN_ERROR => array(
					'message'   => 'Your Campaign Monitor connection has expired. [reconnect](%s)',
					'help_link' => $this->helpers->get_settings_page_url(),
				),
			)
		);

		// Map credential request key to access_token until migration to vault.
		$this->set_credential_request_key( 'access_token' );
	}

	/**
	 * Prepare credentials for request.
	 *
	 * @param array $credentials
	 * @param array $args
	 *
	 * @return string
	 * @throws Exception
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		// Check if token needs refresh.
		if ( $this->token_requires_refresh( $credentials ) ) {
			$credentials = $this->refresh_access_token( $credentials );
		}

		// Return access token until migration to vault.
		return $credentials['access_token'];
	}

	/**
	 * Check if token requires refresh.
	 *
	 * @param array $credentials
	 *
	 * @return bool
	 * @throws Exception
	 */
	private function token_requires_refresh( $credentials ) {
		$expires_on = $credentials['expires_on'] ?? 0;

		if ( empty( $expires_on ) ) {
			throw new Exception( esc_html( self::INVALID_TOKEN_ERROR ) );
		}

		// Validate expiration.
		return time() >= $expires_on;
	}

	/**
	 * Refresh access token.
	 *
	 * @param array $credentials
	 *
	 * @return array
	 * @throws Exception
	 */
	private function refresh_access_token( $credentials ) {

		$refresh_token = $credentials['refresh_token'] ?? '';

		if ( empty( $refresh_token ) ) {
			throw new Exception( esc_html( self::INVALID_TOKEN_ERROR ) );
		}

		$response = $this->api_request(
			array(
				'action'        => 'refresh_access_token',
				'refresh_token' => $refresh_token,
			),
			null, // No action data.
			array(
				'exclude_credentials' => true, // This will skip refresh check.
			)
		);

		if ( empty( $response['data'] ) ) {
			throw new Exception( esc_html( self::INVALID_TOKEN_ERROR ) );
		}

		// Save new credentials.
		$this->helpers->store_credentials( $response['data'] );

		return $response['data'];
	}

	/**
	 * Check for errors in the response.
	 *
	 * @param mixed $response
	 * @param array $args
	 *
	 * @return void
	 * @throws Exception
	 */
	public function check_for_errors( $response, $args = array() ) {

		// Catch internal invalid token error for custom messaging.
		if ( isset( $response['error'] ) && self::INVALID_TOKEN_ERROR === $response['error'] ) {
			$this->handle_400_error( $response, $args );
		}

		if ( isset( $response['data']['error'] ) ) {
			$message = $response['data']['error_description'] ?? $response['data']['error'];
			throw new Exception( esc_html( $message ), 400 );
		}

		if ( isset( $response['statusCode'] ) && $response['statusCode'] >= 400 ) {
			$message = $response['data']['error_description'] ?? $response['data']['Message'] ?? esc_html_x( 'An error occurred', 'Campaign Monitor', 'uncanny-automator' );
			throw new Exception( esc_html( $message ), absint( $response['statusCode'] ) );
		}

		if ( empty( $response['data'] ) && 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'No data returned from Campaign Monitor', 'Campaign Monitor', 'uncanny-automator' ), 400 );
		}
	}

	/**
	 * Get primary contact.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_primary_contact() {
		return $this->api_request( 'get_primary_contact' );
	}

	/**
	 * Get clients.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_clients() {
		return $this->api_request( 'get_clients' );
	}

	/**
	 * Get lists.
	 *
	 * @param string $client_id

	 * @return array
	 * @throws Exception
	 */
	public function get_lists( $client_id = '' ) {
		return $this->api_request(
			array(
				'action'    => 'get_lists',
				'client_id' => $client_id,
			)
		);
	}

	/**
	 * Get custom fields.
	 *
	 * @param string $list_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_custom_fields( $list_id ) {
		return $this->api_request(
			array(
				'action'  => 'get_custom_fields',
				'list_id' => $list_id,
			)
		);
	}
}
