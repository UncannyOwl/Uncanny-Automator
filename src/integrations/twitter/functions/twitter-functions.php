<?php

namespace Uncanny_Automator;

/**
 * Class Twitter_Functions
 *
 * @package Uncanny_Automator
 */
class Twitter_Functions {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/twitter';

	/**
	 * The verification nonce.
	 *
	 * @var NONCE nonce.
	 */
	const NONCE = 'automator_twitter';

	/**
	 * @var string
	 */
	public $setting_tab = 'twitter-api';

	/**
	 *
	 * @return array $tokens
	 */
	public function get_client() {

		$tokens = automator_get_option( '_uncannyowl_twitter_settings', array() );

		if ( empty( $tokens['oauth_token'] ) || empty( $tokens['oauth_token_secret'] ) ) {
			throw new \Exception( 'Twitter is not connected' );
		}

		return $tokens;
	}

	/**
	 * get_settings_page_url
	 *
	 * Returns the settings page URL
	 *
	 * @return string
	 */
	public function get_settings_page_url() {
		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => $this->setting_tab,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param bool   $tokens
	 * @param string $type
	 * @param string $default
	 * @param bool
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function textarea_field( $option_code = 'TEXT', $label = null, $tokens = true, $type = 'text', $default = null, $required = true, $description = '', $placeholder = null, $max_length = null ) {

		if ( ! $label ) {
			$label = __( 'Text', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = '';
		}

		$option = array(
			'option_code'      => $option_code,
			'label'            => $label,
			'description'      => $description,
			'placeholder'      => $placeholder,
			'input_type'       => $type,
			'supports_tokens'  => $tokens,
			'required'         => $required,
			'default_value'    => $default,
			'supports_tinymce' => false,
			'max_length'       => $max_length,
		);

		return apply_filters( 'uap_option_text_field', $option );
	}

	/**
	 * api_request
	 *
	 * @param array $body
	 * @param array $action_data
	 * @param int   $timeout
	 *
	 * @return mixed
	 */
	public function api_request( $body, $action_data = null, $timeout = null ) {

		$client = $this->get_client();

		$body['oauth_token']        = $client['oauth_token'];
		$body['oauth_token_secret'] = $client['oauth_token_secret'];

		if ( ! empty( $client['api_key'] ) && ! empty( $client['api_secret'] ) ) {
			$body['api_key']    = $client['api_key'];
			$body['api_secret'] = $client['api_secret'];
		}

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		if ( null !== $timeout ) {
			$params['timeout'] = $timeout;
		}

		$response = Api_Server::api_call( $params );

		return $response;
	}

	/**
	 * is_current_settings_tab
	 *
	 * @return void
	 */
	public function is_current_settings_tab() {

		if ( 'uo-recipe' !== automator_filter_input( 'post_type' ) ) {
			return false;
		}

		if ( 'uncanny-automator-config' !== automator_filter_input( 'page' ) ) {
			return false;
		}

		if ( 'premium-integrations' !== automator_filter_input( 'tab' ) ) {
			return false;
		}

		if ( automator_filter_input( 'integration' ) !== $this->setting_tab ) {
			return;
		}

		return true;
	}

	/**
	 * Returns the link to disconnect Twitter
	 *
	 * @return string The link to disconnect the site
	 */
	public function get_disconnect_url() {
		// Define the parameters of the URL
		$parameters = array(
			// Parameter used to detect the request
			'disconnect' => '1',
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			$this->get_settings_page_url()
		);
	}

	/**
	 * disconnect
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( ! $this->is_current_settings_tab() ) {
			return;
		}

		if ( empty( automator_filter_input( 'disconnect' ) ) ) {
			return;
		}

		delete_option( '_uncannyowl_twitter_settings' );
		delete_option( 'automator_twitter_user' );

		// Reload the page
		wp_safe_redirect( $this->get_settings_page_url() );

		die;

	}

	/**
	 * is_legacy_client_connected
	 *
	 * Returns true if Twitter is connected with the legacy credentials
	 *
	 * @return bool
	 */
	public function is_legacy_client_connected() {

		try {
			$client = $this->get_client();
		} catch ( \Exception $e ) {
			return false;
		}

		if ( isset( $client['api_secret'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * is_user_app_connected
	 *
	 * Returns true if Twitter is connected with user app credentials
	 *
	 * @return bool
	 */
	public function is_user_app_connected() {

		try {
			$client = $this->get_client();
		} catch ( \Exception $e ) {
			return false;
		}

		if ( ! empty( $client['api_secret'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * verify_credentials
	 *
	 * Makes a request to the API to verify the entered credentials.
	 *
	 * @param array $client
	 *
	 * @return mixed
	 */
	public function verify_credentials( $client ) {

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => array(
				'action' => 'verify_credentials',
				'client' => wp_json_encode( $client ),
			),
		);

		$response = Api_Server::api_call( $params );

		if ( 200 !== $response['statusCode'] || empty( $response['data'] ) ) {
			throw new \Exception( 'Could not verify your X/Twitter app credentials.' );
		}

		return $response['data'];
	}

	/**
	 * Send data to Automator API.
	 *
	 * @param string $status
	 *
	 * @return mixed
	 */
	public function statuses_update( $status, $media = '', $action_data = null ) {

		$body['action'] = 'statuses_update';
		$body['status'] = $status;
		$body['media']  = $media;

		// If a user app is used, switch the action
		if ( $this->is_user_app_connected() ) {
			$body['action'] = 'manage_tweets_user_app';
		}

		$response = $this->api_request( $body, $action_data, 60 );

		return $response;
	}

	// Legacy OAuth flow funcitons

	/**
	 * Captures oauth tokens after the redirect from Twitter
	 */
	public function capture_legacy_oauth_tokens() {

		if ( ! $this->is_current_settings_tab() ) {
			return;
		}

		// Check if the user is in the premium integrations settings page

		// Check if the API returned the tokens
		// If this exists, then we can assume the user is trying to connect his account
		$automator_api_response = automator_filter_input( 'automator_api_message' );

		if ( empty( $automator_api_response ) ) {
			return;
		}

		$connect = 2;

		// Parse the tokens
		$tokens = Automator_Helpers_Recipe::automator_api_decode_message(
			$automator_api_response,
			wp_create_nonce( 'automator_twitter_api_authentication' )
		);

		// Check is the parsed tokens are valid
		if ( $tokens ) {

			// Save them
			update_option( '_uncannyowl_twitter_settings', $tokens );

			$connect = 1;
		}

		// Reload and add a parameter to catch the error
		wp_safe_redirect(
			add_query_arg(
				array(
					'connect' => $connect,
				),
				$this->get_settings_page_url()
			)
		);

		die;
	}


	/**
	 * Returns the link to connect to Twitter
	 *
	 * @return string The link to connect the site
	 */
	public function get_connect_url( $redirect_url = '' ) {
		// Check if there is a custom redirect URL defined, otherwise, use the default one
		$redirect_url = ! empty( $redirect_url ) ? $redirect_url : $this->get_settings_page_url();

		// Define the parameters of the URL
		$parameters = array(
			// Authentication nonce
			'nonce'        => wp_create_nonce( 'automator_twitter_api_authentication' ),

			// Action
			'action'       => 'authorization_request',

			// Redirect URL
			'redirect_url' => rawurlencode( $redirect_url ),
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			AUTOMATOR_API_URL . self::API_ENDPOINT
		);
	}

	/**
	 * get_username
	 *
	 * @return string
	 */
	public function get_username() {

		if ( $this->is_user_app_connected() ) {
			return $this->get_user_app_username();
		}

		return $this->get_oauth_username();
	}

	/**
	 * get_user_app_username
	 *
	 * @return string
	 */
	public function get_user_app_username() {

		$user = get_option( 'automator_twitter_user', array() );

		if ( ! empty( $user['screen_name'] ) ) {
			return $user['screen_name'];
		}

		return '';
	}

	/**
	 * get_oauth_username
	 *
	 * @return string
	 */
	public function get_oauth_username() {

		try {
			$client = $this->get_client();

			return $client['screen_name'];
		} catch ( \Exception $e ) {
			return '';
		}
	}
}

