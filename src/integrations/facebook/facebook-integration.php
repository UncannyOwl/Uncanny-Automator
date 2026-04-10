<?php

namespace Uncanny_Automator\Integrations\Facebook;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Facebook_Integration
 *
 * @package Uncanny_Automator
 */
class Facebook_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'FACEBOOK',       // Integration code.
			'icon'         => 'FACEBOOK',       // Integration icon ID
			'name'         => 'Facebook Pages', // Integration name.
			'api_endpoint' => 'v2/facebook',    // Automator API server endpoint.
			'settings_id'  => 'facebook-pages', // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Facebook_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/facebook-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		// Load migration script for vault migration.
		new Facebook_Credentials_Migration( 'facebook_credentials_to_vault', $this->dependencies->api );

		// Load settings page.
		new Facebook_Settings( $this->dependencies, $this->get_settings_config() );

		// Load actions.
		new FACEBOOK_PAGE_CREATE_DRAFT( $this->dependencies );
		new FACEBOOK_PAGE_PUBLISH_LINK( $this->dependencies );
		new FACEBOOK_PAGE_PUBLISH_POST( $this->dependencies );
		new FACEBOOK_PAGE_PUBLISH_PHOTO( $this->dependencies );
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
		// Get linked pages ajax handler.
		add_action( 'wp_ajax_automator_facebook_get_linked_pages', array( $this->helpers, 'get_linked_pages_ajax' ) );
	}
}
