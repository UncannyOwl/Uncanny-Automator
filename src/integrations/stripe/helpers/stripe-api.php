<?php

namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\Api_Server;

/**
 * Class Stripe_Api
 *
 * @package Uncanny_Automator
 */
class Stripe_Api {

	const API_ENDPOINT = 'v2/stripe';

	protected $helpers;

	/**
	 * __construct
	 *
	 * @param  mixed $functions
	 * @return void
	 */
	public function __construct( $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * api_request
	 *
	 * @param array $body
	 * @param array $action_data
	 *
	 * @return array
	 */
	public function api_request( $body, $action_data = null ) {

		$client = $this->helpers->get_credentials();

		$body['credentials'] = wp_json_encode( $client );

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;
	}

	/**
	 * check_for_errors
	 *
	 * @param  array $response
	 * @return void
	 * */
	public function check_for_errors( $response ) {}

	/**
	 * get_user_details
	 *
	 * @return array
	 */
	public function get_user_details() {

		$user_details = automator_get_option( $this->helpers::USER_OPTION );

		if ( ! empty( $user_details ) ) {
			return $user_details;
		}

		$body = array(
			'action' => 'get_account',
		);

		try {
			$response = $this->api_request( $body );
			automator_update_option( $this->helpers::USER_OPTION, $response['data'] );
			return $response['data'];
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * get_prices_options
	 *
	 * @return array
	 */
	public function get_prices_options( $type = null ) {

		$options = array();

		$request_params = array(
			'action' => 'get_prices',
		);

		try {

			$response = $this->api_request( $request_params );

			if ( empty( $response['data']['prices'] ) ) {
				throw new \Exception( 'Prices could not be fetched' );
			}

			foreach ( $response['data']['prices'] as $price ) {

				if ( null !== $type && $price['type'] !== $type ) {
					continue;
				}

				$name = $price['product']['name'] . ' - ' . $this->helpers->generate_price_name( $price );

				$options[] = array(
					'text'  => $name,
					'value' => $price['id'],
				);
			}
		} catch ( \Exception $e ) {
			return array(
				array(
					'text'  => $e->getMessage(),
					'value' => '',
				),
			);
		}

		return $options;
	}

	/**
	 * create_payment_link
	 *
	 * @param  array $payment_link
	 * @param  array $action_data
	 * @return array
	 */
	public function create_payment_link( $payment_link, $action_data ) {

		$body = array(
			'action'       => 'create_payment_link',
			'payment_link' => wp_json_encode( $payment_link ),
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * create_customer
	 *
	 * @param  array $customer
	 * @param  array $action_data
	 * @return array
	 */
	public function create_customer( $customer, $action_data ) {

		$body = array(
			'action'   => 'create_customer',
			'customer' => wp_json_encode( $customer ),
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * get_subscriptions_options
	 *
	 * @return array
	 * */
	public function get_subscriptions_options() {

		$options = array();

		$request_params = array(
			'action' => 'get_subscriptions',
		);

		try {

			$response = $this->api_request( $request_params );

			if ( empty( $response['data']['subscriptions'] ) ) {
				throw new \Exception( 'Subscriptions could not be fetched' );
			}

			foreach ( $response['data']['subscriptions'] as $subscription ) {

				$name = $subscription['customer']['name'] . ' - ' . $subscription['plan']['nickname'];

				$options[] = array(
					'text'  => $name,
					'value' => $subscription['id'],
				);
			}
		} catch ( \Exception $e ) {
			return array(
				array(
					'text'  => $e->getMessage(),
					'value' => '',
				),
			);
		}

		return $options;
	}

	/**
	 * Retrieve all Stripe webhooks.
	 *
	 * @return array The response from the API request.
	 */
	public function get_webhooks() {

		$body = array(
			'action' => 'get_webhooks',
		);

		return $this->api_request( $body );
	}

	/**
	 * delete_customer
	 *
	 * @param  array $customer
	 * @param  array $action_data
	 * @return array
	 */
	public function delete_customer( $customer, $action_data ) {

		$body = array(
			'action'   => 'delete_customer',
			'customer' => wp_json_encode( $customer ),
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Get session details.
	 *
	 * @param array $session Stripe checkout session.
	 * @return array Stripe checkout session with expanded data.
	 */
	public function get_session( $session_id ) {

		$body = array(
			'action'     => 'get_session',
			'session_id' => $session_id,
		);

		return $this->api_request( $body );
	}

	public function get_subscription( $subscription_id ) {

		$body = array(
			'action'          => 'get_subscription',
			'subscription_id' => $subscription_id,
		);

		return $this->api_request( $body );
	}

	public function get_all_sessions( $payment_intent_id ) {

		$body = array(
			'action'            => 'get_all_sessions',
			'payment_intent_id' => $payment_intent_id,
		);

		return $this->api_request( $body );
	}
}
