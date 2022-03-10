<?php
/**
 * Zoom Webinars settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Ajay Verma.
 */

namespace Uncanny_Automator;

/**
 * Zoom Webinar Settings
 */
class Zoom_Webinar_Settings {

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

		$this->set_id( 'zoom-webinar-api' );

		$this->set_icon( 'zoom' );

		$this->set_name( 'Zoom Webinars' );

        $this->register_option( 'uap_automator_zoom_webinar_api_consumer_key' );
		$this->register_option( 'uap_automator_zoom_webinar_api_consumer_secret' );

		$this->user = $this->helpers->api_get_user_info();

        $this->is_connected = false !== $this->user;
		
		$this->set_status( $this->is_connected ? 'success' : '' );

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

        $api_key = get_option( 'uap_automator_zoom_webinar_api_consumer_key', '' );

        $api_secret =  get_option( 'uap_automator_zoom_webinar_api_consumer_secret', '' );

        $user = $this->helpers->api_get_user_info();

        $disconnect_url = $this->helpers->disconnect_url();

		include_once 'view-zoom-webinar.php';

	}

}

