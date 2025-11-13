<?php

namespace Uncanny_Automator\Integrations\Helpscout;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Helpscout_Integration
 *
 * @package Uncanny_Automator
 */
class Helpscout_Integration extends App_Integration {

	/**
	 * Get integration config
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'HELPSCOUT',
			'name'         => 'Help Scout',
			'api_endpoint' => 'v2/helpscout',
			'settings_id'  => 'helpscout',
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Initialize helpers first for App Integration.
		$this->helpers = new Helpscout_App_Helpers( self::get_config() );

		// Set icon.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/help-scout-icon.svg' );

		// Set up the App Integration.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	protected function load() {

		// Initialize settings.
		new Helpscout_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions.
		new Helpscout_Conversation_Create( $this->dependencies );
		new Helpscout_Customer_Properties_Update( $this->dependencies );
		new Helpscout_Conversation_Tag_Add( $this->dependencies );
		new Helpscout_Conversation_Note_Add( $this->dependencies );

		// Triggers.
		new Hs_Note_Added( $this->dependencies );
		new Hs_Conversation_Tag_Updated( $this->dependencies );
		new Hs_Rating_Received( $this->dependencies );
		new Hs_Conversation_Customer_Reply_Received( $this->dependencies );
		new Hs_Conversation_Created( $this->dependencies );
	}

	/**
	 * Check if app is connected
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();
		return ! empty( $credentials ) && is_array( $credentials ) && ! empty( $credentials['access_token'] );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Register AJAX handlers for dynamic fields.
		add_action( 'wp_ajax_helpscout_fetch_conversations', array( $this->helpers, 'ajax_fetch_conversations' ) );
		add_action( 'wp_ajax_automator_helpscout_fetch_mailbox_users', array( $this->helpers, 'ajax_fetch_mailbox_users' ) );
		add_action( 'wp_ajax_automator_helpscout_fetch_properties', array( $this->helpers, 'ajax_fetch_properties' ) );
	}
}
