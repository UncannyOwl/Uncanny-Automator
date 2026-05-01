<?php

namespace Uncanny_Automator\Integrations\Keap;

/**
 * Class Keap_Integration
 *
 * @package Uncanny_Automator
 */
class Keap_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Define configuration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'KEAP',
			'name'         => 'Keap',
			'api_endpoint' => 'v2/keap',
			'settings_id'  => 'keap',
		);
	}

	/**
	 * Setup integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$config = self::get_config();

		// Create helpers with config.
		$this->helpers = new Keap_App_Helpers( $config );

		// Set icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/keap-icon.svg' );

		// Setup app integration with same config.
		$this->setup_app_integration( $config );
	}

	/**
	 * Load components
	 *
	 * @return void
	 */
	public function load() {
		// Settings page.
		new Keap_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions.
		new KEAP_ADD_UPDATE_CONTACT( $this->dependencies );
		new KEAP_ADD_TAGS_CONTACT( $this->dependencies );
		new KEAP_REMOVE_TAGS_CONTACT( $this->dependencies );
		new KEAP_ADD_NOTE_CONTACT( $this->dependencies );
		new KEAP_ADD_UPDATE_COMPANY( $this->dependencies );

		// Migration.
		new Keap_Credentials_Migration( 'keap_credentials_migration', $this->dependencies );
	}

	/**
	 * Check if the app is connected
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();

		return ! empty( $credentials['vault_signature'] ) && ! empty( $credentials['keap_id'] );
	}

	/**
	 * Register hooks for UI AJAX handlers
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_keap_get_tags', array( $this->helpers, 'get_tags_ajax' ) );
		add_action( 'wp_ajax_automator_keap_get_contact_custom_fields', array( $this->helpers, 'get_contact_custom_fields_repeater_ajax' ) );
		add_action( 'wp_ajax_automator_keap_get_companies', array( $this->helpers, 'get_companies_ajax' ) );
		add_action( 'wp_ajax_automator_keap_get_account_users', array( $this->helpers, 'get_account_users_ajax' ) );
		add_action( 'wp_ajax_automator_keap_get_company_custom_fields', array( $this->helpers, 'get_company_custom_fields_repeater_ajax' ) );
	}
}
