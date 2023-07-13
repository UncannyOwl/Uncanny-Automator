<?php
/**
 * Creates the settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Uncanny_Automator\Settings;
/**
 * Facebook Settings
 */
class GoToWebinar_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'go-to-webinar' );

		$this->set_icon( 'GTW' );

		$this->set_name( 'GoTo Webinar' );

		// Add settings (optional)
		$this->register_option( 'uap_automator_gtw_api_consumer_key' );

		$this->register_option( 'uap_automator_gtw_api_consumer_secret' );

	}

	public function get_status() {
		$settings = automator_get_option( '_uncannyowl_gtw_settings', array() );
		return ! empty( $settings ) ? 'success' : '';
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

		$key = trim( automator_get_option( 'uap_automator_gtw_api_consumer_key', '' ) );

		$secret = trim( automator_get_option( 'uap_automator_gtw_api_consumer_secret', '' ) );

		$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=go-to-webinar';

		$disconnect_url = $this->get_helper()->get_disconnect_url();

		$connection = automator_filter_input( 'connect' );

		$user = automator_get_option( '_uncannyowl_gtw_settings', array() );

		$is_connected = ! empty( $user );

		$user_first_name = isset( $user['firstName'] ) ? $user['firstName'] : '';

		$user_last_name = isset( $user['lastName'] ) ? $user['lastName'] : '';

		$user_display_name = implode( ' ', array( $user_first_name, $user_last_name ) );

		$user_email_address = isset( $user['email'] ) ? $user['email'] : '';

		include_once 'view-gotowebinar.php';

	}

}
