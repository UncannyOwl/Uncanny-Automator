<?php

namespace Uncanny_Automator\Integrations\Twilio;

use Uncanny_Automator\App_Integrations\App_Helpers;
use WP_Error;

/**
 * Class Twilio_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Twilio_Api_Caller $api
 */
class Twilio_App_Helpers extends App_Helpers {

	/**
	 * Account SID option name.
	 *
	 * @var string
	 */
	const ACCOUNT_SID = 'uap_automator_twilio_api_account_sid';

	/**
	 * Auth token option name.
	 *
	 * @var string
	 */
	const AUTH_TOKEN = 'uap_automator_twilio_api_auth_token';

	/**
	 * Phone number option name.
	 *
	 * @var string
	 */
	const PHONE_NUMBER = 'uap_automator_twilio_api_phone_number';

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Map account option name.
		$this->set_account_option_name( 'uap_twilio_connected_user' );
	}

	/**
	 * Validate credentials format
	 *
	 * @param array $credentials
	 * @param array $args Optional additional arguments
	 * @return true|WP_Error
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		if ( empty( $credentials['account_sid'] ) ) {
			return new WP_Error( 'missing_account_sid', esc_html_x( 'Account SID is required', 'Twilio', 'uncanny-automator' ) );
		}

		if ( empty( $credentials['auth_token'] ) ) {
			return new WP_Error( 'missing_auth_token', esc_html_x( 'Auth token is required', 'Twilio', 'uncanny-automator' ) );
		}

		if ( empty( $credentials['phone_number'] ) ) {
			return new WP_Error( 'missing_phone_number', esc_html_x( 'Active phone number is required', 'Twilio', 'uncanny-automator' ) );
		}

		// Validate phone number format
		$phone = $this->validate_phone_number( $credentials['phone_number'] );
		if ( ! $phone ) {
			return new WP_Error( 'invalid_phone_number', esc_html_x( 'Phone number is not valid', 'Twilio', 'uncanny-automator' ) );
		}

		return true;
	}

	/**
	 * Validate phone number format
	 *
	 * @param string $phone
	 * @return false|string
	 */
	public function validate_phone_number( $phone ) {
		// Remove all whitespace from the phone number
		$phone = preg_replace( '/\s+/', '', $phone );

		// Allow +, - and . in phone number
		$filtered_phone_number = filter_var( $phone, FILTER_SANITIZE_NUMBER_INT );
		// Remove "-" from number
		$phone_to_check = str_replace( '-', '', $filtered_phone_number );

		// Check the length of number
		if ( strlen( $phone_to_check ) < 10 || strlen( $phone_to_check ) > 14 ) {
			return false;
		}

		return $phone_to_check;
	}

	/**
	 * Get stored credentials
	 *
	 * @return array
	 */
	public function get_credentials() {
		$credentials = array(
			'account_sid'  => $this->get_constant_option( 'ACCOUNT_SID' ),
			'auth_token'   => $this->get_constant_option( 'AUTH_TOKEN' ),
			'phone_number' => $this->get_constant_option( 'PHONE_NUMBER' ),
		);

		// Check if all credentials are present.
		if ( empty( $credentials['account_sid'] ) || empty( $credentials['auth_token'] ) || empty( $credentials['phone_number'] ) ) {
			return array();
		}

		return $credentials;
	}

	/**
	 * Store credentials
	 *
	 * @param array $credentials
	 *
	 * @return void
	 */
	public function store_credentials( $credentials ) {
		// Store individual options for backward compatibility.
		automator_update_option( self::ACCOUNT_SID, $credentials['account_sid'] );
		automator_update_option( self::AUTH_TOKEN, $credentials['auth_token'] );
		automator_update_option( self::PHONE_NUMBER, $credentials['phone_number'] );
	}

	/**
	 * Get constant option name.
	 *
	 * @param string $const_name
	 *
	 * @return string
	 */
	public function get_constant_option( $const_name ) {
		return automator_get_option( $this->get_const( $const_name ), '' );
	}
}

// CRITICAL: Add class_alias for Pro compatibility
class_alias( __NAMESPACE__ . '\Twilio_App_Helpers', 'Twilio_Helpers' );
