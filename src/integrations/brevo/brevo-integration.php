<?php

namespace Uncanny_Automator\Integrations\Brevo;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Brevo_Integration
 *
 * @package Uncanny_Automator
 */
class Brevo_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'BREVO',       // Integration code.
			'name'         => 'Brevo',       // Integration name.
			'api_endpoint' => 'v2/brevo',    // Automator API server endpoint.
			'settings_id'  => 'brevo',       // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Brevo_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/brevo-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load actions.
		new BREVO_ADD_UPDATE_CONTACT( $this->dependencies );
		new BREVO_DELETE_CONTACT( $this->dependencies );
		new BREVO_ADD_CONTACT_TO_LIST( $this->dependencies );
		new BREVO_REMOVE_CONTACT_FROM_LIST( $this->dependencies );

		// Load settings.
		new Brevo_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$account = $this->helpers->get_saved_account_details();
		return ! empty( $account['status'] ) && 'success' === $account['status'];
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Recipe UI ajax endpoints.
		add_action( 'wp_ajax_automator_brevo_get_lists', array( $this->helpers, 'ajax_get_list_options' ) );
		add_action( 'wp_ajax_automator_brevo_get_templates', array( $this->helpers, 'ajax_get_templates' ) );
	}
}
