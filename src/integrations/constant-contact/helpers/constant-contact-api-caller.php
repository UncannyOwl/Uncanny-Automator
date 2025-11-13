<?php

namespace Uncanny_Automator\Integrations\Constant_Contact;

use Exception;

/**
 * Class Constant_Contact_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Constant_Contact_App_Helpers $helpers
 */
class Constant_Contact_Api_Caller extends \Uncanny_Automator\App_Integrations\Api_Caller {

	/**
	 * The transient name for the access token refresh.
	 *
	 * @var string
	 */
	const TRANSIENT_ACCESS_TOKEN_REFRESH = 'automator_constant_contact_token_refresh';

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set the credential request key.
		$this->set_credential_request_key( 'access_token' );
	}

	/**
	 * Prepare request credentials.
	 *
	 * @param mixed $credentials
	 * @param array $args
	 *
	 * @return string
	 * @throws Exception
	 */
	public function prepare_request_credentials( $credentials, $args = array() ) {
		// Check if token needs refresh.
		if ( $this->should_refresh_token( $credentials ) ) {
			$credentials = $this->refresh_access_token( $credentials );
		}

		return $credentials['access_token'];
	}

	/**
	 * Check if token should be refreshed.
	 *
	 * @param array $credentials
	 * @return bool
	 */
	private function should_refresh_token( $credentials ) {
		// Check if we have expires_in and refresh_token.
		if ( empty( $credentials['expires_in'] ) || empty( $credentials['refresh_token'] ) ) {
			return false;
		}

		// Check if already refreshing.
		$is_refreshing = get_transient( self::TRANSIENT_ACCESS_TOKEN_REFRESH );
		if ( false !== $is_refreshing ) {
			return false;
		}

		// Check if token is about to expire (within 2 hours).
		$expires_at   = $credentials['expires_at'] ?? 0;
		$current_time = time();
		$buffer_time  = 2 * HOUR_IN_SECONDS;

		return ( $expires_at - $buffer_time ) <= $current_time;
	}

	/**
	 * Refresh the access token.
	 *
	 * @param array $credentials
	 * @return array
	 * @throws Exception
	 */
	private function refresh_access_token( $credentials ) {
		// Set transient to prevent multiple refresh attempts.
		set_transient( self::TRANSIENT_ACCESS_TOKEN_REFRESH, true, 30 );

		try {
			$body = array(
				'access_token'  => $credentials['access_token'],
				'refresh_token' => $credentials['refresh_token'],
				'action'        => 'refresh_access_token',
			);

			$response = $this->api_request( $body, null, array( 'exclude_credentials' => true ) );

			if ( isset( $response['data'] ) ) {
				// Add expires_at timestamp.
				$response['data']['expires_at'] = time() + intval( $response['data']['expires_in'] );

				// Store new credentials.
				$this->helpers->store_credentials( $response['data'] );

				// Delete transient.
				delete_transient( self::TRANSIENT_ACCESS_TOKEN_REFRESH );

				return $response['data'];
			}

			throw new Exception( esc_html_x( 'Failed to refresh access token.', 'Constant Contact', 'uncanny-automator' ) );

		} catch ( Exception $e ) {
			// Delete transient on error.
			delete_transient( self::TRANSIENT_ACCESS_TOKEN_REFRESH );
			throw $e;
		}
	}

	/**
	 * Get user info.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_user_info() {
		return $this->api_request( 'get_user_info' );
	}

	/**
	 * Get contact custom fields.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function contact_fields_get() {
		return $this->api_request( 'contact_fields_get' );
	}

	/**
	 * Get tags list.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function list_tags() {
		return $this->api_request( 'list_tags' );
	}

	/**
	 * Get list memberships.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function list_memberships_get() {
		return $this->api_request( 'list_memberships_get' );
	}

	/**
	 * Create or update a contact.
	 *
	 * @param array $body
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function create_contact( $body, $action_data = null ) {
		$body['action'] = 'create_contact';
		return $this->api_request( $body, $action_data );
	}

	/**
	 * Delete a contact.
	 *
	 * @param string $email_address
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function contact_delete( $email_address, $action_data = null ) {
		$body = array(
			'action'        => 'contact_delete',
			'email_address' => $email_address,
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Add contact to list.
	 *
	 * @param string $email_address
	 * @param string $list_id
	 * @param array $action_data
	 * @return array
	 * @throws Exception
	 */
	public function contact_list_add_to( $email_address, $list_id, $action_data = null ) {
		$body = array(
			'action'        => 'contact_list_add_to',
			'email_address' => $email_address,
			'list'          => $list_id,
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Add tag to contact.
	 *
	 * @param string $email_address
	 * @param string $tag_id
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function contact_tag_add_to( $email_address, $tag_id, $action_data = null ) {
		$body = array(
			'action'        => 'contact_tag_add_to',
			'email_address' => $email_address,
			'tag'           => $tag_id,
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Check for errors in response.
	 *
	 * @param array $response
	 * @param array $args
	 * @return void
	 * @throws Exception
	 */
	public function check_for_errors( $response, $args = array() ) {

		if ( ! empty( $response['data']['error_key'] ) ) {
			$message = sprintf( '[%s] &mdash; %s', $response['data']['error_key'], $response['data']['error_message'] );
			throw new Exception( esc_html( $message ), absint( $response['statusCode'] ) );
		}

		if ( ! in_array( $response['statusCode'], array( 200, 201, 204 ), true ) ) {

			$error_message = sprintf(
				// translators: 1: status code, 2: response data
				esc_html_x( 'Constant Contant has responded with status code %d' . PHP_EOL . '%s', 'Constant Contact', 'uncanny-automator' ),
				$response['statusCode'],
				wp_json_encode( $response['data'] )
			);

			throw new Exception( esc_html( $error_message ) );
		}
	}
}
