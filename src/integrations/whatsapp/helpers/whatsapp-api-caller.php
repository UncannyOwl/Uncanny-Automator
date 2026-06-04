<?php

namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class WhatsApp_Api_Caller
 *
 * @package Uncanny_Automator\Integrations\WhatsApp
 *
 * @property WhatsApp_Helpers $helpers
 * @property WhatsApp_Webhooks $webhooks
 */
class WhatsApp_Api_Caller extends Api_Caller {


	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Set the properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override the default credential request key until migration to vault.
		// This will automatically apply the access token to the request body.
		$this->set_credential_request_key( 'access_token' );

		// Register WhatsApp-specific error messages.
		$this->register_error_messages( $this->get_whatsapp_error_messages() );
	}

	/**
	 * Check for errors in the API response.
	 *
	 * Extends the base class to handle all non-200 status codes (not just 400)
	 * and clean up error messages from the API proxy.
	 *
	 * @param array $response The API response.
	 * @param array $args     The request arguments.
	 *
	 * @return void
	 *
	 * @throws Exception If an error is detected.
	 */
	public function check_for_errors( $response, $args = array() ) {
		// Success - no error handling needed.
		if ( 200 === ( $response['statusCode'] ?? 0 ) ) {
			return;
		}

		// Use the framework's pattern matching for registered error messages.
		// This handles token expiration, OAuth errors, etc. with proper settings links.
		$this->handle_400_error( $response, $args );

		// If we get here, no registered pattern matched.
		// Show the actual error message from Meta (cleaned up).
		$error_text = $this->get_error_text( $response );
		if ( $error_text ) {
			// Strip the "API has responded with an error message: " prefix.
			$clean_message = preg_replace( '/^api has responded with an error message:\s*/i', '', $error_text );
			throw new Exception( esc_html( $clean_message ) );
		}

		// Fallback for responses with no extractable error message.
		throw new Exception(
			sprintf(
				// translators: %d: HTTP status code
				esc_html_x( 'WhatsApp API request failed with status code: %d', 'WhatsApp', 'uncanny-automator' ),
				absint( $response['statusCode'] ?? 0 )
			)
		);
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Get WhatsApp-specific error messages.
	 *
	 * @return array
	 */
	private function get_whatsapp_error_messages() {
		$settings_url = $this->helpers->get_settings_page_url();
		// translators: %s: settings page URL
		$reconnect_message = esc_html_x( 'Your WhatsApp access token has expired or is invalid. Please [reconnect your account](%s).', 'WhatsApp', 'uncanny-automator' );

		return array(
			// Token expiration/validation errors.
			'access token'        => array(
				'message'   => $reconnect_message,
				'help_link' => $settings_url,
			),
			'session has expired' => array(
				'message'   => $reconnect_message,
				'help_link' => $settings_url,
			),
		);
	}

	/**
	 * Verify the token
	 *
	 * @param string $token
	 * @return array
	 *
	 * @throws Exception
	 */
	public function verify_token( $token ) {
		$body = array(
			'action'       => 'verify_token',
			'access_token' => $token,
		);

		// Flag to exclude the default credential request key.
		$args = array(
			'exclude_credentials' => true,
		);

		$response = $this->api_request( $body, null, $args );

		// Extract just the data we need, matching verify_token_old format.
		$client = $response['data']['data'] ?? null;

		// Check for missing scopes.
		if ( $this->helpers->has_missing_scopes( $client ) ) {
			throw new Exception(
				esc_html_x( 'The provided access token contains missing permissions. Make sure both whatsapp_business_management and whatsapp_business_messaging permissions are included.', 'WhatsApp', 'uncanny-automator' ),
				400
			);
		}

		// Return in existing format for now.
		return array(
			'data' => $response['data'],
		);
	}

	/**
	 * Re-validate the account business and phone IDs.
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function revalidate_account() {
		return $this->api_request(
			array(
				'action'      => 'revalidate_account',
				'business_id' => $this->helpers->get_business_account_id(),
				'phone_id'    => $this->helpers->get_phone_number_id(),
			)
		);
	}

	/**
	 * Fetch templates
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function fetch_templates() {
		$body = array(
			'action'      => 'list_template',
			'business_id' => $this->helpers->get_business_account_id(),
		);

		return $this->api_request( $body, null );
	}

	/**
	 * Fetch a specific template
	 *
	 * @param string $template_name
	 * @param string $language
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public function fetch_template( $template_name, $language ) {
		$response  = $this->fetch_templates();
		$templates = $response['data']['data'] ?? array();

		foreach ( $templates as $template ) {
			if ( $template['name'] === $template_name && $template['language'] === $language ) {
				return $template;
			}
		}

		throw new Exception(
			sprintf(
				// translators: %1$s: template name, %2$s: language
				esc_html_x( 'No template found for name: %1$s and language: %2$s', 'WhatsApp', 'uncanny-automator' ),
				esc_html( $template_name ),
				esc_html( $language ),
			),
			404
		);
	}
}
