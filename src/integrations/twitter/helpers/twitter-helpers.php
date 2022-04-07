<?php

namespace Uncanny_Automator;

/**
 * Class Twitter_Helpers
 *
 * @package Uncanny_Automator
 */
class Twitter_Helpers {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/twitter';

	/**
	 * @var Twitter_Helpers
	 */
	public $options;

	/**
	 * @var Twitter_Helpers
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Twitter_Helpers constructor.
	 */
	public function __construct() {

		$this->setting_tab = 'twitter-api';

		$this->settings_page_url = add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => $this->setting_tab,
			),
			admin_url( 'edit.php' )
		);

		add_action( 'init', array( $this, 'capture_oauth_tokens' ) );
		add_action( 'init', array( $this, 'disconnect' ) );

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		$this->load_settings();
	}

	/**
	 * Load the settings
	 * 
	 * @return void
	 */
	private function load_settings() {
		include_once __DIR__ . '/../settings/settings-twitter.php';
		new Twitter_Settings( $this );
	}

	/**
	 * @param Twitter_Helpers $options
	 */
	public function setOptions( Twitter_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 *
	 * @return array $tokens
	 */
	public function get_client() {
		
		$tokens = get_option( '_uncannyowl_twitter_settings', array() );

		if ( empty( $tokens['oauth_token'] ) || empty( $tokens['oauth_token_secret'] ) ) {
			throw new \Exception( 'Twitter is not connected' );
		}

		return $tokens;
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param bool $tokens
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

	public function api_request( $body, $action_data = null, $timeout = null ) {

		$client = $this->get_client();

		$body['oauth_token'] = $client['oauth_token'];
		$body['oauth_token_secret'] = $client['oauth_token_secret'];

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body' => $body,
			'action' => $action_data
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

		if ( $this->setting_tab !== automator_filter_input( 'integration' ) ) {
			return;
		}

		return true;
	}

	/**
	 * Returns the link to connect to Twitter
	 *
	 * @return string The link to connect the site
	 */
	public function get_connect_url( $redirect_url = '' ) {
		// Check if there is a custom redirect URL defined, otherwise, use the default one
		$redirect_url = ! empty( $redirect_url ) ? $redirect_url : $this->settings_page_url;

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
			$this->settings_page_url
		);
	}

	/**
	 * Captures oauth tokens after the redirect from Twitter
	 */
	public function capture_oauth_tokens() {

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
				$this->settings_page_url
			)
		);

		die;
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

		// Reload the page
		wp_safe_redirect( $this->settings_page_url );

		die;

	}
}

