<?php
/**
 * Zoom settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */

namespace Uncanny_Automator;

/**
 * Zoom Settings
 */
class Zoom_Settings {

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

		$this->set_id( 'zoom-api' );

		$this->set_icon( 'zoom' );

		$this->set_name( 'Zoom Meetings' );

        $this->register_option( 'uap_automator_zoom_api_consumer_key' );
		$this->register_option( 'uap_automator_zoom_api_consumer_secret' );
		$this->register_option( 'uap_automator_zoom_api_settings_timestamp' );

		$this->api_key    = trim( get_option( 'uap_automator_zoom_api_consumer_key', '' ) );
		$this->api_secret = trim( get_option( 'uap_automator_zoom_api_consumer_secret', '' ) );

		$this->user = false;

		if ( ! empty( $this->api_key ) && ! empty( $this->api_secret ) ) {
			$this->user = $this->helpers->get_user(); 
		}

		$this->is_connected = false;

		if ( ! empty( $this->user['email'] ) ) {
			$this->is_connected = true;
		}
		
		$this->set_status( $this->is_connected ? 'success' : '' );
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

        $disconnect_url = $this->helpers->disconnect_url();

		include_once 'view-zoom.php';

	}

}

