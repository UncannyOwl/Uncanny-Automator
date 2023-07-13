<?php


namespace Uncanny_Automator;

/**
 * Class Gototraining_Helpers
 *
 * @package Uncanny_Automator
 */
class Gototraining_Helpers {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/goto';

	const TRANSIENT = 'automator_gtt_settings';

	/**
	 * Options.
	 *
	 * @var mixed $options
	 */
	public $options;

	/**
	 * Pro.
	 *
	 * @var mixed $pro
	 */
	public $pro;

	/**
	 * Settings tab.
	 *
	 * @var mixed $setting_tab
	 */
	public $setting_tab;

	/**
	 * Load options.
	 *
	 * @var bool
	 */
	public $load_options;


	public function __construct() {

		$this->setting_tab = 'gtt_api';

		add_action( 'update_option_uap_automator_gtt_api_consumer_secret', array( $this, 'gtt_oauth_update' ), 100, 3 );
		add_action( 'add_option_uap_automator_gtt_api_consumer_secret', array( $this, 'gtt_oauth_new' ), 100, 2 );
		add_action( 'init', array( $this, 'validate_oauth_tokens' ), 100, 3 );
		add_action( 'init', array( $this, 'gtt_oauth_save' ), 200 );

		// Disconnect action.
		add_action( 'wp_ajax_gtt_disconnect', array( $this, 'disconnect' ) );

		$this->load_settings();

	}

	/**
	 * Load the settings page.
	 *
	 * @return void
	 */
	public function load_settings() {

		// Check if the Trait exists in Automator base.
		require_once __DIR__ . '/../settings/gototraining-settings.php';

		new GoToTraining_Settings( $this );

	}

	/**
	 * Set options.
	 *
	 * @param Gototraining_Helpers $options
	 */
	public function setOptions( Gototraining_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set pro.
	 *
	 * @param Gototraining_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Gototraining_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * get_trainings
	 *
	 * @return void
	 */
	public function get_trainings() {

		$trainings = array();

		try {

			$body['action'] = 'get_trainings';

			$response = $this->remote_request( $body );

			if ( 200 !== $response['statusCode'] ) {
				throw new \Exception( __( 'Unable to fetch trainings from this account', 'uncanny-automator' ) );
			}

			if ( count( $response['data'] ) < 1 ) {
				throw new \Exception( __( 'No trainings were found in this account', 'uncanny-automator' ) );
			}

			foreach ( $response['data'] as $training ) {

				$trainings[] = array(
					'text'  => $training['name'],
					'value' => (string) $training['trainingKey'] . '-objectkey',
				);

			}
		} catch ( \Exception $e ) {

			$trainings[] = array(
				'text'  => $e->getMessage(),
				'value' => '',
			);
		}

		return $trainings;

	}

	/**
	 * For registering user to training action method.
	 *
	 * @param string $user_id
	 * @param string $training_key
	 *
	 * @return array
	 */
	public function gtt_register_user( $user_id, $training_key, $action_data = null ) {

		$user = get_userdata( $user_id );

		if ( is_wp_error( $user ) ) {
			throw new \Exception( __( 'GoTo Training user not found.', 'uncanny-automator' ) );
		}

		$customer_first_name = $user->first_name;
		$customer_last_name  = $user->last_name;
		$customer_email      = $user->user_email;

		if ( ! empty( $customer_email ) ) {
			$customer_email_parts = explode( '@', $customer_email );
			$customer_first_name  = empty( $customer_first_name ) ? $customer_email_parts[0] : $customer_first_name;
			$customer_last_name   = empty( $customer_last_name ) ? $customer_email_parts[0] : $customer_last_name;
		}

		$body['action']       = 'gtt_register_user';
		$body['training_key'] = $training_key;
		$body['user']         = wp_json_encode(
			array(
				'givenName' => $customer_first_name,
				'surname'   => $customer_last_name,
				'email'     => $customer_email,
			)
		);

		$response = $this->remote_request( $body, $action_data );

		$jsondata = $response['data'];
		$code     = $response['statusCode'];

		if ( 201 !== $code ) {
			throw new \Exception( $jsondata['description'], $code );
		}

		if ( ! isset( $jsondata['joinUrl'] ) ) {
			throw new \Exception( __( 'Error adding user to GoTo Training', 'uncanny-automator' ) );
		}

		update_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_registrantKey', $jsondata['registrantKey'] );
		update_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_joinUrl', $jsondata['joinUrl'] );
		update_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_confirmationUrl', $jsondata['confirmationUrl'] );

	}

	/**
	 * For un-registering user to training action method.
	 *
	 * @param string $user_id
	 * @param string $training_key
	 *
	 * @return array
	 */
	public function gtt_unregister_user( $user_id, $training_key, $action_data = null ) {

		$user_registrant_key = get_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_registrantKey', true );

		if ( empty( $user_registrant_key ) ) {
			throw new \Exception( __( 'User was not registered for training session.', 'uncanny-automator' ) );
		}

		$body['action']              = 'gtt_unregister_user';
		$body['training_key']        = $training_key;
		$body['user_registrant_key'] = $user_registrant_key;

		$response = $this->remote_request( $body, $action_data );

		$jsondata = $response['data'];
		$code     = $response['statusCode'];

		if ( 201 !== $code && 204 !== $code ) {
			throw new \Exception( esc_html( $jsondata['description'] ) );
		}

		delete_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_registrantKey' );
		delete_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_joinUrl' );
		delete_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_confirmationUrl' );

	}

	/**
	 * To get training access token and organizer key
	 *
	 * @return array
	 */
	public function get_training_token() {

		$get_transient = get_transient( self::TRANSIENT );

		if ( false !== $get_transient ) {

			return $get_transient;

		}

		$oauth_settings        = automator_get_option( '_uncannyowl_gtt_settings', array() );
		$current_refresh_token = isset( $oauth_settings['refresh_token'] ) ? $oauth_settings['refresh_token'] : '';

		if ( empty( $current_refresh_token ) ) {
			update_option( '_uncannyowl_gtt_settings_expired', true );
			throw new \Exception( __( 'GoTo Training credentails have expired.', 'uncanny-automator' ) );
		}

		$consumer_key    = trim( automator_get_option( 'uap_automator_gtt_api_consumer_key', '' ) );
		$consumer_secret = trim( automator_get_option( 'uap_automator_gtt_api_consumer_secret', '' ) );

		$params = array(
			'method'  => 'POST',
			'url'     => 'https://api.getgo.com/oauth/v2/token',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Content-Type'  => 'application/x-www-form-urlencoded; charset=utf-8',
			),
			'body'    => array(
				'refresh_token' => $current_refresh_token,
				'grant_type'    => 'refresh_token',
			),
		);

		$response = Api_Server::call( $params );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			update_option( '_uncannyowl_gtt_settings', array() );
			update_option( '_uncannyowl_gtt_settings_expired', true );
			throw new \Exception( __( 'GoTo Training credentails have expired.', 'uncanny-automator' ) );
		}

