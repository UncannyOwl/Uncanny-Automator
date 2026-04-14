<?php

namespace Uncanny_Automator\Integrations\Zoho_Campaigns;

use Exception;

/**
 * Zoho_Campaigns_Integration
 *
 * @package Uncanny_Automator
 *
 * @property Zoho_Campaigns_App_Helpers $helpers
 */
class Zoho_Campaigns_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'ZOHO_CAMPAIGNS',    // Integration code.
			'name'         => 'Zoho Campaigns',    // Integration name.
			'api_endpoint' => 'v2/zoho-campaigns', // Automator API server endpoint.
			'settings_id'  => 'zoho_campaigns',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Integration Set-up.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Zoho_Campaigns_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/zoho-campaigns-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		// Migrate legacy credentials to vault storage.
		new Zoho_Campaigns_Credentials_Migration(
			'zoho_campaigns_credentials_vault_migration',
			$this->dependencies
		);

		// Load settings page.
		new Zoho_Campaigns_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new ZOHO_CAMPAIGNS_CONTACT_LIST_SUB( $this->dependencies );
		new ZOHO_CAMPAIGNS_CONTACT_LIST_UNSUB( $this->dependencies );
		new ZOHO_CAMPAIGNS_LIST_ADD( $this->dependencies );
		new ZOHO_CAMPAIGNS_CONTACT_DONOTMAIL_MOVE( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			return ! empty( $credentials );
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
		// AJAX handlers for dynamic option & repeater fields.
		add_action( 'wp_ajax_automator_zoho_campaigns_get_list_options', array( $this->helpers, 'ajax_get_list_options' ) );
		add_action( 'wp_ajax_automator_zoho_campaigns_get_topic_options', array( $this->helpers, 'ajax_get_topic_options' ) );
		add_action( 'wp_ajax_automator_zoho_campaigns_get_fields_rows', array( $this->helpers, 'ajax_get_fields_rows' ) );
	}
}
