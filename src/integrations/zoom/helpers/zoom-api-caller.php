<?php

namespace Uncanny_Automator\Integrations\Zoom;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Zoom_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Zoom_App_Helpers $helpers
 */
class Zoom_Api_Caller extends Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Set up the API caller properties.
	 *
	 * @return void
	 */
	public function set_properties() {

		// Override the default credential request key until migration to vault.
		$this->set_credential_request_key( 'access_token' );

		// Register custom error messages
		$this->register_error_messages(
			array(
				'invalid_credentials' => array(
					// translators: %s Settings page URL
					'message'   => esc_html_x( 'Your Zoom connection has expired. [reconnect your account](%s)', 'Zoom', 'uncanny-automator' ),
					'help_link' => $this->helpers->get_settings_page_url(),
				),
			),
		);
	}

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return array - The prepared credentials.
	 * @throws Exception
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		// Extract access token.
		$token = $this->get_access_token_from_credentials( $credentials );

		// Maybe refresh token.
		$credentials = $this->maybe_refresh_token( $credentials );

		// Extract access token.
		$token = $this->get_access_token_from_credentials( $credentials );

		// Return access token.
		return $token;
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Authorize account.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function authorize_account() {
		// Make initial API call to set client and fetch token.
		$this->refresh_token();
	}

	/**
	 * Get access token.
	 *
	 * @param array $credentials
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_access_token_from_credentials( $credentials ) {
		$token = $credentials['access_token'] ?? '';
		if ( empty( $token ) ) {
			throw new Exception( 'Access token is required' );
		}
		return $token;
	}

	/**
	 * Maybe refresh token.
	 *
	 * @param array $credentials
	 *
	 * @return array
	 * @throws Exception
	 */
	public function maybe_refresh_token( $credentials ) {
		$expires = $credentials['expires'] ?? '';
		if ( empty( $expires ) || $expires - 5 < time() ) {
			return $this->refresh_token();
		}
		return $credentials;
	}

	/**
	 * Refresh token.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function refresh_token() {

		$client = array();

		// Get the API key and secret.
		$account_id    = $this->helpers->get_const_option_value( 'ACCOUNT_ID' );
		$client_id     = $this->helpers->get_const_option_value( 'CLIENT_ID' );
		$client_secret = $this->helpers->get_const_option_value( 'CLIENT_SECRET' );

		if ( empty( $account_id ) || empty( $client_id ) || empty( $client_secret ) ) {
			throw new Exception( esc_html_x( 'Zoom credentials are missing', 'Zoom', 'uncanny-automator' ) );
		}

		$body = array(
			'action'        => 'get_token',
			'account_id'    => $account_id,
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		);

		// Exclude credentials for initial connection.
		$args = array(
			'exclude_credentials' => true,
		);

		$response = $this->api_request( $body, null, $args );

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'Could not fetch the token. Please check the credentials.', 'Zoom', 'uncanny-automator' ) );
		}

		$client['access_token'] = $response['data']['access_token'];
		$client['expires']      = $response['data']['expires_in'];

		// Cache it in settings.
		$this->helpers->store_credentials( $client );

		return $client;
	}

	/**
	 * Get user information from Zoom API.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_user_info() {
		return $this->api_request( 'get_user' );
	}

	/**
	 * Get meetings for a user.
	 *
	 * @param string $user
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_meeting_options( $user = null ) {

		$body = array(
			'action'      => 'get_meetings',
			'page_number' => 1,
			'page_size'   => 1000,
			'type'        => 'upcoming',
		);

		if ( ! is_null( $user ) ) {
			$body['user'] = $user;
		}

		$response = $this->api_request( $body );

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'Could not fetch user meetings from Zoom', 'Zoom', 'uncanny-automator' ), absint( $response['statusCode'] ) );
		}

		if ( empty( $response['data']['meetings'] ) ) {
			throw new Exception( esc_html_x( 'User meetings were not found', 'Zoom', 'uncanny-automator' ), absint( $response['statusCode'] ) );
		}

		$options = array();
		foreach ( $response['data']['meetings'] as $meeting ) {
			if ( empty( $meeting['topic'] ) ) {
				continue;
			}

			// Use index to prevent duplicates.
			$options[ $meeting['id'] ] = array(
				'text'  => $meeting['topic'],
				'value' => (string) $meeting['id'],
			);
		}

		// Remove indexes.
		return array_values( $options );
	}

	/**
	 * Get meeting occurrences.
	 *
	 * @param string $meeting_id

	 * @return array
	 * @throws Exception
	 */
	public function get_meeting_occurrences_options( $meeting_id ) {

		$response = $this->api_request(
			array(
				'action'     => 'get_meeting',
				'meeting_id' => $meeting_id,
			)
		);

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'Could not fetch meeting occurrences from Zoom', 'Zoom', 'uncanny-automator' ), absint( $response['statusCode'] ) );
		}

		$occurrences = $response['data']['occurrences'] ?? array();
		if ( empty( $occurrences ) ) {
			throw new Exception( esc_html_x( 'No occurrences found', 'Zoom', 'uncanny-automator' ) );
		}

		$options = array();
		foreach ( $occurrences as $occurrence ) {
			$options[] = array(
				'text'  => $this->helpers->convert_datetime( $occurrence['start_time'] ),
				'value' => (string) $occurrence['occurrence_id'],
			);
		}

		return $options;
	}

	/**
	 * Get meeting questions.
	 *
	 * @param string $meeting_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_meeting_questions( $meeting_id ) {
		return $this->api_request(
			array(
				'action'     => 'get_meeting_questions',
				'meeting_id' => $meeting_id,
			)
		);
	}

	/**
	 * Get account user options for AJAX.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_account_user_options() {

		$response = $this->api_request( 'get_account_users' );
		$options  = array();

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'Could not fetch users from Zoom', 'Zoom', 'uncanny-automator' ), absint( $response['statusCode'] ) );
		}

		$users = $response['data']['users'] ?? array();

		if ( empty( $users ) || count( $users ) < 1 ) {
			throw new Exception( esc_html_x( 'No users were found in your account', 'Zoom', 'uncanny-automator' ) );
		}

		foreach ( $users as $user ) {
			$options[] = array(
				'value' => $user['email'],
				'text'  => $user['first_name'] . ' ' . $user['last_name'],
			);
		}

		return $options;
	}

	/**
	 * Create a meeting.
	 *
	 * @param array $meeting_data
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function create_meeting( $body, $action_data = null ) {
		$body['action'] = 'create_meeting';
		return $this->api_request( $body, $action_data );
	}

	/**
	 * Register user for meeting with occurrences
	 *
	 * @param array $user_data
	 * @param string $meeting_key
	 * @param array $occurrences
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function register_user_for_meeting( $user_data, $meeting_key, $occurrences = array(), $action_data = null ) {
		$body = array(
			'action'      => 'register_meeting_user',
			'meeting_key' => $meeting_key,
		);

		if ( ! empty( $occurrences ) ) {
			$body['occurrences'] = implode( ',', $occurrences );
		}

		$body = array_merge( $body, $user_data );

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Unregister user from meeting.
	 *
	 * @param string $email
	 * @param string $meeting_key
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function unregister_user_from_meeting( $email, $meeting_key, $action_data = null ) {
		$body = array(
			'action'      => 'unregister_meeting_user',
			'meeting_key' => $meeting_key,
			'email'       => $email,
		);

		return $this->api_request( $body, $action_data );
	}
}
