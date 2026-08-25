<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENT_CART_SUBSCRIPTION_STATUS_CHANGED
 *
 * Fires on `fluent_cart/payments/subscription_status_changed` and matches the
 * selected status against `$data['new_status']`.
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 *
 * @property Fluent_Cart_Helpers $item_helpers
 */
class Fluent_Cart_Subscription_Status_Changed extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'FLUENT_CART_SUBSCRIPTION_STATUS_CHANGED', 'FLUENT_CART' )
			->trigger_type( 'user' )
			->trigger_meta( 'FLUENT_CART_SUBSCRIPTION_STATUS' )
			->hook( 'fluent_cart/payments/subscription_status_changed', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Subscription status */
				esc_html_x( "A user's subscription changes to {{a status:%1\$s}}", 'FluentCart', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "A user's subscription changes to {{a status}}", 'FluentCart', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->subscription_status_field( $this->get_trigger_meta() ),
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
			$this->item_helpers->subscription_tokens(),
			$this->item_helpers->customer_tokens(),
			array(
				array(
					'tokenId'   => 'FLUENT_CART_SUBSCRIPTION_OLD_STATUS',
					'tokenName' => esc_html_x( 'Old subscription status', 'FluentCart', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'FLUENT_CART_SUBSCRIPTION_NEW_STATUS',
					'tokenName' => esc_html_x( 'New subscription status', 'FluentCart', 'uncanny-automator' ),
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

		$data         = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();
		$subscription = isset( $data['subscription'] ) ? $data['subscription'] : null;

		if ( empty( $subscription ) ) {
			return false;
		}

		$selected_status = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? (string) $trigger['meta'][ $this->get_trigger_meta() ] : Fluent_Cart_Helpers::ANY;
		$new_status      = isset( $data['new_status'] ) ? (string) $data['new_status'] : '';

		if ( Fluent_Cart_Helpers::ANY !== $selected_status && $selected_status !== $new_status ) {
			return false;
		}

		$order    = isset( $data['order'] ) ? $data['order'] : null;
		$customer = isset( $data['customer'] ) && ! empty( $data['customer'] ) ? $data['customer'] : $this->item_helpers->prop( $order, 'customer', null );
		$user_id  = $this->item_helpers->get_user_id_from_customer( $customer );

		// No customer in the payload — resolve through the subscription's own link.
		if ( 0 === $user_id ) {
			$user_id = $this->item_helpers->get_user_id_from_subscription( $subscription );
		}

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

		$data         = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();
		$subscription = isset( $data['subscription'] ) ? $data['subscription'] : null;
		$order        = isset( $data['order'] ) ? $data['order'] : null;
		$customer     = isset( $data['customer'] ) && ! empty( $data['customer'] ) ? $data['customer'] : $this->item_helpers->prop( $order, 'customer', null );

		// Same fallback as validate(), so the customer tokens are not left blank.
		if ( empty( $customer ) ) {
			$customer = $this->item_helpers->get_customer_from_subscription( $subscription );
		}

		return array_merge(
			$this->item_helpers->hydrate_subscription_tokens( $subscription ),
			$this->item_helpers->hydrate_customer_tokens( $customer ),
			array(
				'FLUENT_CART_SUBSCRIPTION_OLD_STATUS' => isset( $data['old_status'] ) ? (string) $data['old_status'] : '',
				'FLUENT_CART_SUBSCRIPTION_NEW_STATUS' => isset( $data['new_status'] ) ? (string) $data['new_status'] : '',
			)
		);
	}
}
