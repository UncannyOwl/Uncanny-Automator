<?php

namespace Uncanny_Automator\Integrations\SureCart;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class SURECART_ORDER_SHIPPED
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\SureCart\SureCart_Helpers get_item_helpers()
 */
class SURECART_ORDER_SHIPPED extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'ORDER_SHIPPED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'PRODUCT';

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
		$this->set_trigger_type( 'anonymous' );
		$this->add_action( 'surecart/order_shipped', 10, 1 );

		// translators: %1$s: SureCart Product
		$this->set_sentence( sprintf( esc_html_x( 'An order for {{a product:%1$s}} is shipped', 'SureCart', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'An order for {{a product}} is shipped', 'SureCart', 'uncanny-automator' ) );
	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Product', 'SureCart', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $this->get_item_helpers()->get_products_dropdown_options(),
				'relevant_tokens' => array(
					$this->get_trigger_meta() => esc_html_x( 'Selected product', 'SureCart', 'uncanny-automator' ),
				),
			),
		);
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
		$product_id    = $trigger['meta'][ $this->get_trigger_meta() ];
		list( $order ) = $hook_args;

		/** @var \SureCart\Models\Checkout $checkout */
		$checkout = class_exists( 'SureCart\Models\Checkout' ) ? \SureCart\Models\Checkout::with( array( 'purchases', 'purchase.product', 'purchase.line_items', 'customer' ) )->find( $order->checkout ) : null;

		// Extract user_id from checkout data if available
		$user_id = null;
		if ( $checkout && isset( $checkout->customer ) && ! empty( $checkout->customer->id ) ) {
			$user_id = $this->get_item_helpers()->get_user_id_from_customer( $checkout->customer->id );
		}

		// Fall back to current user if no user found in hook data
		if ( empty( $user_id ) ) {
			$user_id = wp_get_current_user_id();
		}

		// Set the user_id for the trigger
		$this->set_user_id( $user_id );

		if ( intval( '-1' ) === intval( $product_id ) ) {
			return true;
		}

		foreach ( $checkout->purchases->data as $purchase_data ) {
			if ( isset( $purchase_data->product ) && ! empty( $purchase_data->product->id ) ) {
				if ( (string) $purchase_data->product->id === (string) $product_id ) {
					return true;
				}
			}
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
		list( $order ) = $hook_args;

		/** @var \SureCart\Models\Checkout $checkout */
		$checkout = class_exists( 'SureCart\Models\Checkout' ) ? \SureCart\Models\Checkout::with( array( 'purchases', 'purchase.product', 'purchase.line_items', 'customer' ) )->find( $order->checkout ) : null;

		$tokens = array();

		if ( $checkout ) {
			// Use existing token hydration methods
			$surecart_tokens = new \Uncanny_Automator\Integrations\SureCart\SureCart_Tokens_New_Framework();

			// Get common tokens
			$common_tokens = $surecart_tokens->hydrate_common_tokens();
			$tokens        = array_merge( $tokens, $common_tokens );

			// Get order tokens
			$order_tokens = $surecart_tokens->hydrate_order_tokens( $checkout );
			$tokens       = array_merge( $tokens, $order_tokens );

			// Add product information for this specific trigger
			$product_ids = array();
			foreach ( $checkout->purchases->data as $purchase_data ) {
				$product       = $purchase_data->product;
				$product_ids[] = $product->id;
			}
			$tokens['PRODUCT'] = implode( ', ', $product_ids );
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
			$surecart_tokens->order_tokens(),
			array(
				array(
					'tokenId'   => 'PRODUCT',
					'tokenName' => esc_html_x( 'Product', 'SureCart', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);

		return array_merge( $tokens, $custom_tokens );
	}
}
