<?php

namespace Uncanny_Automator\Integrations\SureCart;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class SURECART_PURCHASE_PRODUCT
 *
 * @package Uncanny_Automator
 */
class SURECART_PURCHASE_PRODUCT extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'PURCHASE_PRODUCT';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'PRODUCT';

	/**
	 * @method \Uncanny_Automator\Integrations\SureCart\SureCart_Helpers get_item_helpers()
	 */

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
		$this->add_action( 'surecart/purchase_created', 10, 2 );

		// translators: %1$s: SureCart Product
		$this->set_sentence( sprintf( esc_html_x( 'A user purchases {{a product:%1$s}}', 'SureCart', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user purchases {{a product}}', 'SureCart', 'uncanny-automator' ) );
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
		list( $purchase, $webhook_data ) = $hook_args;
		$product_id                      = $trigger['meta'][ $this->get_trigger_meta() ];

		if ( empty( $purchase ) || empty( $purchase->id ) ) {
			return false;
		}

		// Extract user_id from purchase data if available
		$user_id = null;
		if ( isset( $purchase->customer ) && ! empty( $purchase->customer ) ) {
			$user_id = $this->get_item_helpers()->get_user_id_from_customer( $purchase->customer );
		}

		// Fall back to current user if no user found in hook data
		if ( empty( $user_id ) ) {
			$user_id = wp_get_current_user_id();
		}

		// Set the user_id for the trigger
		$this->set_user_id( $user_id );

		// Duplicate check - prevent the same purchase + product from triggering multiple times
		$purchase_id         = $purchase->id ?? '';
		$purchase_product_id = $purchase->product ?? '';
		$trigger_id          = $trigger['ID'] ?? '';

		if ( ! empty( $purchase_id ) && ! empty( $purchase_product_id ) && ! empty( $trigger_id ) ) {
			// Create a unique deduplication key based on purchase + product + trigger ID
			// This ensures the same purchase + product can't trigger the same trigger multiple times
			// but allows different triggers to fire for the same purchase + product
			$dedup_key = 'automator_surecart_purchase_' . $trigger_id . '_' . $purchase_id . '_' . $purchase_product_id;

			// Check if this purchase + product + trigger combination was already processed
			if ( get_transient( $dedup_key ) ) {
				return false; // Return false to prevent trigger
			}

			// Set transient to prevent duplicate processing
			// Use a longer timeout (5 minutes) to handle potential webhook delays
			// while still preventing duplicate executions
			set_transient( $dedup_key, 1, 5 * MINUTE_IN_SECONDS );
		}

		// If "Any product" is selected (-1), always return true
		if ( intval( '-1' ) === intval( $product_id ) ) {
			return true;
		}

		// Check if the purchased product matches the selected product (string comparison)
		$product_matches = (string) $purchase->product === (string) $product_id;

		return $product_matches;
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
		list( $purchase, $webhook_data ) = $hook_args;

		$tokens = array();

		if ( $purchase ) {
			// Use existing token hydration methods
			$surecart_tokens = new \Uncanny_Automator\Integrations\SureCart\SureCart_Tokens_New_Framework();

			// Get common tokens
			$common_tokens = $surecart_tokens->hydrate_common_tokens();
			$tokens        = array_merge( $tokens, $common_tokens );

			// Get product tokens
			$product_tokens = $surecart_tokens->hydrate_product_tokens( $purchase );
			$tokens         = array_merge( $tokens, $product_tokens );

			// Add specific product ID for this trigger
			$tokens['PRODUCT'] = $purchase->product;
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
			$surecart_tokens->product_tokens(),
			$surecart_tokens->shipping_tokens(),
			$surecart_tokens->billing_tokens(),
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
