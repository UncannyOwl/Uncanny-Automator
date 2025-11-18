<?php

namespace Uncanny_Automator\Integrations\Twitter;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Twitter_Integration
 *
 * @package Uncanny_Automator
 */
class Twitter_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'TWITTER',    // Integration code.
			'name'         => 'X/Twitter',  // Integration name.
			'api_endpoint' => 'v2/twitter', // Automator API server endpoint.
			'settings_id'  => 'twitter-api', // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Twitter_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/x-twitter-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		// Load settings page
		new Twitter_Settings( $this->dependencies, $this->get_settings_config() );

		// Load actions.
		new TWITTER_POSTSTATUS_2( $this->dependencies );

		// Load the deprecated action for legacy support ( will be removed in the future ).
		new TWITTER_POSTSTATUS( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
		} catch ( Exception $e ) {
			return false;
		}

		return ! empty( $credentials );
	}
}
