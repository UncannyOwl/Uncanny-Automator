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
class Helpscout_Settings {

	use Settings\Premium_Integrations;

	protected $helper = null;

	/**
	 * Creates the settings page
	 */
	public function __construct( $helper ) {

		$this->helper = $helper;

		// Registers the tab.
		$this->setup_settings();

	}

	/**
	 * Sets up the properties of the settings page
	 */
	protected function set_properties() {

		$this->set_id( 'helpscout' );

		$this->set_icon( 'HELPSCOUT' );

		$this->set_name( 'Help Scout' );

		$this->register_option( 'uap_helpscout_enable_webhook' );

		$this->register_option( 'uap_helpscout_webhook_key' );

		$this->set_js( '/helpscout/settings/assets/scripts.js' );

		$this->set_status( false !== $this->helper->get_client() ? 'success' : '' );

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

		$vars = array(
			'connect_url'            => $this->helper->get_oauth_url(),
			'disconnect_url'         => $this->helper->get_disconnect_url(),
			'webhook_url'            => $this->helper->get_webhook_url(),
			'webhook_key'            => $this->helper->get_webhook_key(),
			'webhook_regenerate_url' => $this->helper->get_regenerate_url(),
			'is_connected'           => false !== $this->helper->get_client(),
			'enable_triggers'        => $this->helper->is_webhook_enabled() ? 'checked' : '',
			'user'                   => $this->helper->get_client_user(),
		);

		if ( 'error' === automator_filter_input( 'status' ) && filter_has_var( INPUT_GET, 'code' ) ) {
			$vars['has_errors']    = true;
			$vars['error_message'] = 'Help Scout has responded with status code: ' . automator_filter_input( 'code' ); // Prefer not to translate err message.
		}

		include_once 'view-helpscout.php';

	}

}
