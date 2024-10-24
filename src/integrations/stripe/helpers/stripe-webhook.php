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

	protected $helpers;

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
	public function get_url( $mode ) {

		$url = get_rest_url( null, AUTOMATOR_REST_API_END_POINT . '/' . $this->get_webhook_endpoint() );

		return $url;
	}

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
	 * has_active_trigger
	 *
	 * @return bool
	 */
	public function has_active_trigger() {

		// If nothing is hooked to automator_stripe_message_received, then there is no active trigger
		if ( ! array_key_exists( self::INCOMING_WEBHOOK_ACTION, $GLOBALS['wp_filter'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * init_wp_webhook
	 *
	 * @return void
	 */
	public function init_wp_webhook() {

		// If there are no active Stripe triggers, bail.
		if ( ! $this->has_active_trigger() ) {
			return;
		}

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

			$request_body = json_decode( $request->get_body(), true );

			if ( empty( $request_body['type'] ) ) {
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

		$request_body = json_decode( $request->get_body(), true );

		do_action( self::INCOMING_WEBHOOK_ACTION, $request_body );

		return new WP_HTTP_Response( array(), 200 );
	}
}
