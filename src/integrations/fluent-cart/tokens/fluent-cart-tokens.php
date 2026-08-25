<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

/**
 * Class Fluent_Cart_Tokens
 *
 * Shared token definitions + hydration for FluentCart triggers. Each group has a
 * paired define/hydrate method. Hydration accepts either a FluentCart model
 * (object) or a serialized array, and always returns the full keyset (empty
 * strings for a missing entity) so a partial map never reaches the recipe.
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 */
class Fluent_Cart_Tokens {

	use Reads_Entity_Props;

	/**
	 * Customer token definitions.
	 *
	 * @return array
	 */
	public function customer_tokens() {
		return array(
			array(
				'tokenId'   => 'FLUENT_CART_CUSTOMER_ID',
				'tokenName' => esc_html_x( 'Customer ID', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FLUENT_CART_CUSTOMER_EMAIL',
				'tokenName' => esc_html_x( 'Customer email', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'FLUENT_CART_CUSTOMER_NAME',
				'tokenName' => esc_html_x( 'Customer name', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Customer token values.
	 *
	 * @param mixed $customer FluentCart Customer model or serialized array.
	 *
	 * @return array
	 */
	public function hydrate_customer_tokens( $customer ) {

		$name = trim( (string) $this->prop( $customer, 'full_name', '' ) );

		if ( '' === $name ) {
			$name = trim( $this->prop( $customer, 'first_name', '' ) . ' ' . $this->prop( $customer, 'last_name', '' ) );
		}

		return array(
			'FLUENT_CART_CUSTOMER_ID'    => (string) $this->prop( $customer, 'id', '' ),
			'FLUENT_CART_CUSTOMER_EMAIL' => (string) $this->prop( $customer, 'email', '' ),
			'FLUENT_CART_CUSTOMER_NAME'  => $name,
		);
	}

	/**
	 * Order token definitions.
	 *
	 * @return array
	 */
	public function order_tokens() {
		return array(
			array(
				'tokenId'   => 'FLUENT_CART_ORDER_ID',
				'tokenName' => esc_html_x( 'Order ID', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FLUENT_CART_ORDER_STATUS',
				'tokenName' => esc_html_x( 'Order status', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FLUENT_CART_ORDER_TOTAL',
				'tokenName' => esc_html_x( 'Order total', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FLUENT_CART_ORDER_CURRENCY',
				'tokenName' => esc_html_x( 'Order currency', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FLUENT_CART_ORDER_PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment method', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FLUENT_CART_ORDER_RECEIPT_NUMBER',
				'tokenName' => esc_html_x( 'Receipt number', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Order token values.
	 *
	 * @param mixed $order FluentCart Order model or serialized array.
	 *
	 * @return array
	 */
	public function hydrate_order_tokens( $order ) {

		$payment_method = (string) $this->prop( $order, 'payment_method_title', '' );

		if ( '' === $payment_method ) {
			$payment_method = (string) $this->prop( $order, 'payment_method', '' );
		}

		return array(
			'FLUENT_CART_ORDER_ID'             => (string) $this->prop( $order, 'id', '' ),
			'FLUENT_CART_ORDER_STATUS'         => (string) $this->prop( $order, 'status', '' ),
			'FLUENT_CART_ORDER_TOTAL'          => '' === $this->prop( $order, 'total_amount', '' ) ? '' : number_format( (float) $this->prop( $order, 'total_amount', 0 ) / 100, 2, '.', '' ),
			'FLUENT_CART_ORDER_CURRENCY'       => (string) $this->prop( $order, 'currency', '' ),
			'FLUENT_CART_ORDER_PAYMENT_METHOD' => $payment_method,
			'FLUENT_CART_ORDER_RECEIPT_NUMBER' => (string) $this->prop( $order, 'receipt_number', '' ),
		);
	}

	/**
	 * Subscription token definitions.
	 *
	 * @return array
	 */
	public function subscription_tokens() {
		return array(
			array(
				'tokenId'   => 'FLUENT_CART_SUBSCRIPTION_ID',
				'tokenName' => esc_html_x( 'Subscription ID', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FLUENT_CART_SUBSCRIPTION_PRODUCT',
				'tokenName' => esc_html_x( 'Subscription product', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FLUENT_CART_SUBSCRIPTION_RECURRING_AMOUNT',
				'tokenName' => esc_html_x( 'Recurring amount', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FLUENT_CART_SUBSCRIPTION_BILLING_INTERVAL',
				'tokenName' => esc_html_x( 'Billing interval', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FLUENT_CART_SUBSCRIPTION_NEXT_BILLING',
				'tokenName' => esc_html_x( 'Next billing date', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FLUENT_CART_SUBSCRIPTION_STATUS',
				'tokenName' => esc_html_x( 'Subscription status', 'FluentCart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Subscription token values.
	 *
	 * @param mixed $subscription FluentCart Subscription model or serialized array.
	 *
	 * @return array
	 */
	public function hydrate_subscription_tokens( $subscription ) {
		return array(
			'FLUENT_CART_SUBSCRIPTION_ID'               => (string) $this->prop( $subscription, 'id', '' ),
			'FLUENT_CART_SUBSCRIPTION_PRODUCT'          => (string) $this->prop( $subscription, 'item_name', '' ),
			'FLUENT_CART_SUBSCRIPTION_RECURRING_AMOUNT' => '' === $this->prop( $subscription, 'recurring_total', '' ) ? '' : number_format( (float) $this->prop( $subscription, 'recurring_total', 0 ) / 100, 2, '.', '' ),
			'FLUENT_CART_SUBSCRIPTION_BILLING_INTERVAL' => (string) $this->prop( $subscription, 'billing_interval', '' ),
			'FLUENT_CART_SUBSCRIPTION_NEXT_BILLING'     => (string) $this->prop( $subscription, 'next_billing_date', '' ),
			'FLUENT_CART_SUBSCRIPTION_STATUS'           => (string) $this->prop( $subscription, 'status', '' ),
		);
	}
}
