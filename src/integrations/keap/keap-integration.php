<?php

namespace Uncanny_Automator\Integrations\Keap;

/**
 * Class Keap_Integration
 *
 * @package Uncanny_Automator
 */
class Keap_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Keap_Helpers();

		$this->set_integration( 'KEAP' );
		$this->set_name( 'Keap' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/keap-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'keap' ) );
		// Register wp-ajax callbacks and filters.
		$this->register_hooks();

	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {

		new Keap_Settings( $this->helpers );
		new KEAP_ADD_UPDATE_CONTACT( $this->helpers );
		new KEAP_ADD_TAGS_CONTACT( $this->helpers );
		new KEAP_REMOVE_TAGS_CONTACT( $this->helpers );
		new KEAP_ADD_NOTE_CONTACT( $this->helpers );
		new KEAP_ADD_UPDATE_COMPANY( $this->helpers );

	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Authorization handler.
		add_action( 'wp_ajax_automator_keap_handle_authorization', array( $this->helpers, 'authenticate' ) );
		// Disconnect handler.
		add_action( 'wp_ajax_automator_keap_disconnect_account', array( $this->helpers, 'disconnect' ) );
		// Get Tags handler.
		add_action( 'wp_ajax_automator_keap_get_tags', array( $this->helpers, 'get_tags_ajax' ) );
		// Get Custom Fields handler.
		add_action( 'wp_ajax_automator_keap_get_contact_custom_fields', array( $this->helpers, 'get_contact_custom_fields_repeater_ajax' ) );
		// Get Companies handler.
		add_action( 'wp_ajax_automator_keap_get_companies', array( $this->helpers, 'get_companies_ajax' ) );
		// Get Account Users handler.
		add_action( 'wp_ajax_automator_keap_get_account_users', array( $this->helpers, 'get_account_users_ajax' ) );
		// Get Company Custom Fields handler.
		add_action( 'wp_ajax_automator_keap_get_company_custom_fields', array( $this->helpers, 'get_company_custom_fields_repeater_ajax' ) );
	}

}
