<?php

namespace Uncanny_Automator\Recipe;

/**
 * Class Automator_Trigger_Condition_Helpers
 * @package Uncanny_Automator\Recipe
 */
class Automator_Trigger_Condition_Helpers {

	/**
	 * @param $order
	 * @param string $return_field
	 *
	 * @return array|bool
	 */
	public function woo_order_items( $order, $return_field = 'id' ) {
		if ( ! $order instanceof \WC_Order ) {
			return array();
		}
		$items    = $order->get_items();
		$products = array();
		/** @var \WC_Order_Item_Product $item */
		foreach ( $items as $item ) {
			$product    = $item->get_product();
			$products[] = array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
				'sku'  => $product->get_sku(),
			);
		}
		\Uncanny_Automator\Utilities::log( $products, '$products', false, '$products' );
		\Uncanny_Automator\Utilities::log( array_column( $products, $return_field ), 'array_column( $products, $return_field )', false, '$products' );

		return ! empty( $products ) ? array_column( $products, $return_field ) : array();
	}
}
