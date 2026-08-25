<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENT_CART_ORDER_REFUNDED
 *
 * Fires on `fluent_cart/order_refunded` ($data['type'] is 'full' or 'partial').
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 *
 * @property Fluent_Cart_Helpers $item_helpers
 */
class Fluent_Cart_Order_Refunded extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'FLUENT_CART_ORDER_REFUNDED', 'FLUENT_CART' )
			->trigger_type( 'user' )
			->trigger_meta( 'FLUENT_CART_REFUND_TYPE' )
			->hook( 'fluent_cart/order_refunded', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Refund type */
				esc_html_x( "A user's order is {{fully or partially:%1\$s}} refunded", 'FluentCart', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "A user's order is {{fully or partially}} refunded", 'FluentCart', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->refund_type_field( $this->get_trigger_meta() ),
		);
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			$this->item_helpers->order_tokens(),
			$this->item_helpers->customer_tokens(),
			array(
				array(
					'tokenId'   => 'FLUENT_CART_REFUND_AMOUNT',
					'tokenName' => esc_html_x( 'Refund amount', 'FluentCart', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'FLUENT_CART_REFUND_TYPE_VALUE',
					'tokenName' => esc_html_x( 'Refund type', 'FluentCart', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
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

		$selected_type = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? (string) $trigger['meta'][ $this->get_trigger_meta() ] : Fluent_Cart_Helpers::ANY;
		$refund_type   = isset( $data['type'] ) ? (string) $data['type'] : '';

		if ( Fluent_Cart_Helpers::ANY !== $selected_type && $selected_type !== $refund_type ) {
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
			$this->item_helpers->hydrate_customer_tokens( $customer ),
			array(
				'FLUENT_CART_REFUND_AMOUNT'     => isset( $data['refunded_amount'] ) ? $this->item_helpers->format_amount( $data['refunded_amount'] ) : '',
				'FLUENT_CART_REFUND_TYPE_VALUE' => isset( $data['type'] ) ? (string) $data['type'] : '',
			)
		);
	}
}
