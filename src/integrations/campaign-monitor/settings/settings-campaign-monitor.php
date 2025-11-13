<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Campaign_Monitor;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Campaign_Monitor_Settings
 *
 * @property Campaign_Monitor_App_Helpers $helpers
 * @property Campaign_Monitor_Api_Caller $api
 */
class Campaign_Monitor_Settings extends App_Integration_Settings {

	/**
	 * Use OAuth trait for OAuth functionality.
	 */
	use \Uncanny_Automator\Settings\OAuth_App_Integration;


	/**
	 * Sets up the properties of the settings page.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set the OAuth action for Campaign Monitor.
		// The API server expects 'authorize' for OAuth flow.
		$this->oauth_action = 'authorize';

		// Campaign Monitor API expects 'user_url' instead of 'redirect_url'.
		$this->redirect_param = 'user_url';

		// Error parameter name.
		$this->error_param = 'error';
	}

	/**
	 * Get formatted account info.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$account = $this->helpers->get_account_details();

		if ( is_wp_error( $account ) ) {
			return array();
		}

		$user_info = sprintf(
			/* translators: 1. Primary Contact email */
			esc_html_x( 'Primary Contact: %1$s', 'Campaign Monitor', 'uncanny-automator' ),
			esc_html( $account['email'] )
		);

		$additional_info = '';

		if ( 'client' === $account['type'] ) {
			// Format additional info as HTML with line breaks for multiple items.
			$additional_info = sprintf(
				'%s<br>%s',
				$user_info,
				sprintf(
					// translators: 1. Client ID
					esc_html_x( 'Client ID: %1$s', 'Campaign Monitor', 'uncanny-automator' ),
					esc_html( $account['client']['value'] )
				)
			);

			// Set Client name as main info.
			$user_info = $account['client']['text'];
		}

		return array(
			'avatar_type'    => 'icon',
			'avatar_value'   => $this->get_icon(),
			'main_info'      => $user_info,
			'main_info_icon' => false,
			'additional'     => $additional_info,
		);
	}

	/**
	 * Output main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message(
			esc_html_x( 'Agency accounts with multiple clients may select their specific client within actions.', 'Campaign Monitor', 'uncanny-automator' )
		);
	}

	/**
	 * Validate integration credentials.
	 *
	 * @param array $credentials
	 * @return array
	 * @throws \Exception
	 */
	protected function validate_integration_credentials( $credentials ) {
		// Campaign Monitor doesn't have vault_signature like other integrations.
		if ( empty( $credentials['access_token'] ) ) {
			throw new Exception(
				esc_html_x( 'Missing access token in credentials', 'Campaign Monitor', 'uncanny-automator' )
			);
		}
		return $credentials;
	}

	/**
	 * Authorize account.
	 *
	 * @param array $response
	 * @param mixed $credentials
	 * @return array
	 * @throws Exception
	 */
	protected function authorize_account( $response, $credentials ) {
		// Validate and fetch account info.
		$account_info = $this->helpers->get_account_details();

		if ( is_wp_error( $account_info ) ) {
			throw new Exception( esc_html( $account_info->get_error_message() ) );
		}

		// Return response unchanged for OAuth flow.
		return $response;
	}

	/**
	 * Before disconnect.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		// Clear transients.
		$this->helpers->clear_transients();

		return $response;
	}
}
