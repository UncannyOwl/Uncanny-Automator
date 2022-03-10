<?php
/**
 * Hubspot settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */

namespace Uncanny_Automator;

/**
 * Hubspot Settings
 */
class Hubspot_Settings {

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

		$this->set_id( 'hubspot-api' );

		$this->set_icon( 'hubspot' );

		$this->set_name( 'Hubspot' );

		$this->set_status( false === $this->helpers->get_client() ? '' : 'success' );

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

        $client = $this->helpers->get_client();

		// Check if Hubspot is connected
		$is_connected = false !== $client;

        $connect_url = $this->helpers->connect_url();

        $disconnect_url = $this->helpers->disconnect_url();

        $token_info = $this->helpers->api_token_info();

		// Check if the user JUST connected the workspace and returned
		// from the HubSpot connection page
		$just_connected = automator_filter_input( 'connect' ) === '1';

		include_once 'view-hubspot.php';

	}

}

