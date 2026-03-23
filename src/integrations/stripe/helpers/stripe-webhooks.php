<?php

namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\App_Integrations\App_Webhooks;
use WP_REST_Response;

/**
 * Class Stripe_Webhooks
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 * @property Stripe_Api_Caller $api
 */
class Stripe_Webhooks extends App_Webhooks {

	/**
	 * The option name for the webhook secret.
	 *
	 * @var string
	 */
	const WEBHOOK_SECRET_OPTION = 'automator_stripe_webhook_secret_';

	/**
	 * The action name for the incoming webhook.
	 *
	 * @var string
	 */
	const INCOMING_WEBHOOK_ACTION = 'automator_stripe_incoming_webhook';

	/**
	 * The action name for the line item paid event.
	 *
	 * @var string
	 */
	const LINE_ITEM_PAID_ACTION = 'automator_stripe_line_item_paid';

	/**
	 * The action name for the line item refunded event.
	 *
	 * @var string
	 */
	const LINE_ITEM_REFUNDED_ACTION = 'automator_stripe_line_item_refuded';

	/**
	 * The action name for the invoice item paid event.
	 *
	 * @var string
	 */
	const INVOICE_ITEM_PAID_ACTION = 'automator_stripe_invoice_item_paid';

	/**
	 * The action name for the invoice item payment failed event.
	 *
	 * @var string
	 */
	const INVOICE_ITEM_PAYMENT_FAILED_ACTION = 'automator_stripe_invoice_item_payment_failed';

	/**
	 * Request body for processing.
	 *
	 * @var array
	 */
	protected $request_body;

	/**
	 * Set the properties for the webhooks.
	 * - Override defaults for legacy compatibility.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override webhook endpoint for legacy compatibility.
		$this->set_webhook_endpoint( $this->get_webhook_endpoint() );

		// Configure webhook key option name.
		$this->set_webhook_key_option_name( $this->get_option_name() );

		// Override auth param for Stripe signature validation.
		$this->set_auth_param( 'stripe-signature' );
	}

	/**
	 * Get the webhooks enabled status.
	 * - Override for Stripe-specific logic.
	 * - Stripe webhooks require webhook secret to be configured.
	 *
	 * @return bool
	 */
	public function get_webhooks_enabled_status() {
		// Check if webhook secret is configured.
		$webhook_secret = $this->get_webhook_key( false );
		return ! empty( $webhook_secret );
	}

	/**
	 * get_webhook_endpoint
	 *
	 * @return string
	 */
	public function get_webhook_endpoint() {

		$endpoint = $this->get_sanitized_id();

		if ( 'test' === $this->helpers->get_mode() ) {
			$endpoint .= '-test';
		}

		return $endpoint;
	}

	/**
	 * get_option_name
	 *
	 * @return string
	 */
	public function get_option_name() {

		$option_name = self::WEBHOOK_SECRET_OPTION . $this->helpers->get_mode();

		try {
			$credentials  = $this->helpers->get_credentials();
			$option_name .= $credentials['stripe_user_id'];
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Fallback to option name without user ID if credentials unavailable
		}

		return $option_name;
	}

	////////////////////////////////////////////////////////////
	// Webhook processing abstract overrides
	////////////////////////////////////////////////////////////

	/**
	 * Validate the webhook request.
	 * - Override for Stripe-specific signature validation.
	 *
	 * @param mixed $request The request object.
	 *
	 * @return bool
	 *
	 * @throws Exception If validation fails.
	 */
	protected function validate_webhook_authorization( $request ) {
		try {
			$secret             = $this->get_webhook_key( false );
			$this->request_body = $this->get_decoded_request_body();

			if ( empty( $this->request_body['type'] ) ) {
				throw new \Exception( 'Request type is required' );
			}

			WebhookVerificator::verify_header(
				$this->get_raw_request_body(),
				$this->get_request_header( 'stripe-signature' ),
				$secret
			);

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'Stripe webhook verification failed' );
			throw new \Exception( 'Webhook verification failed' );
		}

