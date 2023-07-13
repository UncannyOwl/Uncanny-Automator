<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class Microsoft_Teams_Integration
 *
 * @package Uncanny_Automator
 */
class Microsoft_Teams_Integration extends \Uncanny_Automator\Integration {

	/**
	 *
	 */
	protected function setup() {

		$this->helpers = new Microsoft_Teams_Helpers();

		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_name( 'Microsoft Teams' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/microsoft-teams-icon.svg' );

		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'microsoft-teams' ) );

		$this->register_hooks();
	}

	/**
	 * load
	 *
	 * @return void
	 */
	public function load() {
		new Microsoft_Teams_Settings( $this->helpers );
		new MICROSOFT_TEAMS_CHANNEL_MESSAGE( $this->helpers );
		new MICROSOFT_TEAMS_CREATE_TEAM( $this->helpers );
		new MICROSOFT_TEAMS_CREATE_CHANNEL( $this->helpers );
		new MICROSOFT_TEAMS_SEND_DM( $this->helpers );
	}

	/**
	 * register_hooks
	 *
	 * @return void
	 */
	public function register_hooks() {

		add_action( 'wp_ajax_automator_microsoft_teams_disconnect_user', array( $this->helpers, 'disconnect' ) );
		add_action( 'wp_ajax_automator_microsoft_teams_get_team_members', array( $this->helpers, 'ajax_get_team_members_options' ) );
		add_action( 'wp_ajax_automator_microsoft_teams_get_team_channels', array( $this->helpers, 'ajax_get_team_channels_options' ) );

		add_action( 'rest_api_init', array( $this->helpers, 'rest_api_endpoint' ) );
	}

}
