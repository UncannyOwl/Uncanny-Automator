<?php
/**
 * Twilio settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */

namespace Uncanny_Automator;

/**
 * Twilio Settings
 */
class Twilio_Settings extends Settings\Premium_Integration_Settings {

	protected $client;
	protected $user;
	protected $is_connected;

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'twilio-api' );

		$this->set_icon( 'TWILIO' );

		$this->set_name( 'Twilio' );

		$this->register_option( 'uap_automator_twilio_api_account_sid' );
		$this->register_option( 'uap_automator_twilio_api_auth_token' );
		$this->register_option( 'uap_automator_twilio_api_phone_number' );
		$this->register_option( 'uap_automator_twilio_api_settings_timestamp' );

	}

	public function get_status() {
		return $this->helpers->integration_status();
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$this->user = false;

		try {

			$this->client = $this->helpers->get_client();
			$this->user   = get_option( 'uap_twilio_connected_user', array() );

			if ( empty( $this->user['sid'] ) ) {
				throw new \Exception( __( 'User account error', 'uncanny-automator' ) );
			}

			$this->is_connected = true;
		} catch ( \Exception $e ) {
			$this->user         = array();
			$this->is_connected = false;
		}

		$account_sid  = ! empty( $this->client['account_sid'] ) ? $this->client['account_sid'] : '';
		$auth_token   = ! empty( $this->client['auth_token'] ) ? $this->client['auth_token'] : '';
		$phone_number = get_option( 'uap_automator_twilio_api_phone_number', '' );

		$disconnect_uri = add_query_arg(
			array(
				'action' => 'automator_twilio_disconnect',
				'nonce'  => wp_create_nonce( 'automator_twilio_disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);

		include_once 'view-twilio.php';
	}
}

