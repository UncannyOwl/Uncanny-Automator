<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads\Tokens\Loopable\Universal;

use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;

/**
 * User_Orders
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads\Tokens\Loopable
 */
class User_Orders extends Universal_Loopable_Token {

	/**
	 * Register loopable token.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = array(
			'ORDER_ID'         => array(
				'name'       => esc_html_x( 'Order ID', 'EDD', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'ORDER_NUMBER'     => array(
				'name'       => esc_html_x( 'Order number', 'EDD', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ORDER_TOTAL'      => array(
				'name'       => esc_html_x( 'Order total', 'EDD', 'uncanny-automator' ),
				'token_type' => 'float',
			),
			'ORDER_SUBTOTAL'   => array(
				'name'       => esc_html_x( 'Order subtotal', 'EDD', 'uncanny-automator' ),
				'token_type' => 'float',
			),
			'ORDER_TAX'        => array(
				'name'       => esc_html_x( 'Order tax', 'EDD', 'uncanny-automator' ),
				'token_type' => 'float',
			),
			'ORDER_DISCOUNT'   => array(
				'name'       => esc_html_x( 'Order discount', 'EDD', 'uncanny-automator' ),
				'token_type' => 'float',
			),
			'ORDER_DATE'       => array(
				'name'       => esc_html_x( 'Order date', 'EDD', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ORDER_STATUS'     => array(
				'name'       => esc_html_x( 'Order status', 'EDD', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ORDER_TYPE'       => array(
				'name'       => esc_html_x( 'Order type', 'EDD', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ORDER_GATEWAY'    => array(
				'name'       => esc_html_x( 'Payment gateway', 'EDD', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ORDER_CURRENCY'   => array(
				'name'       => esc_html_x( 'Order currency', 'EDD', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ORDER_EMAIL'      => array(
				'name'       => esc_html_x( 'Order email', 'EDD', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ORDER_ITEMS'      => array(
				'name'       => esc_html_x( 'Order items (CSV)', 'EDD', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ORDER_ITEM_NAMES' => array(
				'name'       => esc_html_x( 'Order item names (CSV)', 'EDD', 'uncanny-automator' ),
				'token_type' => 'text',
			),
		);

		$this->set_id( 'EDD_USER_ORDERS' );
		$this->set_name( esc_html_x( "User's orders", 'EDD', 'uncanny-automator' ) );
		$this->set_log_identifier( '#Order: {{ORDER_NUMBER}} - {{ORDER_DATE}}' );
		$this->set_child_tokens( $child_tokens );
	}

	/**
	 * Hydrate the tokens.
	 *
	 * @param mixed $args
	 *
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $args ) {

		$loopable = new Loopable_Token_Collection();

		$orders = $this->get_user_orders( $args['user_id'] );

		foreach ( $orders as $order ) {

			$loopable->create_item(
				array(
					'ORDER_ID'         => $order['order_id'],
					'ORDER_NUMBER'     => $order['order_number'],
					'ORDER_TOTAL'      => $order['order_total'],
					'ORDER_SUBTOTAL'   => $order['order_subtotal'],
					'ORDER_TAX'        => $order['order_tax'],
					'ORDER_DISCOUNT'   => $order['order_discount'],
					'ORDER_DATE'       => $order['order_date'],
					'ORDER_STATUS'     => $order['order_status'],
					'ORDER_TYPE'       => $order['order_type'],
					'ORDER_GATEWAY'    => $order['order_gateway'],
					'ORDER_CURRENCY'   => $order['order_currency'],
					'ORDER_EMAIL'      => $order['order_email'],
					'ORDER_ITEMS'      => $order['order_items'],
					'ORDER_ITEM_NAMES' => $order['order_item_names'],
				)
			);
		}

		return $loopable;
	}

	/**
	 * Retrieves orders for a specific user in EDD.
	 *
	 * @param int $user_id The ID of the user whose orders are being retrieved.
	 * @return array|false An array of orders with details or false on failure.
	 */
	private function get_user_orders( $user_id ) {
		// Ensure EDD functions are available
		if ( ! function_exists( 'edd_get_orders' ) || ! function_exists( 'edd_get_customer_by' ) ) {
			return array(); // EDD is not active or not available.
		}

		//Validate the user ID
		$user_id = absint( $user_id );
		if ( empty( $user_id ) ) {
			$user_id = wp_get_current_user()->ID;
			if ( 0 === $user_id ) {
				return array(); // Invalid user ID provided.
			}
		}

		// First, get the customer ID for this user
		$customer = edd_get_customer_by( 'user_id', $user_id );
		if ( empty( $customer ) || empty( $customer->id ) ) {
			return array(); // No customer found for this user.
		}

		// Use EDD 3.0+ orders query with customer_id to get user orders
		$orders = edd_get_orders(
			array(
				'customer_id' => $customer->id,
				'number'      => 999, // Get all orders
				'orderby'     => 'date_created',
				'order'       => 'ASC', // Oldest first (chronological order)
			)
		);

		if ( empty( $orders ) ) {
			return array(); // No orders found for this user.
		}

		$orders_data = array();

		// Process each order (already sorted oldest first - chronological order)
		foreach ( $orders as $order ) {
			// Double-check that this order actually belongs to the user
			if ( absint( $order->user_id ) === absint( $user_id ) ) {

				// Get order items for this order
				$order_items  = $this->get_order_items( $order->id );
				$item_names   = array();
				$item_details = array();

				foreach ( $order_items as $item ) {
					$item_names[]   = $item['name'];
					$item_details[] = $item['name'] . ' (Qty: ' . $item['quantity'] . ')';
				}

				$orders_data[] = array(
					'order_id'         => $order->id,
					'order_number'     => $order->order_number ? $order->order_number : $order->id,
					'order_total'      => edd_format_amount( $order->total ),
					'order_subtotal'   => edd_format_amount( $order->subtotal ),
					'order_tax'        => edd_format_amount( $order->tax ),
					'order_discount'   => edd_format_amount( $order->discount ),
					'order_date'       => $order->date_created,
					'order_status'     => $order->status,
					'order_type'       => $order->type,
					'order_gateway'    => $order->gateway,
					'order_currency'   => $order->currency,
					'order_email'      => $order->email,
					'order_items'      => implode( ', ', $item_details ),
					'order_item_names' => implode( ', ', $item_names ),
				);
			}
		}

		// Return the orders data or false if no orders were found
		return ! empty( $orders_data ) ? $orders_data : array();
	}

	/**
	 * Retrieves order items for a specific order.
	 *
	 * @param int $order_id The ID of the order.
	 * @return array An array of order items with details.
	 */
	private function get_order_items( $order_id ) {
		// Ensure EDD functions are available
		if ( ! function_exists( 'edd_get_order_items' ) ) {
			return array();
		}

		$order_items = edd_get_order_items( array( 'order_id' => $order_id ) );

		if ( empty( $order_items ) ) {
			return array();
		}

		$items_data = array();

		foreach ( $order_items as $item ) {
			// Replace em dash with regular hyphen to avoid JSON encoding issues
			$product_name = str_replace( '—', '-', $item->product_name );

			$items_data[] = array(
				'name'     => $product_name,
				'quantity' => $item->quantity,
			);
		}

		return $items_data;
	}
}
