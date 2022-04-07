<?php
namespace Uncanny_Automator;

/**
 * Class Gotowebinar_Helpers
 *
 * @package Uncanny_Automator
 */
class Gotowebinar_Helpers {

	/**
	 * Options.
	 *
	 * @var Gotowebinar_Helpers
	 */
	public $options;

	/**
	 * Helpers.
	 *
	 * @var Gotowebinar_Helpers
	 */
	public $pro;

	/**
	 * The settings tab.
	 *
	 * @var string
	 */
	public $setting_tab;

	/**
	 * Load options.
	 *
	 * @var bool
	 */
	public $load_options;


	public function __construct() {

		$this->setting_tab = 'gtw_api';

		add_action( 'update_option_uap_automator_gtw_api_consumer_secret', array( $this, 'gtw_oauth_update' ), 100, 3 );
		add_action( 'add_option_uap_automator_gtw_api_consumer_secret', array( $this, 'gtw_oauth_new' ), 100, 2 );
		add_action( 'init', array( $this, 'validate_oauth_tokens' ), 100, 3 );
		add_action( 'init', array( $this, 'gtw_oauth_save' ), 200 );

		// Disconnect action.
		add_action( 'wp_ajax_gtw_disconnect', array( $this, 'disconnect' ) );

		$this->load_settings();

	}

	/**
	 * Load the settings.
	 *
	 * @return void
	 */
	public function load_settings() {

		require_once __DIR__ . '/../settings/gotowebinar-settings.php';

		new GoToWebinar_Settings( $this );

	}

	/**
	 * Set the options.
	 *
	 * @param Gotowebinar_Helpers $options
	 *
	 * @return void.
	 */
	public function setOptions( Gotowebinar_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set pro.
	 *
	 * @param Gotowebinar_Pro_Helpers $pro
	 *
	 * @return void.
	 */
	public function setPro( \Uncanny_Automator_Pro\Gotowebinar_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}


	public function get_webinars() {

		$webinars = array();

		try {
			list( $access_token, $organizer_key ) = $this->get_webinar_token();

			$current_time = current_time( 'Y-m-d\TH:i:s\Z' );
	
			$current_time_plus_years = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+2 year', strtotime( $current_time ) ) );
	
			$params['headers'] = array(
				'Authorization' => $access_token,
			);
	
			$params['method'] = 'GET';
			$params['url'] = 'https://api.getgo.com/G2W/rest/v2/organizers/' . $organizer_key . '/webinars?fromTime=' . $current_time . '&toTime=' . $current_time_plus_years . '&page=0&size=200';
	
			$response = $this->remote_request( $params );

			$code = wp_remote_retrieve_response_code( $response );
	
			// Prepare webinar list.
			if ( 200 !== $code ) {
				throw new \Exception( __( 'Unable to fetch webinars from this account', 'uncanny-automator' ) );
			}
	
			$jsondata = json_decode( preg_replace( '/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', wp_remote_retrieve_body( $response ) ), true );

			$jsondata = isset( $jsondata['_embedded']['webinars'] ) ? $jsondata['_embedded']['webinars'] : array();

			if ( count( $jsondata ) < 1 ) {
				throw new \Exception( __( 'No webinars were found in this account', 'uncanny-automator' ) );
			}

			foreach ( $jsondata as $key1 => $webinar ) {

				$webinars[] = array(
					'text'  => $webinar['subject'],
					'value' => (string) $webinar['webinarKey'] . '-objectkey',
				);

			}
		
		} catch ( \Exception $e ) {
			
			$webinars[] = array(
				'text'  => $e->getMessage(),
				'value' => ''
			);
		}

		return $webinars;
	}

	/**
	 * For registering user to webinar action method.
	 *
	 * @param string $user_id
	 * @param string $webinar_key
	 *
	 * @return array
	 */
	public function gtw_register_user( $user_id, $webinar_key, $action_data = null ) {

		$user = get_userdata( $user_id );

		if ( is_wp_error( $user ) ) {
			throw new \Exception( __( 'GoTo Webinar user not found.', 'uncanny-automator' ) );
		}

		$customer_first_name = $user->first_name;
		$customer_last_name = $user->last_name;
		$customer_email = $user->user_email;

		if ( ! empty( $customer_email ) ) {
			$customer_email_parts = explode( '@', $customer_email );
			$customer_first_name = empty( $customer_first_name ) ? $customer_email_parts[0] : $customer_first_name;
			$customer_last_name = empty( $customer_last_name ) ? $customer_email_parts[0] : $customer_last_name;
		}

		list( $access_token, $organizer_key ) = $this->get_webinar_token();

		$params = array(
			'method'      => 'POST',
			'url' 		  => "https://api.getgo.com/G2W/rest/v2/organizers/{$organizer_key}/webinars/{$webinar_key}/registrants?resendConfirmation=true",
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => $access_token,
				'Content-type'  => 'application/json',
			),
			'body'        => wp_json_encode(
				array(
					'firstName' => $customer_first_name,
					'lastName'   => $customer_last_name,
					'email'     => $customer_email,
				)
			),
		);

		$response = $this->remote_request( $params, $action_data );

		$code = wp_remote_retrieve_response_code( $response );

		$jsondata = json_decode( $response['body'], true, 512, JSON_BIGINT_AS_STRING );

		if ( 201 !== $code ) {
			throw new \Exception( $jsondata['description'], $code );
		}

		if ( ! isset( $jsondata['joinUrl'] ) ) {
			throw new \Exception( __( 'Error adding user to GoTo Webinar', 'uncanny-automator' ) );
		}

		update_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey', $jsondata['registrantKey'] );
		update_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_joinUrl', $jsondata['joinUrl'] );
	}

