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

		// Selectively load options.
		if ( method_exists( '\Uncanny_Automator\Automator_Helpers_Recipe', 'maybe_load_trigger_options' ) ) {
			$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		} else {
			$this->load_options = true;
		}

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

		list( $access_token, $organizer_key ) = self::get_webinar_token();

		if ( ! empty( $access_token ) ) {

			$current_time = current_time( 'Y-m-d\TH:i:s\Z' );

			$current_time_plus_years = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+2 year', strtotime( $current_time ) ) );

			// Get the webinars.
			$json_feed = wp_remote_get(
				'https://api.getgo.com/G2W/rest/v2/organizers/' . $organizer_key . '/webinars?fromTime=' . $current_time . '&toTime=' . $current_time_plus_years . '&page=0&size=200',
				array(
					'headers' => array(
						'Authorization' => $access_token,
					),
				)
			);

			$json_response = wp_remote_retrieve_response_code( $json_feed );

			// Prepare webinar list.
			if ( 200 === $json_response ) {

				$jsondata = json_decode( preg_replace( '/("\w+"):(\d+(\.\d+)?)/', '\\1:"\\2"', wp_remote_retrieve_body( $json_feed ) ), true );

				$jsondata = isset( $jsondata['_embedded']['webinars'] ) ? $jsondata['_embedded']['webinars'] : array();

				if ( count( $jsondata ) > 0 ) {

					foreach ( $jsondata as $key1 => $webinar ) {

						$webinars[] = array(
							'text'  => $webinar['subject'],
							'value' => (string) $webinar['webinarKey'] . '-objectkey',
						);

					}
				}
			}
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
	public static function gtw_register_user( $user_id, $webinar_key ) {

		$user = get_userdata( $user_id );

		if ( is_wp_error( $user ) ) {
			return array(
				'result'  => false,
				'message' => __( 'GoTo Webinar user not found.', 'uncanny-automator' ),
			);
		}

		$customer_first_name = $user->first_name;

		$customer_last_name = $user->last_name;

		$customer_email = $user->user_email;

		if ( ! empty( $customer_email ) ) {

			$customer_email_parts = explode( '@', $customer_email );

			$customer_first_name = empty( $customer_first_name ) ? $customer_email_parts[0] : $customer_first_name;

			$customer_last_name = empty( $customer_last_name ) ? $customer_email_parts[0] : $customer_last_name;

		}

		list( $access_token, $organizer_key ) = self::get_webinar_token();

		if ( empty( $access_token ) ) {

			return array(
				'result'  => false,
				'message' => __( 'GoTo Webinar credentails has expired.', 'uncanny-automator' ),
			);

		}

		// API register call
		$response = wp_remote_post(
			"https://api.getgo.com/G2W/rest/v2/organizers/{$organizer_key}/webinars/{$webinar_key}/registrants?resendConfirmation=true",
			array(
				'method'      => 'POST',
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
						'lastName'  => $customer_last_name,
						'email'     => $customer_email,
					)
				),

			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 201 === wp_remote_retrieve_response_code( $response ) || 409 === wp_remote_retrieve_response_code( $response ) ) {
				$jsondata = json_decode( $response['body'], true, 512, JSON_BIGINT_AS_STRING );
				if ( isset( $jsondata['joinUrl'] ) ) {
					update_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey', $jsondata['registrantKey'] );
					update_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_joinUrl', $jsondata['joinUrl'] );

					return array(
						'result'  => true,
						'message' => __( 'Successfully registered', 'uncanny-automator' ),
					);
				}
			} else {
				$jsondata = json_decode( $response['body'], true, 512, JSON_BIGINT_AS_STRING );

				return array(
					'result'  => false,
					'message' => esc_html( $jsondata['description'] ),
				);
			}
		} else {
			return array(
				'result'  => false,
				'message' => __( 'The GoTo Webinar API returned an error.', 'uncanny-automator' ),
			);
		}
	}

	/**
	 * For un-registering user to webinar action method.
	 *
	 * @param string $user_id
	 * @param string $webinar_key
	 *
	 * @return array
	 */
	public static function gtw_unregister_user( $user_id, $webinar_key ) {

		list( $access_token, $organizer_key ) = self::get_webinar_token();

		if ( empty( $access_token ) ) {
			return array(
				'result'  => false,
				'message' => __( 'GoTo Webinar credentails has expired.', 'uncanny-automator' ),
			);
		}

		$user_registrant_key = get_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey', true );

		if ( empty( $user_registrant_key ) ) {
			return array(
				'result'  => false,
				'message' => __( 'User was not registered for webinar.', 'uncanny-automator' ),
			);
		}

		// API register call
		$response = wp_remote_post(
			"https://api.getgo.com/G2W/rest/v2/organizers/{$organizer_key}/webinars/{$webinar_key}/registrants/{$user_registrant_key}",
			array(
				'method'      => 'DELETE',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Authorization' => $access_token,
					'Content-type'  => 'application/json',
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {

			if ( 201 === wp_remote_retrieve_response_code( $response ) || 204 === wp_remote_retrieve_response_code( $response ) ) {

				delete_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_registrantKey' );

				delete_user_meta( $user_id, '_uncannyowl_gtw_webinar_' . $webinar_key . '_joinUrl' );

				return array(
					'result'  => true,
					'message' => __( 'Successfully registered', 'uncanny-automator' ),
				);

			} else {
				$jsondata = json_decode( $response['body'], true, 512, JSON_BIGINT_AS_STRING );

				return array(
					'result'  => false,
					'message' => esc_html( $jsondata['description'] ),
				);
			}
		} else {
			return array(
				'result'  => false,
				'message' => __( 'The GoTo Webinar API returned an error.', 'uncanny-automator' ),
			);
		}
	}

	/**
	 * To get webinar access token and organizer key
	 *
	 * @return array
	 */
	public static function get_webinar_token() {

		$get_transient = get_transient( '_uncannyowl_gtw_settings' );

		if ( false !== $get_transient ) {

			$tokens = explode( '|', $get_transient );

			return array( $tokens[0], $tokens[1] );

		} else {

			$oauth_settings        = get_option( '_uncannyowl_gtw_settings' );
			$current_refresh_token = isset( $oauth_settings['refresh_token'] ) ? $oauth_settings['refresh_token'] : '';
			if ( empty( $current_refresh_token ) ) {
				update_option( '_uncannyowl_gtw_settings_expired', true );

				return array( '', '' );
			}
			$consumer_key    = trim( get_option( 'uap_automator_gtw_api_consumer_key', '' ) );
			$consumer_secret = trim( get_option( 'uap_automator_gtw_api_consumer_secret', '' ) );
			//do response
			$response = wp_remote_post(
				'https://api.getgo.com/oauth/v2/token',
				array(
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
						'Content-Type'  => 'application/x-www-form-urlencoded; charset=utf-8',
					),
					'body'    => array(
						'refresh_token' => $current_refresh_token,
						'grant_type'    => 'refresh_token',
					),
				)
			);

			if ( ! is_wp_error( $response ) ) {
				if ( 200 === wp_remote_retrieve_response_code( $response ) ) {

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

				} else {
					// Empty settings
					update_option( '_uncannyowl_gtw_settings', array() );
					update_option( '_uncannyowl_gtw_settings_expired', true );

					return array( '', '' );
				}
			} else {
				// Empty settings
				update_option( '_uncannyowl_gtw_settings', array() );
				update_option( '_uncannyowl_gtw_settings_expired', true );

				return array( '', '' );
			}
		}
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

		if ( isset( $_REQUEST['code'] ) && ! empty( $_REQUEST['code'] ) && isset( $_REQUEST['state'] ) && $_REQUEST['state'] === $this->setting_tab ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$consumer_key = trim( get_option( 'uap_automator_gtw_api_consumer_key', '' ) );

			$consumer_secret = trim( get_option( 'uap_automator_gtw_api_consumer_secret', '' ) );

			$code = $_REQUEST['code']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$response = wp_remote_post(
				'https://api.getgo.com/oauth/v2/token',
				array(
					'headers' => array(
						'Content-Type'  => 'application/x-www-form-urlencoded; charset=utf-8',
						'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
						'Accept'        => 'application/json',
					),
					'body'    => array(
						'code'       => $code,
						'grant_type' => 'authorization_code',
					),
				)
			);

			if ( ! is_wp_error( $response ) ) {
				// On success
				$status_code = wp_remote_retrieve_response_code( $response );

				if ( 200 === $status_code ) {

					// lets get the response and decode it.
					$sent_response = json_decode( $response['body'], true );

					// Update the options.
					update_option( '_uncannyowl_gtw_settings', $sent_response );
					delete_option( '_uncannyowl_gtw_settings_expired' );

					// Set the transient.
					set_transient( '_uncannyowl_gtw_settings', $sent_response['access_token'] . '|' . $sent_response['organizer_key'], 60 * 50 );

					wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-webinar' ) . '&connect=1' );

					die;

				} else {
					wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-webinar' ) . '&connect=error-status-code' );
					die;
				}
			} else {
				wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-webinar' ) . '&connect=error-wordpress' );
				die;
			}
		}
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

}
