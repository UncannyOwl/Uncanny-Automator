<?php
namespace Uncanny_Automator;

use Exception;

class Zoho_Campaigns_Client_Auth {

	/**
	 * The nonce key to use when validating user credentials.
	 *
	 * @var string
	 */
	const NONCE_KEY = 'automator_zoho_agent';

	/**
	 * The access token expires value. 3600 = 1 hour.
	 *
	 * @var int
	 */
	const ACCESS_TOKEN_EXPIRES = 3600;

	/**
	 * The credentials that will be stored in the db.
	 *
	 * @var array
	 */
	protected $agent_credentials = array();

	/**
	 * Authorize client from http requests.
	 *
	 * This method is used when the user is redirected back from the OAuth dialog.
	 *
	 * @return self
	 */
	public function auth_from_http_query() {

		$message     = automator_filter_input( 'automator_api_message' );
		$credentials = Automator_Helpers_Recipe::automator_api_decode_message( $message, wp_create_nonce( self::NONCE_KEY ) );

		if ( false === $credentials ) {
			throw new Exception( 'Cannot parse returned message from the API.', 400 );
		}

		$this->set_agent_credentials( $credentials );

		return $this;
	}

	/**
	 * Sets the client credentials.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return self
	 */
	public function set_agent_credentials( $credentials ) {

		$this->agent_credentials = $credentials;

		return $this;
	}

	/**
	 * Retrieves the object's client credentials.
	 *
	 * @return array The client credentials.
	 */
	public function get_agent_credentials() {

		return $this->agent_credentials;
	}

	/**
	 * Updates the client.
	 *
	 * @param callable $success_callback Pass a callable method or function as a callback when HTTP request is successful.
	 * @param callable $error_callback Pass a callable method or function as a callback when HTTP request failed.
	 *
	 * @return void
	 */
	public function update_agent( callable $success_callback, callable $error_callback ) {

		try {

			automator_update_option( 'zoho_campaigns_credentials', $this->get_agent_credentials(), true );
			automator_update_option( 'zoho_campaigns_credentials_last_refreshed', time(), true );

			if ( is_callable( $success_callback ) ) {
				return call_user_func( $success_callback );
			}

			return true;

		} catch ( \Exception $e ) {

			if ( is_callable( $error_callback ) ) {
				return call_user_func( $error_callback, $e->getMessage() );
			}

			return false;

		}
	}

	/**
	 * Disconnects the client.
	 *
	 * @param callable $callback The method of function to call when the client has been successfully deleted.
	 *
	 * @return mixed The value returns from the callable parameter.
	 */
	public function disconnect_agent( callable $callback ) {

		if ( ! current_user_can( automator_get_admin_capability() ) || ! wp_verify_nonce( automator_filter_input( 'nonce' ), self::NONCE_KEY ) ) {
			wp_die( 'Insufficient privilege or nonce is invalid.', 403 );
		}

		// Delete credentials.
		automator_delete_option( 'zoho_campaigns_credentials' );
		// Delete last refreshed info.
		automator_delete_option( 'zoho_campaigns_credentials_last_refreshed' );

		do_action( 'automator_zoho_campaigns_client_disconnected', $this );

		return call_user_func( $callback );
	}

	/**
	 * Refreshes the access token when the difference in seconds reaches 3600.
	 *
	 * @param Api_Server $client The Api_Server.
	 *
	 * @throws Exception When something access token refresh is not successful.
	 *
	 * @return bool True if access token was refreshed.
	 */
	public function maybe_refresh_token( Api_Server $client = null ) {

		$current_datetime = time();

		$last_updated_datetime = automator_get_option( 'zoho_campaigns_credentials_last_refreshed', 0 );

		if ( empty( $last_updated_datetime ) ) {
			throw new Exception( 'Failed to renew refresh token. Last refreshed info is unknown. Disconnect and reconnect Zoho Campaigns.', 400 );
		}

		if ( $current_datetime - $last_updated_datetime >= self::ACCESS_TOKEN_EXPIRES ) {
			return $this->refresh_access_token( $client );
		}

		return true;
	}

	/**
	 * Refreshes the access token using the refresh token.
	 *
	 * @throws \Exception If refresh token is not generated.
	 *
	 * @return boolean True if credentials where successfully refreshed.
	 */
	public function refresh_access_token( Api_Server $client = null ) {

		do_action( 'automator_zoho_campaigns_before_refresh_access_token' );

		$body = array(
			'refresh_token' => $this->get_refresh_token(),
			'access_token'  => $this->get_access_token(),
			'action'        => 'refresh_token',
		);

		$params = array(
			'endpoint' => 'v2/zoho-campaigns',
			'body'     => $body,
			'action'   => null,
			'timeout'  => 45,
		);

		$response = $client::api_call( $params );

		$credentials = automator_get_option( 'zoho_campaigns_credentials' );

		// Update the access token from the credentials.
		if ( ! empty( $response['data']['access_token'] ) ) {

			$credentials['access_token'] = $response['data']['access_token'];

			// Update the access token with the new token coming from refresh token endpoint.
			automator_update_option( 'zoho_campaigns_credentials', $credentials, true );

			// Update last refresh time.
			automator_update_option( 'zoho_campaigns_credentials_last_refreshed', time(), true );

			do_action( 'automator_zoho_campaigns_before_access_token_succesful', $credentials, $response, $this );

			return true;

		}

		do_action( 'automator_zoho_campaigns_before_access_token_failed' );

		throw new \Exception( 'Unable to refresh the access token. Please disconnect and reconnect.', 400 );
	}

	/**
	 * Retrieves access token from the db.
	 *
	 * @return string|bool The access token. Returns false otherwise.
	 */
	public function get_access_token() {

		$credentials = automator_get_option( 'zoho_campaigns_credentials' );

		$access_token = ! empty( $credentials['access_token'] ) ? $credentials['access_token'] : false;

		return apply_filters( 'zoho_campaigns_get_access_token', $access_token, $credentials, $this );
	}

	/**
	 * Retrieves the user location from the db.
	 *
	 * @return string The user location.
	 */
	public function get_user_location() {

		$credentials = automator_get_option( 'zoho_campaigns_credentials' );

		$user_location = ! empty( $credentials['location'] ) ? $credentials['location'] : 'us';

		if ( ! is_string( $user_location ) ) {
			$user_location = 'us';
		}

		return apply_filters( 'automator_zoho_campaigns_user_location', $user_location, $credentials, $this );
	}

	/**
	 * Retrieves refresh token from the db.
	 *
	 * @return string|bool The access token. Returns false otherwise.
	 */
	public function get_refresh_token() {

		$credentials = automator_get_option( 'zoho_campaigns_credentials' );

		$refresh_token = ! empty( $credentials['refresh_token'] ) ? $credentials['refresh_token'] : false;

		return apply_filters( 'zoho_campaigns_get_refresh_token', $refresh_token, $credentials, $this );
	}
}
