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
 * Facebook Settings
 */
class Facebook_Settings {

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

		$this->set_id( 'facebook-pages' );

		$this->set_icon( 'facebook' );

		$this->set_name( 'Facebook Pages' );

		$this->set_status( $is_user_connected ? 'success' : '' );

		if ( $is_user_connected ) {
			$this->set_js( '/facebook/settings/assets/script.js' );
		}

		$this->set_css( '/facebook/settings/assets/style.css' );

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

		$is_user_connected = $this->get_helper()->is_user_connected();

		$error_status = automator_filter_input( 'status' );

		$connection = automator_filter_input( 'connection' );

		$login_dialog_uri = $this->get_helper()->get_login_dialog_uri();

		$facebook_user = $this->get_user();

		$disconnect_uri = $this->get_helper()->get_disconnect_url();

		include_once 'view-facebook.php';

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
