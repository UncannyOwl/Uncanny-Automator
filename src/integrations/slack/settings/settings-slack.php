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
class Slack_Settings extends Settings\Premium_Integration_Settings {

	protected $client;
	protected $is_connected;

	/**
	 * Sets up the properties of the settings page
	 */
	public function set_properties() {

		// Define the ID
		// This should go first
		$this->set_id( 'slack_api' );

		// Set the icon
		// This expects a valid <uo-icon> ID
		// Check the Design Guidelines to see the list of valid IDs
		$this->set_icon( 'SLACK' );

		// Set the name
		// As this is the brand name, it probably shouldn't be translatable
		$this->set_name( 'Slack' );

		// Add settings (optional)
		$this->register_option( 'uap_automator_slack_api_bot_name' );
		$this->register_option( 'uap_automator_alck_api_bot_icon' );

		// Set assets (optional)
		$this->set_css( '/slack/settings/assets/style.css' );
		$this->set_js( '/slack/settings/assets/script.js' );
	}

	public function get_status() {
		return $this->helpers->integration_status();
	}

	/**
	 * Creates the output of the settings page
	 */
	public function output() {

		try {
			$this->client       = $this->helpers->get_slack_client();
			$this->is_connected = true;
		} catch ( \Exception $e ) {
			$this->client       = array();
			$this->is_connected = false;
		}

		$slack_user_data = isset( $this->client->team ) ? $this->client->team : (object) array();

		// Get the Slack channel
		$slack_workspace = ! empty( $slack_user_data->name ) ? $slack_user_data->name : '';

		// Get the Slack ID
		$slack_id = ! empty( $slack_user_data->id ) ? $slack_user_data->id : '';

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
