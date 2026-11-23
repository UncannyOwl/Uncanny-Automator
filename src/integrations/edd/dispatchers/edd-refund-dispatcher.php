<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads\Dispatchers;

/**
 * Class Edd_Refund_Dispatcher
 *
 * EDD reports a refund through three different paths, and none of them is "a refund happened":
 *
 * 1. `edd_refund_order( $order_id, $refund_id, $all_refunded )` fires whenever EDD creates a
 *    refund record: a refund made on the order screen (any gateway), and a full refund that
 *    Stripe or PayPal reports through EDD's webhook. `$all_refunded` says full or partial.
 * 2. A partial refund made in the Stripe dashboard creates no refund record. EDD's Stripe webhook
 *    only sets the order to "partially_refunded" and then fires
 *    `edds_stripe_event_charge.refunded`, carrying the charge and the running total refunded.
 * 3. A partial refund made in the PayPal dashboard creates no record either; EDD sets the order
 *    to "partially_refunded", which fires `edd_transition_order_status`. PayPal's amount is not
 *    stored in EDD, so that path carries none. The same path catches an order someone marks
 *    "partially refunded" by hand, for any gateway: a Stripe order is left to the Stripe listener only
 *    while Stripe's own webhook is the one changing it.
 *
 * Each path emits one normalized action, so a recipe runs once per refund event:
 *
 *   `automator_edd_order_refunded( int $order_id, int $refund_id, string $type, string $amount )`
 *
 * - `$refund_id` is the EDD refund record, or 0 when EDD made none (paths 2 and 3).
 * - `$type` is "full" or "partial".
 * - `$amount` is this refund's amount in the order currency ("49.00"), only for a refund with no
 *   record that Stripe reported; otherwise an empty string and the amount is read from the record.
 *
 * Paths 2 and 3 skip a refund EDD already recorded: a refund made on the order screen reaches
 * Stripe, and Stripe's webhook comes back, but EDD created the record first, so the webhook finds
 * the amount already covered. Every argument is a scalar, so the event survives the loopback when
 * the gateway callback ends the request before recipe parts load.
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads\Dispatchers
 */
class Edd_Refund_Dispatcher {

	/**
	 * Whether the upstream listeners have been registered.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Idempotent boot.
	 *
	 * @return void
	 */
	public static function boot() {

		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'edd_refund_order', array( __CLASS__, 'on_refund_order' ), 10, 3 );
		add_action( 'edds_stripe_event_charge.refunded', array( __CLASS__, 'on_stripe_charge_refunded' ), 10, 1 );
		add_action( 'edd_transition_order_status', array( __CLASS__, 'on_order_status_transition' ), 10, 3 );
	}

	/**
	 * Remove the listeners and allow boot() to run again. For tests.
	 *
	 * @return void
	 */
	public static function reset_boot() {
		remove_action( 'edd_refund_order', array( __CLASS__, 'on_refund_order' ), 10 );
		remove_action( 'edds_stripe_event_charge.refunded', array( __CLASS__, 'on_stripe_charge_refunded' ), 10 );
		remove_action( 'edd_transition_order_status', array( __CLASS__, 'on_order_status_transition' ), 10 );
		self::$booted = false;
	}

	/**
	 * A refund record exists: EDD's own refund of any gateway, or a full refund a gateway reported.
	 *
	 * @param int  $order_id     The original order.
	 * @param int  $refund_id    The new refund record.
	 * @param bool $all_refunded Whether the entire order is now refunded.
	 *
	 * @return void
	 */
	public static function on_refund_order( $order_id, $refund_id, $all_refunded ) {

		do_action( 'automator_edd_order_refunded', absint( $order_id ), absint( $refund_id ), $all_refunded ? 'full' : 'partial', '' );
	}

	/**
	 * Stripe reported a refund. A full one already came through edd_refund_order; a partial one made
	 * in the Stripe dashboard has no EDD record, so it is emitted here with its amount: Stripe's
	 * running total less the total before this refund when the event carries it, otherwise less
	 * what EDD has recorded.
	 *
	 * @param object $event The Stripe event.
	 *
	 * @return void
	 */
	public static function on_stripe_charge_refunded( $event ) {

		$charge = is_object( $event ) && isset( $event->data->object ) ? $event->data->object : null;

		if ( ! is_object( $charge ) || empty( $charge->id ) || ! empty( $charge->refunded ) || ! function_exists( 'edd_get_order_id_from_transaction_id' ) ) {
			return;
		}

		$order_id = absint( edd_get_order_id_from_transaction_id( $charge->id ) );

		if ( 0 === $order_id ) {
			return;
		}

		$reported = self::to_major_units( isset( $charge->amount_refunded ) ? $charge->amount_refunded : 0 );
		$new      = round( $reported - self::recorded_refund_total( $order_id ), 2 );

		// EDD already holds a refund record covering what Stripe reports.
		if ( $new <= 0 ) {
			return;
		}

		// Dashboard refunds leave no EDD record, so only Stripe's previous total isolates this one.
		if ( isset( $event->data->previous_attributes->amount_refunded ) ) {
			$new = round( $reported - self::to_major_units( $event->data->previous_attributes->amount_refunded ), 2 );
		}

		do_action( 'automator_edd_order_refunded', $order_id, 0, 'partial', number_format( $new, 2, '.', '' ) );
	}

	/**
	 * An order became "partially refunded" with no refund record, and Stripe is not the gateway:
	 * a partial refund made in the PayPal dashboard, or a status an admin set by hand.
	 *
	 * @param string $old_status The previous status.
	 * @param string $new_status The new status.
	 * @param int    $order_id   The order.
	 *
	 * @return void
	 */
	public static function on_order_status_transition( $old_status, $new_status, $order_id ) {

		if ( 'partially_refunded' !== $new_status || ! function_exists( 'edd_get_order' ) ) {
			return;
		}

		$order = edd_get_order( $order_id );

		if ( ! is_object( $order ) || 'sale' !== $order->type ) {
			return;
		}

		// Inside Stripe's webhook, the Stripe listener reports this refund next, with its amount.
		if ( 'stripe' === $order->gateway && self::is_stripe_webhook_request() ) {
			return;
		}

		if ( ! empty( edd_get_order_refunds( $order->id ) ) ) {
			return;
		}

		do_action( 'automator_edd_order_refunded', absint( $order->id ), 0, 'partial', '' );
	}

	/**
	 * Whether this request is Stripe calling EDD's webhook.
	 *
	 * @return bool
	 */
	private static function is_stripe_webhook_request() {
		return 'stripe' === automator_request_input( 'edd-listener' );
	}

	/**
	 * The total EDD holds in refund records for an order, as a positive number.
	 *
	 * @param int $order_id The original order.
	 *
	 * @return float
	 */
	private static function recorded_refund_total( $order_id ) {

		$total = 0.0;

		foreach ( (array) edd_get_order_refunds( $order_id ) as $refund ) {
			$total += abs( (float) $refund->total );
		}

		return $total;
	}

	/**
	 * Convert a Stripe amount in the currency's smallest unit to a major-unit amount.
	 *
	 * @param int|float $minor The amount in cents, or whole units for a zero-decimal currency.
	 *
	 * @return float
	 */
	private static function to_major_units( $minor ) {

		if ( function_exists( 'edds_is_zero_decimal_currency' ) && edds_is_zero_decimal_currency() ) {
			return (float) $minor;
		}

		return round( (float) $minor / 100, 2 );
	}
}
