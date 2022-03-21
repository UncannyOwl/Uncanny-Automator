<?php


namespace Uncanny_Automator;

/**
 * Class Gototraining_Helpers
 *
 * @package Uncanny_Automator
 */
class Gototraining_Helpers {

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

		// Selectively load options
		if ( method_exists( '\Uncanny_Automator\Automator_Helpers_Recipe', 'maybe_load_trigger_options' ) ) {
			$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		} else {
			$this->load_options = true;
		}

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

	public function get_trainings() {

		$trainings = array();

		list( $access_token, $organizer_key ) = self::get_training_token();

		$current_time = current_time( 'Y-m-d\TH:i:s\Z' );

		$current_time_plus_years = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+2 year', strtotime( $current_time ) ) );

		// get trainings
		$json_feed = wp_remote_get(
			'https://api.getgo.com/G2T/rest/organizers/' . $organizer_key . '/trainings',
			array(
				'headers' => array(
					'Authorization' => $access_token,
				),
			)
		);

		$json_response = wp_remote_retrieve_response_code( $json_feed );

		// prepare training lists
		if ( 200 === (int) $json_response ) {

			$jsondata = json_decode( wp_remote_retrieve_body( $json_feed ), true );

			if ( count( $jsondata ) > 0 ) {

				foreach ( $jsondata as $key1 => $training ) {

					$trainings[] = array(
						'text'  => $training['name'],
						'value' => (string) $training['trainingKey'] . '-objectkey',
					);

				}
			}
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
	public static function gtt_register_user( $user_id, $training_key ) {

		$user = get_userdata( $user_id );

		if ( is_wp_error( $user ) ) {
			return array(
				'result'  => false,
				'message' => __( 'GoTo Training user not found.', 'uncanny-automator' ),
			);
		}
		$customer_first_name = $user->first_name;
		$customer_last_name  = $user->last_name;
		$customer_email      = $user->user_email;

		if ( ! empty( $customer_email ) ) {
			$customer_email_parts = explode( '@', $customer_email );
			$customer_first_name  = empty( $customer_first_name ) ? $customer_email_parts[0] : $customer_first_name;
			$customer_last_name   = empty( $customer_last_name ) ? $customer_email_parts[0] : $customer_last_name;
		}

		list( $access_token, $organizer_key ) = self::get_training_token();

		if ( empty( $access_token ) ) {
			return array(
				'result'  => false,
				'message' => __( 'GoTo Training credentails has expired.', 'uncanny-automator' ),
			);
		}
		// API register call
		$response = wp_remote_post(
			"https://api.getgo.com/G2T/rest/organizers/{$organizer_key}/trainings/{$training_key}/registrants?resendConfirmation=true",
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
						'givenName' => $customer_first_name,
						'surname'   => $customer_last_name,
						'email'     => $customer_email,
					)
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			if ( 201 === wp_remote_retrieve_response_code( $response ) ) {
				$jsondata = json_decode( $response['body'], true, 512, JSON_BIGINT_AS_STRING );
				if ( isset( $jsondata['joinUrl'] ) ) {
					update_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_registrantKey', $jsondata['registrantKey'] );
					update_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_joinUrl', $jsondata['joinUrl'] );
					update_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_confirmationUrl', $jsondata['confirmationUrl'] );

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
				'message' => __( 'The GoTo Training API returned an error.', 'uncanny-automator' ),
			);
		}
	}

	/**
	 * For un-registering user to training action method.
	 *
	 * @param string $user_id
	 * @param string $training_key
	 *
	 * @return array
	 */
	public static function gtt_unregister_user( $user_id, $training_key ) {

		list( $access_token, $organizer_key ) = self::get_training_token();

		if ( empty( $access_token ) ) {
			return array(
				'result'  => false,
				'message' => __( 'GoTo Training credentails has expired.', 'uncanny-automator' ),
			);
		}

		$user_registrant_key = get_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_registrantKey', true );

		if ( empty( $user_registrant_key ) ) {
			return array(
				'result'  => false,
				'message' => __( 'User was not registered for training session.', 'uncanny-automator' ),
			);
		}

		// API register call
		$response = wp_remote_post(
			"https://api.getgo.com/G2T/rest/organizers/{$organizer_key}/trainings/{$training_key}/registrants/{$user_registrant_key}",
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
				delete_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_registrantKey' );
				delete_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_joinUrl' );
				delete_user_meta( $user_id, '_uncannyowl_gtt_training_' . $training_key . '_confirmationUrl' );

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
				'message' => __( 'The GoTo Training API returned an error.', 'uncanny-automator' ),
			);
		}
	}

	/**
	 * To get training access token and organizer key
	 *
	 * @return array
	 */
	public static function get_training_token() {

		$get_transient = get_transient( '_uncannyowl_gtt_settings' );

		if ( false !== $get_transient ) {

			$tokens = explode( '|', $get_transient );

			return array( $tokens[0], $tokens[1] );

		} else {

			$oauth_settings        = get_option( '_uncannyowl_gtt_settings' );
			$current_refresh_token = isset( $oauth_settings['refresh_token'] ) ? $oauth_settings['refresh_token'] : '';
			if ( empty( $current_refresh_token ) ) {
				update_option( '_uncannyowl_gtt_settings_expired', true );

				return array( '', '' );
			}

			$consumer_key    = trim( get_option( 'uap_automator_gtt_api_consumer_key', '' ) );
			$consumer_secret = trim( get_option( 'uap_automator_gtt_api_consumer_secret', '' ) );
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

				$jsondata = array();

				if ( 200 === wp_remote_retrieve_response_code( $response ) ) {

					//get new access token and refresh token
					$jsondata = json_decode( $response['body'], true );

					update_option( '_uncannyowl_gtt_settings', $jsondata );
					set_transient( '_uncannyowl_gtt_settings', $jsondata['access_token'] . '|' . $jsondata['organizer_key'], 60 * 50 );
					delete_option( '_uncannyowl_gtt_settings_expired' );

					//return the array
					return array( $jsondata['access_token'], $jsondata['organizer_key'] );

				} else {
					// Empty settings
					update_option( '_uncannyowl_gtt_settings', array() );
					update_option( '_uncannyowl_gtt_settings_expired', true );

					return array( '', '' );
				}
			} else {
				// Empty settings
				update_option( '_uncannyowl_gtt_settings', array() );
				update_option( '_uncannyowl_gtt_settings_expired', true );

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

		$consumer_key    = trim( get_option( 'uap_automator_gtt_api_consumer_key', '' ) );
		$consumer_secret = trim( get_option( 'uap_automator_gtt_api_consumer_secret', '' ) );
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

		if ( isset( $_REQUEST['code'] ) && ! empty( $_REQUEST['code'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& isset( $_REQUEST['state'] ) && $_REQUEST['state'] === $this->setting_tab // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			) {

			$consumer_key    = trim( get_option( 'uap_automator_gtt_api_consumer_key', '' ) );
			$consumer_secret = trim( get_option( 'uap_automator_gtt_api_consumer_secret', '' ) );

			$code = wp_unslash( $_REQUEST['code'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=' . $this->setting_tab;

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
						//'redirect_uri' => urlencode( $tab_url ),
					),
				)
			);

			if ( ! is_wp_error( $response ) ) {

				$jsondata = array();

				// On success
				if ( 200 === wp_remote_retrieve_response_code( $response ) ) {

					//lets get the response and decode it
					$jsondata = json_decode( $response['body'], true );

					// Update the options.
					update_option( '_uncannyowl_gtt_settings', $jsondata );
					delete_option( '_uncannyowl_gtt_settings_expired' );

					// Set the transient.
					set_transient( '_uncannyowl_gtt_settings', $jsondata['access_token'] . '|' . $jsondata['organizer_key'], 60 * 50 );

					wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-training' ) . '&connect=1' );

					die;

				} else {

					// On Error
					wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-training' ) . '&connect=2' );

					die;
				}
			} else {
				// On Error
				wp_safe_redirect( automator_get_premium_integrations_settings_url( 'go-to-training' ) . '&connect=2' );
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
				'_uncannyowl_gtt_settings',
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

	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'gtt_disconnect',
				'nonce'  => wp_create_nonce( 'gtt-disconnect-nonce' ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}
}
