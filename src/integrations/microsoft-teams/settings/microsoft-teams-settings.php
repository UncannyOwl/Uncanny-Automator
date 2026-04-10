<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Exception;

/**
 * Class Microsoft_Teams_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class Microsoft_Teams_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array Formatted account information for UI display.
	 */
	protected function get_formatted_account_info() {
		$account_info = $this->helpers->get_account_info();
		if ( empty( $account_info ) ) {
			return array();
		}

		// Get first letter for avatar.
		$avatar_text = '';
		if ( ! empty( $account_info['displayName'] ) ) {
			$avatar_text = strtoupper( substr( $account_info['displayName'], 0, 1 ) );
		}

		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => ! empty( $avatar_text ) ? $avatar_text : 'M',
			'main_info'      => $account_info['displayName'] ?? esc_html_x( 'Microsoft Teams Account', 'Microsoft Teams', 'uncanny-automator' ),
			'main_info_icon' => true,
			'additional'     => ! empty( $account_info['userPrincipalName'] )
				? sprintf(
					// translators: %s: Account email
					esc_html_x( 'Account email: %s', 'Microsoft Teams', 'uncanny-automator' ),
					$account_info['userPrincipalName']
				)
				: '',
		);
	}

	////////////////////////////////////////////////////////////
	// Framework methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate integration-specific credentials from OAuth response.
	 *
	 * Extracts account info (displayName, userPrincipalName) from the credentials
	 * returned by the API proxy and stores it locally, avoiding an extra API call.
	 *
	 * @param array $credentials The credentials from OAuth encrypted message.
	 *
	 * @return array The credentials with account info keys removed (only vault keys stored).
	 */
	protected function validate_integration_credentials( $credentials ) {

		$this->validate_vault_signature( $credentials );

		// Clear the reauth flag if it exists — user has reconnected with updated permissions.
		automator_delete_option( 'automator_microsoft_teams_needs_reauth' );

		// Extract and store account display info from the OAuth response.
		$this->helpers->store_account_info(
			array(
				'microsoft_teams_id' => $credentials['microsoft_teams_id'] ?? '',
				'displayName'        => $credentials['displayName'] ?? '',
				'userPrincipalName'  => $credentials['userPrincipalName'] ?? '',
			)
		);

		// Only persist vault keys as credentials.
		return array(
			'microsoft_teams_id' => $credentials['microsoft_teams_id'] ?? '',
			'vault_signature'    => $credentials['vault_signature'] ?? '',
		);
	}

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header(
			esc_html_x( "Connect Uncanny Automator to Microsoft Teams to send messages, create channels and more when people perform WordPress actions like submitting forms, making purchases and joining groups. Turn Microsoft Teams into a communications hub that's tightly integrated with everything that happens on your WordPress site and beyond.", 'Microsoft Teams', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions.
		$this->output_available_items();
	}

	/**
	 * Output main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message(
			esc_html_x( 'You can only connect to a Microsoft Teams account for which you have read and write access.', 'Microsoft Teams', 'uncanny-automator' )
		);

		// Show a warning if the user needs to reconnect their account.
		if ( automator_get_option( 'automator_microsoft_teams_needs_reauth', false ) ) {
			$this->alert_html(
				array(
					'type'    => 'warning',
					'heading' => esc_html_x( 'Reconnection required', 'Microsoft Teams', 'uncanny-automator' ),
					'content' => esc_html_x( 'Please reconnect your account to grant additional permissions required by newly available actions.', 'Microsoft Teams', 'uncanny-automator' ) . $this->helpers->get_kb_learn_more_link( 'settings' ),
					'button'  => array(
						'action' => 'oauth_init',
						'label'  => esc_html_x( 'Reconnect Microsoft Teams', 'Microsoft Teams', 'uncanny-automator' ),
						'args'   => array( 'color' => 'primary' ),
					),
				)
			);
			$this->output_panel_separator();
		}
	}

	/**
	 * Before disconnect - clean up.
	 *
	 * @param array $response The response array.
	 * @param array $data     The request data.
	 *
	 * @return array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {

		try {
			// Request API proxy to remove vault credentials.
			$this->api->api_request( 'disconnect' );
		} catch ( Exception $e ) {
			// Silently ignore - just a cleanup operation.
			unset( $e );
		}

		return $response;
	}

	/**
	 * After disconnect - clean up.
	 *
	 * @param array $response The response array.
	 * @param array $data     The request data.
	 *
	 * @return array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		$this->delete_option_data( $this->helpers->get_option_prefix() );
		return $response;
	}
}
