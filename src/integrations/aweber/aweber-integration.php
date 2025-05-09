<?php

namespace Uncanny_Automator\Integrations\Aweber;

/**
 * Class Aweber_Integration
 *
 * @package Uncanny_Automator
 */
class Aweber_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Aweber_Helpers();

		$this->set_integration( 'AWEBER' );
		$this->set_name( 'AWeber' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/aweber-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'aweber' ) );
		// Register wp-ajax callbacks.
		$this->register_hooks();

	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {

		new Aweber_Settings( $this->helpers );
		new AWEBER_SUBSCRIBER_ADD( $this->helpers );
		new AWEBER_SUBSCRIBER_UPDATE( $this->helpers );
		new AWEBER_SUBSCRIBER_TAG_ADD( $this->helpers );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {

		// Authorization handler.
		add_action( 'wp_ajax_automator_aweber_handle_authorization', array( $this->helpers, 'authenticate' ) );
		// Disconnect handler.
		add_action( 'wp_ajax_automator_aweber_disconnect_account', array( $this->helpers, 'disconnect' ) );
		// List of accounts.
		add_action( 'wp_ajax_automator_aweber_accounts_fetch', array( $this->helpers, 'accounts_fetch' ) );
		// List all 'Lists'.
		add_action( 'wp_ajax_automator_aweber_list_fetch', array( $this->helpers, 'lists_fetch' ) );
		// Fetch custom fields.
		add_action( 'wp_ajax_automator_aweber_custom_fields_fetch', array( $this->helpers, 'custom_fields_fetch' ) );

	}

}
