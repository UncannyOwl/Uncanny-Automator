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
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/twilio';

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

		$this->setting_tab = 'twilio-api';
		$this->tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=' . $this->setting_tab;

		add_action( 'update_option_uap_automator_twilio_api_settings_timestamp', array( $this, 'settings_updated' ), 100, 3 );
		add_action( 'add_option_uap_automator_twilio_api_settings_timestamp', array( $this, 'settings_updated' ), 100, 3 );

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
	public function send_sms( $to, $body, $user_id, $action = null ) {

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
		$request['from'] = $from;
		$request['to'] = $to;
		$request['body'] = $body;

		try {
			$response = $this->api_call( $request, $action );
		} catch ( \Exception $e ) {
			return array(
				'result'  => false,
				'message' => $e->getMessage(),
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
			throw new \Exception( 'Twilio is not connected' );
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

		$body['action'] = 'account_info';

		$twilio_account = $this->api_call( $body );

		update_option( 'uap_twilio_connected_user', $twilio_account );

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
				'uap_twilio_connected_user',
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

	/**
	 * api_call
	 *
	 * @param  mixed $body
	 * @param  mixed $action
	 * @return void
	 */
	public function api_call( $body, $action = null ) {

		$client = $this->get_client();

		$body['account_sid'] = $client['account_sid'];
		$body['auth_token'] = $client['auth_token'];
		
		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body' => $body,
			'action' => $action
		);

		$response = Api_Server::api_call( $params );

		if ( 200 !== $response['statusCode'] ) {
			throw new \Exception( $params['endpoint'] . ' failed' );
		}

		return $response['data'];
	
	}

	/**
	 * settings_updated
	 *
	 * @return void
	 */
	public function settings_updated() {

		$redirect_url = $this->tab_url;

		if ( $this->credentials_updated() ) {
			
			$result = 1;

			try {
				$this->get_twilio_accounts_connected();
			} catch ( \Exception $e ) { 
				delete_option( 'uap_twilio_connected_user' );
				$result = $e->getMessage();
			}

			$redirect_url .= '&connect=' . $result;
		}

		wp_safe_redirect( $redirect_url );
		die;
	}
	
	/**
	 * credentials_updated
	 *
	 * @return void
	 */
	public function credentials_updated() {

		$client = $this->get_client();
		$account_credentials = $client['account_sid'] . ':' . $client['auth_token'];
		$new_credentials_hash = base64_encode( $account_credentials );		
		$old_credentials_hash = get_option( 'uap_automator_twilio_api_credentials_hash', time() );

		if ( $new_credentials_hash !== $old_credentials_hash ) {
			update_option( 'uap_automator_twilio_api_credentials_hash', $new_credentials_hash );
			return true;
		}

		return false;
	}
	
	/**
	 * get_user
	 *
	 * @return void
	 */
	public function get_user() {
		$users_option_exist = get_option( 'uap_twilio_connected_user', 'no' );

		if ( 'no' !== $users_option_exist ) {
			return $users_option_exist;
		}
				
		return $this->get_twilio_accounts_connected();
	}
}
