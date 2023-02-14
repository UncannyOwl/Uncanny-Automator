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
class Google_Calendar_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'google-calendar' );

		$this->set_icon( 'GOOGLE_CALENDAR' );

		$this->set_name( 'Google Calendar' );

	}

	public function get_status() {

		$is_user_connected = false;

		if ( false !== $this->get_helper()->get_client() ) {
			$is_user_connected = true;
		}

		return $is_user_connected ? 'success' : '';
	}

	/**
	 * Returns the helper class.
	 *
	 * @return object The helper object.
	 */
	public function get_helper() {

		return $this->helpers;

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

		if ( $is_user_connected ) {
			$this->load_js( '/google-calendar/settings/assets/script.js' );
			$this->load_css( '/google-calendar/settings/assets/style.css' );
		}

		$user_info = $helper->get_user_info();

		$auth_error = automator_filter_input( 'auth_error' );

		$auth_success = automator_filter_input( 'auth_success' );

		$disconnect_uri = $helper->get_disconnect_url();

		include_once 'view-google-calendar.php';

	}

}
