<?php

namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\App_Integrations\Api_Caller;

/**
 * Class Stripe_Api_Caller
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
	 * Inspect an API response for errors. No-op for Stripe (errors handled upstream).
	 *
	 * @param array $response The decoded API response.
	 * @param array $args     Optional contextual arguments.
	 *
	 * @return void
	 */
	public function check_for_errors( $response, $args = array() ) {}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the connected Stripe account details, from cache or the API.
	 *
	 * @return array The account details, or an empty array on failure.
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
	 * Fetch formatted price options for one price type from the proxy.
	 *
	 * @param string $type 'recurring' or 'one_time'.
	 * @return array Decoded proxy response: { data: { options: [{text,value}] } }.
	 */
	public function get_price_options( $type ) {
		$body = array(
			'action' => 'get_price_options',
			'type'   => $type,
		);
		return $this->api_request( $body );
	}

	/**
	 * Create a Stripe payment link via the API.
	 *
	 * @param array $payment_link The payment link payload.
	 * @param array $action_data  The action data for the request.
	 *
	 * @return array The API response.
	 */
	public function create_payment_link( $payment_link, $action_data ) {

		$body = array(
			'action'       => 'create_payment_link',
			'payment_link' => wp_json_encode( $payment_link ),
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Create a Stripe customer via the API.
	 *
	 * @param array $customer    The customer payload.
	 * @param array $action_data The action data for the request.
	 *
	 * @return array The API response.
	 */
	public function create_customer( $customer, $action_data ) {

		$body = array(
			'action'   => 'create_customer',
			'customer' => wp_json_encode( $customer ),
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Delete a Stripe customer via the API.
	 *
	 * @param array $customer    The customer payload identifying who to delete.
	 * @param array $action_data The action data for the request.
	 *
	 * @return array The API response.
	 */
	public function delete_customer( $customer, $action_data ) {

		$body = array(
			'action'   => 'delete_customer',
			'customer' => wp_json_encode( $customer ),
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Get a Stripe checkout session with expanded data.
	 *
	 * @param string $session_id The checkout session ID.
	 *
	 * @return array The session with expanded data.
	 */
	public function get_session( $session_id ) {

		$body = array(
			'action'     => 'get_session',
			'session_id' => $session_id,
		);

		return $this->api_request( $body );
	}

	/**
	 * Get a Stripe subscription by ID.
	 *
	 * @param string $subscription_id The subscription ID.
	 *
	 * @return array The API response.
	 */
	public function get_subscription( $subscription_id ) {

		$body = array(
			'action'          => 'get_subscription',
			'subscription_id' => $subscription_id,
		);

		return $this->api_request( $body );
	}

	/**
	 * Get all checkout sessions for a payment intent.
	 *
	 * @param string $payment_intent_id The payment intent ID.
	 *
	 * @return array The API response.
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
