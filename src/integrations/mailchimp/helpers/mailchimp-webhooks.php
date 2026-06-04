<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

use Uncanny_Automator\App_Integrations\App_Webhooks;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Mailchimp_Webhooks
 *
 * @package Uncanny_Automator
 */
class Mailchimp_Webhooks extends App_Webhooks {

	/**
	 * Set webhook properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override webhook endpoint for legacy compatibility.
		$this->set_webhook_endpoint(
			apply_filters(
				'automator_mailchimp_webhook_endpoint',
				'mailchimp',
				$this->helpers
			)
		);

		// Set webhook enabled option name (preserve existing).
		$this->set_webhooks_enabled_option_name( 'uap_mailchimp_enable_webhook' );

		// Set webhook key option name (preserve existing).
		$this->set_webhook_key_option_name( 'uap_mailchimp_webhook_key' );

		// Mailchimp requires GET for webhook validation handshake.
		$this->set_accepts_get_requests( true );
	}

	/**
	 * Get webhook enabled status.
	 *
	 * Override to support legacy 'checked' value format.
	 *
	 * @return bool
	 */
	public function get_webhooks_enabled_status() {
		if ( ! $this->is_connected ) {
			return false;
		}

		$enabled_status = automator_get_option( $this->get_webhooks_enabled_option_name(), false );

		// Support multiple formats for backward compatibility including legacy 'checked' value.
		$enabled_values = array( 'on', 'checked', 1, '1', true, 'true' );

		return in_array( $enabled_status, $enabled_values, true );
	}

	/**
	 * Validate webhook request.
	 *
	 * Mailchimp-specific validation for user-agent header.
	 * Key validation is handled by the framework's validate_webhook_authorization().
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool|WP_REST_Response
	 */
	protected function validate_webhook_request( $request ) {
		// Handle GET requests (Mailchimp validation handshake).
		// Return success response immediately without further processing.
		if ( WP_REST_Server::READABLE === $request->get_method() ) {
			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		// Verify user agent (Mailchimp sets user agent to MailChimp).
		$user_agent = $request->get_header( 'user_agent' );
		if ( empty( $user_agent ) || false === strpos( strtolower( $user_agent ), 'mailchimp' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Set shutdown data.
	 *
	 * Override to fire event-type-specific actions (e.g., automator_mailchimp_webhook_received_subscribe).
	 *
	 * @param WP_REST_Request $request The WP_REST_Request object.
	 *
	 * @return array
	 */
	protected function set_shutdown_data( $request ) {
		$body = $request->get_body();

		// Parse the webhook data (Mailchimp sends form-encoded data).
		parse_str( $body, $parsed_data );

		// Get the event type.
		$type = isset( $parsed_data['type'] ) ? sanitize_key( $parsed_data['type'] ) : '';

		// Log the event.
		$this->log_event( 'Automator received webhook data from Mailchimp of type: ' . $type );

		// Return event-type-specific action name.
		return array(
			'action_name'   => 'automator_mailchimp_webhook_received_' . $type,
			'action_params' => array( $parsed_data ),
		);
	}

	/**
	 * Log webhook events.
	 *
	 * @param string $message The message to log.
	 * @param bool   $force_debug Force debug logging.
	 *
	 * @return mixed
	 */
	private function log_event( $message, $force_debug = false ) {
		return automator_log( $message, 'Mailchimp Webhook Trigger Entry', $force_debug, 'mailchimp-webhook' );
	}
}
