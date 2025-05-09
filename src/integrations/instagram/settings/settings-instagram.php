<?php
/**
 * Creates the settings page for Instagram.
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Joseph G.
 */

namespace Uncanny_Automator;

/**
 * Instagram Settings
 */
class Instagram_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'instagram' );

		$this->set_icon( 'INSTAGRAM' );

		$this->set_name( 'Instagram' );

	}

	public function get_status() {
		$is_user_connected = $this->get_helper()->is_user_connected();
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

		$fb_helper = null;

		if ( isset( Automator()->helpers->recipe->facebook->options ) ) {

			$fb_helper = Automator()->helpers->recipe->facebook->options;

		}

		$is_user_connected = $this->get_helper()->is_user_connected();

		if ( $is_user_connected ) {
			$this->load_js( '/instagram/settings/assets/script.js' );
		}

		$this->load_css( '/instagram/settings/assets/style.css' );

		$facebook_pages_settings_uri = $this->get_helper()->get_facebook_pages_settings_url();

		$facebook_pages_oauth_dialog_uri = $this->get_helper()->get_facebook_pages_oauth_dialog_uri();

		if ( $fb_helper ) {

			$user_info = $this->extract_user_info( (object) $fb_helper->get_user_connected() );

			$disconnect_uri = $fb_helper->get_disconnect_url();

		}

		include_once 'view-instagram.php';

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