		$jsondata = array();

		//get new access token and refresh token
		$jsondata = json_decode( $response['body'], true );

		update_option( '_uncannyowl_gtt_settings', $jsondata );
		set_transient( self::TRANSIENT, $jsondata, 60 * 50 );
		delete_option( '_uncannyowl_gtt_settings_expired' );

		//return the array
		return $jsondata;

	}

	/**
	 * Action when settings updated, it will redirect user to 3rd party for OAuth connect.
	 *
	 * @param string|array $old_value
	 * @param string|array $new_value
	 * @param string $option
	 */
	public function gtt_oauth_update( $old_value, $new_value, $option ) {
		if ( 'uap_automator_gtt_api_consumer_secret' === $option && $old_value !== $new_value ) {
			$this->oauth_redirect();
		}
	}

	/**
	 * Action when settings added, it will redirect user to 3rd party for OAuth connect.
	 *
	 * @param string|array $old_value
	 * @param string|array $new_value
	 * @param string $option
	 */
	public function gtt_oauth_new( $option, $new_value ) {
		if ( 'uap_automator_gtt_api_consumer_secret' === $option && ! empty( $new_value ) ) {
			$this->oauth_redirect();
		}
	}

	/**
	 * Action when settings added, it will redirect user to 3rd party for OAuth connect.
	 */
	public function gtt_oauth_save() {

		if ( isset( $_POST['uap_automator_gtt_api_consumer_key'] ) && ! empty( $_POST['uap_automator_gtt_api_consumer_key'] )
			&& isset( $_POST['uap_automator_gtt_api_consumer_secret'] ) && ! empty( $_POST['uap_automator_gtt_api_consumer_secret'] )
			&& isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'uncanny_automator_go-to-training-options' )
			) {

			update_option( 'uap_automator_gtt_api_consumer_key', sanitize_text_field( wp_unslash( $_POST['uap_automator_gtt_api_consumer_key'] ) ) );

			update_option( 'uap_automator_gtt_api_consumer_secret', sanitize_text_field( wp_unslash( $_POST['uap_automator_gtt_api_consumer_secret'] ) ) );

			$this->oauth_redirect();

		}

	}

	/**
	 * Redirect to gtt oauth dialog.
	 */
	private function oauth_redirect() {

		$consumer_key    = trim( automator_get_option( 'uap_automator_gtt_api_consumer_key', '' ) );
		$consumer_secret = trim( automator_get_option( 'uap_automator_gtt_api_consumer_secret', '' ) );
		if ( isset( $consumer_key ) && isset( $consumer_secret ) && strlen( $consumer_key ) > 0 && strlen( $consumer_secret ) > 0 ) {

			$tab_url    = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=' . $this->setting_tab;
			$oauth_link = 'https://api.getgo.com/oauth/v2/authorize?response_type=code&client_id=' . $consumer_key . '&state=' . $this->setting_tab;

			wp_redirect( $oauth_link ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			die;
		}
	}

	/**
	 * Callback function for OAuth redirect verification.
	 */
	public function validate_oauth_tokens() {

		if ( ! automator_filter_has_var( 'state' ) || $this->setting_tab !== automator_filter_input( 'state' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'code' ) ) {
			return;
		}

		$consumer_key    = trim( automator_get_option( 'uap_automator_gtt_api_consumer_key', '' ) );
		$consumer_secret = trim( automator_get_option( 'uap_automator_gtt_api_consumer_secret', '' ) );

		$code = wp_unslash( automator_filter_input( 'code' ) );

		$params = array(
			'method'  => 'POST',
			'url'     => 'https://api.getgo.com/oauth/v2/token',
			'headers' => array(
				'Content-Type'  => 'application/x-www-form-urlencoded; charset=utf-8',
				'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Accept'        => 'application/json',
			),
			'body'    => array(
				'code'       => $code,
				'grant_type' => 'authorization_code',
				//'redirect_uri' => urlencode( $tab_url ),
			),
		);

		$connect = 2;

		try {

			$response = Api_Server::call( $params );

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				throw new \Exception( __( 'Error validating Oauth tokens', 'uncanny-automator' ) );
			}

			$jsondata = array();

			//lets get the response and decode it
			$jsondata = json_decode( $response['body'], true );

			// Update the options.
			update_option( '_uncannyowl_gtt_settings', $jsondata );
			delete_option( '_uncannyowl_gtt_settings_expired' );

			// Set the transient.
			set_transient( self::TRANSIENT, $jsondata, 60 * 50 );

			$connect = 1;

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}

		wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-training' ) . '&connect=' . $connect );
		die;

	}

	/**
	 * Disconnect the current connect by removing the options saved in wp_options.
	 *
	 * @return void
	 */
	public function disconnect() {

		// Check nonce.
		if ( false === wp_verify_nonce( automator_filter_input( 'nonce' ), 'gtt-disconnect-nonce' ) ) {
			return;
		}

		// Admin only action.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$configs = array(
			'options'    => array(
				'_uncannyowl_gtt_settings',
				'_uncannyowl_gtt_settings_expired',
				'uap_automator_gtt_api_consumer_key',
				'uap_automator_gtt_api_consumer_secret',
			),
			'transients' => array(
				self::TRANSIENT,
			),
		);

		// Delete the options.
		foreach ( $configs['options'] as $option_key ) {
			delete_option( $option_key );
		}

		// Delete the transients.
		foreach ( $configs['transients'] as $transient_key ) {
			delete_transient( $transient_key );
		}

		wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-training' ) . '&connection=disconnected' );

		die;

	}

	/**
	 * get_disconnect_url
	 *
	 * @return void
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'gtt_disconnect',
				'nonce'  => wp_create_nonce( 'gtt-disconnect-nonce' ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * remote_request
	 *
	 * @param  mixed $params
	 * @param  mixed $action_data
	 * @return void
	 */
	public function remote_request( $body, $action_data = null ) {

		$body['client'] = $this->get_training_token();

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
			'timeout'  => 30,
		);

		$response = Api_Server::api_call( $params );

		return $response;
	}
}
