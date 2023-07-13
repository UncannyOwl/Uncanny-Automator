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
 * GoToTraining Settings
 */
class GoToTraining_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'go-to-training' );

		$this->set_icon( 'GTT' );

		$this->set_name( 'GoTo Training' );

		// Add settings (optional)
		#$this->register_option( 'uap_automator_gtw_api_consumer_key' );

		#$this->register_option( 'uap_automator_gtw_api_consumer_secret' );

	}

	public function get_status() {

		$user = automator_get_option( '_uncannyowl_gtt_settings', array() );

		return ! empty( $user ) ? 'success' : '';
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

		$key = automator_get_option( 'uap_automator_gtt_api_consumer_key' );

		$secret = automator_get_option( 'uap_automator_gtt_api_consumer_secret' );

		$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=go-to-training';

		$disconnect_url = $this->get_helper()->get_disconnect_url();

		$connection = automator_filter_input( 'connect' );

		$user = automator_get_option( '_uncannyowl_gtt_settings', array() );

		$is_connected = ! empty( $user );

		$user_first_name = isset( $user['firstName'] ) ? $user['firstName'] : '';

		$user_last_name = isset( $user['lastName'] ) ? $user['lastName'] : '';

		$user_display_name = implode( ' ', array( $user_first_name, $user_last_name ) );

		$user_email_address = isset( $user['email'] ) ? $user['email'] : '';

		include_once 'view-gototraining.php';

	}

}
