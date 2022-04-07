<?php
/**
 * Creates the settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Agustin B.
 */

namespace Uncanny_Automator;

/**
 * Slack_Settings
 */
class Slack_Settings {
	/**
	 * This trait defines properties and methods shared across all the
	 * settings pages of Premium Integrations
	 */
	use Settings\Premium_Integrations;

	/**
	 * Creates the settings page
	 */
	public function __construct( $helpers ) {

		$this->helpers = $helpers;

		// Register the tab
		$this->setup_settings();

		// The methods above load even if the tab is not selected
		if ( ! $this->is_current_page_settings() ) {
			return;
		}
	}

	/**
	 * Sets up the properties of the settings page
	 */
	protected function set_properties() {
		
		// Define the ID
		// This should go first
		$this->set_id( 'slack_api' );

		// Set the icon
		// This expects a valid <uo-icon> ID
		// Check the Design Guidelines to see the list of valid IDs
		$this->set_icon( 'slack' );

		// Set the name
		// As this is the brand name, it probably shouldn't be translatable
		$this->set_name( 'Slack' );

		try {
			$this->client = $this->helpers->get_slack_client();
			$this->is_connected = true;
		} catch ( \Exception $e) {
			$this->client = array();
			$this->is_connected = false;
		}

		// Set the status
		// This expects a valid <uo-tab> status
		// Check the Design Guidelines to see the list of valid statuses
		$this->set_status( $this->is_connected ? 'success' : '' );

		// Add settings (optional)
		$this->register_option( 'uap_automator_slack_api_bot_name' );
		$this->register_option( 'uap_automator_alck_api_bot_icon' );

		// Set assets (optional)
		$this->set_css( '/slack/settings/assets/style.css' );
		$this->set_js( '/slack/settings/assets/script.js' );
	}

	/**
	 * Creates the output of the settings page
	 */
	public function output() {

		$slack_user_data = isset( $this->client->team ) ? $this->client->team : (object) array();

		// Get the Slack channel
		$slack_workspace = ! empty( $slack_user_data->name ) ? $slack_user_data->name : '';

		// Get the Slack ID
		$slack_id = ! empty( $slack_user_data->id ) ?  $slack_user_data->id : '';

		// Get the link to connect Slack
		$connect_slack_url = $this->helpers->get_connect_url();

		// Get the link to disconnect Slack
		$disconnect_slack_url = $this->helpers->get_disconnect_url();

		// Check if the user JUST connected the workspace and returned
		// from the Slack connection page
		$user_just_connected_site = automator_filter_input( 'connect' ) === '1';

		// Get the current bot name
		$bot_name = get_option( 'uap_automator_slack_api_bot_name', '' );

		// Get the current bot icon
		$bot_icon = get_option( 'uap_automator_alck_api_bot_icon', '' );

		// Load view
		include_once 'view-slack.php';
	}
}
