<?php

namespace Uncanny_Automator\Integrations\Gotomeeting;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Gotomeeting_Integration
 *
 * @package Uncanny_Automator
 */
class Gotomeeting_Integration extends App_Integration {

	/**
	 * Get the integration config
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'GTM',
			'name'         => 'GoTo Meeting',
			'api_endpoint' => 'v2/goto',
			'settings_id'  => 'go-to-meeting',
		);
	}

	/**
	 * Setup the integration
	 *
	 * @return void
	 */
	protected function setup() {

		// Create helpers instance with config.
		$this->helpers = new Gotomeeting_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/goto-icon.svg' );

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
		new Gotomeeting_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions.
		new GTM_CREATEMEETING( $this->dependencies );
		new GTM_DELETEMEETING( $this->dependencies );
	}

	/**
	 * Check if app is connected
	 *
	 * GoToMeeting V1 endpoints are scoped by the access token alone — there
	 * is no organizerKey path segment (unlike Training/Webinar), so a valid
	 * access token is the only connection requirement.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();

		return ! empty( $credentials['access_token'] );
	}
}
