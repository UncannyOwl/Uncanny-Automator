<?php

namespace Uncanny_Automator\Integrations\Helpscout;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Uncanny_Automator\Settings\Premium_Integration_Webhook_Settings;
use Exception;

/**
 * Class Helpscout_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Helpscout_App_Helpers $helpers
 * @property Helpscout_Api_Caller $api
 * @property Helpscout_Webhooks $webhooks
 */
class Helpscout_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;
	use Premium_Integration_Webhook_Settings;

	/**
	 * Set properties
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set custom OAuth parameters for Help Scout.
		$this->oauth_action   = 'authorization_request';
		$this->redirect_param = 'user_url';  // Help Scout API expects 'user_url'
		$this->error_param    = 'error';
	}

	/**
	 * Register connected options
	 *
	 * @return void
	 */
	protected function register_connected_options() {
		// Register webhook-related options.
		$this->register_webhook_options();
	}

	/**
	 * Get formatted account information
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$user  = $this->helpers->get_account_info();
		$first = $user['firstName'] ?? '';
		$last  = $user['lastName'] ?? '';
		$email = $user['email'] ?? '';

		// Get first letter of firstName for avatar (matching original Help Scout implementation)
		$avatar_text = '';
		if ( ! empty( $first ) ) {
			$avatar_text = strtoupper( substr( $first, 0, 1 ) );
		} elseif ( ! empty( $email ) ) {
			// Fallback to email first letter if no firstName
			$avatar_text = strtoupper( substr( $email, 0, 1 ) );
		}

		return array(
			'avatar_type'  => 'text',
			'avatar_value' => $avatar_text,
			'main_info'    => sprintf( '%s %s', $first, $last ),
			'additional'   => $email ?? '',
		);
	}

	/**
	 * Output main connected content
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		// Output standard single account message.
		$this->output_single_account_message();
		// Output webhook settings with switch and conditional content.
		$this->output_webhook_settings();
	}

	/**
	 * Output webhook content.
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
							// translators: %s: Knowledge Base article link
							esc_html_x(
								"Enabling Help Scout triggers requires setting up a webhook in your Help Scout account using the URL below. A few steps and you'll be up and running in no time. Visit our %s for simple instructions.",
								'Help Scout',
								'uncanny-automator'
							),
							$this->get_escaped_link(
								automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/helpscout/', 'settings', 'helpscout-kb_article' ),
								esc_html_x( 'Knowledge Base article', 'Help Scout', 'uncanny-automator' )
							)
						),
					),
					array(
						'type'   => 'field',
						'config' => array(
							'id'                => $this->webhooks->get_webhook_key_option_name(),
							'value'             => $this->webhooks->get_webhook_key(),
							'label'             => esc_attr_x( 'Secret key', 'Help Scout', 'uncanny-automator' ),
							'helper'            => esc_html_x( "You'll be asked to enter a secret key.", 'Help Scout', 'uncanny-automator' ),
							'disabled'          => true,
							'copy-to-clipboard' => true,
							'class'             => 'uap-spacing-bottom',
						),
					),
					array(
						'type'   => 'field',
						'config' => array(
							'value'             => $this->webhooks->get_webhook_url(),
							'label'             => esc_attr_x( 'Callback URL', 'Help Scout', 'uncanny-automator' ),
							'helper'            => esc_html_x( "You'll be asked to enter a webhook URL.", 'Help Scout', 'uncanny-automator' ),
							'disabled'          => true,
							'copy-to-clipboard' => true,
						),
					),
					$this->get_webhook_regeneration_button(),
				),
			)
		);
	}

	/**
	 * Validate integration credentials
	 * Override to handle Help Scout specific validation
	 *
	 * @param array $credentials
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function validate_integration_credentials( $credentials ) {
		// Help Scout might not have vault_signature.
		if ( empty( $credentials['access_token'] ) ) {
			throw new Exception(
				esc_html_x( 'Missing access token in credentials', 'Help Scout', 'uncanny-automator' )
			);
		}
		return $credentials;
	}

	/**
	 * Authorize account after OAuth
	 * Fetch user info and store with credentials

	 * @param array $response - response.
	 * @param array $credentials - saved credentials.
	 *
	 * @return array Modified response
	 */
	protected function authorize_account( $response, $credentials ) {
		try {
			// Include timeout and exclude error check.
			$args = array(
				'include_timeout'     => 15,
				'exclude_error_check' => true,
			);

			// Get connected user info.
			$api_response = $this->api->api_request( 'get_resource_owner', null, $args );

			if ( 200 === $api_response['statusCode'] && ! empty( $api_response['data'] ) ) {
				// Add user info to credentials and save again.
				$credentials         = $this->helpers->get_credentials();
				$credentials['user'] = $api_response['data'];
				$this->helpers->store_credentials( $credentials );
			}
		} catch ( Exception $e ) {
			// Delete all registered options and return error.
			$this->delete_all_registered_options();
			throw new Exception(
				sprintf(
					// translators: %s: error message
					esc_html_x( 'Error authorizing account: %s', 'Help Scout', 'uncanny-automator' ),
					esc_html( $e->getMessage() )
				)
			);
		}

		return $response;
	}

	/**
	 * Before disconnect
	 *
	 * @param array $response Current response
	 * @param array $data Posted data
	 *
	 * @return array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		// Delete any transients.
		$mail_const = $this->helpers->get_const( 'TRANSIENT_MAILBOXES' );
		delete_transient( "{$mail_const}_any" );
		delete_transient( "{$mail_const}_not_any" );

		return $response;
	}

	/**
	 * Before save settings
	 *
	 * @param array $response Current response
	 * @param array $data Posted data
	 *
	 * @return array
	 */
	protected function before_save_settings( $response = array(), $data = array() ) {
		return $this->handle_webhook_status_before_save( $response, $data );
	}
}
