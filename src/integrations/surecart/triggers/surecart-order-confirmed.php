<?php

namespace Uncanny_Automator\Integrations\SureCart;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class SURECART_ORDER_CONFIRMED
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\SureCart\SureCart_Helpers get_item_helpers()
 */
class SURECART_ORDER_CONFIRMED extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'ORDER_CONFIRMED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'ORDER_CONFIRMED_META';

	/**
	 * Setup trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'SURECART' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'user' );

		$this->add_action( 'surecart/checkout_confirmed', 10, 2 );

		$this->set_sentence( esc_html_x( "A user's order status is changed to confirmed", 'SureCart', 'uncanny-automator' ) );

		$this->set_readable_sentence(
			esc_html_x( "A user's order status is changed to confirmed", 'SureCart', 'uncanny-automator' )
		);
	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function options() {
		return array();
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool True if validation was successful.
	 */
	public function validate( $trigger, $hook_args ) {
		list( $checkout ) = $hook_args;

		// Extract user_id from checkout data if available
		$user_id = null;
		if ( isset( $checkout->customer_id ) && ! empty( $checkout->customer_id ) ) {
			$user_id = $this->get_item_helpers()->get_user_id_from_customer( $checkout->customer_id );
		}

		// Fall back to current user if no user found in hook data
		if ( empty( $user_id ) ) {
			$user_id = wp_get_current_user_id();
		}

		// Set the user_id for the trigger
		$this->set_user_id( $user_id );

		if ( 'paid' === $checkout->status ) {
			return true;
		}

		return false;
	}

	/**
	 * Hydrate tokens with values.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array The token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $checkout, $webhook_data ) = $hook_args;

		// Get the checkout with related data
		/** @var \SureCart\Models\Checkout $checkout_data */
		$checkout_data = class_exists( 'SureCart\Models\Checkout' ) ? \SureCart\Models\Checkout::with( array( 'purchases', 'purchase.product', 'purchase.line_items' ) )->find( $checkout->id ) : null;

		$tokens = array();

		if ( $checkout_data ) {
			// Use existing token hydration methods
			$surecart_tokens = new \Uncanny_Automator\Integrations\SureCart\SureCart_Tokens_New_Framework();

			// Get common tokens
			$common_tokens = $surecart_tokens->hydrate_common_tokens();
			$tokens        = array_merge( $tokens, $common_tokens );

			// Get order tokens
			$order_tokens = $surecart_tokens->hydrate_order_tokens( $checkout_data );
			$tokens       = array_merge( $tokens, $order_tokens );
		}

		return $tokens;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $tokens The existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		// Use existing token definitions
		$surecart_tokens = new \Uncanny_Automator\Integrations\SureCart\SureCart_Tokens_New_Framework();

		$custom_tokens = array_merge(
			$surecart_tokens->common_tokens(),
			$surecart_tokens->order_tokens()
		);

		return array_merge( $tokens, $custom_tokens );
	}
}
