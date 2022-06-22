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
class Twilio_Settings {

	/**
	 * This trait defines properties and methods shared across all the
	 * settings pages of Premium Integrations
	 */
	use Settings\Premium_Integrations;

	protected $helpers;

	/**
	 * Creates the settings page
	 */
	public function __construct( $helpers ) {

		$this->helpers = $helpers;

		// Register the tab
		$this->setup_settings();

		// The methods above load even if the tab is not selected
		if ( ! $this->is_current_page_settings() ) {
			return;
		}

	}

	/**
	 * Sets up the properties of the settings page
	 */
	protected function set_properties() {

		$this->set_id( 'twilio-api' );

		$this->set_icon( 'twilio' );

		$this->set_name( 'Twilio' );

		$this->register_option( 'uap_automator_twilio_api_account_sid' );
		$this->register_option( 'uap_automator_twilio_api_auth_token' );
		$this->register_option( 'uap_automator_twilio_api_phone_number' );
		$this->register_option( 'uap_automator_twilio_api_settings_timestamp' );

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

		$this->set_status( $this->is_connected ? 'success' : '' );

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

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

