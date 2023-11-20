<?php

namespace Uncanny_Automator\Integrations\WooCommerce_Bookings;

use Uncanny_Automator\Wc_Tokens;

/**
 * Class Wc_Bookings_Helpers
 *
 * @package Uncanny_Automator
 */
class Wc_Bookings_Helpers {

	/**
	 * Booking common tokens
	 *
	 * @return array[]
	 */
	public function wcb_booking_common_tokens() {
		$wc_tokens    = new Wc_Tokens();
		$order_tokens = $wc_tokens->possible_order_fields;
		$fields       = array();
		foreach ( $order_tokens as $token_id => $input_title ) {
			if ( 'billing_email' === (string) $token_id || 'shipping_email' === (string) $token_id ) {
				$input_type = 'email';
			} elseif ( 'order_qty' === (string) $token_id ) {
				$input_type = 'int';
			} else {
				$input_type = 'text';
			}
			$fields[] = array(
				'tokenId'   => $token_id,
				'tokenName' => $input_title,
				'tokenType' => $input_type,
			);
		}

		$tokens = array(
			array(
				'tokenId'   => 'WCB_BOOKING_ORDER_ID',
				'tokenName' => __( 'Booking order ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'WCB_BOOKING_ID',
				'tokenName' => __( 'Booking ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'WCB_CUSTOMER_EMAIL',
				'tokenName' => __( 'Customer email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'WCB_CUSTOMER_NAME',
				'tokenName' => __( 'Customer name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WCB_PRODUCT_TITLE',
				'tokenName' => __( 'Booking product title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WCB_PRODUCT_URL',
				'tokenName' => __( 'Booking product URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'WCB_PRODUCT_DETAILS',
				'tokenName' => __( 'Booking details', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WCB_PRODUCT_PRICE',
				'tokenName' => __( 'Booking product price', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WCB_BOOKING_START',
				'tokenName' => __( 'Booking start', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'WCB_BOOKING_END',
				'tokenName' => __( 'Booking end', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'WCB_BOOKING_STATUS',
				'tokenName' => __( 'Booking status', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $tokens, $fields );
	}

	/**
	 * @param $product
	 * @param $booking
	 *
	 * @return string
	 */
	public function get_booked_details_token_value( $product, $booking ) {
		$booked_data = '';
		if ( $product->has_resources() ) {
			$booked_data .= esc_html( sprintf( __( 'Type: %s', 'woocommerce-bookings' ), $product->get_resource( $booking->get_resource_id() )->get_title() ) ) . "\n";
		}
		if ( $product->has_persons() ) {
			if ( $product->has_person_types() ) {
				$person_types  = $product->get_person_types();
				$person_counts = $booking->get_person_counts();

				if ( ! empty( $person_types ) && is_array( $person_types ) ) {
					foreach ( $person_types as $person_type ) {

						if ( empty( $person_counts[ $person_type->get_id() ] ) ) {
							continue;
						}

						$booked_data .= esc_html( sprintf( '%s: %d', $person_type->get_name(), $person_counts[ $person_type->get_id() ] ) ) . "\n";
					}
				}
			} else {
				/* translators: 1: person count */
				$booked_data = esc_html( sprintf( __( '%d Persons', 'woocommerce-bookings' ), array_sum( $booking->get_person_counts() ) ) ) . "\n";
			}
		}

		return $booked_data;
	}

	/**
	 * @param $order_id
	 * @param $product_id
	 *
	 * @return array
	 */
	public function get_wc_order_tokens( $order_id, $product_id ) {
		$order_token_values = array();
		$order              = wc_get_order( $order_id );

		if ( $order instanceof \WC_Order ) {
			$wc_order_tokens = new Wc_Tokens();
			$comments        = $order->get_customer_note();
			if ( is_array( $comments ) ) {
				$comments = join( ' | ', $comments );
			}

			$coupons = $order->get_coupon_codes();
			$coupons = join( ', ', $coupons );

			$items                  = $order->get_items();
			$ordered_products       = array();
			$ordered_products_links = array();
			$ordered_products_qty   = array();
			$qty                    = 0;
			if ( $items ) {
				/** @var \WC_Order_Item_Product $item */
				foreach ( $items as $item ) {
					$product                  = $item->get_product();
					$ordered_products[]       = $product->get_title();
					$ordered_products_qty[]   = $product->get_title() . ' x ' . $item->get_quantity();
					$qty                      += $item->get_quantity();
					$ordered_products_links[] = '<a href="' . $product->get_permalink() . '">' . $product->get_title() . '</a>';
				}
			}
			$ordered_products       = join( ' | ', $ordered_products );
			$ordered_products_qty   = join( ' | ', $ordered_products_qty );
			$ordered_products_links = join( ' | ', $ordered_products_links );

			$stripe_fee    = 0;
			$stripe_payout = 0;
			if ( function_exists( 'stripe_wc' ) ) {
				$stripe_fee    = \WC_Stripe_Utils::display_fee( $order );
				$stripe_payout = \WC_Stripe_Utils::display_net( $order );
			}
			if ( ( function_exists( 'woocommerce_gateway_stripe' ) || class_exists( '\WC_Stripe_Helper' ) ) && 0 === $stripe_fee ) {
				$stripe_fee = \WC_Stripe_Helper::get_stripe_fee( $order );
			}
			if ( class_exists( '\WC_Stripe_Helper' ) && 0 === $stripe_payout ) {
				$stripe_payout = \WC_Stripe_Helper::get_stripe_net( $order );
			}

			$order_token_values = array(
				'order_id'              => $order_id,
				'billing_first_name'    => $order->get_billing_first_name(),
				'billing_last_name'     => $order->get_billing_last_name(),
				'billing_company'       => $order->get_billing_company(),
				'billing_country'       => $order->get_billing_country(),
				'billing_country_name'  => $wc_order_tokens->get_country_name_from_code( $order->get_billing_country() ),
				'billing_address_1'     => $order->get_billing_address_1(),
				'billing_address_2'     => $order->get_billing_address_2(),
				'billing_city'          => $order->get_billing_city(),
				'billing_state'         => $order->get_billing_state(),
				'billing_state_name'    => $wc_order_tokens->get_state_name_from_codes( $order->get_billing_state(), $order->get_billing_country() ),
				'billing_postcode'      => $order->get_billing_postcode(),
				'billing_phone'         => $order->get_billing_phone(),
				'billing_email'         => $order->get_billing_email(),
				'order_date'            => $order->get_date_created()->format( get_option( 'date_format', 'F j, Y' ) ),
				'order_time'            => $order->get_date_created()->format( get_option( 'time_format', 'H:i:s' ) ),
				'order_date_time'       => $order->get_date_created()->format( sprintf( '%s %s', get_option( 'date_format', 'F j, Y' ), get_option( 'time_format', 'H:i:s' ) ) ),
				'shipping_first_name'   => $order->get_shipping_first_name(),
				'shipping_company'      => $order->get_shipping_company(),
				'shipping_country'      => $order->get_shipping_country(),
				'shipping_country_name' => $wc_order_tokens->get_country_name_from_code( $order->get_shipping_country() ),
				'shipping_address_1'    => $order->get_shipping_address_1(),
				'shipping_address_2'    => $order->get_shipping_address_2(),
				'shipping_last_name'    => $order->get_shipping_last_name(),
				'shipping_method'       => $order->get_shipping_method(),
				'product_sku'           => $wc_order_tokens->get_products_skus( $order ),
				'WOOPRODUCT_CATEGORIES' => $wc_order_tokens->get_woo_product_categories_from_items( $order, $product_id ),
				'WOOPRODUCT_TAGS'       => $wc_order_tokens->get_woo_product_tags_from_items( $order, $product_id ),
				'order_summary'         => $wc_order_tokens->build_summary_style_html( $order ),
				'shipping_city'         => $order->get_shipping_city(),
				'shipping_state'        => $order->get_shipping_state(),
				'shipping_state_name'   => $wc_order_tokens->get_state_name_from_codes( $order->get_shipping_state(), $order->get_shipping_country() ),
				'shipping_postcode'     => $order->get_shipping_postcode(),
				'shipping_phone'        => get_post_meta( $order_id, 'shipping_phone', true ),
				'order_comments'        => $comments,
				'order_status'          => $order->get_status(),
				'order_total'           => wp_strip_all_tags( wc_price( $order->get_total() ) ),
				'order_total_raw'       => $order->get_total(),
				'order_subtotal'        => wp_strip_all_tags( wc_price( $order->get_subtotal() ) ),
				'order_subtotal_raw'    => $order->get_subtotal(),
				'order_tax'             => wp_strip_all_tags( wc_price( $order->get_total_tax() ) ),
				'order_fees'            => wc_price( $order->get_total_fees() ),
				'order_shipping'        => wc_price( $order->get_shipping_total() ),
				'order_tax_raw'         => $order->get_total_tax(),
				'order_discounts'       => wp_strip_all_tags( wc_price( $order->get_discount_total() * - 1 ) ),
				'order_discounts_raw'   => ( $order->get_discount_total() * - 1 ),
				'order_coupons'         => $coupons,
				'order_products'        => $ordered_products,
				'order_products_qty'    => $ordered_products_qty,
				'order_qty'             => $qty,
				'order_products_links'  => $ordered_products_links,
				'payment_method'        => $order->get_payment_method_title(),
				'payment_url'           => $order->get_checkout_payment_url(),
				'payment_url_checkout'  => $order->get_checkout_payment_url( true ),
				'stripe_fee'            => $stripe_fee,
				'stripe_payout'         => $stripe_payout,
			);
		}

		return $order_token_values;
	}

}
