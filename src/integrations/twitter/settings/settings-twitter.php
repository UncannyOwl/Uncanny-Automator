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

class Twitter_Settings {
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
	private function set_properties() {
		// Define the ID
		// This should go first
		$this->set_id( 'twitter-api' );

		// Set the icon
		// This expects a valid <uo-icon> ID
		// Check the Design Guidelines to see the list of valid IDs
		$this->set_icon( 'twitter' );

		// Set the name
		// As this is the brand name, it probably shouldn't be translatable
		$this->set_name( 'Twitter' );

		try {
			$this->client = $this->helpers->get_client();
			$this->is_connected = true;
		} catch ( \Exception $th ) {
			$this->client = false;
			$this->is_connected = false;
		}

		// Set the status
		// This expects a valid <uo-tab> status
		// Check the Design Guidelines to see the list of valid statuses
		$this->set_status( $this->is_connected ? 'success' : '' );
	}

	/**
	 * Creates the output of the settings page
	 */
	public function output() {

		// Get the Twitter username
		$twitter_username = ! empty( $this->client['screen_name'] ) ? $this->client['screen_name'] : '';

		// Get the Twitter ID
		$twitter_id = ! empty( $this->client['user_id'] ) ? $this->client['user_id'] : '';

		// Get the link to connect Twitter
		$connect_twitter_url = $this->helpers->get_connect_url();

		// Get the link to disconnect Twitter
		$disconnect_twitter_url = $this->helpers->get_disconnect_url();

		// Check if the user JUST connected the workspace and returned
		// from the Slack connection page
		$user_just_connected_site = automator_filter_input( 'connect' ) === '1';

		// Load view
		include_once 'view-twitter.php';
	}	
}


