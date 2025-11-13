<?php

namespace Uncanny_Automator\Integrations\Helpscout;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Helpscout_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Helpscout_App_Helpers $helpers
 */
class Helpscout_Api_Caller extends Api_Caller {

	/**
	 * The key for the token refresh lock.
	 *
	 * @var string
	 */
	const TOKEN_REFRESH_LOCK_KEY = 'helpscout_token_refresh_lock';

	/**
	 * Set custom properties.
	 */
	public function set_properties() {
		// Set the legacy credential request key.
		$this->set_credential_request_key( 'access_token' );
	}

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The credentials to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string Valid access token.
	 * @throws Exception if access token is missing / invalid.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		$token = $this->validate_access_token( $credentials );
		return $token;
	}

	/**
	 * Validate access token.
	 *
	 * @param array $credentials The credentials to validate.
	 *
	 * @return string
	 * @throws Exception invalid credentials which will be mapped to formatted error message
	 */
	private function validate_access_token( $credentials ) {

		// Get the access token.
		$token = $credentials['access_token'] ?? null;
		if ( empty( $token ) ) {
			throw new Exception( 'invalid credentials', 400 );
		}

		// Get the expiration date.
		$expires_on = absint( $credentials['expires_on'] ?? 0 );
		if ( empty( $expires_on ) ) {
			throw new Exception( 'invalid credentials', 400 );
		}

		// Check if token needs refresh (with 2 hour buffer).
		if ( time() >= $expires_on - 7200 ) {
			$token = $this->handle_token_refresh( $credentials );
		}

		return $token;
	}

	/**
	 * Handle token refresh with locking to prevent concurrent refreshes.
	 * TODO: Remove this when migrating to vault-based token management (Phase 2).
	 *
	 * @param array $credentials The credentials to refresh.
	 *
	 * @return string The refreshed access token.
	 * @throws Exception if token refresh fails.
	 */
	private function handle_token_refresh( $credentials ) {
		if ( get_transient( self::TOKEN_REFRESH_LOCK_KEY ) ) {
			// Another request is refreshing - wait for it to complete.
			sleep( 4 );
		} else {
			// We're the first request - perform the refresh.
			$this->refresh_access_token( $credentials );
		}

		// Fetch the latest credentials (either refreshed by us or by the other request).
		$credentials = $this->helpers->get_credentials();
		$token       = $credentials['access_token'] ?? null;

		if ( empty( $token ) ) {
			throw new Exception( 'invalid credentials', 400 );
		}

		return $token;
	}

	/**
	 * Refresh access token with lock to prevent concurrent refreshes.
	 *
	 * @param array $credentials The credentials to refresh.
	 *
	 * @return void
	 */
	private function refresh_access_token( $credentials ) {

		// Check if another request is already refreshing - if so, bail out.
		if ( get_transient( self::TOKEN_REFRESH_LOCK_KEY ) ) {
			return;
		}

		// Set lock for 15 seconds.
		set_transient( self::TOKEN_REFRESH_LOCK_KEY, true, 15 );

		try {
			// Exclude credentials and error check.
			$args = array(
				'include_timeout'     => 15,
				'exclude_error_check' => true,
				'exclude_credentials' => true,
			);

			$response = $this->api_request( 'refresh_access_token', null, $args );
			if ( 200 === $response['statusCode'] && ! empty( $response['data'] ) ) {
				// Add existing user info to credentials.
				// REVIEW - this should be migrated and seperated to account_info option.
				$response['data']['user'] = $credentials['user'];
				$this->helpers->store_credentials( $response['data'] );
			}
		} catch ( Exception $e ) {
			// Disconnect the integration completely to prevent further invalid requests.
			$this->helpers->delete_credentials();

			// Throw invalid credentials error to inform user they need to reconnect.
			throw new Exception( 'invalid credentials', 400 );
		} finally {
			// Always release the lock.
			delete_transient( self::TOKEN_REFRESH_LOCK_KEY );
		}
	}

	/**
	 * Check for errors in API response
	 *
	 * @param array $response
	 * @param array $args
	 *
	 * @return void
	 * @throws Exception
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

	/**
	 * Get tags from HelpScout
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_tags() {
		$response = $this->api_request( 'get_tags' );

		return $response['data']['_embedded']['tags'] ?? array();
	}

	/**
	 * Get mailboxes from HelpScout
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_mailboxes() {
		$response = $this->api_request( 'get_mailboxes' );

		return $response['data']['_embedded']['mailboxes'] ?? array();
	}

	/**
	 * Get conversations from a mailbox
	 *
	 * @param int $mailbox_id
	 *
	 * @return array
	 * @throws Exception
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
	 * Get mailbox users
	 *
	 * @param int $mailbox_id
	 * @return array
	 * @throws Exception
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
	 * Get customer properties
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_properties() {
		$response = $this->api_request( 'get_properties' );
		return $response['data']['_embedded']['customer-properties'] ?? array();
	}
}
