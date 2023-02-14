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
class Hubspot_Settings extends Settings\Premium_Integration_Settings {

	protected $client;
	protected $is_connected;

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'hubspot-api' );

		$this->set_icon( 'HUBSPOT' );

		$this->set_name( 'Hubspot' );

	}

	public function get_status() {

		try {
			$this->helpers->get_client();
			$is_connected = true;
		} catch ( \Exception $e ) {
			$is_connected = false;
		}

		return false === $is_connected ? '' : 'success';
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		try {
			$this->client       = $this->helpers->get_client();
			$this->is_connected = true;
		} catch ( \Exception $e ) {
			$this->client       = false;
			$this->is_connected = false;
		}

		$connect_url = $this->helpers->connect_url();

		$disconnect_url = $this->helpers->disconnect_url();

		$token_info = $this->helpers->api_token_info();

		// Check if the user JUST connected the workspace and returned
		// from the HubSpot connection page
		$just_connected = automator_filter_input( 'connect' ) === '1';

		include_once 'view-hubspot.php';

	}

}

