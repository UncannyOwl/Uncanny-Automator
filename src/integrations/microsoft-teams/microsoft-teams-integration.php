<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Microsoft_Teams_Integration
 *
 * @package Uncanny_Automator
 */
class Microsoft_Teams_Integration extends App_Integration {

	/**
	 * The integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'MICROSOFT_TEAMS',
			'name'         => 'Microsoft Teams',
			'api_endpoint' => 'v2/microsoft-teams',
			'settings_id'  => 'microsoft-teams',
		);
	}

	/**
	 * Setup the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Microsoft_Teams_App_Helpers( self::get_config() );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/microsoft-teams-icon.svg' );
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load the integration.
	 *
	 * @return void
	 */
	public function load() {

		// Migrations.
		new Microsoft_Teams_Credentials_Migration( 'microsoft_teams_vault_credentials', $this->dependencies );
		new Microsoft_Teams_Channel_Type_Migration( 'microsoft_teams_channel_type_rename' );

		// Settings.
		new Microsoft_Teams_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions.
		new MICROSOFT_TEAMS_CHANNEL_MESSAGE( $this->dependencies );
		new MICROSOFT_TEAMS_CREATE_TEAM( $this->dependencies );
		new MICROSOFT_TEAMS_CREATE_CHANNEL( $this->dependencies );
		new MICROSOFT_TEAMS_SEND_DM( $this->dependencies );
		new TEAMS_ADD_MEMBER( $this->dependencies );
		new TEAMS_REMOVE_MEMBER( $this->dependencies );
		new TEAMS_CREATE_MEETING( $this->dependencies );
		new TEAMS_REPLY_CHANNEL_MESSAGE( $this->dependencies );
		new TEAMS_CREATE_GROUP_CHAT( $this->dependencies );
		new TEAMS_CREATE_TAG( $this->dependencies );
		new TEAMS_ADD_TAG_MEMBER( $this->dependencies );
		new TEAMS_ARCHIVE_TEAM( $this->dependencies );
		new TEAMS_DELETE_CHANNEL( $this->dependencies );
		new TEAMS_UPDATE_TEAM( $this->dependencies );
	}

	/**
	 * Check if the integration is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			return ! empty( $credentials['vault_signature'] );
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
		add_action( 'wp_ajax_automator_microsoft_teams_get_teams', array( $this->helpers, 'ajax_get_teams_options' ) );
		add_action( 'wp_ajax_automator_microsoft_teams_get_team_members', array( $this->helpers, 'ajax_get_team_members_options' ) );
		add_action( 'wp_ajax_automator_microsoft_teams_get_team_channels', array( $this->helpers, 'ajax_get_team_channels_options' ) );
		add_action( 'wp_ajax_automator_microsoft_teams_get_team_tags', array( $this->helpers, 'ajax_get_team_tags_options' ) );
	}
}
