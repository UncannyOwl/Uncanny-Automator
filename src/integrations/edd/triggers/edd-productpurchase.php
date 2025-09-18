<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class EDD_PRODUCTPURCHASE
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads
 * @method \Uncanny_Automator\Integrations\Easy_Digital_Downloads\EDD_Helpers get_item_helpers()
 */
class EDD_PRODUCTPURCHASE extends Trigger {

	/**
	 * Trigger code
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'EDD_PRODUCTPURCHASE';

	/**
	 * Trigger meta
	 *
	 * @var string
	 */
	const TRIGGER_META = 'EDDPRODUCT';

	/**
	 * Set up Automator trigger.
	 */
	protected function setup_trigger() {
		$this->set_integration( 'EDD' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->add_action( 'edd_complete_purchase', 10, 3 );
		// translators: %1$s: Download
		$this->set_sentence(
			sprintf( esc_html_x( 'A user purchases {{a download:%1$s}}', 'Easy Digital Downloads', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		$this->set_readable_sentence( esc_html_x( 'A user purchases {{a download}}', 'Easy Digital Downloads', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_item_helpers()->all_edd_downloads( esc_html_x( 'Download', 'Easy Digital Downloads', 'uncanny-automator' ), $this->get_trigger_meta(), true, false ),
		);
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
		return array(
			'EDDPRODUCT_DISCOUNT_CODES'  => array(
				'name'      => esc_html_x( 'Discount codes used', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDPRODUCT_DISCOUNT_CODES',
				'tokenName' => esc_html_x( 'Discount codes used', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT'                 => array(
				'name'      => esc_html_x( 'Download title', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDPRODUCT',
				'tokenName' => esc_html_x( 'Download title', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_ID'              => array(
				'name'      => esc_html_x( 'Download ID', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'EDDPRODUCT_ID',
				'tokenName' => esc_html_x( 'Download ID', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_URL'             => array(
				'name'      => esc_html_x( 'Download URL', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'EDDPRODUCT_URL',
				'tokenName' => esc_html_x( 'Download URL', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_THUMB_ID'        => array(
				'name'      => esc_html_x( 'Download featured image ID', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'EDDPRODUCT_THUMB_ID',
				'tokenName' => esc_html_x( 'Download featured image ID', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_THUMB_URL'       => array(
				'name'      => esc_html_x( 'Download featured image URL', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'EDDPRODUCT_THUMB_URL',
				'tokenName' => esc_html_x( 'Download featured image URL', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_LICENSE_KEY'     => array(
				'name'      => esc_html_x( 'License key', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDPRODUCT_LICENSE_KEY',
				'tokenName' => esc_html_x( 'License key', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_ORDER_DISCOUNTS' => array(
				'name'      => esc_html_x( 'Order discounts', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDPRODUCT_ORDER_DISCOUNTS',
				'tokenName' => esc_html_x( 'Order discounts', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_ORDER_SUBTOTAL'  => array(
				'name'      => esc_html_x( 'Order subtotal', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDPRODUCT_ORDER_SUBTOTAL',
				'tokenName' => esc_html_x( 'Order subtotal', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_ORDER_TAX'       => array(
				'name'      => esc_html_x( 'Order tax', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDPRODUCT_ORDER_TAX',
				'tokenName' => esc_html_x( 'Order tax', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_ORDER_TOTAL'     => array(
				'name'      => esc_html_x( 'Order total', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDPRODUCT_ORDER_TOTAL',
				'tokenName' => esc_html_x( 'Order total', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDDPRODUCT_PAYMENT_METHOD'  => array(
				'name'      => esc_html_x( 'Payment method', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDPRODUCT_PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment method', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
		);
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
		if ( empty( $hook_args ) || ! isset( $hook_args[0] ) ) {
			return false;
		}

		$payment_id = $hook_args[0];
		$cart_items = edd_get_payment_meta_cart_details( $payment_id );

		if ( ! class_exists( '\EDD_Payment' ) ) {
			return false;
		}

		$payment = new \EDD_Payment( $payment_id );

		if ( empty( $cart_items ) ) {
			return false;
		}

		$user_id = get_current_user_id();

		// Check if user is logged in
		if ( ! $user_id ) {
			return false;
		}

		// Set user ID for the trigger
		$this->set_user_id( $user_id );

		// Check if the selected product matches any item in the cart
		$selected_product = $trigger['meta'][ self::TRIGGER_META ];

		foreach ( $cart_items as $item ) {
			// Allow "Any product" option (-1) or specific product match
			if ( intval( '-1' ) === intval( $selected_product ) || absint( $selected_product ) === absint( $item['id'] ) ) {
				return true;
			}
		}

		return false;
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
		if ( empty( $hook_args ) || ! isset( $hook_args[0] ) ) {
			return array();
		}
		$payment_id = $hook_args[0];
		$payment    = new \EDD_Payment( $payment_id );
		$cart_items = edd_get_payment_meta_cart_details( $payment_id );

		// Find the matching item for token data
		$selected_product = $trigger['meta'][ self::TRIGGER_META ];
		$matching_item    = null;

		foreach ( $cart_items as $item ) {
			if ( intval( '-1' ) === intval( $selected_product ) || absint( $selected_product ) === absint( $item['id'] ) ) {
				$matching_item = $item;
				break;
			}
		}

		if ( ! $matching_item ) {
			return array();
		}

		$download_id = $matching_item['id'];

		// Get license key if Software Licensing plugin is active
		$license_key = '';
		if ( class_exists( '\EDD_Software_Licensing' ) ) {
			$license_key = $this->get_item_helpers()->get_licenses( $payment_id );
		}

		return array(
			'EDDPRODUCT_DISCOUNT_CODES'  => $payment->discounts,
			'EDDPRODUCT'                 => $matching_item['name'],
			'EDDPRODUCT_ID'              => $download_id,
			'EDDPRODUCT_URL'             => get_permalink( $download_id ),
			'EDDPRODUCT_THUMB_ID'        => get_post_thumbnail_id( $download_id ),
			'EDDPRODUCT_THUMB_URL'       => get_the_post_thumbnail_url( $download_id ),
			'EDDPRODUCT_LICENSE_KEY'     => $license_key,
			'EDDPRODUCT_ORDER_DISCOUNTS' => edd_currency_filter( edd_format_amount( $matching_item['discount'] ) ),
			'EDDPRODUCT_ORDER_SUBTOTAL'  => edd_currency_filter( edd_format_amount( $payment->subtotal ) ),
			'EDDPRODUCT_ORDER_TAX'       => edd_currency_filter( edd_format_amount( $payment->tax ) ),
			'EDDPRODUCT_ORDER_TOTAL'     => edd_currency_filter( edd_format_amount( $payment->total ) ),
			'EDDPRODUCT_PAYMENT_METHOD'  => $payment->gateway,
		);
	}
}
