<?php

namespace Uncanny_Automator\Integrations\Campaign_Monitor;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Campaign_Monitor_Integration
 *
 * @package Uncanny_Automator
 */
class Campaign_Monitor_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'CAMPAIGN_MONITOR',
			'name'         => 'Campaign Monitor',
			'api_endpoint' => 'v2/campaignmonitor',
			'settings_id'  => 'campaignmonitor', // Keep existing settings ID
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		// Define helpers with common config values.
		$this->helpers = new Campaign_Monitor_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/campaign-monitor-icon.svg' );

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
		new Campaign_Monitor_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new CAMPAIGN_MONITOR_ADD_UPDATE_SUBSCRIBER( $this->dependencies );
		new CAMPAIGN_MONITOR_REMOVE_SUBSCRIBER( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			return ! empty( $credentials['access_token'] );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Register hooks for AJAX handlers (field loading only).
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Agency Account Get Clients handler.
		add_action( 'wp_ajax_automator_campaign_monitor_get_clients', array( $this->helpers, 'get_clients_ajax' ) );
		// Get Lists handler.
		add_action( 'wp_ajax_automator_campaign_monitor_get_lists', array( $this->helpers, 'get_lists_ajax' ) );
		// Get Custom Fields handler.
		add_action( 'wp_ajax_automator_campaign_monitor_get_custom_fields', array( $this->helpers, 'get_custom_fields_repeater_ajax' ) );
		// Filter action field values before save.
		add_filter( 'automator_field_values_before_save', array( $this->helpers, 'maybe_save_action_client_meta' ), 20, 2 );
	}
}
