<?php

namespace Uncanny_Automator\Integrations\Bitly;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Bitly_Integration
 *
 * @package Uncanny_Automator
 */
class Bitly_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'BITLY',       // Integration code.
			'name'         => 'Bitly',       // Integration name.
			'api_endpoint' => 'v2/bitly',    // Automator API server endpoint.
			'settings_id'  => 'bitly',       // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		// Define helpers with common config values.
		$this->helpers = new Bitly_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/bitly-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		// Load settings.
		new Bitly_Settings( $this->dependencies, $this->get_settings_config() );
		// Load actions.
		new BITLY_SHORTEN_URL( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$account = $this->helpers->get_account_info();
		return isset( $account['login'] ) && ! empty( $account['login'] );
	}
}
