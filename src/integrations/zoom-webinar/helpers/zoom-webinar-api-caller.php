<?php

namespace Uncanny_Automator\Integrations\Zoom_Webinar;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Zoom_Webinar_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Zoom_Webinar_App_Helpers $helpers
 */
class Zoom_Webinar_Api_Caller extends Api_Caller {

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

		// Register custom error messages.
		$this->register_error_messages(
			array(
				'invalid_credentials' => array(
					'message'   => 'Your Zoom Webinar connection has expired. [reconnect your account](%s)',
					'help_link' => $this->helpers->get_settings_page_url(),
				),
			)
		);
	}

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return array - The prepared credentials.
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
	 * Authorize account
	 *
	 * @return void
	 * @throws Exception
	 */
	public function authorize_account() {
		// Make initial API call to set client and fetch token.
		$this->refresh_token();
	}

	/**
	 * Get access token from credentials.
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
			throw new Exception( esc_html_x( 'Zoom Webinar credentials are missing', 'Zoom Webinar', 'uncanny-automator' ) );
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
	 * Get user information from Zoom Webinar API.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_user_info() {
		return $this->api_request( 'get_user' );
	}

	/**
	 * Get webinars for a user.
	 *
	 * @param string $user
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_webinars( $user = null ) {
		$body = array(
			'action'      => 'get_webinars',
			'page_number' => 1,
			'page_size'   => 1000,
			'type'        => 'upcoming',
		);

		if ( ! is_null( $user ) ) {
			$body['user'] = $user;
		}

		return $this->api_request( $body );
	}

	/**
	 * Get webinar options for a user.
	 *
	 * @param string $user
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_webinar_options( $user = null ) {

		$body = array(
			'action'      => 'get_webinars',
			'page_number' => 1,
			'page_size'   => 1000,
			'type'        => 'upcoming',
		);

		if ( ! is_null( $user ) ) {
			$body['user'] = $user;
		}

		$response = $this->api_request( $body );

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'Could not fetch user webinars from Zoom', 'Zoom Webinar', 'uncanny-automator' ), absint( $response['statusCode'] ) );
		}

		if ( empty( $response['data']['webinars'] ) ) {
			throw new Exception( esc_html_x( 'User webinars were not found', 'Zoom Webinar', 'uncanny-automator' ), absint( $response['statusCode'] ) );
		}

		$options = array();
		foreach ( $response['data']['webinars'] as $webinar ) {
			if ( empty( $webinar['topic'] ) ) {
				continue;
			}

			// Use index to prevent duplicates.
			$options[ $webinar['id'] ] = array(
				'text'  => $webinar['topic'],
				'value' => (string) $webinar['id'],
			);
		}

		// Remove indexes.
		return array_values( $options );
	}

	/**
	 * Get webinar questions.
	 *
	 * @param string $webinar_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_webinar_questions( $webinar_id ) {

		$body = array(
			'action'     => 'get_webinar_questions',
			'webinar_id' => $webinar_id,
		);

		return $this->api_request( $body );
	}

	/**
	 * Create a webinar.
	 *
	 * @param array $body
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function create_webinar( $body, $action_data = null ) {
		$body['action'] = 'create_webinar';

		return $this->api_request( $body, $action_data );
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
			throw new Exception( esc_html_x( 'Could not fetch users from Zoom', 'Zoom Webinar', 'uncanny-automator' ), absint( $response['statusCode'] ) );
		}

		$users = $response['data']['users'] ?? array();

		if ( empty( $users ) || count( $users ) < 1 ) {
			throw new Exception( esc_html_x( 'No users were found in your account', 'Zoom Webinar', 'uncanny-automator' ) );
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
	 * Get webinar occurrences options.
	 *
	 * @param string $webinar_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_webinar_occurrences_options( $webinar_id ) {

		$response = $this->api_request(
			array(
				'action'     => 'get_webinar',
				'webinar_id' => $webinar_id,
			)
		);

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'Could not fetch webinar occurrences from Zoom', 'Zoom Webinar', 'uncanny-automator' ), absint( $response['statusCode'] ) );
		}

		$occurrences = $response['data']['occurrences'] ?? array();
		if ( empty( $occurrences ) ) {
			throw new Exception( esc_html_x( 'No occurrences found', 'Zoom Webinar', 'uncanny-automator' ) );
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
	 * Register user for webinar with occurrences.
	 *
	 * @param array $user_data
	 * @param string $webinar_key
	 * @param array $occurrences
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function register_user_for_webinar( $user_data, $webinar_key, $occurrences = array(), $action_data = null ) {
		$body = array(
			'action'      => 'register_webinar_user',
			'webinar_key' => $webinar_key,
		);

		if ( ! empty( $occurrences ) ) {
			$body['occurrences'] = implode( ',', $occurrences );
		}

		$body = array_merge( $body, $user_data );

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Unregister user from webinar.
	 *
	 * @param string $email
	 * @param string $webinar_key
	 * @param array $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function unregister_user_from_webinar( $email, $webinar_key, $action_data = null ) {
		$body = array(
			'action'      => 'unregister_webinar_user',
			'webinar_key' => $webinar_key,
			'email'       => $email,
		);

		return $this->api_request( $body, $action_data );
	}
}
