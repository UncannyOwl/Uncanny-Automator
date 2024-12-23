<?php
/**
 * Creates the settings page for Help Scout integration.
 *
 * Handles the properties, output, and helper functionalities
 * for the Help Scout settings page.
 *
 * @since   4.8
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

/**
 * Help Scout Settings class.
 *
 * Extends the Premium Integration Settings class to provide specific
 * settings and functionalities for Help Scout integration.
 *
 * @package Uncanny_Automator
 */
class Helpscout_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Sets up the properties of the settings page.
	 *
	 * Defines the ID, icon, name, and options for the Help Scout settings page.
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'helpscout' );
		$this->set_icon( 'HELPSCOUT' );
		$this->set_name( 'Help Scout' );
		$this->register_option( 'uap_helpscout_enable_webhook' );
		$this->register_option( 'uap_helpscout_webhook_key' );

	}

	/**
	 * Retrieves the connection status of the Help Scout integration.
	 *
	 * @return string 'success' if connected, otherwise an empty string.
	 */
	public function get_status() {
		return false !== $this->helpers->get_client() ? 'success' : '';
	}

	/**
	 * Returns the helper class instance.
	 *
	 * Provides access to the helper methods for Help Scout.
	 *
	 * @return object The helper object.
	 */
	public function get_helper() {
		return $this->helpers;
	}

	/**
	 * Outputs the settings page content.
	 *
	 * Loads necessary scripts, prepares variables for the view, and includes
	 * the settings page template.
	 *
	 * @return void
	 */
	public function output() {

		$this->load_js( '/helpscout/settings/assets/scripts.js' );

		$vars = array(
			'connect_url'            => $this->helpers->get_oauth_url(),
			'disconnect_url'         => $this->helpers->get_disconnect_url(),
			'webhook_url'            => $this->helpers->get_webhook_url(),
			'webhook_key'            => $this->helpers->get_webhook_key(),
			'webhook_regenerate_url' => $this->helpers->get_regenerate_url(),
			'is_connected'           => false !== $this->helpers->get_client(),
			'enable_triggers'        => $this->helpers->is_webhook_enabled() ? 'checked' : '',
			'user'                   => $this->helpers->get_client_user(),
		);

		if ( 'error' === automator_filter_input( 'status' ) && filter_has_var( INPUT_GET, 'code' ) ) {
			$vars['has_errors']    = true;
			$vars['error_message'] = 'Help Scout has responded with status code: ' . automator_filter_input( 'code' ); // Prefer not to translate error messages.
		}

		include_once 'view-helpscout.php';

	}

}
