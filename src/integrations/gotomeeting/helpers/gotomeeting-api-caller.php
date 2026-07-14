<?php

namespace Uncanny_Automator\Integrations\Gotomeeting;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator\App_Integrations\Api_Caller;
use Uncanny_Automator\App_Integrations\Token_Refresh_Lock;
use Exception;

/**
 * Class Gotomeeting_Api_Caller
 *
 * @package Uncanny_Automator
 * @property Gotomeeting_App_Helpers $helpers
 */
class Gotomeeting_Api_Caller extends Api_Caller {

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
	 * Uses locking to prevent concurrent token refresh attempts. GoToMeeting
	 * V1 needs only the access token (no organizerKey path segment).
	 *
	 * @param array $credentials The stored credentials (access_token, expires_at).
	 * @param array $args Additional arguments.
	 *
	 * @return array Client credentials for the request.
	 * @throws Exception If required credentials are missing.
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		if ( empty( $credentials['access_token'] ) ) {
			throw new Exception( esc_html_x( 'GoTo Meeting credentials are missing or invalid. Please reconnect your account.', 'GoToMeeting', 'uncanny-automator' ) );
		}

		// Check if token is expired or about to expire (uses trait's buffer).
		$expires_at = $credentials['expires_at'] ?? 0;
		if ( $this->is_token_expiring( $expires_at ) ) {
			$credentials = $this->handle_token_refresh_with_lock( $credentials, array( $this, 'refresh_and_store_token' ) );
		}

		return array(
			'access_token' => $credentials['access_token'],
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
		if ( ! wp_verify_nonce( $state, 'automator_gtm_oauth_' . get_current_user_id() ) ) {
			throw new Exception( esc_html_x( 'Invalid OAuth state. Please try again.', 'GoToMeeting', 'uncanny-automator' ) );
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
			throw new Exception( esc_html_x( 'Error validating OAuth tokens', 'GoToMeeting', 'uncanny-automator' ) );
		}

		$token_data = json_decode( $response['body'], true );

		if ( empty( $token_data['access_token'] ) ) {
			throw new Exception( esc_html_x( 'Invalid token response from GoTo', 'GoToMeeting', 'uncanny-automator' ) );
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
			throw new Exception( esc_html_x( 'GoTo Meeting credentials have expired. Please reconnect your account.', 'GoToMeeting', 'uncanny-automator' ) );
		}

		$token_data = $this->refresh_access_token( $credentials['refresh_token'] );

		// Merge new token data with existing credentials (preserves user info).
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
			throw new Exception( esc_html_x( 'GoTo Meeting credentials have expired. Please reconnect your account.', 'GoToMeeting', 'uncanny-automator' ) );
		}

		$token_data = json_decode( $response['body'], true );

		if ( empty( $token_data['access_token'] ) ) {
			throw new Exception( esc_html_x( 'Failed to refresh GoTo Meeting token. Please reconnect your account.', 'GoToMeeting', 'uncanny-automator' ) );
		}

		return $token_data;
	}

	////////////////////////////////////////////////////////////
	// API methods
	////////////////////////////////////////////////////////////

	/**
	 * Create a GoTo meeting.
	 *
	 * Posts the meeting body to the platform proxy, which forwards it to
	 * POST /G2M/rest/meetings. GoToMeeting V1 returns an array containing a
	 * single meeting object.
	 *
	 * @param array $meeting     The GoToMeeting create body (subject, starttime, endtime, ...).
	 * @param mixed $action_data Action data for logging.
	 *
	 * @return array The created meeting (meetingid, joinURL, ...).
	 * @throws Exception On API errors.
	 */
	public function create_meeting( $meeting, $action_data = null ) {

		$body = array(
			'action'  => 'gtm_create_meeting',
			'meeting' => wp_json_encode( $meeting ),
		);

		$response = $this->api_request( $body, $action_data );

		$code     = $response['statusCode'];
		$jsondata = $response['data'];

		if ( 200 !== $code && 201 !== $code ) {
			$description = is_array( $jsondata ) && isset( $jsondata['description'] ) ? $jsondata['description'] : esc_html_x( 'Error creating GoTo meeting', 'GoToMeeting', 'uncanny-automator' );
			throw new Exception( esc_html( $description ), absint( $code ) );
		}

		// V1 returns a single-element array of meeting objects.
		$created = isset( $jsondata[0] ) && is_array( $jsondata[0] ) ? $jsondata[0] : $jsondata;

		if ( empty( $created['meetingid'] ) ) {
			throw new Exception( esc_html_x( 'Error creating GoTo meeting', 'GoToMeeting', 'uncanny-automator' ) );
		}

		return $created;
	}

	/**
	 * Delete a GoTo meeting.
	 *
	 * @param string $meeting_id  The GoToMeeting meeting id.
	 * @param mixed  $action_data Action data for logging.
	 *
	 * @return void
	 * @throws Exception On API errors.
	 */
	public function delete_meeting( $meeting_id, $action_data = null ) {

		$body = array(
			'action'     => 'gtm_delete_meeting',
			'meeting_id' => $meeting_id,
		);

		$response = $this->api_request( $body, $action_data );

		$jsondata = $response['data'];
		$code     = $response['statusCode'];

		if ( 200 !== $code && 201 !== $code && 204 !== $code ) {
			$description = is_array( $jsondata ) && isset( $jsondata['description'] ) ? $jsondata['description'] : esc_html_x( 'Error deleting GoTo meeting', 'GoToMeeting', 'uncanny-automator' );
			throw new Exception( esc_html( $description ), absint( $code ) );
		}
	}
}
