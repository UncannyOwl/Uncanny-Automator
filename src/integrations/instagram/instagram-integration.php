<?php

namespace Uncanny_Automator\Integrations\Instagram;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Instagram_Integration
 *
 * @package Uncanny_Automator
 */
class Instagram_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'INSTAGRAM',   // Integration code.
			'name'         => 'Instagram',   // Integration name.
			'api_endpoint' => 'v2/facebook', // Instagram purposely uses facebook endpoint.
			'settings_id'  => 'instagram',   // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Instagram_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/instagram-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		// Load settings page.
		new Instagram_Settings( $this->dependencies, $this->get_settings_config() );
		// Load actions.
		new INSTAGRAM_PUBLISH_PHOTO( $this->dependencies );
	}

	/**
	 * Check if the app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		return $this->helpers->is_connected();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Register publishing photo retry hooks.
		$this->helpers->register_retry_hooks();
	}
}
