<?php

namespace Uncanny_Automator\Integrations\Zoom;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Zoom_Integration
 *
 * @package Uncanny_Automator
 * @property Zoom_App_Helpers $helpers
 * @property Zoom_Api_Caller $api
 */
class Zoom_Integration extends App_Integration {

	/**
	 * Get integration configuration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'ZOOM',
			'name'         => 'Zoom Meetings',
			'api_endpoint' => 'v2/zoom',
			'settings_id'  => 'zoom-api',
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Create helpers instance.
		$this->helpers = new Zoom_App_Helpers( self::get_config() );

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
		// Actions with dependencies.
		new ZOOM_CREATEMEETING( $this->dependencies );
		new ZOOM_CREATERECURRINGMEETING( $this->dependencies );
		new ZOOM_REGISTERUSER( $this->dependencies );
		new ZOOM_REGISTERUSERLESS( $this->dependencies );
		new ZOOM_UNREGISTERUSER( $this->dependencies );
		new ZOOM_UNREGISTERUSERLESS( $this->dependencies );

		// Load settings with dependencies.
		new Zoom_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);
	}

	/**
	 * Check if Zoom is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$zoom_client = automator_get_option( '_uncannyowl_zoom_settings', array() );
		$user        = automator_get_option( 'uap_zoom_api_connected_user', '' );

		return ! empty( $zoom_client['access_token'] ) && ! empty( $user );
	}

	/**
	 * Register integration-specific hooks.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		// Recipe options AJAX handlers.
		add_action( 'wp_ajax_uap_zoom_meetings_api_get_meeting_questions', array( $this->helpers, 'ajax_get_meeting_questions_repeater' ) );
		add_action( 'wp_ajax_uap_zoom_meetings_api_get_meetings', array( $this->helpers, 'ajax_get_meetings' ), 10 );
		add_action( 'wp_ajax_uap_zoom_meetings_api_get_meeting_occurrences', array( $this->helpers, 'ajax_get_meeting_occurrences' ), 10 );
		add_action( 'wp_ajax_uap_zoom_meetings_api_get_account_users', array( $this->helpers, 'ajax_get_account_users' ), 10 );
	}
}
