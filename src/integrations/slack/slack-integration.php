<?php

namespace Uncanny_Automator\Integrations\Slack;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Slack_Integration
 *
 * @package Uncanny_Automator
 */
class Slack_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'SLACK',       // Integration code.
			'name'         => 'Slack',       // Integration name.
			'api_endpoint' => 'v2/slack',    // Automator API server endpoint.
			'settings_id'  => 'slack-api',   // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Slack_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/slack-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		// Load settings page
		new Slack_Settings( $this->dependencies, $this->get_settings_config() );
		// Load actions
		new SLACK_CREATECHANNEL( $this->dependencies );
		new SLACK_ADDUSERTOCHANNEL( $this->dependencies );
		new SLACK_SENDDIRECTMESSAGE( $this->dependencies );
		new SLACK_SENDMESSAGE( $this->dependencies );
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
	public function register_hooks() {
		// Get users handler.
		add_action( 'wp_ajax_automator_slack_get_users', array( $this->helpers, 'get_user_options_ajax' ) );
		// Get channels handler.
		add_action( 'wp_ajax_automator_slack_get_channels', array( $this->helpers, 'get_channel_options_ajax' ) );
		// Get joinable channels handler.
		add_action( 'wp_ajax_automator_slack_get_joinable_channels', array( $this->helpers, 'get_joinable_channel_options_ajax' ) );
	}
}
