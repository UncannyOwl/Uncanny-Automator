<?php

namespace Uncanny_Automator\Integrations\Zoom_Webinar;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Zoom_Webinar_Integration
 *
 * @package Uncanny_Automator
 */
class Zoom_Webinar_Integration extends App_Integration {

	/**
	 * Get integration configuration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'ZOOMWEBINAR',
			'name'         => 'Zoom Webinar',
			'api_endpoint' => 'v2/zoom',
			'settings_id'  => 'zoom-webinar-api',
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Create helpers instance.
		$this->helpers = new Zoom_Webinar_App_Helpers( self::get_config() );

		// Set icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/zoom-icon.svg' );

		// Finalize setup.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load actions.
		new ZOOM_WEBINAR_CREATEWEBINAR( $this->dependencies );
		new ZOOM_WEBINAR_REGISTERUSER( $this->dependencies );
		new ZOOM_WEBINAR_REGISTERUSERLESS( $this->dependencies );
		new ZOOM_WEBINAR_UNREGISTERUSER( $this->dependencies );
		new ZOOM_WEBINAR_UNREGISTERUSERLESS( $this->dependencies );

		// Load settings.
		new Zoom_Webinar_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);
	}

	/**
	 * Check if Zoom Webinar is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$client = automator_get_option( '_uncannyowl_zoom_webinar_settings', array() );
		$user   = automator_get_option( 'uap_zoom_webinar_api_connected_user', '' );

		return ! empty( $client['access_token'] ) && ! empty( $user );
	}

	/**
	 * Register integration-specific hooks.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		// Recipe options AJAX handlers
		add_action( 'wp_ajax_uap_zoom_webinar_api_get_webinar_questions', array( $this->helpers, 'ajax_get_meeting_questions_repeater' ) );
		add_action( 'wp_ajax_uap_zoom_webinar_api_get_webinars', array( $this->helpers, 'ajax_get_webinars' ), 10 );
		add_action( 'wp_ajax_uap_zoom_webinar_api_get_webinar_occurrences', array( $this->helpers, 'ajax_get_webinar_occurrences' ), 10 );
		add_action( 'wp_ajax_uap_zoom_webinar_api_get_account_users', array( $this->helpers, 'ajax_get_account_users' ), 10 );
	}
}
