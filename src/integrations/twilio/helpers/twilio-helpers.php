<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator_Pro\Twilio_Pro_Helpers;

/**
 * Class Twilio_Helpers
 * @package Uncanny_Automator
 */
class Twilio_Helpers {

	/**
	 * @var Twilio_Helpers
	 */
	public $options;

	/**
	 * @var Twilio_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var string
	 */
	public $setting_tab;
	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Twilio_Helpers constructor.
	 */
	public function __construct() {
		// Selectively load options
		if ( method_exists( '\Uncanny_Automator\Automator_Helpers_Recipe', 'maybe_load_trigger_options' ) ) {
			global $uncanny_automator;
			$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		}

		$this->setting_tab = 'twilio_api';
		$this->automator_api = AUTOMATOR_API_URL . 'v2/twilio';

		add_action( 'update_option_uap_automator_twilio_api_auth_token', array( $this, 'twilio_setting_update' ), 100, 3 );
		add_action( 'update_option_uap_automator_twilio_api_account_sid', array( $this, 'twilio_setting_update' ), 100, 3 );

		// Add twilio disconnect action.
		add_action( 'wp_ajax_automator_twilio_disconnect', array( $this, 'automator_twilio_disconnect' ), 100 );

		$this->load_settings();

	}

	/**
	 * Load the settings
	 * 
	 * @return void
	 */
	private function load_settings() {
		include_once __DIR__ . '/../settings/settings-twilio.php';
		new Twilio_Settings( $this );
	}

	/**
	 * @param Twilio_Helpers $options
	 */
	public function setOptions( Twilio_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Twilio_Helpers $pro
	 */
	public function setPro( Twilio_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 *
	 * @param string $to
	 * @param string $message
	 * @param string $user_id
	 *
	 * @return array
	 * @throws ConfigurationException
	 * @throws TwilioException
	 */
	public function send_sms( $to, $body, $user_id ) {

		$client = $this->get_client();

		if ( ! $client ) {
			return array(
				'result'  => false,
				'message' => __( 'Twilio credentails are missing or expired.', 'uncanny-automator' ),
			);
		}

		$from = trim( get_option( 'uap_automator_twilio_api_phone_number', '' ) );

		if ( empty( $from ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Twilio number is missing.', 'uncanny-automator' ),
			);
		}

		$to = self::validate_phone_number( $to );

		if ( ! $to ) {
			return array(
				'result'  => false,
				'message' => __( 'To number is not valid.', 'uncanny-automator' ),
			);
		}

		$request['action'] = 'send_sms';
		$request['account_sid'] = $client['account_sid'];
		$request['auth_token'] = $client['auth_token'];


		$request['from'] = $from;
		$request['to'] = $to;
		$request['body'] = $body;

		try {
			$response = Api_Server::api_call( 'v2/twilio', $request );
		} catch ( \Exception $th ) {
			return array(
				'result'  => false,
				'message' => $th->getMessage(),
			);
		}

		update_user_meta( $user_id, '_twilio_sms_', $response );

		return array(
			'result'  => true,
			'message' => '',
		);

	}

	/**
	 * @param $phone
	 *
	 * @return false|mixed|string|string[]
	 */
	private function validate_phone_number( $phone ) {
		// Allow +, - and . in phone number
		$filtered_phone_number = filter_var( $phone, FILTER_SANITIZE_NUMBER_INT );
		// Remove "-" from number
		$phone_to_check = str_replace( '-', '', $filtered_phone_number );

		// Check the lenght of number
		// This can be customized if you want phone number from a specific country
		if ( strlen( $phone_to_check ) < 10 || strlen( $phone_to_check ) > 14 ) {
			return false;
		} else {
			return $phone_to_check;
		}
	}

	/**
	 * get_client
	 *
	 * @return void|bool
	 */
	public function get_client() {

		$sid      = get_option( 'uap_automator_twilio_api_account_sid' );
		$token    = get_option( 'uap_automator_twilio_api_auth_token' );

		if ( empty( $sid ) || empty( $token ) ) {
			return false;
		}

		return array('account_sid' => $sid, 'auth_token' => $token );

	}

	/**
	 * Get the Twilio Accounts connected using the account id and auth token.
	 * This functions sends an http request with Basic Authentication to Twilio API.
	 *
	 * @return array $twilio_accounts The twilio accounts connected.
	 */
	public function get_twilio_accounts_connected() {

		$client = $this->get_client();

		if ( empty( $client ) ) {
			return array();
		}

		// Return the transient if its available.
		$accounts_saved = get_transient( '_automator_twilio_account_info' );

		if ( false !== $accounts_saved ) {
			return $accounts_saved;
		}

		$body['action'] = 'account_info';
		$body['account_sid'] = $client['account_sid'];
		$body['auth_token'] = $client['auth_token'];

		try {
			$twilio_account = Api_Server::api_call( 'v2/twilio', $body );
		} catch ( \Exception $th ) {
			return array();
		}

		if ( empty( $twilio_account ) ) {
			return array();
		}

		// Update the transient.
		set_transient( '_automator_twilio_account_info', $twilio_account, DAY_IN_SECONDS );

		return $twilio_account;
		
	}

	/**
	 * Callback function to hook wp_ajax_automator_twilio_disconnect.
	 * Deletes all the option and transients then redirect the user back to the settings page.
	 *
	 * @return void.
	 */
	public function automator_twilio_disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), 'automator_twilio_disconnect' ) ) {

			// Remove option
			$option_keys = array(
				'_uncannyowl_twilio_settings',
				'_uncannyowl_twilio_settings_expired',
				'uap_automator_twilio_api_auth_token',
				'uap_automator_twilio_api_phone_number',
				'uap_automator_twilio_api_account_sid',
			);

			foreach ( $option_keys as $option_key ) {
				delete_option( $option_key );
			}

			// Remove transients.
			$transient_keys = array(
				'_uncannyowl_twilio_settings',
				'_automator_twilio_account_info',
				'uap_automator_twilio_api_accounts_response'
			);

			foreach ( $transient_keys as $transient_key ) {
				delete_transient( $transient_key );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' 	=> 'uo-recipe',
					'page'      	=> 'uncanny-automator-config',
					'tab'       	=> 'premium-integrations',
					'integration' 	=> 'twilio-api'
				),
				admin_url( 'edit.php' )
			)
		);

		exit;
	}

	public function twilio_setting_update() {
		delete_transient( '_automator_twilio_account_info' );
	}
}
