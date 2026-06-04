<?php

namespace Uncanny_Automator\Integrations\Gotowebinar;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator\App_Integrations\Api_Caller;
use Uncanny_Automator\App_Integrations\Token_Refresh_Lock;
use Exception;

/**
 * Class Gotowebinar_Api_Caller
 *
 * @package Uncanny_Automator
 * @property Gotowebinar_App_Helpers $helpers
 */
class Gotowebinar_Api_Caller extends Api_Caller {

	use Token_Refresh_Lock;

	/**
	 * GoTo OAuth base URL.
	 *
	 * @var string
	 */
	const OAUTH_BASE_URL = 'https://api.getgo.com/oauth/v2/';

	/**
	 * Get the OAuth base URL.
	 *
	 * @return string
	 */
	public function get_oauth_base_url() {
		return self::OAUTH_BASE_URL;
	}

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set properties for API caller.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Use 'client' key for legacy API proxy compatibility.
		$this->set_credential_request_key( 'client' );
	}

	/**
	 * Prepare request credentials for API requests.
	 *
	 * Checks token expiry and refreshes if needed before returning credentials.
	 * Uses locking to prevent concurrent token refresh attempts.
	 *
	 * @param array $credentials The stored credentials (access_token, organizer_key, expires_at).
	 * @param array $args Additional arguments.
	 *
	 * @return array Client credentials for the request.
	 * @throws Exception If required credentials are missing.
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		if ( empty( $credentials['access_token'] ) || empty( $credentials['organizer_key'] ) ) {
			throw new Exception( esc_html_x( 'GoTo Webinar credentials are missing or invalid. Please reconnect your account.', 'GoToWebinar', 'uncanny-automator' ) );
		}

		// Check if token is expired or about to expire (uses trait's buffer).
		$expires_at = $credentials['expires_at'] ?? 0;
		if ( $this->is_token_expiring( $expires_at ) ) {
			$credentials = $this->handle_token_refresh_with_lock( $credentials, array( $this, 'refresh_and_store_token' ) );
		}

		return array(
			'access_token'  => $credentials['access_token'],
			'organizer_key' => $credentials['organizer_key'],
		);
	}

	////////////////////////////////////////////////////////////
	// OAuth methods
	////////////////////////////////////////////////////////////

	/**
	 * Exchange authorization code for access tokens.
	 *
	 * @param string $code  The authorization code from GoTo.
	 * @param string $state The state/nonce parameter for validation.
	 *
	 * @return array Token data from GoTo.
	 * @throws Exception If token exchange fails.
	 */
	public function exchange_code_for_tokens( $code, $state ) {

		// Validate nonce (includes user ID for uniqueness).
		if ( ! wp_verify_nonce( $state, 'automator_gtw_oauth_' . get_current_user_id() ) ) {
			throw new Exception( esc_html_x( 'Invalid OAuth state. Please try again.', 'GoToWebinar', 'uncanny-automator' ) );
		}

		$params = array(
			'method'  => 'POST',
			'url'     => self::OAUTH_BASE_URL . 'token',
			'headers' => array(
				'Content-Type'  => 'application/x-www-form-urlencoded; charset=utf-8',
				'Authorization' => 'Basic ' . base64_encode( $this->helpers->get_client_id() . ':' . $this->helpers->get_client_secret() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Accept'        => 'application/json',
			),
			'body'    => array(
				'code'         => $code,
				'grant_type'   => 'authorization_code',
				'redirect_uri' => $this->helpers->get_settings_page_url(),
			),
		);

		$response = Api_Server::call( $params );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			throw new Exception( esc_html_x( 'Error validating OAuth tokens', 'GoToWebinar', 'uncanny-automator' ) );
		}

		$token_data = json_decode( $response['body'], true );

		if ( empty( $token_data['access_token'] ) ) {
			throw new Exception( esc_html_x( 'Invalid token response from GoTo', 'GoToWebinar', 'uncanny-automator' ) );
		}

		return $token_data;
	}

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

		if ( empty( $credentials['refresh_token'] ) ) {
			throw new Exception( esc_html_x( 'GoTo Webinar credentials have expired. Please reconnect your account.', 'GoToWebinar', 'uncanny-automator' ) );
		}

		$token_data = $this->refresh_access_token( $credentials['refresh_token'] );

		// Merge new token data with existing credentials (preserves organizer_key, user info).
		$updated_credentials = array_merge( $credentials, $token_data );

		// Store updated credentials.
		$this->helpers->store_credentials( $updated_credentials );

		return $this->helpers->get_credentials();
	}

	/**
	 * Refresh access token using refresh token.
	 *
	 * @param string $refresh_token The refresh token.
	 *
	 * @return array Token data from GoTo.
	 * @throws Exception If refresh fails.
	 */
	private function refresh_access_token( $refresh_token ) {

		$params = array(
			'method'  => 'POST',
			'url'     => self::OAUTH_BASE_URL . 'token',
			'headers' => array(
				'Content-Type'  => 'application/x-www-form-urlencoded; charset=utf-8',
				'Authorization' => 'Basic ' . base64_encode( $this->helpers->get_client_id() . ':' . $this->helpers->get_client_secret() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Accept'        => 'application/json',
			),
			'body'    => array(
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
			),
		);

		$response = Api_Server::call( $params );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			throw new Exception( esc_html_x( 'GoTo Webinar credentials have expired. Please reconnect your account.', 'GoToWebinar', 'uncanny-automator' ) );
		}

		$token_data = json_decode( $response['body'], true );

		if ( empty( $token_data['access_token'] ) ) {
			throw new Exception( esc_html_x( 'Failed to refresh GoTo Webinar token. Please reconnect your account.', 'GoToWebinar', 'uncanny-automator' ) );
		}

		return $token_data;
	}

	////////////////////////////////////////////////////////////
	// API methods
	////////////////////////////////////////////////////////////

	/**
	 * Register user to a webinar session.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $webinar_key Webinar key.
	 * @param mixed  $action_data Action data for logging.
	 *
	 * @return array The registration data (joinUrl, registrantKey).
	 * @throws Exception On API errors.
	 */
	public function register_user_to_webinar( $user_id, $webinar_key, $action_data = null ) {

		$user = get_userdata( $user_id );

		if ( empty( $user ) ) {
			throw new Exception( esc_html_x( 'GoTo Webinar user not found.', 'GoToWebinar', 'uncanny-automator' ) );
		}

		$customer_first_name = $user->first_name;
		$customer_last_name  = $user->last_name;
		$customer_email      = $user->user_email;

		if ( ! empty( $customer_email ) ) {
			$customer_email_parts = explode( '@', $customer_email );
			$customer_first_name  = empty( $customer_first_name ) ? $customer_email_parts[0] : $customer_first_name;
			$customer_last_name   = empty( $customer_last_name ) ? $customer_email_parts[0] : $customer_last_name;
		}

		$body = array(
			'action'      => 'gtw_register_user',
			'webinar_key' => $webinar_key,
			'user'        => wp_json_encode(
				array(
					'firstName' => $customer_first_name,
					'lastName'  => $customer_last_name,
					'email'     => $customer_email,
				)
			),
		);

		$response = $this->api_request( $body, $action_data );

		$code     = $response['statusCode'];
		$jsondata = $response['data'];

		if ( 200 !== $code && 201 !== $code ) {
			throw new Exception( esc_html( $jsondata['description'] ), absint( $code ) );
		}

		if ( ! isset( $jsondata['joinUrl'] ) ) {
			throw new Exception( esc_html_x( 'Error adding user to GoTo Webinar', 'GoToWebinar', 'uncanny-automator' ) );
		}

		update_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey', $jsondata['registrantKey'] );
		update_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_joinUrl', $jsondata['joinUrl'] );

		return $jsondata;
	}

	/**
	 * Unregister user from a webinar session.
	 *
	 * @param int    $user_id     User ID.
	 * @param string $webinar_key Webinar key.
	 * @param mixed  $action_data Action data for logging.
	 *
	 * @return void
	 * @throws Exception On API errors.
	 */
	public function unregister_user_from_webinar( $user_id, $webinar_key, $action_data = null ) {

		$user_registrant_key = get_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey', true );

		if ( empty( $user_registrant_key ) ) {
			throw new Exception( esc_html_x( 'User was not registered for webinar.', 'GoToWebinar', 'uncanny-automator' ) );
		}

		$body = array(
			'action'              => 'gtw_unregister_user',
			'webinar_key'         => $webinar_key,
			'user_registrant_key' => $user_registrant_key,
		);

		$response = $this->api_request( $body, $action_data );

		$jsondata = $response['data'];
		$code     = $response['statusCode'];

		if ( 200 !== $code && 201 !== $code && 204 !== $code ) {
			throw new Exception( esc_html( $jsondata['description'] ) );
		}

		delete_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey' );
		delete_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_joinUrl' );
	}
}
