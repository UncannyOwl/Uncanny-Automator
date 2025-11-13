<?php

namespace Uncanny_Automator\Integrations\Constant_Contact;

/**
 * Class Constant_Contact_Integration
 *
 * @package Uncanny_Automator
 */
class Constant_Contact_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'CONSTANT_CONTACT',
			'name'         => 'Constant Contact',
			'api_endpoint' => 'v2/constant-contact',
			'settings_id'  => 'constant-contact',
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Create helpers instance with config.
		$this->helpers = new Constant_Contact_App_Helpers( self::get_config() );

		// Set icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/constant-contact-icon.svg' );

		// Finalize setup.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load settings page.
		new Constant_Contact_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions with dependencies.
		new CREATE_UPDATE_CONTACT( $this->dependencies );
		new CONTACT_LIST_ADD_TO( $this->dependencies );
		new CONTACT_TAG_ADD_TO( $this->dependencies );
		new CONTACT_DELETE( $this->dependencies );

		// Deprecated since Oct 2025
		new CREATE( $this->dependencies );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Register AJAX handlers for field loading only.
		add_action( 'wp_ajax_automator_constant_contact_list_memberships_get', array( $this->helpers, 'list_memberships_get' ) );
		add_action( 'wp_ajax_automator_constant_contact_tags_get', array( $this->helpers, 'tag_list' ) );
		add_action( 'wp_ajax_automator_constant_contact_contact_fields_get', array( $this->helpers, 'contact_contact_fields_get' ) );
		add_action( 'wp_ajax_automator_constant_contact_get_custom_fields_repeater', array( $this->helpers, 'get_custom_fields_repeater' ) );
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
		} catch ( \Exception $e ) {
			return false;
		}
	}
}
