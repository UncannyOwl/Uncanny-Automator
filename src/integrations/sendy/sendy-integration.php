<?php

namespace Uncanny_Automator\Integrations\Sendy;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Sendy_Integration
 *
 * @package Uncanny_Automator
 */
class Sendy_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'SENDY',    // Integration code.
			'name'         => 'Sendy',    // Integration name.
			'api_endpoint' => 'v2/sendy', // Automator API server endpoint.
			'settings_id'  => 'sendy',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Sendy_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sendy-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load settings.
		new Sendy_Settings( $this->dependencies, $this->get_settings_config() );
		// Load actions.
		new SENDY_ADD_UPDATE_LIST_CONTACT( $this->dependencies );
		new SENDY_UNSUBSCRIBE_LIST_CONTACT( $this->dependencies );
		new SENDY_DELETE_LIST_CONTACT( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();
		// User entered credentials.
		$api_key = $credentials['api_key'] ?? '';
		$url     = $credentials['url'] ?? '';
		// API connection status.
		$status = $credentials['status'] ?? false;
		// Return true if all credentials are set and the API connection is successful.
		return ! empty( $api_key ) && ! empty( $url ) && $status;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Register recipe UI data AJAX actions.
		add_action( 'wp_ajax_automator_sendy_get_lists', array( $this->helpers, 'ajax_get_list_options' ) );
	}
}
