<?php

namespace Uncanny_Automator\Integrations\Helpscout;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Uncanny_Automator\App_Integrations\Token_Refresh_Lock;
use Exception;

/**
 * Class Helpscout_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Helpscout_App_Helpers $helpers
 */
class Helpscout_Api_Caller extends Api_Caller {

	use Token_Refresh_Lock;

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set custom properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set the legacy credential request key.
		$this->set_credential_request_key( 'access_token' );

		// HelpScout uses a 2-hour buffer before token expiry.
		$this->set_token_refresh_buffer_seconds( 7200 );
	}

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The credentials to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string Valid access token.
	 * @throws Exception If access token is missing or invalid.
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		$token = $credentials['access_token'] ?? null;

		if ( empty( $token ) ) {
			throw new Exception( 'invalid credentials', 400 );
		}

		// HelpScout uses expires_on (timestamp when token expires).
		$expires_on = absint( $credentials['expires_on'] ?? 0 );

		// Check if token is expired or about to expire.
		if ( $this->is_token_expiring( $expires_on ) ) {
			$credentials = $this->handle_token_refresh_with_lock( $credentials, array( $this, 'refresh_and_store_token' ) );
			$token       = $credentials['access_token'] ?? null;

			if ( empty( $token ) ) {
				throw new Exception( 'invalid credentials', 400 );
			}
		}

		return $token;
	}

	/**
	 * Check for errors in API response.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs.
	 */
	public function check_for_errors( $response, $args = array() ) {

		$status_code = isset( $response['statusCode'] ) ? $response['statusCode'] : 0;

		$is_status_ok = $status_code >= 200 && $status_code <= 299;

		// Check for internal invalid credentials error.
		if ( isset( $response['error'] ) && false !== strpos( strtolower( $response['error'] ), 'invalid credentials' ) ) {
			// Handle invalid credentials error with formatted error message.
			$this->handle_400_error( $response, $args );
			return;
		}

		if ( 401 === $status_code ) {
			throw new Exception(
				esc_html_x( 'Authentication failed. Please check your connection.', 'Help Scout', 'uncanny-automator' ),
				401
			);
		}

		if ( ! $is_status_ok ) {

			// Check if API proxy already formatted the error message and threw exception.
			if ( ! empty( $response['error'] ) ) {
				throw new Exception(
					esc_html( $response['error'] ),
					absint( $status_code )
				);
			}

			// Check for OAuth error format.
			if ( ! empty( $response['data']['error'] ) ) {
				$err_message = $response['data']['error'] . ' - ' . $response['data']['error_description'];

				throw new Exception(
					esc_html( $err_message ),
					absint( $status_code )
				);
			}

			// Check for embedded errors format (raw API responses).
			if ( isset( $response['data']['_embedded']['errors'] ) ) {
				$errors         = $response['data']['_embedded']['errors'];
				$error_messages = array_column( $errors, 'message' );
				$err_message    = implode( '. ', $error_messages );

				throw new Exception(
					esc_html( $err_message ),
					absint( $status_code )
				);
			}

			throw new Exception(
				wp_json_encode( $response ),
				absint( $status_code )
			);
		}
	}

	////////////////////////////////////////////////////////////
	// OAuth methods
	////////////////////////////////////////////////////////////

	/**
	 * Refresh access token and store updated credentials.
	 *
	 * Used as callback for handle_token_refresh_with_lock().
	 *
	 * @param array $credentials Current credentials with refresh_token.
	 *
	 * @return array Updated credentials.
	 * @throws Exception If refresh fails.
	 */
	protected function refresh_and_store_token( $credentials ) {

		try {
			// Exclude credentials and error check for refresh request.
			$args = array(
				'include_timeout'     => 15,
				'exclude_error_check' => true,
				'exclude_credentials' => true,
			);

			$response = $this->api_request( 'refresh_access_token', null, $args );

			if ( 200 === $response['statusCode'] && ! empty( $response['data'] ) ) {
				// Add existing user info to credentials.
				// REVIEW - this should be migrated and separated to account_info option.
				$response['data']['user'] = $credentials['user'];
				$this->helpers->store_credentials( $response['data'] );

				return $this->helpers->get_credentials();
			}

			throw new Exception( 'Token refresh failed', 400 );

		} catch ( Exception $e ) {
			// Disconnect the integration to prevent further invalid requests.
			$this->helpers->delete_credentials();

			// Throw invalid credentials error to inform user they need to reconnect.
			throw new Exception( 'invalid credentials', 400 );
		}
	}

	////////////////////////////////////////////////////////////
	// API methods
	////////////////////////////////////////////////////////////

	/**
	 * Get tags from HelpScout.
	 *
	 * @return array
	 * @throws Exception If request fails.
	 */
	public function get_tags() {
		$response = $this->api_request( 'get_tags' );

		return $response['data']['_embedded']['tags'] ?? array();
	}

	/**
	 * Get mailboxes from HelpScout.
	 *
	 * @return array
	 * @throws Exception If request fails.
	 */
	public function get_mailboxes() {
		$response = $this->api_request( 'get_mailboxes' );

		return $response['data']['_embedded']['mailboxes'] ?? array();
	}

	/**
	 * Get conversations from a mailbox.
	 *
	 * @param int $mailbox_id The mailbox ID.
	 *
	 * @return array
	 * @throws Exception If request fails.
	 */
	public function get_conversations( $mailbox_id ) {
		$body = array(
			'mailbox' => $mailbox_id,
			'action'  => 'get_conversations',
		);

		$response = $this->api_request( $body );

		return $response['data']['_embedded']['conversations'] ?? array();
	}

	/**
	 * Get mailbox users.
	 *
	 * @param int $mailbox_id The mailbox ID.
	 *
	 * @return array
	 * @throws Exception If request fails.
	 */
	public function get_mailbox_users( $mailbox_id ) {
		$response = $this->api_request(
			array(
				'mailbox_id' => $mailbox_id,
				'action'     => 'get_mailbox_users',
			)
		);

		return $response['data']['_embedded']['users'] ?? array();
	}

	/**
	 * Get customer properties.
	 *
	 * @return array
	 * @throws Exception If request fails.
	 */
	public function get_properties() {
		$response = $this->api_request( 'get_properties' );

		return $response['data']['_embedded']['customer-properties'] ?? array();
	}
}
