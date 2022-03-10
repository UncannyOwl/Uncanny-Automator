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
	public function __construct() {
		// Register the tab
		$this->setup_settings();

		// The methods above load even if the tab is not selected
		if ( ! $this->is_current_page_settings() ) {
			return;
		}

		// Capture oauth tokens after the redirect from Twitter
		$this->twitter_capture_oauth_tokens();
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

		// Set the status
		// This expects a valid <uo-tab> status
		// Check the Design Guidelines to see the list of valid statuses
		$this->set_status( Twitter_Helpers::get_is_connected() ? 'success' : '' );
	}

	/**
	 * Creates the output of the settings page
	 */
	public function output() {
		// Get data about the connected Twitter
		$twitter_user_data = Twitter_Helpers::get_client();

		// Get the Twitter username
		$twitter_username = ! empty( $twitter_user_data['screen_name'] ) ? $twitter_user_data['screen_name'] : '';

		// Get the Twitter ID
		$twitter_id = ! empty( $twitter_user_data['user_id'] ) ? $twitter_user_data['user_id'] : '';

		// Check if Twitter is connected
		$twitter_is_connected = Twitter_Helpers::get_is_connected();

		// Get the link to connect Twitter
		$connect_twitter_url = $this->twitter_get_connect_url();

		// Get the link to disconnect Twitter
		$disconnect_twitter_url = $this->twitter_get_disconnect_url();

		// Check if the user JUST connected the workspace and returned
		// from the Slack connection page
		$user_just_connected_site = automator_filter_input( 'connect' ) === '1';

		// Load view
		include_once 'view-twitter.php';
	}

	/**
	 * Returns the link to connect to Twitter
	 *
	 * @return string The link to connect the site
	 */
	private function twitter_get_connect_url( $redirect_url = '' ) {
		// Check if there is a custom redirect URL defined, otherwise, use the default one
		$redirect_url = ! empty( $redirect_url ) ? $redirect_url : $this->get_settings_page_url();

		// Define the parameters of the URL
		$parameters = array(
			// Authentication nonce
			'nonce'        => wp_create_nonce( 'automator_twitter_api_authentication' ),

			// Plugin version
			'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,

			// API version
			'api_ver'      => '1.0',

			// Action
			'action'       => 'authorization_request',

			// Redirect URL
			'redirect_url' => rawurlencode( $redirect_url ),
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			Twitter_Helpers::$automator_api
		);
	}

	/**
	 * Returns the link to disconnect Twitter
	 *
	 * @return string The link to disconnect the site
	 */
	private function twitter_get_disconnect_url() {
		// Define the parameters of the URL
		$parameters = array(
			// Parameter used to detect the request
			'disconnect' => '1',
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			$this->get_settings_page_url()
		);
	}

	/**
	 * Captures oauth tokens after the redirect from Twitter
	 */
	private function twitter_capture_oauth_tokens() {
		// Add callback on init
		add_action(
			'init',
			function () {
				// Check if the user is in the premium integrations settings page
				if ( $this->is_current_page_settings() ) {

					// Check if the API returned the tokens
					// If this exists, then we can assume the user is trying to connect his account
					$automator_api_response = automator_filter_input( 'automator_api_message' );

					// Check if the user is rather trying to disconnect his account
					$is_user_disconnecting_account = ! empty( automator_filter_input( 'disconnect' ) );

					// Check if the user is trying to connect his account
					if ( ! empty( $automator_api_response ) ) {
						// Parse the tokens
						$tokens = Automator_Helpers_Recipe::automator_api_decode_message(
							$automator_api_response,
							wp_create_nonce( 'automator_twitter_api_authentication' )
						);

						// Check is the parsed tokens are valid
						if ( $tokens ) {

							// Save them
							update_option( '_uncannyowl_twitter_settings', $tokens );

							// Reload
							wp_safe_redirect(
								add_query_arg(
									array(
										'connect' => 1,
									),
									$this->get_settings_page_url()
								)
							);

							die;

						} else {

							// Reload and add a parameter to catch the error
							wp_safe_redirect(
								add_query_arg(
									array(
										'connect' => 2,
									),
									$this->get_settings_page_url()
								)
							);

							die;

						}
					} elseif ( $is_user_disconnecting_account ) {
						// Delete the saved data
						delete_option( '_uncannyowl_twitter_settings' );

						// Reload the page
						wp_safe_redirect( $this->get_settings_page_url() );

						die;
					}
				}

			}
		);
	}
}

new Twitter_Settings();
