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
class Facebook_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'facebook-pages' );

		$this->set_icon( 'FACEBOOK' );

		$this->set_name( 'Facebook Pages' );

	}

	public function get_status() {
		return $this->get_helper()->is_user_connected() ? 'success' : '';
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

		$is_user_connected = $this->get_helper()->is_user_connected();

		if ( $is_user_connected ) {
			$this->load_js( '/facebook/settings/assets/script.js' );
		}

		$this->load_css( '/facebook/settings/assets/style.css' );

		$error_status = automator_filter_input( 'status' );

		$connection = automator_filter_input( 'connection' );

		$login_dialog_uri = $this->get_helper()->get_login_dialog_uri();

		$facebook_user = $this->get_user();

		$user_info = $this->extract_user_info( $facebook_user );

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

	/**
	 * Extracts user info from Facebook user.
	 *
	 * @return array The user info.
	 */
	private function extract_user_info( $facebook_user ) {

		$facebook_user = (array) $facebook_user;

		$defaults = array(
			'picture' => '',
			'name'    => '',
			'user_id' => '',
		);

		$user_info = isset( $facebook_user['user-info'] ) ? $facebook_user['user-info'] : array();

		return wp_parse_args( $user_info, $defaults );

	}

}
