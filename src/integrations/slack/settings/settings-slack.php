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
	 * The Slack application scope
	 * 
	 * @var String
	 */
	protected $slack_scope = '';

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

		// Set the Slack scope
		$this->slack_scope = implode(
			',',
			array(
				'channels:read',
				'groups:read',
				'channels:manage',
				'groups:write',
				'chat:write',
				'users:read',
				'chat:write.customize',
			)
		);

		// Capture oauth tokens after the redirect from Slack
		$this->slack_capture_oauth_tokens();
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

		// Set the status
		// This expects a valid <uo-tab> status
		// Check the Design Guidelines to see the list of valid statuses
		$this->set_status( Slack_Helpers::get_is_connected() ? 'success' : '' );

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
		// Get data about the connected Slack
		$slack_user_data = Slack_Helpers::get_slack_client();
		$slack_user_data = isset( $slack_user_data->team ) ? $slack_user_data->team : (object) array();

		// Get the Slack channel
		$slack_workspace = ! empty( $slack_user_data->name ) ? $slack_user_data->name : '';

		// Get the Slack ID
		$slack_id = ! empty( $slack_user_data->id ) ? $slack_user_data->id : '';

		// Check if Slack is connected
		$slack_is_connected = Slack_Helpers::get_is_connected();

		// Get the link to connect Slack
		$connect_slack_url = $this->slack_get_connect_url();

		// Get the link to disconnect Slack
		$disconnect_slack_url = $this->slack_get_disconnect_url();

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

	/**
	 * Returns the link to connect to Slack
	 *
	 * @return string The link to connect the site
	 */
	private function slack_get_connect_url( $redirect_url = '' ) {
		// Check if there is a custom redirect URL defined, otherwise, use the default one
		$redirect_url = ! empty( $redirect_url ) ? $redirect_url : $this->get_settings_page_url();

		// Define the parameters of the URL
		$parameters = array(
			// Authentication nonce
			'nonce'        => wp_create_nonce( 'automator_slack_api_authentication' ),

			// Plugin version
			'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,

			// API version
			'api_ver'      => '1.0',

			// Action
			'action'       => 'slack_authorization_request',

			// Redirect URL
			'redirect_url' => rawurlencode( $redirect_url ),

			// The Slack scope
			'scope'        => $this->slack_scope,
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			Slack_Helpers::$api_integration_url
		);
	}

	/**
	 * Returns the link to disconnect Slack
	 *
	 * @return string The link to disconnect the site
	 */
	private function slack_get_disconnect_url() {
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
	 * Captures oauth tokens after the redirect from Slack
	 */
	private function slack_capture_oauth_tokens() {
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
							wp_create_nonce( 'automator_slack_api_authentication' )
						);

						// Check is the parsed tokens are valid
						if ( $tokens ) {

							// Save them
							update_option( '_uncannyowl_slack_settings', $tokens );

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
						delete_option( '_uncannyowl_slack_settings' );

						// Reload the page
						wp_safe_redirect( $this->get_settings_page_url() );

						die;
					}
				}

			}
		);
	}
}

new Slack_Settings();
