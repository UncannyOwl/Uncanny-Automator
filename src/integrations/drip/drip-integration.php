<?php

namespace Uncanny_Automator\Integrations\Drip;

/**
 * Drip Integration
 *
 * @package Uncanny_Automator
 */
class Drip_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	////////////////////////////////////////////////////////////
	// Abstract method implementations
	////////////////////////////////////////////////////////////

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'DRIP',
			'name'         => 'Drip',
			'api_endpoint' => 'v2/drip',
			'settings_id'  => 'drip',
		);
	}

	/**
	 * Integration Set-up.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Drip_App_Helpers( self::get_config() );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/drip-icon.svg' );
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Settings.
		new Drip_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Actions.
		new DRIP_CREATE_SUBSCRIBER( $this->dependencies );
		new DRIP_ADD_TAG( $this->dependencies );
		new DRIP_REMOVE_TAG( $this->dependencies );
		new DRIP_UNSUBSCRIBE_ALL( $this->dependencies );
		new DRIP_DELETE_SUBSCRIBER( $this->dependencies );
		new DRIP_SUBSCRIBE_TO_CAMPAIGN( $this->dependencies );
		new DRIP_REMOVE_FROM_CAMPAIGN( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		return ! empty( $this->helpers->get_credentials() );
	}

	////////////////////////////////////////////////////////////
	// AJAX hook registration
	////////////////////////////////////////////////////////////

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_drip_get_tags_options', array( $this->helpers, 'get_tags_options_ajax' ) );
		add_action( 'wp_ajax_automator_drip_get_campaigns_options', array( $this->helpers, 'get_campaigns_options_ajax' ) );
		add_action( 'wp_ajax_automator_drip_get_campaigns_with_unsubscribe_options', array( $this->helpers, 'get_campaigns_with_unsubscribe_options_ajax' ) );
		add_action( 'wp_ajax_automator_drip_custom_fields_handler', array( $this->helpers, 'get_custom_fields_handler_ajax' ) );
	}
}
