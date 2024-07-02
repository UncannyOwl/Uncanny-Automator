<?php

namespace Uncanny_Automator\Integrations\Campaign_Monitor;

/**
 * Class Campaign_Monitor_Integration
 *
 * @package Uncanny_Automator
 */
class Campaign_Monitor_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Campaign_Monitor_Helpers();

		$this->set_integration( 'CAMPAIGN_MONITOR' );
		$this->set_name( 'Campaign Monitor' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/campaign-monitor-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'campaignmonitor' ) );
		// Register wp-ajax callbacks and filters.
		$this->register_hooks();

	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {

		new Campaign_Monitor_Settings( $this->helpers );
		new CAMPAIGN_MONITOR_ADD_UPDATE_SUBSCRIBER( $this->helpers );
		new CAMPAIGN_MONITOR_REMOVE_SUBSCRIBER( $this->helpers );

	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Authorization handler.
		add_action( 'wp_ajax_automator_handle_authorization', array( $this->helpers, 'authenticate' ) );
		// Disconnect handler.
		add_action( 'wp_ajax_automator_campaign_monitor_disconnect_account', array( $this->helpers, 'disconnect' ) );
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
