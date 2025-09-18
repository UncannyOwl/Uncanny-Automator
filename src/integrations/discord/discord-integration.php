<?php

namespace Uncanny_Automator\Integrations\Discord;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Discord_Integration
 *
 * @package Uncanny_Automator
 */
class Discord_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'DISCORD',    // Integration code.
			'name'         => 'Discord',    // Integration name.
			'api_endpoint' => 'v2/discord', // Automator API server endpoint.
			'settings_id'  => 'discord',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		// Define helpers with common config values.
		$this->helpers = new Discord_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/discord-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {

		new Discord_Settings( $this->dependencies, $this->get_settings_config() );
		// Send a message to a channel
		new DISCORD_SEND_MESSAGE_TO_CHANNEL( $this->dependencies );
		// Send a direct message to a Discord member
		new DISCORD_SEND_DIRECT_MESSAGE_TO_MEMBER( $this->dependencies );
		// Assign a role to a member
		new DISCORD_ASSIGN_ROLE_TO_MEMBER( $this->dependencies );
		// Remove a role from a member
		new DISCORD_REMOVE_ROLE_FROM_MEMBER( $this->dependencies );
		// Remove a member
		new DISCORD_REMOVE_MEMBER( $this->dependencies );
		// Invite a member to a server
		new DISCORD_INVITE_MEMBER_TO_SERVER( $this->dependencies );
		// Add a member to a channel
		new DISCORD_ADD_MEMBER_TO_CHANNEL( $this->dependencies );
		// Update a member
		new DISCORD_UPDATE_MEMBER( $this->dependencies );
		// Create a channel
		new DISCORD_CREATE_CHANNEL( $this->dependencies );

		// Load Discord-specific components only when connected
		$this->maybe_load_connected_components();
	}

	/**
	 * Load Discord-specific components only when the app is connected.
	 *
	 * @return void
	 */
	private function maybe_load_connected_components() {
		if ( ! $this->is_app_connected() ) {
			return;
		}

		// Add shortcode for individual WP User OAuth Discord -> WP User mapping.
		new Discord_User_Mapping_Shortcode( $this->dependencies );

		// Load universal tokens
		new Discord_Universal_Token();

		// Handle migrations.
		new Discord_Member_Encryption_Migration( 'discord_member_encryption_6.7.0', $this->helpers );
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
		// Get servers handler.
		add_action( 'wp_ajax_automator_discord_get_servers', array( $this->helpers, 'get_servers_ajax' ) );
		// Get server channels handler.
		add_action( 'wp_ajax_automator_discord_get_server_channels', array( $this->helpers, 'get_server_channels_ajax' ) );
		// Get verified members handler.
		add_action( 'wp_ajax_automator_discord_get_verified_members', array( $this->helpers, 'get_verified_members_ajax' ) );
		// Get server roles handler.
		add_action( 'wp_ajax_automator_discord_get_server_roles', array( $this->helpers, 'get_server_roles_ajax' ) );
		// Get allowed server channel types handler.
		add_action( 'wp_ajax_automator_discord_get_allowed_channel_types', array( $this->helpers, 'get_allowed_channel_types_ajax' ) );
	}
}
