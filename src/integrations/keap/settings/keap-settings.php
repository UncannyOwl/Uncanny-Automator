<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Keap;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Exception;

/**
 * Keap_Settings
 *
 * @property Keap_App_Helpers $helpers
 * @property Keap_Api_Caller $api
 */
class Keap_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Custom OAuth parameters for Keap integration.
		$this->oauth_action   = 'authorize';
		$this->redirect_param = 'user_url';
		$this->error_param    = 'error_message';
	}

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		$this->output_legacy_connection_notice();

		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Keap to streamline automations that incorporate contact management, email marketing, and activity on your WordPress site.', 'Keap', 'uncanny-automator' )
		);

		// Output available recipe items (actions/triggers list).
		$this->output_available_items();
	}

	/**
	 * Tell a site whose connection predates the vault (Free < 7.2.3) to reconnect.
	 *
	 * @since 8.0.4
	 *
	 * @return void
	 */
	protected function output_legacy_connection_notice() {

		if ( ! $this->helpers->has_legacy_credentials() ) {
			return;
		}

		$this->alert_html(
			array(
				'type'    => 'warning',
				'heading' => esc_html_x( 'Reconnection required', 'Keap', 'uncanny-automator' ),
				'content' => esc_html_x( 'This site was connected to Keap with an older version of Uncanny Automator, and that connection is no longer supported. Connect your Keap account again to run your Keap actions.', 'Keap', 'uncanny-automator' ),
			)
		);
	}

	/**
	 * Validate integration-specific credentials.
	 *
	 * @param array $credentials
	 * @return array
	 *
	 * @throws Exception
	 */
	protected function validate_integration_credentials( $credentials ) {

		if ( empty( $credentials['vault_signature'] ) || empty( $credentials['keap_id'] ) ) {
			throw new Exception( 'Missing or invalid credentials. Please reconnect your Keap account.' );
		}

		return array(
			'vault_signature' => sanitize_text_field( $credentials['vault_signature'] ),
			'keap_id'         => sanitize_text_field( $credentials['keap_id'] ),
		);
	}

	/**
	 * Authorize account after successful OAuth
	 * This fetches and stores user/account information
	 *
	 * @param mixed $response
	 * @param array $credentials
	 * @return mixed
	 */
	protected function authorize_account( $response, $credentials ) {

		// Store only the vault credentials (without email/sub).
		$this->helpers->store_credentials(
			array(
				'vault_signature' => $credentials['vault_signature'],
				'keap_id'         => $credentials['keap_id'],
			)
		);

		// Fetch and store full account details including contact configuration.
		// This populates phone_types, fax_types, suffix_types, etc. needed by actions.
		$this->helpers->get_account_details( $credentials['keap_id'] );

		return $response;
	}

	/**
	 * Get formatted account info for display
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$account = $this->helpers->get_account_info();

		if ( empty( $account ) || ! is_array( $account ) ) {
			return array();
		}

		$email  = isset( $account['email'] ) && is_string( $account['email'] ) ? $account['email'] : '';
		$app_id = isset( $account['app_id'] ) && is_string( $account['app_id'] ) ? $account['app_id'] : '';

		// Format the additional info to show the app ID.
		$additional_info = '';
		if ( ! empty( $app_id ) ) {
			$additional_info = sprintf(
				// translators: %1$s App ID
				esc_html_x( 'Current App: %1$s', 'Keap', 'uncanny-automator' ),
				$app_id
			);
		}

		return array(
			'avatar_type'  => 'icon',
			'avatar_value' => 'KEAP',
			'main_info'    => $email,
			'additional'   => $additional_info,
		);
	}

	/**
	 * Once the platform confirmed the disconnect (handoff R8): notify the API
	 * proxy to delete the vault. Errors are ignored, so it is safe to run twice.
	 *
	 * @param array $response
	 * @param array $data
	 *
	 * @return array
	 */
	protected function after_platform_disconnect( $response = array(), $data = array() ) {
		try {
			// Request API proxy to remove vault credentials.
			$this->api->api_request( 'disconnect' );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Silently ignore - just a cleanup operation.
		}

		return $response;
	}

	/**
	 * After disconnect hook.
	 * Clean up Keap-specific option data.
	 *
	 * @param array $response
	 * @param array $data
	 *
	 * @return void
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		// Clean up all Keap cached option data.
		$this->delete_option_data( $this->helpers->get_option_prefix() );

		return $response;
	}
}