	/**
	 * For un-registering user to webinar action method.
	 *
	 * @param string $user_id
	 * @param string $webinar_key
	 *
	 * @return array
	 */
	public function gtw_unregister_user( $user_id, $webinar_key, $action_data = null ) {

		list( $access_token, $organizer_key ) = $this->get_webinar_token();

		$user_registrant_key = get_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey', true );

		if ( empty( $user_registrant_key ) ) {
			throw new \Exception( __( 'User was not registered for webinar.', 'uncanny-automator' ) );
		}

		$params = array(
			'url' => "https://api.getgo.com/G2W/rest/v2/organizers/{$organizer_key}/webinars/{$webinar_key}/registrants/{$user_registrant_key}",
			'method'      => 'DELETE',
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,
			'headers'     => array(
				'Authorization' => $access_token,
				'Content-type'  => 'application/json',
			),
		);

		$response = $this->remote_request( $params, $action_data );

		$code = wp_remote_retrieve_response_code( $response );

		if ( 201 !== $code && 204 !== $code ) {
			$jsondata = json_decode( $response['body'], true, 512, JSON_BIGINT_AS_STRING );
			throw new \Exception( esc_html( $jsondata['description'] ) );
		}

		delete_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey' );
		delete_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_joinUrl' );
	}

	/**
	 * To get webinar access token and organizer key
	 *
	 * @return array
	 */
	public function get_webinar_token() {

		$get_transient = get_transient( '_uncannyowl_gtw_settings' );

		if ( false !== $get_transient ) {

			$tokens = explode( '|', $get_transient );

			return array( $tokens[0], $tokens[1] );

		} 

		$oauth_settings        = get_option( '_uncannyowl_gtw_settings' );
		$current_refresh_token = isset( $oauth_settings['refresh_token'] ) ? $oauth_settings['refresh_token'] : '';

		if ( empty( $current_refresh_token ) ) {
			update_option( '_uncannyowl_gtw_settings_expired', true );
			throw new \Exception( __( 'GoTo Webinar credentails have expired.', 'uncanny-automator' ) );
		}

		$consumer_key    = trim( get_option( 'uap_automator_gtw_api_consumer_key', '' ) );
		$consumer_secret = trim( get_option( 'uap_automator_gtw_api_consumer_secret', '' ) );

		$params = array(
			'method' => 'POST',
			'url' => 'https://api.getgo.com/oauth/v2/token',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Content-Type'  => 'application/x-www-form-urlencoded; charset=utf-8',
			),
			'body'    => array(
				'refresh_token' => $current_refresh_token,
				'grant_type'    => 'refresh_token',
			),
		);

