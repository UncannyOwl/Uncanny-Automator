<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Asana Integration
 */
class Asana_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'ASANA',    // Integration code.
			'name'         => 'Asana',    // Integration name.
			'api_endpoint' => 'v2/asana', // Automator API server endpoint.
			'settings_id'  => 'asana',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Integration Set-up.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Asana_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/asana-icon.svg' );

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
		new Asana_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new ASANA_CREATE_TASK( $this->dependencies );
		new ASANA_UPDATE_TASK( $this->dependencies );
		new ASANA_ADD_COMMENT_TASK( $this->dependencies );
		new ASANA_ADD_TAG_TASK( $this->dependencies );
		new ASANA_REMOVE_TAG_TASK( $this->dependencies );
		new ASANA_GET_TASK_DETAILS( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			return ! empty( $credentials['asana_id'] );
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
		// Recipe options AJAX handlers.
		add_action( 'wp_ajax_automator_asana_get_workspace_options', array( $this->helpers, 'get_workspace_options_ajax' ) );
		add_action( 'wp_ajax_automator_asana_get_project_options', array( $this->helpers, 'get_project_options_ajax' ) );
		add_action( 'wp_ajax_automator_asana_get_task_options', array( $this->helpers, 'get_task_options_ajax' ) );
		add_action( 'wp_ajax_automator_asana_get_tag_options', array( $this->helpers, 'get_tag_options_ajax' ) );
		add_action( 'wp_ajax_automator_asana_get_user_options', array( $this->helpers, 'get_user_options_ajax' ) );
		add_action( 'wp_ajax_automator_asana_get_field_options', array( $this->helpers, 'get_field_options_ajax' ) );
		add_action( 'wp_ajax_automator_asana_get_custom_fields_repeater', array( $this->helpers, 'get_custom_fields_repeater_ajax' ) );

		// Dynamic tokens filter.
		add_filter(
			"automator_action_ASANA_TASK_DETAILS_CODE_tokens_renderable",
			array( ASANA_GET_TASK_DETAILS::class, 'add_dynamic_field_tokens' ),
			20,
			3
		);
	}
}
