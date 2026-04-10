<?php

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Uncanny_Automator\App_Integrations\App_Webhooks;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Facebook Lead Ads Webhooks
 *
 * Extends the App_Webhooks framework class to handle Facebook Lead Ads webhook processing.
 * Uses a dynamic route pattern to handle both main webhook and verification endpoints
 * while properly leveraging the framework's handle_webhook_request flow.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 */
class Facebook_Lead_Ads_Webhooks extends App_Webhooks {

	/**
	 * Legacy REST namespace.
	 *
	 * @var string
	 */
	const LEGACY_REST_NAMESPACE = 'automator/v1';

	/**
	 * Legacy route base.
	 *
	 * @var string
	 */
	const LEGACY_ROUTE_BASE = '/integration/facebook-lead-ads';

	/**
	 * Trigger action.
	 *
	 * @var string
	 */
	const TRIGGER_ACTION_NAME = 'automator_facebook_lead_ads_rest_api_handle_request_after';

	/**
	 * Set additional properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set endpoint for reference.
		$this->set_webhook_endpoint( 'integration/facebook-lead-ads' );

		// Facebook webhooks don't use key-based auth (the API server handles auth).
		$this->set_auth_param( '' );
	}

	/**
	 * Register legacy REST API endpoint with dynamic pattern.
	 * Single route handles both main webhook and verification requests.
	 *
	 * @return void
	 */
	public function register_legacy_endpoints() {
		// Dynamic route pattern: matches both /integration/facebook-lead-ads and /integration/facebook-lead-ads/verification
		register_rest_route(
			self::LEGACY_REST_NAMESPACE,
			self::LEGACY_ROUTE_BASE . '(?:/(?P<endpoint_action>[a-zA-Z0-9_-]+))?',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook_request' ), // Use framework method.
				'permission_callback' => '__return_true', // Public route - API server handles auth.
			)
		);
	}

	/**
	 * Get the legacy listener endpoint URL.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		return rest_url( self::LEGACY_REST_NAMESPACE . self::LEGACY_ROUTE_BASE );
	}

	/**
	 * Get the verification endpoint URL.
	 *
	 * @return string
	 */
	public function get_verification_url() {
		return rest_url( self::LEGACY_REST_NAMESPACE . self::LEGACY_ROUTE_BASE . '/verification' );
	}

	/**
	 * Validate the webhook request.
	 * Handles verification requests by returning an early response.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool|WP_REST_Response
	 */
	protected function validate_webhook_request( $request ) {
		$endpoint_action = $request->get_param( 'endpoint_action' );
		$params          = $request->get_params();

		// Handle verification request - return response immediately.
		if ( 'verification' === $endpoint_action ) {
			return new WP_REST_Response(
				array( 'received' => time() ),
				200
			);
		}

		// Acknowledge but don't process if integration is not connected.
		if ( ! $this->is_connected ) {
			return new WP_REST_Response(
				array(
					'code'    => 'rest_not_connected',
					'message' => esc_html_x( 'Webhook received but not processed. Integration is not connected.', 'Facebook Lead Ads', 'uncanny-automator' ),
				),
				200
			);
		}

		// Validate data exists for main webhook.
		unset( $params['endpoint_action'] ); // Remove route param from data check.

		if ( empty( $params ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'rest_invalid_data',
					'message' => esc_html_x( 'Webhook received but not processed. No data provided.', 'Facebook Lead Ads', 'uncanny-automator' ),
				),
				200
			);
		}

		return true;
	}

	/**
	 * Validate webhook authorization.
	 * Facebook webhooks from the Automator API server don't use key-based auth.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool
	 */
	protected function validate_webhook_authorization( $request ) {
		return true;
	}

	/**
	 * Get the do_action name for the webhook.
	 *
	 * @return string
	 */
	protected function get_do_action_name() {
		return self::TRIGGER_ACTION_NAME;
	}

	/**
	 * Set the shutdown data for deferred processing.
	 * Matches the legacy action parameters structure.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return array
	 */
	protected function set_shutdown_data( $request ) {
		$params = $request->get_params();
		unset( $params['endpoint_action'] ); // Remove route param from data.

		return array(
			'action_name'   => $this->get_do_action_name(),
			'action_params' => array(
				array(
					'data'    => $params,
					'request' => $request,
				),
			),
		);
	}

	/**
	 * Generate webhook response.
	 * Matches the legacy response structure.
	 *
	 * @return WP_REST_Response
	 */
	protected function generate_webhook_response() {
		$request = $this->get_current_request();
		$params  = $request->get_params();
		unset( $params['endpoint_action'] );

		return new WP_REST_Response(
			array(
				'code'    => 'rest_success',
				'message' => esc_html_x( 'Data processed successfully.', 'Facebook Lead Ads', 'uncanny-automator' ),
				'data'    => $params,
			),
			200
		);
	}

	/**
	 * Check if framework webhooks should be registered.
	 * Returns false - we use legacy endpoints via initialize().
	 *
	 * @return bool
	 */
	public function should_register_webhooks() {
		return false;
	}
}
