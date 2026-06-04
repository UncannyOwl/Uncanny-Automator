<?php

namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Class WhatsApp_Settings
 *
 * @package Uncanny_Automator
 * @property WhatsApp_Helpers $helpers
 * @property WhatsApp_Api_Caller $api
 * @property WhatsApp_Webhooks $webhooks
 */
class WhatsApp_Settings extends App_Integration_Settings {

	/**
	 * Whether to revalidate the account after saving settings.
	 *
	 * @var bool
	 */
	private $revalidate_account = false;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		// Get the account data via framework method.
		$account = $this->helpers->get_account_info();
		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => strtoupper( $account['application'][0] ),
			'main_info'      => $account['application'],
			'main_info_icon' => true,
			'additional'     => sprintf(
				// translators: %1$s is the ID
				esc_html_x( 'ID: %1$s', 'WhatsApp', 'uncanny-automator' ),
				esc_html( $account['app_id'] )
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Register the uap_options for the WhatsApp integration.
	 *
	 * @return void
	 */
	public function register_options() {
		$this->register_option( $this->helpers->get_const( 'PHONE_ID' ) );
		$this->register_option( $this->helpers->get_const( 'BUSINESS_ID' ) );
	}

	/**
	 * Register the options for the disconnected state.
	 *
	 * @return void
	 */
	public function register_disconnected_options() {
		$this->register_option(
			$this->helpers->get_credentials_option_name(),
			array(
				'sanitize_callback' => array( $this, 'handle_token_validation' ),
			)
		);
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate the access token before save.
	 *
	 * @param string $token The access token to validate.
	 *
	 * @return string The validated access token.
	 */
	public function handle_token_validation( $token ) {
		try {
			// Verify the token and store account info via framework.
			$response = $this->api->verify_token( $token );
			$this->helpers->store_account_info( $response );

			// Get the account info.
			$account = $this->helpers->get_account_info();

			$this->register_connected_alert(
				sprintf(
					// translators: 1. The name of the WhatsApp Business Account
					esc_html_x( 'Your account "%s" has been connected successfully!', 'WhatsApp', 'uncanny-automator' ),
					$account['application']
				),
			);

		} catch ( Exception $e ) {
			// Register error for refresh.
			$this->register_error_alert(
				sprintf(
					// translators: %1$s is the error message.
					esc_html_x( 'Authentication error: %1$s', 'WhatsApp', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}

		return $token;
	}

	/**
	 * Before save settings
	 * - revalidate account if the business or phone ID has changed.
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function before_save_settings( $response = array(), $data = array() ) {
		// Retrieve posted values.
		$posted_business_id = (string) $this->get_data_option( $this->helpers->get_const( 'BUSINESS_ID' ), $data );
		$posted_phone_id    = (string) $this->get_data_option( $this->helpers->get_const( 'PHONE_ID' ), $data );

		// Check if posted values differ from current values.
		$business_id_changed = $posted_business_id !== (string) $this->helpers->get_business_account_id();
		$phone_id_changed    = $posted_phone_id !== (string) $this->helpers->get_phone_number_id();

		// Set the revalidate account flag if either value changed.
		if ( $business_id_changed || $phone_id_changed ) {
			$this->revalidate_account = true;
		}

		return $response;
	}

	/**
	 * Validate Phone and Business ID after resaving settings.
	 *
	 * @param array $response - The current response array
	 * @param array $options - The options that were saved.
	 *
	 * @return array
	 */
	protected function after_save_settings( $response = array(), $options = array() ) {

		if ( ! $this->revalidate_account ) {
			$response['reload'] = false;
			$response['alert']  = $this->get_info_alert(
				esc_html_x( 'No changes were made to the connected WhatsApp account.', 'WhatsApp', 'uncanny-automator' ),
			);
			return $response;
		}

		// Reset the revalidate account flag.
		$this->revalidate_account = false;

		try {
			$this->api->revalidate_account();
			$this->register_success_alert(
				esc_html_x( 'WhatsApp account re-verified successfully!', 'WhatsApp', 'uncanny-automator' )
			);
		} catch ( Exception $e ) {
			$this->register_error_alert(
				sprintf(
					// translators: %1$s is the error message.
					esc_html_x( 'Error verifying account: %1$s', 'WhatsApp', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}

		return $response;
	}

	/**
	 * Handle verify token regeneration.
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function handle_regenerate_webhook_url( $response = array(), $data = array() ) {
		$this->webhooks->regenerate_webhook_key();
		$this->register_success_alert(
			esc_html_x( 'Webhook URL regenerated successfully! Please update the URL in your WhatsApp webhook configuration.', 'WhatsApp', 'uncanny-automator' )
		);

		$response['reload'] = true;
		return $response;
	}

	/**
	 * Handle access token update.
	 *
	 * Allows updating the access token without disconnecting, preserving
	 * Phone ID, Business ID, and webhook configuration.
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function handle_update_access_token( $response = array(), $data = array() ) {
		$response['reload'] = false;

		$new_token = sanitize_text_field( $data['new_access_token'] ?? '' );
		if ( empty( $new_token ) ) {
			$response['alert'] = $this->get_error_alert(
				esc_html_x( 'Please provide a new access token.', 'WhatsApp', 'uncanny-automator' )
			);
			return $response;
		}

		try {
			// Verify the new token (also checks for missing scopes).
			$token_response = $this->api->verify_token( $new_token );

			// Store the new credentials and account info.
			$this->helpers->store_credentials( $new_token );
			$this->helpers->store_account_info( $token_response );

			// Revalidate the account with existing Phone ID and Business ID.
			$this->api->revalidate_account();

			$response['alert'] = $this->get_success_alert(
				esc_html_x( 'Access token updated successfully!', 'WhatsApp', 'uncanny-automator' )
			);

		} catch ( Exception $e ) {
			$response['alert'] = $this->get_error_alert(
				sprintf(
					// translators: %s is the error message.
					esc_html_x( 'Failed to update access token: %s', 'WhatsApp', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}

		return $response;
	}

	/**
	 * After disconnect
	 *
	 * @param array $response - The current response array
	 * @param array $data - The posted data
	 *
	 * @return array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		// Delete all cached option data for this integration (templates, dropdowns, etc.).
		$this->delete_option_data( $this->helpers->get_option_prefix() );

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Abstract Templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void - Outputs the generated HTML.
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected header with description
		$this->output_disconnected_header(
			esc_html_x( 'Integrate your WordPress site directly with WhatsApp. Send WhatsApp messages to users when they make a purchase, fill out a form, complete a course, or complete any combination of supported triggers.', 'WhatsApp', 'uncanny-automator' )
		);

		// Output available recipe items.
		$this->output_available_items();

		// Add separator.
		$this->output_panel_separator();

		// Output the setup instructions
		$this->output_setup_instructions(
			sprintf(
				// translators: %1$s is a link to the WhatsApp knowledge base article
				esc_html_x( 'To connect to WhatsApp, you need to create a business Meta application and get 3 values from your account. %1$s for detailed instructions.', 'WhatsApp', 'uncanny-automator' ),
				$this->get_kb_link( 'section-access-token', esc_html_x( 'Visit our Knowledge Base article', 'WhatsApp', 'uncanny-automator' ) )
			)
		);

		// Output the access token form field.
		$this->output_access_token_form_field();

		// Output the account form fields.
		$this->output_account_form_fields();
	}

	/**
	 * Output main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		// Output the account form fields.
		$this->output_account_form_fields();

		// Add separator.
		$this->output_panel_separator();

		// Output the webhook details.
		$this->output_webhook_details();
	}

	////////////////////////////////////////////////////////////
	// Integration specific templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Output the form fields for the disconnected state.
	 *
	 * @return void - Outputs the generated HTML.
	 */
	private function output_access_token_form_field() {
		$this->text_input_html(
			array(
				'id'       => esc_attr( $this->helpers->get_credentials_option_name() ),
				'name'     => esc_attr( $this->helpers->get_credentials_option_name() ),
				'value'    => esc_attr( $this->helpers->get_credentials() ), // access token is stored in the credentials option
				'label'    => esc_html_x( 'Access token', 'WhatsApp', 'uncanny-automator' ),
				'class'    => 'uap-spacing-top',
				'required' => true,
				'helper'   => sprintf(
					// translators: %1$s is a link to the WhatsApp knowledge base article.
					esc_html_x( 'You may use the temporary access token found in your WhatsApp product for testing purposes. %1$s to learn how to create a permanent access token.', 'WhatsApp', 'uncanny-automator' ),
					$this->get_kb_link( 'section-access-token', esc_html_x( 'Click here', 'WhatsApp', 'uncanny-automator' ) )
				),
			)
		);
	}

	/**
	 * Output the form fields for the disconnected state.
	 *
	 * @return void - Outputs the generated HTML.
	 */
	private function output_account_form_fields() {

		// Use empty string instead of 0.
		$phone_id = $this->helpers->get_phone_number_id();
		$phone_id = empty( $phone_id ) ? '' : $phone_id;

		$this->text_input_html(
			array(
				'id'       => esc_attr( $this->helpers->get_const( 'PHONE_ID' ) ),
				'name'     => esc_attr( $this->helpers->get_const( 'PHONE_ID' ) ),
				'value'    => esc_attr( $phone_id ),
				'label'    => esc_html_x( 'Phone number ID', 'WhatsApp', 'uncanny-automator' ),
				'class'    => 'uap-spacing-top',
				'required' => true,
			)
		);

		$this->text_input_html(
			array(
				'id'       => esc_attr( $this->helpers->get_const( 'BUSINESS_ID' ) ),
				'name'     => esc_attr( $this->helpers->get_const( 'BUSINESS_ID' ) ),
				'value'    => esc_attr( $this->helpers->get_business_account_id() ),
				'label'    => esc_html_x( 'WhatsApp Business Account ID', 'WhatsApp', 'uncanny-automator' ),
				'class'    => 'uap-spacing-top',
				'required' => true,
				'helper'   => sprintf(
					// translators: %1$s is a link to the WhatsApp knowledge base article.
					esc_html_x( 'Your Phone number ID and WhatsApp Business Account ID can be found in your Meta developer app settings under WhatsApp product. %1$s to learn more.', 'WhatsApp', 'uncanny-automator' ),
					$this->get_kb_link( 'section-phone-id', esc_html_x( 'Learn more', 'WhatsApp', 'uncanny-automator' ) )
				),
			)
		);

		// Only show update token button in connected state.
		if ( $this->is_connected ) {
			$this->output_action_button(
				'update_access_token',
				esc_html_x( 'Update access token', 'WhatsApp', 'uncanny-automator' ),
				array(
					'size'    => 'small',
					'color'   => 'secondary',
					'class'   => 'uap-spacing-top',
					'icon'    => 'lock',
					'confirm' => array(
						'heading' => esc_html_x( 'Update access token', 'WhatsApp', 'uncanny-automator' ),
						'content' => esc_html_x( 'Enter your new WhatsApp access token below.', 'WhatsApp', 'uncanny-automator' ),
						'button'  => esc_html_x( 'Update token', 'WhatsApp', 'uncanny-automator' ),
						'fields'  => $this->get_update_token_fields(),
					),
				)
			);
		}
	}

	/**
	 * Output the webhook details.
	 *
	 * @return void - Outputs the generated HTML.
	 */
	private function output_webhook_details() {
		$this->text_input_html(
			array(
				'value'             => esc_url( $this->webhooks->get_authorized_url() ),
				'label'             => esc_html_x( 'Webhook URL', 'WhatsApp', 'uncanny-automator' ),
				'helper'            => esc_html_x( 'This is the URL Meta will be sending the events to. Copy and paste this value in your WhatsApp webhook configuration.', 'WhatsApp', 'uncanny-automator' ),
				'disabled'          => true,
				'copy-to-clipboard' => true,
			)
		);

		$this->text_input_html(
			array(
				'value'             => esc_attr( $this->webhooks->get_webhook_key() ),
				'label'             => esc_html_x( 'Verify token', 'WhatsApp', 'uncanny-automator' ),
				'helper'            => esc_html_x( 'Copy and paste this value in your WhatsApp webhook configuration under Verify token.', 'WhatsApp', 'uncanny-automator' ),
				'disabled'          => true,
				'class'             => 'uap-spacing-top',
				'copy-to-clipboard' => true,
			)
		);

		$this->output_action_button(
			'regenerate_webhook_url',
			esc_html_x( 'Regenerate webhook URL', 'WhatsApp', 'uncanny-automator' ),
			array(
				'size'    => 'small',
				'color'   => 'secondary',
				'class'   => 'uap-spacing-top',
				'icon'    => 'rotate',
				'confirm' => array(
					'heading' => esc_html_x( 'Regenerate webhook URL', 'WhatsApp', 'uncanny-automator' ),
					'content' => esc_html_x( 'Regenerating the webhook URL will prevent WhatsApp triggers from working until the new URL is set in your WhatsApp webhook configuration. Continue?', 'WhatsApp', 'uncanny-automator' ),
				),
			)
		);
	}

	/**
	 * Get the fields for the update access token confirmation dialog.
	 *
	 * @return array The field configuration.
	 */
	private function get_update_token_fields() {
		return array(
			array(
				'type'        => 'text-field',
				'id'          => 'new_access_token',
				'name'        => 'new_access_token',
				'label'       => esc_html_x( 'New access token', 'WhatsApp', 'uncanny-automator' ),
				'required'    => true,
				'placeholder' => esc_attr_x( 'Enter your new access token', 'WhatsApp', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Generate a knowledge base link.
	 *
	 * @param string $section   The section anchor in the knowledge base URL.
	 * @param string $link_text The text to display for the link.
	 * @return string The formatted link HTML.
	 */
	private function get_kb_link( $section, $link_text ) {
		return $this->get_escaped_link(
			$this->helpers->get_knowledgebase_url( 'premium-integrations', 'whatsapp', $section ),
			$link_text,
			array(
				'title' => esc_html_x( 'Visit our Knowledge Base article', 'WhatsApp', 'uncanny-automator' ),
			)
		);
	}
}
