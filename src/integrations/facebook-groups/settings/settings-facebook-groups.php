<?php
/**
 * Creates the settings page
 *
 * @since   3.10
 * @version 1.0
 * @package Uncanny_Automator
 * @author  UncannyOwl
 */

namespace Uncanny_Automator;

/**
 * Facebook Settings
 */
class Facebook_Group_Settings {

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

		$is_user_connected = $this->get_helper()->is_user_connected();

		$this->set_id( 'facebook-groups' );

		// This has same icon as Facebook.
		$this->set_icon( 'facebook' );

		$this->set_name( 'Facebook Groups' );

		$this->set_status( $is_user_connected ? 'success' : '' );

		if ( $is_user_connected ) {
			$this->set_js( '/facebook-groups/settings/assets/script.js' );
		}

		$this->set_css( '/facebook-groups/settings/assets/style.css' );

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

		$status = automator_filter_input( 'status' );

		$error_code = automator_filter_input( 'error_code' );

		$error_message = automator_filter_input( 'error_message' );

		$connection = automator_filter_input( 'connection' );

		$facebook_user = $this->get_user();

		$is_user_connected = $this->get_helper()->is_user_connected();

		$login_dialog_uri = $this->get_helper()->get_login_dialog_uri();

		$disconnect_uri = $this->get_helper()->get_disconnect_url();

		$is_credentials_valid = $this->get_helper()->is_credentials_valid();

		include_once 'view-facebook-groups.php';

	}

	/**
	 * Get the connected user.
	 *
	 * @return array The connected user.
	 */
	public function get_user() {

		return (object) $this->get_helper()->get_user_connected();

	}
}
