<?php
/**
 * Creates the settings page
 */

namespace Uncanny_Automator\Integrations\Mailchimp;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Uncanny_Automator\Settings\Premium_Integration_Webhook_Settings;

/**
 * Mailchimp_Settings
 *
 * @property Mailchimp_App_Helpers $helpers
 * @property Mailchimp_Api_Caller $api
 * @property Mailchimp_Webhooks $webhooks
 */
class Mailchimp_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;
	use Premium_Integration_Webhook_Settings;

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set OAuth action name for Mailchimp (trait default is 'authorization_request').
		$this->oauth_action = 'mailchimp_authorization_request';

		// Register webhook hooks for cleanup on disconnect.
		$this->register_webhook_hooks();
	}

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array Formatted account information for UI display.
	 */
	protected function get_formatted_account_info() {
		$account_info = $this->helpers->get_account_info();
		$name         = $account_info['name'] ?? '';
		$email        = $account_info['email'] ?? '';

		// Use name if available, fallback to email.
		$display_name = ! empty( $name ) ? $name : $email;
		$display_name = ! empty( $display_name )
			? $display_name
			: esc_html_x( 'Mailchimp User', 'Mailchimp', 'uncanny-automator' );

		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => strtoupper( substr( $display_name, 0, 1 ) ),
			'main_info'      => $display_name,
			'main_info_icon' => true,
			'additional'     => $email,
		);
	}

	/**
	 * Register connected options.
	 *
	 * @return void
	 */
	protected function register_connected_options() {
		$this->register_webhook_options();
	}

	/**
	 * Validate Mailchimp OAuth credentials.
	 *
	 * Mailchimp doesn't use the vault system - credentials are returned directly
	 * from the OAuth flow. Validates required fields are present.
	 *
	 * @param array $credentials The credentials from OAuth response.
	 *
	 * @return array Validated credentials.
	 * @throws \Exception If required credentials are missing.
	 */
	protected function validate_integration_credentials( $credentials ) {
		// Validate access_token is present.
		if ( empty( $credentials['access_token'] ) ) {
			throw new \Exception(
				esc_html_x( 'Invalid OAuth response: missing access token.', 'Mailchimp', 'uncanny-automator' )
			);
		}

		// Validate datacenter is present (required for API calls).
		if ( empty( $credentials['dc'] ) ) {
			throw new \Exception(
				esc_html_x( 'Invalid OAuth response: missing datacenter information.', 'Mailchimp', 'uncanny-automator' )
			);
		}

		return $credentials;
	}

	/**
	 * Before save settings.
	 *
	 * @param array $response Current response.
	 * @param array $data Posted data.
	 *
	 * @return array
	 */
	protected function before_save_settings( $response = array(), $data = array() ) {
		return $this->handle_webhook_status_before_save( $response, $data );
	}

	/**
	 * Cleanup cached data after disconnecting.
	 *
	 * Deletes all Mailchimp cached options by prefix. This ensures complete
	 * cleanup even if lists were deleted in Mailchimp and would otherwise
	 * leave orphaned options in the database.
	 *
	 * @param array $response The current response array.
	 * @param array $data     The posted data.
	 *
	 * @return array Modified response array.
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}uap_options WHERE option_name LIKE %s",
				'automator\_mailchimp\_%'
			)
		);

		return $response;
	}

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {

		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Mailchimp to better engage with your customers. Add contacts, manage tags, and create campaigns based on user activity on your WordPress site, or automatically trigger actions when users are added or tagged in Mailchimp.', 'Mailchimp', 'uncanny-automator' )
		);

		$this->output_available_items();
	}

	/**
	 * Output main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message();
		$this->output_panel_separator();
		$this->output_webhook_settings();
	}

	/**
	 * Output the webhook content.
	 *
	 * @return void
	 */
	public function output_webhook_content() {
		$this->output_webhook_instructions(
			array(
				'sections' => array(
					array(
						'type'    => 'text',
						'content' => sprintf(
							// translators: %1$s is a link to the Knowledge Base article.
							esc_html_x( "Enabling Mailchimp triggers requires setting up a webhook in your Mailchimp account using the URL below. A few steps and you'll be up and running in no time. Visit our %1\$s for simple instructions.", 'Mailchimp', 'uncanny-automator' ),
							$this->get_escaped_link(
								automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/mailchimp-wordpress-triggers/', 'settings', 'mailchimp-triggers-kb_article' ),
								esc_html_x( 'Knowledge Base article', 'Mailchimp', 'uncanny-automator' )
							)
						),
					),
					array(
						'type'   => 'field',
						'config' => array(
							'value'             => $this->webhooks->get_authorized_url(),
							'label'             => esc_attr_x( 'Webhook URL', 'Mailchimp', 'uncanny-automator' ),
							'disabled'          => true,
							'copy-to-clipboard' => true,
						),
					),
					$this->get_webhook_regeneration_button(),
				),
			)
		);
	}
}
