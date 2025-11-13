<?php

namespace Uncanny_Automator\Integrations\Helpscout;

use Uncanny_Automator\App_Integrations\App_Webhooks;
use WP_REST_Response;

/**
 * Class Helpscout_Webhooks
 *
 * @package Uncanny_Automator
 */
class Helpscout_Webhooks extends App_Webhooks {

	/**
	 * Set webhook properties
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set legacy webhook endpoint.
		$this->set_webhook_endpoint( 'helpscout' );

		// Set webhook enabled option name.
		$this->set_webhooks_enabled_option_name( 'uap_helpscout_enable_webhook' );

		// Set webhook key option name.
		$this->set_webhook_key_option_name( 'uap_helpscout_webhook_key' );
	}

	/**
	 * Validate the signature.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return bool
	 */
	protected function validate_webhook_authorization( $request ) {
		// Get signature from headers.
		$signature = $request->get_header( 'x-helpscout-signature' );

		// Validate signature using raw body and key..
		$body     = $request->get_body();
		$expected = base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			hash_hmac(
				'sha1',
				$body,
				$this->get_webhook_key(),
				true
			)
		);

		return hash_equals( $expected, $signature );
	}

	/**
	 * Set the shutdown data for webhook processing.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return array
	 */
	protected function set_shutdown_data( $request ) {
		return array(
			'action_name'   => $this->get_do_action_name(),
			'action_params' => array( $request->get_params(), $request->get_headers() ),
		);
	}

	/**
	 * Check if webhook request matches specific event.
	 *
	 * @param array $headers Request headers
	 * @param string $event Event type to check
	 *
	 * @return bool
	 */
	public function is_webhook_request_matches_event( $headers, $event ) {
		$event_header = $headers['x_helpscout_event'] ?? '';

		if ( is_array( $event_header ) && isset( $event_header[0] ) ) {
			$event_header = $event_header[0];
		}

		if ( $event_header === $event || 'any' === $event ) {
			return true;
		}

		return false;
	}
}
