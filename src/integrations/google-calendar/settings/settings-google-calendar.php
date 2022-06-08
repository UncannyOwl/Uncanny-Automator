<?php
/**
 * Creates the settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Joseph G.
 */

namespace Uncanny_Automator;

/**
 * Google_ShGoogle_Calendar_Settingseet_Settings Settings
 */
class Google_Calendar_Settings {

	/**
	 * This trait defines properties and methods shared across all the
	 * settings pages of Premium Integrations
	 */
	use Settings\Premium_Integrations;

	protected $helper = '';
	/**
	 * Creates the settings page
	 */
	public function __construct( $helper ) {

		$this->helper = $helper;

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

		$this->set_id( 'google-calendar' );

		$this->set_icon( 'google-calendar' );

		$this->set_name( 'Google Calendar' );

		$is_user_connected = false;

		if ( false !== $this->get_helper()->get_client() ) {
			$is_user_connected = true;
		}

		$this->set_status( $is_user_connected ? 'success' : '' );

		if ( $is_user_connected ) {
			$this->set_js( '/google-calendar/settings/assets/script.js' );
			$this->set_css( '/google-calendar/settings/assets/style.css' );
		}

	}

	/**
	 * Returns the helper class.
	 *
	 * @return object The helper object.
	 */
	public function get_helper() {

		return $this->helper;

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$helper = $this->get_helper();

		$client = $helper->get_client();

		$authentication_url = $helper->get_authentication_url();

		$is_user_connected = $helper->is_user_connected();

		$user_info = $helper->get_user_info();

		$auth_error = automator_filter_input( 'auth_error' );

		$auth_success = automator_filter_input( 'auth_success' );

		$disconnect_uri = $helper->get_disconnect_url();

		include_once 'view-google-calendar.php';

	}

}
