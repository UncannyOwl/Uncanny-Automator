<?php

namespace Uncanny_Automator;

use WP_HTTP_Response;

/**
 * Class Telegram_Webhook
 *
 * @package Uncanny_Automator
 */
class Telegram_Webhook {

	const WEBHOOK_ENPOINT = 'automator_telegram';

	const WEBHOOK_OPTION = 'automator_telegram_webhook';

	const INCOMING_WEBHOOK_ACTION = 'automator_telegram_incoming_webhook';

	protected $functions;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct( $functions ) {
		$this->functions = $functions;
	}

	/**
	 * register_hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'check_telegram_webhook' ) );
		add_action( 'rest_api_init', array( $this, 'init_wp_webhook' ) );
	}

	/**
	 * get_url
	 *
	 * @return string
	 */
	public function get_url() {
		return get_rest_url( null, AUTOMATOR_REST_API_END_POINT . '/' . self::WEBHOOK_ENPOINT );
	}

	/**
	 * has_active_trigger
	 *
	 * @return bool
	 */
	public function has_active_trigger() {

		// If nothing is hooked to automator_telegram_message_received, then there is no active trigger
		if ( ! array_key_exists( self::INCOMING_WEBHOOK_ACTION, $GLOBALS['wp_filter'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * register_telegram_webhook
	 *
	 * @return void
	 */
	public function register_telegram_webhook() {

		try {

			$webhook = array(
				'secret_token'    => wp_create_nonce(),
				'url'             => $this->get_url(),
				'allowed_updates' => array( 'message', 'channel_post' ),
			);

			$response = $this->functions->api->register_telegram_webhook( $webhook );

			$this->store_webhook_details( $webhook );

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}
	}

	/**
	 * store_webhook_details
	 *
	 * @param  mixed $webhook
	 * @return bool
	 */
	public function store_webhook_details( $webhook ) {
		return update_option( self::WEBHOOK_OPTION, $webhook );
	}

	/**
	 * delete_telegram_webhook
	 *
	 * @return void
	 */
	public function delete_telegram_webhook() {

		if ( false === $this->get_webhook_details() ) {
			return;
		}

		try {
			$this->functions->api->delete_telegram_webhook();
			delete_option( self::WEBHOOK_OPTION );
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}
	}

	/**
	 * get_webhook
	 *
	 * @return mixed
	 */
	public function get_webhook_details() {
		return automator_get_option( self::WEBHOOK_OPTION, false );
	}

	/**
	 * check_telegram_webhook
	 *
	 * @return void
	 */
	public function check_telegram_webhook() {

		// If there is an active trigger, but Telegram webhook is not enabled, register the webhook with Telegram API
		if ( $this->has_active_trigger() && false === $this->get_webhook_details() ) {

				$this->register_telegram_webhook();
				return;
		}

		// If there is no active triggers, but Telegram webhook is enabled, delete the webhook from Telegram API
		if ( ! $this->has_active_trigger() && false !== $this->get_webhook_details() ) {
			$this->delete_telegram_webhook();
			return;
		}

		// In all other scenarios, so nothing

	}

	/**
	 * init_wp_webhook
	 *
	 * @return void
	 */
	public function init_wp_webhook() {

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			self::WEBHOOK_ENPOINT,
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

		$request_security_token = $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );

		if ( empty( $request_security_token ) ) {
			return false;
		}

		$webhook = $this->get_webhook_details();

		if ( empty( $webhook['secret_token'] ) ) {
			return false;
		}

		if ( $request_security_token !== $webhook['secret_token'] ) {
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
		do_action( self::INCOMING_WEBHOOK_ACTION, $request );
		return new WP_HTTP_Response( array(), 200 );
	}
}
