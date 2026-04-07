<?php

namespace Uncanny_Automator\Integrations\Linkedin;

use Exception;

/**
 * Linkedin Integration
 *
 * @package Uncanny_Automator
 *
 * @property Linkedin_App_Helpers $helpers
 */
class Linkedin_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'LINKEDIN',
			'name'         => 'LinkedIn Pages',
			'api_endpoint' => 'v2/linkedin',
			'settings_id'  => 'linkedin',
		);
	}

	/**
	 * Integration Set-up.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Linkedin_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/linkedin-icon.svg' );

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
		new Linkedin_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new LINKEDIN_POST_PUBLISH( $this->dependencies );
		new LINKEDIN_POST_PUBLISH_IMAGE( $this->dependencies );
		new LINKEDIN_POST_SCHEDULE( $this->dependencies );
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
		// Recipe options AJAX handler.
		add_action( 'wp_ajax_automator_linkedin_get_pages', array( $this->helpers, 'get_pages_ajax' ) );

		// Refresh token expiration notice.
		add_action( 'admin_init', array( $this->helpers, 'check_refresh_token_expiration' ) );

		// Scheduled post hooks (publish, unschedule).
		$scheduled_posts = new Linkedin_Scheduled_Posts_Manager( $this->helpers, $this->dependencies->api );
		$scheduled_posts->register_hooks();
	}
}
