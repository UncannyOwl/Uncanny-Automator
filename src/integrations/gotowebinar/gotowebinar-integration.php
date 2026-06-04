<?php

namespace Uncanny_Automator\Integrations\Gotowebinar;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Gotowebinar_Integration
 *
 * @package Uncanny_Automator
 */
class Gotowebinar_Integration extends App_Integration {

	/**
	 * Get the integration config
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'GTW',
			'name'         => 'GoTo Webinar',
			'api_endpoint' => 'v2/goto',
			'settings_id'  => 'go-to-webinar',
		);
	}

	/**
	 * Setup the integration
	 *
	 * @return void
	 */
	protected function setup() {

		// Create helpers instance with config.
		$this->helpers = new Gotowebinar_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/gotowebinar-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		// Settings.
		new Gotowebinar_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions.
		new GTW_REGISTERUSER( $this->dependencies );
		new GTW_UNREGISTERUSER( $this->dependencies );
	}

	/**
	 * Check if app is connected
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();

		return ! empty( $credentials['access_token'] ) && ! empty( $credentials['organizer_key'] );
	}
}
