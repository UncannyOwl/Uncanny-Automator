<?php

namespace Uncanny_Automator;

/**
 * Class SURECART_ORDER_SHIPPED
 *
 * @package Uncanny_Automator
 */
class SURECART_ORDER_SHIPPED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @var SureCart_Tokens_New_Framework
	 */
	public $surecart_tokens;

	/**
	 * @var SureCart_Helpers
	 */
	public $helpers;

	/**
	 *
	 */
	private $checkout;

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->helpers         = new SureCart_Helpers();
		$this->surecart_tokens = new SureCart_Tokens_New_Framework();
		$this->set_integration( 'SURECART' );
		$this->set_trigger_code( 'ORDER_SHIPPED' );
		$this->set_trigger_meta( 'PRODUCT' );
		$this->set_support_link( $this->helpers->support_link( $this->trigger_code ) );
		$this->set_is_login_required( false );

		/* Translators: Product name */
		$this->set_sentence( sprintf( 'An order for {{a product:%1$s}} is shipped', $this->get_trigger_meta() ) );

		$this->set_readable_sentence( 'An order for {{a product}} is shipped' );

		$this->add_action( 'surecart/order_shipped', 10, 1 );

		$this->set_trigger_type( 'anonymous' );
	}

	/**
	 * Method options
	 *
	 * @return void
	 */
	public function options() {
		return array( $this->helpers->get_products_dropdown() );
	}

	/**
	 * define_tokens
	 *
	 * @param  array $trigger
	 * @param  array $tokens
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			$this->surecart_tokens->common_tokens(),
			$this->surecart_tokens->order_tokens()
		);
	}


	/**
	 * validate
	 *
	 * @param  array $trigger
	 * @param  array $hook_args
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$product_id = $trigger['meta'][ $this->get_trigger_meta() ];

		list( $order ) = $hook_args;

		$this->checkout = \SureCart\Models\Checkout::with( array( 'purchases', 'purchase.product', 'purchase.line_items' ) )->find( $order->checkout );

		if ( intval( '-1' ) === intval( $product_id ) ) {
			return true;
		}

		foreach ( $this->checkout->purchases->data as $purchase_data ) {

			$product = $purchase_data->product;

			if ( $product->id === $product_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Method hydrate_tokens.
	 *
	 * @param $parsed
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $order ) = $hook_args;

		$product_ids = array();

		foreach ( $this->checkout->purchases->data as $purchase_data ) {

			$product = $purchase_data->product;

			$product_ids[] = $product->id;
		}

		$trigger_tokens = array(
			'PRODUCT' => implode( ', ', $product_ids ),
		);

		$common_tokens = $this->surecart_tokens->hydrate_common_tokens();

		$order_tokens = $this->surecart_tokens->hydrate_order_tokens( $this->checkout );

		return $trigger_tokens + $common_tokens + $order_tokens;
	}
}
