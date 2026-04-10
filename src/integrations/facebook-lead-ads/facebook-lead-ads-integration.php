<?php

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Facebook_Lead_Ads_Integration
 *
 * @package Uncanny_Automator
 */
class Facebook_Lead_Ads_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'FACEBOOK_LEAD_ADS',    // Integration code.
			'name'         => 'Facebook Lead Ads',    // Integration name.
			'api_endpoint' => 'v2/facebook-lead-ads', // Automator API server endpoint.
			'settings_id'  => 'facebook_lead_ads',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {

		// Define helpers with common config values.
		$this->helpers = new Facebook_Lead_Ads_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/facebook-lead-ads-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 */
	public function load() {
		// Load settings page.
		new Facebook_Lead_Ads_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load triggers.
		new Lead_Created( $this->dependencies );
	}

	/**
	 * Is App connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			return $this->helpers->has_connection();
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Register AJAX handlers for recipe builder.
		add_action(
			'wp_ajax_automator_facebook_lead_ads_forms_handler',
			array( $this->helpers, 'forms_handler_ajax' )
		);

		// Register token analysis for form field caching.
		add_action(
			'automator_recipe_before_update',
			array( $this->helpers, 'analyze_tokens' ),
			10,
			2
		);

		// Register legacy endpoints.
		add_action(
			'rest_api_init',
			array( $this->dependencies->webhooks, 'register_legacy_endpoints' ),
			10,
			0
		);
	}
}
