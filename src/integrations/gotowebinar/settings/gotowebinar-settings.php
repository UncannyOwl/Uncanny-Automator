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
class GoToWebinar_Settings {

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

		$this->set_id( 'go-to-webinar' );

		$this->set_icon( 'gotowebinar' );

		$this->set_name( 'GoTo Webinar' );

		$this->set_status( false !== get_option( '_uncannyowl_gtw_settings', false ) ? 'success' : '' );

		// Add settings (optional)
		$this->register_option( 'uap_automator_gtw_api_consumer_key' );

		$this->register_option( 'uap_automator_gtw_api_consumer_secret' );

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

		$key = trim( get_option( 'uap_automator_gtw_api_consumer_key', '' ) );

		$secret = trim( get_option( 'uap_automator_gtw_api_consumer_secret', '' ) );

		$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=go-to-webinar';

		$disconnect_url = $this->get_helper()->get_disconnect_url();

		$connection = automator_filter_input( 'connect' );

		$user = get_option( '_uncannyowl_gtw_settings', false );

		$is_connected = false !== $user;

		$user_first_name = isset( $user['firstName'] ) ? $user['firstName'] : '';

		$user_last_name = isset( $user['lastName'] ) ? $user['lastName'] : '';

		$user_display_name = implode( ' ', array( $user_first_name, $user_last_name ) );

		$user_email_address = isset( $user['email'] ) ? $user['email'] : '';

		include_once 'view-gotowebinar.php';

	}

}
