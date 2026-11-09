<?php

namespace Uncanny_Automator;

use WC_Order;
use WC_Stripe_Helper;
use WC_Stripe_Order_Helper;
use WC_Stripe_Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the Stripe fee and payout that a Stripe plugin recorded on a WooCommerce order.
 *
 * Payment Plugins for Stripe WooCommerce answers through WC_Stripe_Utils. WooCommerce
 * Stripe Gateway answers through WC_Stripe_Order_Helper since 10.0.0; 10.7.0 removed
 * the static WC_Stripe_Helper methods but kept the class, so a class check alone no
 * longer proves the methods are there.
 *
 * @since 8.0.3
 */
class Wc_Stripe_Amounts {

	/**
	 * The Stripe fee recorded on the order.
	 *
	 * @since 8.0.3
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return mixed The fee as the Stripe plugin returns it, or 0 when none answers.
	 */
	public static function fee( $order ) {

		if ( ! $order instanceof WC_Order ) {
			return 0;
		}

		$value = 0;

		if ( function_exists( 'stripe_wc' ) ) {
			$value = WC_Stripe_Utils::display_fee( $order );
		}

		if ( 0 === $value ) {
			$value = self::woocommerce_gateway_fee( $order );
		}

		return $value;
	}

	/**
	 * The Stripe payout (the order total less the fee) recorded on the order.
	 *
	 * @since 8.0.3
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return mixed The payout as the Stripe plugin returns it, or 0 when none answers.
	 */
	public static function payout( $order ) {

		if ( ! $order instanceof WC_Order ) {
			return 0;
		}

		$value = 0;

		if ( function_exists( 'stripe_wc' ) ) {
			$value = WC_Stripe_Utils::display_net( $order );
		}

		if ( 0 === $value ) {
			$value = self::woocommerce_gateway_payout( $order );
		}

		return $value;
	}

	/**
	 * The fee from WooCommerce Stripe Gateway, through the API the installed release has.
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return mixed
	 */
	private static function woocommerce_gateway_fee( WC_Order $order ) {

		if ( method_exists( 'WC_Stripe_Order_Helper', 'get_stripe_fee' ) ) {
			return WC_Stripe_Order_Helper::get_instance()->get_stripe_fee( $order );
		}

		// Releases before 10.0.0.
		if ( method_exists( 'WC_Stripe_Helper', 'get_stripe_fee' ) ) {
			return WC_Stripe_Helper::get_stripe_fee( $order );
		}

		return 0;
	}

	/**
	 * The payout from WooCommerce Stripe Gateway, through the API the installed release has.
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return mixed
	 */
	private static function woocommerce_gateway_payout( WC_Order $order ) {

		if ( method_exists( 'WC_Stripe_Order_Helper', 'get_stripe_net' ) ) {
			return WC_Stripe_Order_Helper::get_instance()->get_stripe_net( $order );
		}

		// Releases before 10.0.0.
		if ( method_exists( 'WC_Stripe_Helper', 'get_stripe_net' ) ) {
			return WC_Stripe_Helper::get_stripe_net( $order );
		}

		return 0;
	}
}
