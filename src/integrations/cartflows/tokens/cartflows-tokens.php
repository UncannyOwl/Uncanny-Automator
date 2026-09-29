<?php

namespace Uncanny_Automator\Integrations\Cartflows\Tokens;

use WC_Order;

/**
 * Class Cartflows_Tokens
 *
 * Order + funnel token definitions shared by both checkout triggers, kept here
 * rather than duplicated across them.
 *
 * @package Uncanny_Automator\Integrations\Cartflows\Tokens
 */
class Cartflows_Tokens {

	/**
	 * Order and funnel tokens.
	 *
	 * @return array
	 */
	public function order_tokens() {
		return array(
			array(
				'tokenId'   => 'CF_ORDER_ID',
				'tokenName' => esc_html_x( 'Order ID', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CF_ORDER_TOTAL',
				'tokenName' => esc_html_x( 'Order total', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'CF_ORDER_STATUS',
				'tokenName' => esc_html_x( 'Order status', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CF_ORDER_PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment method', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CF_ORDER_COUPONS',
				'tokenName' => esc_html_x( 'Coupon codes', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CF_BILLING_FIRST_NAME',
				'tokenName' => esc_html_x( 'Billing first name', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CF_BILLING_LAST_NAME',
				'tokenName' => esc_html_x( 'Billing last name', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CF_BILLING_EMAIL',
				'tokenName' => esc_html_x( 'Billing email', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'CF_BILLING_PHONE',
				'tokenName' => esc_html_x( 'Billing phone', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'tel',
			),
			array(
				'tokenId'   => 'CF_PRODUCT_NAMES',
				'tokenName' => esc_html_x( 'Product names', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CF_PRODUCT_IDS',
				'tokenName' => esc_html_x( 'Product IDs', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CF_FLOW_ID',
				'tokenName' => esc_html_x( 'Flow ID', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CF_FLOW_TITLE',
				'tokenName' => esc_html_x( 'Flow title', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CF_STEP_ID',
				'tokenName' => esc_html_x( 'Checkout step ID', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CF_STEP_TITLE',
				'tokenName' => esc_html_x( 'Checkout step title', 'CartFlows', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the order and funnel tokens. Returns the full keyset (empty
	 * values for an unresolvable order) so a recipe never resolves a partial map.
	 *
	 * @param int $order_id The order ID.
	 * @param int $flow_id  The funnel ID.
	 * @param int $step_id  The checkout step ID.
	 *
	 * @return array
	 */
	public function hydrate_order_tokens( $order_id, $flow_id, $step_id ) {

		$tokens = array(
			'CF_ORDER_ID'             => 0,
			'CF_ORDER_TOTAL'          => '',
			'CF_ORDER_STATUS'         => '',
			'CF_ORDER_PAYMENT_METHOD' => '',
			'CF_ORDER_COUPONS'        => '',
			'CF_BILLING_FIRST_NAME'   => '',
			'CF_BILLING_LAST_NAME'    => '',
			'CF_BILLING_EMAIL'        => '',
			'CF_BILLING_PHONE'        => '',
			'CF_PRODUCT_NAMES'        => '',
			'CF_PRODUCT_IDS'          => '',
			'CF_FLOW_ID'              => absint( $flow_id ),
			'CF_FLOW_TITLE'           => 0 === absint( $flow_id ) ? '' : (string) get_the_title( absint( $flow_id ) ),
			'CF_STEP_ID'              => absint( $step_id ),
			'CF_STEP_TITLE'           => 0 === absint( $step_id ) ? '' : (string) get_the_title( absint( $step_id ) ),
		);

		$order = function_exists( 'wc_get_order' ) ? wc_get_order( absint( $order_id ) ) : false;

		if ( ! $order instanceof WC_Order ) {
			return $tokens;
		}

		$names = array();
		$ids   = array();

		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name();
			$ids[]   = $item->get_product_id();
		}

		$tokens['CF_ORDER_ID']             = absint( $order->get_id() );
		$tokens['CF_ORDER_TOTAL']          = (string) $order->get_total();
		$tokens['CF_ORDER_STATUS']         = (string) $order->get_status();
		$tokens['CF_ORDER_PAYMENT_METHOD'] = (string) $order->get_payment_method_title();
		$tokens['CF_ORDER_COUPONS']        = implode( ', ', (array) $order->get_coupon_codes() );
		$tokens['CF_BILLING_FIRST_NAME']   = (string) $order->get_billing_first_name();
		$tokens['CF_BILLING_LAST_NAME']    = (string) $order->get_billing_last_name();
		$tokens['CF_BILLING_EMAIL']        = (string) $order->get_billing_email();
		$tokens['CF_BILLING_PHONE']        = (string) $order->get_billing_phone();
		$tokens['CF_PRODUCT_NAMES']        = implode( ', ', $names );
		$tokens['CF_PRODUCT_IDS']          = implode( ', ', $ids );

		return $tokens;
	}
}
