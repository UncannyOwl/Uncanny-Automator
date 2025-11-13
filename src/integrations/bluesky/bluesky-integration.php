<?php

namespace Uncanny_Automator\Integrations\Bluesky;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Bluesky_Integration
 *
 * @package Uncanny_Automator
 */
class Bluesky_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'BLUESKY',     // Integration code.
			'name'         => 'Bluesky',     // Integration name.
			'api_endpoint' => 'v2/bluesky',  // Automator API server endpoint.
			'settings_id'  => 'bluesky',     // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		// Define helpers with common config values.
		$this->helpers = new Bluesky_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/bluesky-icon.svg' );

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
		new Bluesky_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new BLUESKY_CREATE_POST( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$this->helpers->get_credentials();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}
}
