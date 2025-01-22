<?php

namespace Uncanny_Automator\Integrations\Stripe;

use WP_HTTP_Response;

/**
 * Class Stripe_Webhook
 *
 * @package Uncanny_Automator
 */
class Stripe_Webhook {

	const WEBHOOK_ENPOINT = 'stripe';

	const WEBHOOK_SECRET_OPTION = 'automator_stripe_webhook_secret_';

	const INCOMING_WEBHOOK_ACTION = 'automator_stripe_incoming_webhook';

	const LINE_ITEM_PAID_ACTION = 'automator_stripe_line_item_paid';

	const LINE_ITEM_REFUNDED_ACTION = 'automator_stripe_line_item_refuded';

	const INVOICE_ITEM_PAID_ACTION = 'automator_stripe_invoice_item_paid';

	const INVOICE_ITEM_PAYMENT_FAILED_ACTION = 'automator_stripe_invoice_item_payment_failed';

	protected $helpers;

	protected $request_body;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct( $helpers ) {
		$this->helpers = $helpers;
		$this->register_hooks();
	}

	/**
	 * register_hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'init_wp_webhook' ) );
	}

	/**
	 * get_webhook_endpoint
	 *
	 * @return string
	 */
	public function get_webhook_endpoint() {

		$endpoint = self::WEBHOOK_ENPOINT;

		if ( 'test' === $this->helpers->get_mode() ) {
			$endpoint .= '-test';
		}

		return $endpoint;
	}

	/**
	 * get_url
	 *
	 * @return string
	 */
	public function get_url() {

		$url = get_rest_url( null, AUTOMATOR_REST_API_END_POINT . '/' . $this->get_webhook_endpoint() );

		return $url;
	}

	/**
	 * get_option_name
	 *
	 * @return string
	 */
	public function get_option_name() {

		$option_name = self::WEBHOOK_SECRET_OPTION . $this->helpers->get_mode();

		try {
			$credentials = $this->helpers->get_credentials();
			$option_name .= $credentials['stripe_user_id'];
		} catch ( \Exception $e ) {
			//Do nothing
		}

		return $option_name;
	}

	/**
	 * get_secret
	 *
	 * @return string
	 */
	public function get_secret() {
		$webhook_secret = automator_get_option( $this->get_option_name(), '' );
		return $webhook_secret;
	}

	/**
	 * init_wp_webhook
	 *
	 * @return void
	 */
	public function init_wp_webhook() {

		// If webhook secret is not set, bail.
		if ( '' === $this->get_secret() ) {
			return;
		}

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			$this->get_webhook_endpoint(),
			array(
				'methods'             => array( 'POST' ),
				'callback'            => array( $this, 'callback' ),
				'permission_callback' => array( $this, 'validate' ),
			)
		);
	}

	/**
	 * validate
	 *
	 * @param  WP_HTTP_Request
	 * @return bool
	 */
	public function validate( $request ) {

		try {

			$secret = $this->get_secret();

			$this->request_body = json_decode( $request->get_body(), true );

			if ( empty( $this->request_body['type'] ) ) {
				throw new \Exception( 'Request type is required' );
			}

			WebhookVerificator::verify_header( $request->get_body(), $request->get_header( 'stripe-signature' ), $secret );

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'Stripe webhook verification failed' );
			return false;
		}

		return true;
	}

	/**
	 * callback
	 *
	 * @param  WP_HTTP_Request
	 * @return WP_HTTP_Response
	 */
	public function callback( $request ) {

		$this->request_body = json_decode( $request->get_body(), true );

		// Release the requester (Stripe) as soon as possible.
		// The request body will be processed later before shutdown.
		add_action( 'shutdown', array( $this, 'process_request' ) );

		return new WP_HTTP_Response( array(), 200 );
	}

	/**
	 * process_request
	 *
	 * @return void
	 */
	public function process_request() {

		if ( empty( $this->request_body ) ) {
			return;
		}

		do_action( self::INCOMING_WEBHOOK_ACTION, $this->request_body );

		$this->session_completed_line_items_events();
		$this->charge_refunded_line_items_events();
		$this->invoice_paid_line_items_events();
		$this->invoice_payment_failed_line_items_events();
	}

	/**
	 * session_completed_line_items_events
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
	 * charge_refunded_line_items_events
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
			$sessions = $this->helpers->api->get_all_sessions( $charge['payment_intent'] );

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
	 * invoice_paid_line_items_events
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
	 * invoice_payment_failed_line_items_events
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

	public function fire_line_items_actions( $session, $hook ) {

		if ( empty( $session['line_items']['data'] ) ) {
			try {
				$response = $this->helpers->api->get_session( $session['id'] );
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
