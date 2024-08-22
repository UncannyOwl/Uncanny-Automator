<?php

namespace Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal;

use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;

/**
 * User_Purchased_Product
 *
 * @package Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable
 */
class User_Purchase_Products extends Universal_Loopable_Token {

	/**
	 * Register loopable token.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = array(
			'ORDER_ID'            => array(
				'name'       => _x( 'Order ID', 'Woo', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'PRODUCT_ID'          => array(
				'name'       => _x( 'Product ID', 'Woo', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'PRODUCT_NAME'        => array(
				'name' => _x( 'Product name', 'Woo', 'uncanny-automator' ),
			),
			'PRODUCT_PRICE'       => array(
				'name' => _x( 'Product price', 'Woo', 'uncanny-automator' ),
			),
			'PRODUCT_DESCRIPTION' => array(
				'name'       => _x( 'Product description', 'Woo', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
		);

		$this->set_id( 'PURCHASED_PRODUCT' );
		$this->set_name( _x( "User's purchased products", 'Woo', 'uncanny-automator' ) );
		$this->set_log_identifier( '#{{PRODUCT_ID}} {{PRODUCT_NAME}}' );
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

		$order_items = $this->get_user_order_items( $args['user_id'] );

		foreach ( $order_items as $product ) {

			$loopable->create_item(
				array(
					'ODER_ID'             => $product['order_id'],
					'PRODUCT_ID'          => $product['product_id'],
					'PRODUCT_NAME'        => $product['name'],
					'PRODUCT_PRICE'       => $product['price'],
					'PRODUCT_DESCRIPTION' => htmlentities( $product['description'] ),
				)
			);
		}

		return $loopable;

	}

	/*
	 * Retrieves unique order items for a specific user in WooCommerce.
	 *
	 * @param int $user_id The ID of the user whose order items are being retrieved.
	 * @return array|false An array of unique products with details or false on failure.
	 */
	private function get_user_order_items( $user_id ) {
		// Ensure WooCommerce functions are available
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false; // WooCommerce is not active or not available.
		}

		// Validate the user ID
		if ( ! is_int( $user_id ) || $user_id <= 0 ) {
			return false; // Invalid user ID provided.
		}

		// Retrieve all orders for the specified user ID
		$customer_query  = new \WC_Order_Query(
			array(
				'limit'       => 999999, // Set to -1 to return all orders
				'customer_id' => $user_id,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'objects', // Can be 'ids', 'objects', or 'both'
			)
		);
		$customer_orders = $customer_query->get_orders();

		// Return false if no orders found
		if ( empty( $customer_orders ) ) {
			return false; // No orders found for this user.
		}

		$order_items = array();
		$product_ids = array();

		// Loop through each order and gather unique product details
		foreach ( $customer_orders as $order ) {

			if ( ! $order ) {
				continue; // Skip if the order is not found.
			}

			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product();

				if ( ! $product ) {
					continue; // Skip if the product is not found or no longer exists.
				}

				$product_id = $product->get_id();

				// Skip duplicate products
				if ( in_array( $product_id, $product_ids ) ) {
					continue;
				}

				$product_ids[] = $product_id;

				$order_items[] = array(
					'order_id'    => $order->get_id(),
					'product_id'  => $product_id,
					'name'        => $product->get_name(),
					'price'       => $product->get_price(),
					'description' => $product->get_description(),
					'quantity'    => $item->get_quantity(),
				);
			}
		}

		// Return the unique order items data or false if no items were found
		return ! empty( $order_items ) ? $order_items : false;
	}


}
