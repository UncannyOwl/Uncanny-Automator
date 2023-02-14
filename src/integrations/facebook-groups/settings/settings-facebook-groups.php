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
class Facebook_Group_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'facebook-groups' );

		// This has same icon as Facebook.
		$this->set_icon( 'FACEBOOK' );

		$this->set_name( 'Facebook Groups' );

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

		$status = automator_filter_input( 'status' );

		$error_code = automator_filter_input( 'error_code' );

		$error_message = automator_filter_input( 'error_message' );

		$connection = automator_filter_input( 'connection' );

		$facebook_user = $this->get_user();

		$user_info = $this->extract_user_info( $facebook_user );

		$is_user_connected = $this->get_helper()->is_user_connected();

		if ( $is_user_connected ) {
			$this->load_js( '/facebook-groups/settings/assets/script.js' );
		}

		$this->load_css( '/facebook-groups/settings/assets/style.css' );

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
