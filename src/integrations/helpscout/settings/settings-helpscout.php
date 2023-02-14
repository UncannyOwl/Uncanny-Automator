<?php
/**
 * Creates the settings page
 *
 * @since   4.8
 *
 * @package Uncanny_Automator
 */
namespace Uncanny_Automator;

/**
 * Helpscout Settings
 */
class Helpscout_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		$this->set_id( 'helpscout' );

		$this->set_icon( 'HELPSCOUT' );

		$this->set_name( 'Help Scout' );

		$this->register_option( 'uap_helpscout_enable_webhook' );

		$this->register_option( 'uap_helpscout_webhook_key' );

	}

	public function get_status() {
		return false !== $this->helpers->get_client() ? 'success' : '';
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
			$vars['error_message'] = 'Help Scout has responded with status code: ' . automator_filter_input( 'code' ); // Prefer not to translate err message.
		}

		include_once 'view-helpscout.php';

	}

}
