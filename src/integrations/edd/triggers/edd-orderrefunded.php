<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class EDD_ORDERREFUNDED
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads
 * @method \Uncanny_Automator\Integrations\Easy_Digital_Downloads\EDD_Helpers get_item_helpers()
 */
class EDD_ORDERREFUNDED extends Trigger {

	/**
	 * Trigger code
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'EDDORDERREFUND';

	/**
	 * Trigger meta
	 *
	 * @var string
	 */
	const TRIGGER_META = 'EDDORDERREFUNDED';

	/**
	 * Set up Automator trigger.
	 */
	protected function setup_trigger() {
		$this->set_integration( 'EDD' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->add_action( 'edds_payment_refunded', 10, 1 );
		$this->set_sentence( esc_html_x( "A user's Stripe payment is refunded", 'Easy Digital Downloads', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( "A user's Stripe payment is refunded", 'Easy Digital Downloads', 'uncanny-automator' ) );
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		// All tokens are already defined in edd-tokens.php, no need to add any here
		return $tokens;
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( empty( $hook_args ) ) {
			return false;
		}

		$order_id     = $hook_args[0];
		$order_detail = edd_get_payment( $order_id );

		if ( empty( $order_detail ) ) {
			return false;
		}

		$user_id = edd_get_payment_user_id( $order_detail->ID );

		if ( ! $user_id ) {
			$user_id = wp_get_current_user()->ID;
		}

		// Check if user is logged in
		if ( ! $user_id ) {
			return false;
		}

		// Set user ID for the trigger
		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$order_id       = $hook_args[0];
		$order_detail   = edd_get_payment( $order_id );
		$total_discount = 0;

		// Initialize default values using the same token IDs as edd-tokens.php
		$tokens = array(
			'EDDCUSTOMER_EMAIL'          => '',
			'EDDPRODUCT_DISCOUNT_CODES'  => '',
			'EDDPRODUCT_LICENSE_KEY'     => '',
			'EDDORDER_ID'                => $order_id,
			'EDDPRODUCT_ORDER_DISCOUNTS' => '0.00',
			'EDDORDER_SUBTOTAL'          => '0.00',
			'EDDPRODUCT_ORDER_TAX'       => '0.00',
			'EDDORDER_TOTAL'             => '0.00',
			'EDDORDER_ITEMS'             => '',
			'EDDPRODUCT_PAYMENT_METHOD'  => '',
		);

		// Return default values if order detail is not found
		if ( empty( $order_detail ) ) {
			return $tokens;
		}

		$item_names  = array();
		$order_items = edd_get_payment_meta_cart_details( $order_id );

		if ( ! empty( $order_items ) ) {
			foreach ( $order_items as $item ) {
				if ( isset( $item['name'] ) ) {
					$item_names[] = $item['name'];
				}
				// Sum the discount.
				if ( isset( $item['discount'] ) && is_numeric( $item['discount'] ) ) {
					$total_discount += $item['discount'];
				}
			}
		}

		// Get license key if Software Licensing plugin is active
		$license_key = '';
		if ( class_exists( '\EDD_Software_Licensing' ) ) {
			$license_key = $this->get_item_helpers()->get_licenses( $order_detail->ID );
		}

		// Update tokens with actual values using the same token IDs as edd-tokens.php
		$tokens['EDDCUSTOMER_EMAIL']          = isset( $order_detail->email ) ? $order_detail->email : '';
		$tokens['EDDPRODUCT_DISCOUNT_CODES']  = isset( $order_detail->discounts ) ? $order_detail->discounts : '';
		$tokens['EDDPRODUCT_LICENSE_KEY']     = $license_key;
		$tokens['EDDPRODUCT_ORDER_DISCOUNTS'] = edd_currency_filter( edd_format_amount( $total_discount ) );
		$tokens['EDDORDER_SUBTOTAL']          = isset( $order_detail->subtotal ) ? edd_currency_filter( edd_format_amount( $order_detail->subtotal ) ) : '0.00';
		$tokens['EDDPRODUCT_ORDER_TAX']       = isset( $order_detail->tax ) ? edd_currency_filter( edd_format_amount( $order_detail->tax ) ) : '0.00';
		$tokens['EDDORDER_TOTAL']             = isset( $order_detail->total ) ? edd_currency_filter( edd_format_amount( $order_detail->total ) ) : '0.00';
		$tokens['EDDORDER_ITEMS']             = implode( ', ', $item_names );
		$tokens['EDDPRODUCT_PAYMENT_METHOD']  = isset( $order_detail->gateway ) ? $order_detail->gateway : '';

		return $tokens;
	}
}
