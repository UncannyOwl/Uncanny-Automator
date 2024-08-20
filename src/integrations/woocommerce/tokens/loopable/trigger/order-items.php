<?php
namespace Uncanny_Automator\Integrations\Woocommerce\Tokens\Trigger\Loopable;

use Exception;
use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Trigger_Loopable_Token;

/**
 * Loopable Order Items.
 *
 * @since 5.10
 *
 * @package Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable
 */
class Order_Items extends Trigger_Loopable_Token {

	/**
	 * Register loopable tokens.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = array(
			'ID'         => array(
				'name'       => _x( 'Item ID', 'Woo', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'TOTAL'      => array(
				'name' => _x( 'Total', 'Woo', 'uncanny-automator' ),
			),
			'SUBTOTAL'   => array(
				'name'       => _x( 'Subtotal', 'Woo', 'uncanny-automator' ),
				'token_type' => 'float',
			),
			'TAX'        => array(
				'name'       => _x( 'Tax', 'Woo', 'uncanny-automator' ),
				'token_type' => 'float',
			),
			'NAME'       => array(
				'name' => _x( 'Product name', 'Woo', 'uncanny-automator' ),
			),
			'PRODUCT_ID' => array(
				'name'       => _x( 'Product ID', 'Woo', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
		);

		$this->set_id( 'ORDER_ITEMS' );
		$this->set_name( _x( 'Order items', 'Woo', 'uncanny-automator' ) );
		$this->set_log_identifier( '#{{PRODUCT_ID}} {{NAME}}' );
		$this->set_child_tokens( $child_tokens );

	}

	/**
	 * Hydrate the tokens.
	 *
	 * @param mixed $trigger_args
	 *
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $trigger_args ) {

		$loopable = new Loopable_Token_Collection();

		try {
			$order = $this->get_order( $trigger_args );
		} catch ( Exception $e ) {
			automator_log( $e->getMessage() );
			return $loopable;
		}

		// Loop through order items
		foreach ( (array) $order->get_items() as $item_id => $item ) {

			$product = $item->get_product();

			if ( $product ) {
				$loopable->create_item(
					array(
						'ID'         => $item_id,
						'SUBTOTAL'   => $item->get_subtotal(),
						'TOTAL'      => $item->get_total(),
						'TAX'        => $item->get_total_tax(),
						'NAME'       => $product->get_name(),
						'PRODUCT_ID' => $product->get_id(),
					)
				);
			}
		}

		return $loopable;

	}

	/**
	 * Retrieve a specific order.
	 *
	 * @param mixed $trigger_args
	 * @return bool|WC_Order|WC_Order_Refund
	 * @throws Exception
	 */
	private function get_order( $trigger_args ) {

		if ( ! function_exists( 'wc_get_order' ) ) {
			throw new \Exception( 'Order is not found', 400 );
		}

		// The order id.
		$order_id = $trigger_args[0] ?? null;

		// Get an instance of the WC_Order object.
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new \Exception( 'Order is not found', 400 );
		}

		return $order;

	}

}
