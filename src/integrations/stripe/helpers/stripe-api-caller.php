<?php

namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\App_Integrations\Api_Caller;

/**
 * Class Stripe_Api
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 */
class Stripe_Api_Caller extends Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string - Encoded credentials.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		// TODO - Once we have all integrations using the vault we can do this auto-magically.
		return wp_json_encode( $credentials );
	}

	/**
	 * check_for_errors
	 *
	 * @param  array $response
	 * @return void
	 * */
	public function check_for_errors( $response, $args = array() ) {}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * get_user_details
	 *
	 * @return array
	 */
	public function get_user_details() {

		$user_details = $this->helpers->get_account_info();
		if ( ! empty( $user_details ) ) {
			return $user_details;
		}

		try {
			$response = $this->api_request( 'get_account' );
			$this->helpers->store_account_info( $response['data'] );
			return $response['data'];
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * get_prices_options
	 *
	 * @param string $type Optional. Filter by price type: 'one_time' or 'recurring'. Default null (no filter).
	 *
	 * @return array
	 */
	public function get_prices_options( $type = null ) {

		$options = array();

		try {

			$response = $this->api_request( 'get_prices' );

			if ( empty( $response['data']['prices'] ) ) {
				throw new \Exception( 'Prices could not be fetched' );
			}

			foreach ( $response['data']['prices'] as $price ) {

				// Skip if filtering by type and price doesn't match
				if ( null !== $type && isset( $price['type'] ) && $price['type'] !== $type ) {
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

		try {

			$response = $this->api_request( 'get_subscriptions' );

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
		return $this->api_request( 'get_webhooks' );
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
	/**
	 * Get subscription.
	 *
	 * @param mixed $subscription_id The ID.
	 * @return mixed
	 */
	public function get_subscription( $subscription_id ) {

		$body = array(
			'action'          => 'get_subscription',
			'subscription_id' => $subscription_id,
		);

		return $this->api_request( $body );
	}
	/**
	 * Get all sessions.
	 *
	 * @param mixed $payment_intent_id The ID.
	 * @return mixed
	 */
	public function get_all_sessions( $payment_intent_id ) {

		$body = array(
			'action'            => 'get_all_sessions',
			'payment_intent_id' => $payment_intent_id,
		);

		return $this->api_request( $body );
	}

	/**
	 * Create and send invoice.
	 *
	 * @param array $invoice_data Invoice data including customer, line items, etc.
	 * @param array $action_data  Action data for the request.
	 * @return array
	 */
	public function create_invoice( $invoice_data, $action_data ) {

		$body = array(
			'action'  => 'create_invoice',
			'invoice' => wp_json_encode( $invoice_data ),
		);

		return $this->api_request( $body, $action_data );
	}
}