		$response = $this->remote_request( $params );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			update_option( '_uncannyowl_gtw_settings', array() );
			update_option( '_uncannyowl_gtw_settings_expired', true );
			throw new \Exception( __( 'GoTo Webinar credentails have expired.', 'uncanny-automator' ) );
		}

		//get new access token and refresh token
		$jsondata = json_decode( $response['body'], true );

		$tokens_info                  = array();
		$tokens_info['access_token']  = $jsondata['access_token'];
		$tokens_info['refresh_token'] = $jsondata['refresh_token'];
		$tokens_info['organizer_key'] = $jsondata['organizer_key'];
		$tokens_info['account_key']   = $jsondata['account_key'];

		update_option( '_uncannyowl_gtw_settings', $tokens_info );
		set_transient( '_uncannyowl_gtw_settings', $tokens_info['access_token'] . '|' . $tokens_info['organizer_key'], 60 * 50 );
		delete_option( '_uncannyowl_gtw_settings_expired' );

		//return the array
		return array( $tokens_info['access_token'], $tokens_info['organizer_key'] );

	}

	/**
	 * Action when settings updated, it will redirect user to 3rd party for OAuth connect.
	 *
	 * @param string|array $old_value
	 * @param string|array $new_value
	 * @param string $option
	 */
	public function gtw_oauth_update( $old_value, $new_value, $option ) {
		if ( 'uap_automator_gtw_api_consumer_secret' === $option && $old_value !== $new_value ) {
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
	public function gtw_oauth_new( $option, $new_value ) {
		if ( 'uap_automator_gtw_api_consumer_secret' === $option && ! empty( $new_value ) ) {
			$this->oauth_redirect();
		}
	}

	/**
	 * Action when settings added, it will redirect user to 3rd party for OAuth connect.
	 */
	public function gtw_oauth_save() {

		if ( isset( $_POST['uap_automator_gtw_api_consumer_key'] ) && ! empty( $_POST['uap_automator_gtw_api_consumer_key'] )
			&& isset( $_POST['uap_automator_gtw_api_consumer_secret'] ) && ! empty( $_POST['uap_automator_gtw_api_consumer_secret'] )
			&& isset( $_POST['_wpnonce'] ) && ! empty( $_POST['_wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'uncanny_automator_go-to-webinar-options' )
			) {

			update_option( 'uap_automator_gtw_api_consumer_key', sanitize_text_field( wp_unslash( $_POST['uap_automator_gtw_api_consumer_key'] ) ) );

			update_option( 'uap_automator_gtw_api_consumer_secret', sanitize_text_field( wp_unslash( $_POST['uap_automator_gtw_api_consumer_secret'] ) ) );

			$this->oauth_redirect();

		}

	}

	/**
	 * OAuth redirect.
	 */
	private function oauth_redirect() {

		$consumer_key = trim( get_option( 'uap_automator_gtw_api_consumer_key', '' ) );

		$consumer_secret = trim( get_option( 'uap_automator_gtw_api_consumer_secret', '' ) );

		if ( isset( $consumer_key ) && isset( $consumer_secret ) && strlen( $consumer_key ) > 0 && strlen( $consumer_secret ) > 0 ) {

			$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=' . $this->setting_tab;

			$oauth_link = 'https://api.getgo.com/oauth/v2/authorize?response_type=code&client_id=' . $consumer_key . '&state=' . $this->setting_tab;// . '&redirect_uri=' . urlencode( $tab_url );

			wp_redirect( $oauth_link ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

			die;

		}

	}

	/**
	 * Callback function for OAuth redirect verification.
	 *
	 * @return void
	 */
	public function validate_oauth_tokens() {

		if ( ! automator_filter_has_var( 'state' ) || $this->setting_tab !== automator_filter_input( 'state' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'code' ) ) {
			return;
		}

		$consumer_key = trim( get_option( 'uap_automator_gtw_api_consumer_key', '' ) );
		$consumer_secret = trim( get_option( 'uap_automator_gtw_api_consumer_secret', '' ) );

		$code = automator_filter_input( 'code' );

		$params = array(
			'method' => 'POST',
			'url' => 'https://api.getgo.com/oauth/v2/token',
			'headers' => array(
				'Content-Type'  => 'application/x-www-form-urlencoded; charset=utf-8',
				'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Accept'        => 'application/json',
			),
			'body'    => array(
				'code'       => $code,
				'grant_type' => 'authorization_code',
				//'redirect_uri' => urlencode( $tab_url ),
			)
		);

		$connect = 2;

		try {

			$response = $this->remote_request( $params );

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				throw new \Exception( __( 'Error validating Oauth tokens', 'uncanny-automator' ) );
			}

			$jsondata = array();
			
			//lets get the response and decode it
			$jsondata = json_decode( $response['body'], true );

			update_option( '_uncannyowl_gtw_settings', $jsondata );
			delete_option( '_uncannyowl_gtw_settings_expired' );

			// Set the transient.
			set_transient( '_uncannyowl_gtw_settings', $jsondata['access_token'] . '|' . $jsondata['organizer_key'], 60 * 50 );

			$connect = 1;

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}
		
		wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-webinar' ) . '&connect=' . $connect );
		die;

	}

	/**
	 * Disconnect the current connect by removing the options saved in wp_options.
	 *
	 * @return void
	 */
	public function disconnect() {

		// Check nonce.
		if ( false === wp_verify_nonce( automator_filter_input( 'nonce' ), 'gtw-disconnect-nonce' ) ) {
			return;
		}

		// Admin only action.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$configs = array(
			'options'    => array(
				'_uncannyowl_gtw_settings',
				'_uncannyowl_gtw_settings_expired',
				'uap_automator_gtw_api_consumer_key',
				'uap_automator_gtw_api_consumer_secret',
			),
			'transients' => array(
				'_uncannyowl_gtw_settings',
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

		wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-webinar' ) . '&connection=disconnected' );

		die;

	}

	/**
	 * Create and retrieve the disconnect url.
	 *
	 * @return string The disconnect url.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'gtw_disconnect',
				'nonce'  => wp_create_nonce( 'gtw-disconnect-nonce' ),
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
	public function remote_request( $params, $action_data = null ) {

		if ( null !== $action_data ) {
			Api_Server::charge_credit();
		}
		
		$params['action'] = $action_data;
		$response = Api_Server::call( $params );
		return $response;
	}

}
