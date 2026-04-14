<?php

namespace Uncanny_Automator\Integrations\Ontraport;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Ontraport_Integration
 *
 * @package Uncanny_Automator
 */
class Ontraport_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'ONTRAPORT',
			'name'         => 'Ontraport',
			'api_endpoint' => 'v2/ontraport',
			'settings_id'  => 'ontraport',
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Ontraport_App_Helpers( self::get_config() );
		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/ontraport-icon.svg' );
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
		new Ontraport_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new Ontraport_Upsert_Contact( $this->dependencies );
		new Ontraport_Add_Update_Contact( $this->dependencies );
		new Ontraport_Create_Tag( $this->dependencies );
		new Ontraport_Delete_Contact( $this->dependencies );
		new Ontraport_Add_Contact_Tag( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$this->helpers->get_credentials();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		// List tags handler.
		add_action( 'wp_ajax_automator_ontraport_list_tags', array( $this->helpers, 'ajax_get_tags' ) );
		// Custom fields handler.
		add_action( 'wp_ajax_automator_ontraport_get_custom_fields', array( $this->helpers, 'ajax_get_custom_fields' ) );
	}
}
