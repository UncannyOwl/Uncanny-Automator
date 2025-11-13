<?php

namespace Uncanny_Automator\Integrations\Get_Response;

/**
 * Class Get_Response_Integration
 *
 * @package Uncanny_Automator
 */
class Get_Response_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'GETRESPONSE',
			'name'         => 'GetResponse',
			'api_endpoint' => 'v2/getresponse',
			'settings_id'  => 'getresponse',
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		// Define helpers with common config values.
		$this->helpers = new Get_Response_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/getresponse-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		// Load settings page.
		new Get_Response_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new GET_RESPONSE_ADD_UPDATE_CONTACT( $this->dependencies );
		new GET_RESPONSE_DELETE_CONTACT( $this->dependencies );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Recipe action hooks - only register methods that still exist
		add_action( 'wp_ajax_automator_getresponse_get_lists', array( $this->helpers, 'ajax_get_list_options' ) );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		// Validate API key.
		$api_key = $this->helpers->get_credentials();
		if ( ! empty( $api_key ) ) {
			// Validate account status.
			$account = $this->helpers->get_account_info();
			if ( ! empty( $account['status'] ) ) {
				return true;
			}
		}
		return false;
	}
}
