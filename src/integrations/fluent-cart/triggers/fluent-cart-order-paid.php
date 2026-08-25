<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENT_CART_ORDER_PAID
 *
 * Fires on `fluent_cart/order_paid_done` (async, plugin-blessed for 3rd parties).
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 *
 * @property Fluent_Cart_Helpers $item_helpers
 */
class Fluent_Cart_Order_Paid extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'FLUENT_CART_ORDER_PAID', 'FLUENT_CART' )
			->trigger_type( 'user' )
			->trigger_meta( 'FLUENT_CART_PRODUCT' )
			->hook( 'fluent_cart/order_paid_done', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Product */
				esc_html_x( "A user's order for {{a product:%1\$s}} is paid", 'FluentCart', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "A user's order for {{a product}} is paid", 'FluentCart', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return $this->item_helpers->product_option_fields( $this->get_trigger_meta() );
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->order_tokens(), $this->item_helpers->customer_tokens() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$data  = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();
		$order = isset( $data['order'] ) ? $data['order'] : null;

		if ( empty( $order ) ) {
			return false;
		}

		$selected_product = $this->item_helpers->required_meta_value( $trigger, $this->get_trigger_meta() );

		// Required field: an absent value must not fall back to "any product",
		// which would widen the trigger to fire for every order.
		if ( null === $selected_product ) {
			return false;
		}

		// Optional field: absent genuinely means "no category filter", so the
		// sentinel is the correct default here.
		$selected_category = isset( $trigger['meta']['FLUENT_CART_PRODUCT_CATEGORY'] ) ? (string) $trigger['meta']['FLUENT_CART_PRODUCT_CATEGORY'] : Fluent_Cart_Helpers::ANY;

		if ( ! $this->item_helpers->order_matches_product( $order, $selected_product, $selected_category ) ) {
			return false;
		}

		$customer = isset( $data['customer'] ) && ! empty( $data['customer'] ) ? $data['customer'] : $this->item_helpers->prop( $order, 'customer', null );
		$user_id  = $this->item_helpers->get_user_id_from_customer( $customer );

		if ( 0 === $user_id ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$data     = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();
		$order    = isset( $data['order'] ) ? $data['order'] : null;
		$customer = isset( $data['customer'] ) && ! empty( $data['customer'] ) ? $data['customer'] : $this->item_helpers->prop( $order, 'customer', null );

		return array_merge(
			$this->item_helpers->hydrate_order_tokens( $order ),
			$this->item_helpers->hydrate_customer_tokens( $customer )
		);
	}
}
