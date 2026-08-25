<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENT_CART_SUBSCRIPTION_CANCELLED
 *
 * Fires on `fluent_cart/subscription_canceled`.
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 *
 * @property Fluent_Cart_Helpers $item_helpers
 */
class Fluent_Cart_Subscription_Cancelled extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'FLUENT_CART_SUBSCRIPTION_CANCELLED', 'FLUENT_CART' )
			->trigger_type( 'user' )
			->trigger_meta( 'FLUENT_CART_PRODUCT' )
			->hook( 'fluent_cart/subscription_canceled', 10, 1 );
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
				esc_html_x( "A user's subscription to {{a product:%1\$s}} is cancelled", 'FluentCart', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "A user's subscription to {{a product}} is cancelled", 'FluentCart', 'uncanny-automator' ) );
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
		return array_merge(
			$tokens,
			$this->item_helpers->subscription_tokens(),
			$this->item_helpers->customer_tokens(),
			array(
				array(
					'tokenId'   => 'FLUENT_CART_CANCELLATION_REASON',
					'tokenName' => esc_html_x( 'Cancellation reason', 'FluentCart', 'uncanny-automator' ),
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

		$selected_product = $this->item_helpers->required_meta_value( $trigger, $this->get_trigger_meta() );

		// Required field: an absent value must not fall back to "any product",
		// which would widen the trigger to fire for every order.
		if ( null === $selected_product ) {
			return false;
		}

		// Optional field: absent genuinely means "no category filter", so the
		// sentinel is the correct default here.
		$selected_category = isset( $trigger['meta']['FLUENT_CART_PRODUCT_CATEGORY'] ) ? (string) $trigger['meta']['FLUENT_CART_PRODUCT_CATEGORY'] : Fluent_Cart_Helpers::ANY;

		if ( ! $this->item_helpers->subscription_matches_product( $subscription, $selected_product, $selected_category ) ) {
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
				'FLUENT_CART_CANCELLATION_REASON' => isset( $data['reason'] ) ? (string) $data['reason'] : '',
			)
		);
	}
}
