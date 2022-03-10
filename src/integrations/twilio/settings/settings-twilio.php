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

		$this->set_status( empty( $this->helpers->get_twilio_accounts_connected() ) ? '' : 'success' );

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

        // Get data about the connected Twilio
		$user = $this->helpers->get_twilio_accounts_connected();

        $client = $this->helpers->get_client();

		// Check if Twilio is connected
		$is_connected = false !== $client;

		$account_sid = ! empty( $client['account_sid'] ) ? $client['account_sid'] : '';
		$auth_token = ! empty( $client['auth_token'] ) ? $client['auth_token'] : '';
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

