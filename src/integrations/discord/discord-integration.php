<?php

namespace Uncanny_Automator\Integrations\Discord;

/**
 * Class Discord_Integration
 *
 * @package Uncanny_Automator
 */
class Discord_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Spins up new integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Discord_Helpers();

		$this->set_integration( 'DISCORD' );
		$this->set_name( 'Discord' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/discord-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'discord' ) );

		// Register wp-ajax callbacks and filters.
		$this->register_hooks();
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		new Discord_Settings( $this->helpers );
		// Send a message to a channel
		new DISCORD_SEND_MESSAGE_TO_CHANNEL( $this->helpers );
		// Send a direct message to a Discord member
		new DISCORD_SEND_DIRECT_MESSAGE_TO_MEMBER( $this->helpers );
		// Assign a role to a member
		new DISCORD_ASSIGN_ROLE_TO_MEMBER( $this->helpers );
		// Remove a role from a member
		new DISCORD_REMOVE_ROLE_FROM_MEMBER( $this->helpers );
		// Remove a member
		new DISCORD_REMOVE_MEMBER( $this->helpers );
		// Invite a member to a server
		new DISCORD_INVITE_MEMBER_TO_SERVER( $this->helpers );
		// Add a member to a channel
		new DISCORD_ADD_MEMBER_TO_CHANNEL( $this->helpers );
		// Update a member
		new DISCORD_UPDATE_MEMBER( $this->helpers );
		// Create a channel
		new DISCORD_CREATE_CHANNEL( $this->helpers );

		// Add shortcode for individual WP User OAuth Discord -> WP User mapping.
		new Discord_User_Mapping_Shortcode( $this->helpers );

		// Load universal tokens
		new Discord_Universal_Token();
		// Handle migrations.
		new Discord_Member_Encryption_Migration( 'discord_member_encryption_6.7.0', $this->helpers );
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
