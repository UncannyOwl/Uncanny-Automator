<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class EDD_ANON_PRODUCT_PURCHASE
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads
 * @method \Uncanny_Automator\Integrations\Easy_Digital_Downloads\EDD_Helpers get_item_helpers()
 */
class EDD_ANON_PRODUCT_PURCHASE extends Trigger {

	/**
	 * Trigger code
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'EDD_ANON_PURCHASE';

	/**
	 * Trigger meta
	 *
	 * @var string
	 */
	const TRIGGER_META = 'EDD_PRODUCTS';

	/**
	 * Set up Automator trigger.
	 */
	protected function setup_trigger() {
		$this->set_integration( 'EDD' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/easy-digital-downloads/' ) );
		// translators: %1$s: Download
		$this->set_sentence(
			sprintf( esc_html_x( 'A customer purchases {{a download:%1$s}}', 'Easy Digital Downloads', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_html_x( 'A customer purchases {{a download}}', 'Easy Digital Downloads', 'uncanny-automator' ) );
		$this->add_action( 'edd_complete_purchase', 10, 3 );
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

		if ( empty( $cart_items ) ) {
			return false;
		}

		// Check if the selected download matches any item in the cart
		$selected_download = $trigger['meta'][ self::TRIGGER_META ];

		foreach ( $cart_items as $item ) {
			// Allow "Any download" option (-1) or specific download match
			if ( intval( '-1' ) === intval( $selected_download ) || absint( $selected_download ) === absint( $item['id'] ) ) {
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
		$selected_download = $trigger['meta'][ self::TRIGGER_META ];
		$matching_item     = null;

		foreach ( $cart_items as $item ) {
			if ( intval( '-1' ) === intval( $selected_download ) || absint( $selected_download ) === absint( $item['id'] ) ) {
				$matching_item = $item;
				break;
			}
		}

		if ( ! $matching_item ) {
			return array();
		}

		$download_id = $matching_item['id'];

		// Get customer info
		$customer_info = $payment->user_info;
		if ( is_array( $customer_info ) ) {
			$first_name = isset( $customer_info['first_name'] ) ? $customer_info['first_name'] : '';
			$last_name  = isset( $customer_info['last_name'] ) ? $customer_info['last_name'] : '';
			$email      = isset( $customer_info['email'] ) ? $customer_info['email'] : '';
		} else {
			$first_name = '';
			$last_name  = '';
			$email      = '';
		}

		// Get customer address
		$address         = $payment->address;
		$address_line1   = isset( $address['line1'] ) ? $address['line1'] : '';
		$address_line2   = isset( $address['line2'] ) ? $address['line2'] : '';
		$address_city    = isset( $address['city'] ) ? $address['city'] : '';
		$address_state   = isset( $address['state'] ) ? $address['state'] : '';
		$address_country = isset( $address['country'] ) ? $address['country'] : '';
		$address_zip     = isset( $address['zip'] ) ? $address['zip'] : '';

		// Get license key if Software Licensing plugin is active
		$license_key = '';
		if ( class_exists( '\EDD_Software_Licensing' ) ) {
			$license_key = $this->get_item_helpers()->get_licenses( $payment_id );
		}

		return array(
			'EDD_DOWNLOAD_ID'              => $download_id,
			'EDD_DOWNLOAD_NAME'            => $matching_item['name'],
			'EDD_DOWNLOAD_URL'             => get_permalink( $download_id ),
			'EDD_DOWNLOAD_THUMB_ID'        => get_post_thumbnail_id( $download_id ),
			'EDD_DOWNLOAD_THUMB_URL'       => get_the_post_thumbnail_url( $download_id ),
			'EDD_DOWNLOAD_PRICE'           => edd_currency_filter( edd_format_amount( $matching_item['price'] ) ),
			'EDD_DOWNLOAD_QUANTITY'        => $matching_item['quantity'],
			'EDD_DOWNLOAD_SUBTOTAL'        => edd_currency_filter( edd_format_amount( $matching_item['subtotal'] ) ),
			'EDD_DOWNLOAD_TAX'             => edd_currency_filter( edd_format_amount( $matching_item['tax'] ) ),
			'EDD_PAYMENT_KEY'              => $payment->key,
			'EDD_PAYMENT_ID'               => $payment->ID,
			'EDD_PAYMENT_SUBTOTAL'         => edd_currency_filter( edd_format_amount( $payment->subtotal ) ),
			'EDD_PAYMENT_TOTAL'            => edd_currency_filter( edd_format_amount( $payment->total ) ),
			'EDD_PAYMENT_TAX'              => edd_currency_filter( edd_format_amount( $payment->tax ) ),
			'EDD_PAYMENT_DISCOUNT'         => edd_currency_filter( edd_format_amount( $matching_item['discount'] ) ),
			'EDD_PAYMENT_STATUS'           => $payment->status_nicename,
			'EDD_PAYMENT_GATEWAY'          => $payment->gateway,
			'EDD_PAYMENT_CURRENCY'         => $payment->currency,
			'EDD_CUSTOMER_ID'              => $payment->customer_id,
			'EDD_CUSTOMER_FIRSTNAME'       => $first_name,
			'EDD_CUSTOMER_LASTNAME'        => $last_name,
			'EDD_CUSTOMER_EMAIL'           => $email,
			'EDD_CUSTOMER_ADDRESS_LINE1'   => $address_line1,
			'EDD_CUSTOMER_ADDRESS_LINE2'   => $address_line2,
			'EDD_CUSTOMER_ADDRESS_CITY'    => $address_city,
			'EDD_CUSTOMER_ADDRESS_STATE'   => $address_state,
			'EDD_CUSTOMER_ADDRESS_COUNTRY' => $address_country,
			'EDD_CUSTOMER_ADDRESS_ZIP'     => $address_zip,
		);
	}
}