		return true;
	}

	/**
	 * Set the shutdown data.
	 * - Override to use decoded request body.
	 *
	 * @param WP_REST_Request $request The WP_REST_Request object.
	 *
	 * @return array
	 */
	protected function set_shutdown_data( $request ) {
		return array(
			'action_name'   => self::INCOMING_WEBHOOK_ACTION,
			'action_params' => array( $this->get_decoded_request_body() ),
		);
	}

	/**
	 * Process webhook request.
	 * - Override for Stripe-specific processing.
	 *
	 * @param string $action_name   The action name.
	 * @param array  $action_params The action parameters array.
	 *
	 * @return void
	 */
	protected function process_webhook_request( $action_name, $action_params ) {
		// Set request body for Stripe processing - extract from params array
		$this->request_body = $action_params[0];

		// Fire the incoming webhook action.
		do_action( $action_name, $this->request_body );

		// Process Stripe-specific events
		$this->session_completed_line_items_events();
		$this->charge_refunded_line_items_events();
		$this->invoice_paid_line_items_events();
		$this->invoice_payment_failed_line_items_events();
	}

	/**
	 * Generate webhook response.
	 * - Override to return empty response like original Stripe webhook.
	 *
	 * @return WP_REST_Response
	 */
	protected function generate_webhook_response() {
		// Return empty response like original Stripe webhook
		return new WP_REST_Response( array(), 200 );
	}

	////////////////////////////////////////////////////////////
	// Stripe specific events
	////////////////////////////////////////////////////////////

	/**
	 * Session completed line items events.
	 *
	 * @return void
	 */
	public function session_completed_line_items_events() {
		if ( 'checkout.session.completed' !== $this->request_body['type'] ) {
			return;
		}

		$session = $this->request_body['data']['object'];

		$this->fire_line_items_actions( $session, self::LINE_ITEM_PAID_ACTION );
	}

	/**
	 * Charge refunded line items events.
	 *
	 * @return void
	 */
	public function charge_refunded_line_items_events() {
		if ( 'charge.refunded' !== $this->request_body['type'] ) {
			return;
		}

		$charge = $this->request_body['data']['object'];

		$session = array();

		try {
			$sessions = $this->api->get_all_sessions( $charge['payment_intent'] );

			if ( empty( $sessions['data'][0]['object'] ) || 'checkout.session' !== $sessions['data'][0]['object'] ) {
				return;
			}

			$session = $sessions['data'][0];

		} catch ( \Exception $e ) {
			return;
		}

		if ( empty( $session ) ) {
			return;
		}

		$this->fire_line_items_actions( $session, self::LINE_ITEM_REFUNDED_ACTION );
	}

	/**
	 * Invoice paid line items events.
	 *
	 * @return void
	 */
	public function invoice_paid_line_items_events() {
		if ( 'invoice.paid' !== $this->request_body['type'] ) {
			return;
		}

		$invoice = $this->request_body['data']['object'];

		foreach ( $invoice['lines']['data'] as $line_item ) {
			do_action( self::INVOICE_ITEM_PAID_ACTION, $line_item, $invoice, $this->request_body );
		}
	}

	/**
	 * Invoice payment failed line items events.
	 *
	 * @return void
	 */
	public function invoice_payment_failed_line_items_events() {
		if ( 'invoice.payment_failed' !== $this->request_body['type'] ) {
			return;
		}

		$invoice = $this->request_body['data']['object'];

		foreach ( $invoice['lines']['data'] as $line_item ) {
			do_action( self::INVOICE_ITEM_PAYMENT_FAILED_ACTION, $line_item, $invoice, $this->request_body );
		}
	}

	/**
	 * Fire line items actions.
	 *
	 * @param array  $session The session data.
	 * @param string $hook    The action hook.
	 *
	 * @return void
	 */
	public function fire_line_items_actions( $session, $hook ) {
		if ( empty( $session['line_items']['data'] ) ) {
			try {
				$response = $this->api->get_session( $session['id'] );
			} catch ( \Exception $e ) {
				return;
			}

			if ( empty( $response['data']['session'] ) ) {
				return;
			}

			$session = $response['data']['session'];

			if ( empty( $session['line_items']['data'] ) ) {
				return;
			}
		}

		foreach ( $session['line_items']['data'] as $line_item ) {
			do_action( $hook, $line_item, $session, $this->request_body );
		}
	}
}
