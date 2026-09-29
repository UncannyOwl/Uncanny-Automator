<?php

namespace Uncanny_Automator\Integrations\Cartflows\Dispatchers;

use Uncanny_Automator\Integrations\Cartflows\Cartflows_Helpers;
use WC_Order;

/**
 * Class Cartflows_Checkout_Dispatcher
 *
 * CartFlows has no "funnel checkout completed" hook of its own — it stamps
 * `_wcf_flow_id` onto the WooCommerce order and leaves the rest to WooCommerce.
 * That leaves three problems a trigger cannot solve in validate():
 *
 * 1. Submitting a checkout is not the same as paying for one.
 *    `woocommerce_checkout_order_processed` fires the moment the order row is
 *    written, before the gateway has taken a penny, so a pending or later-failed
 *    order would still fire a trigger that says the checkout completed. This
 *    dispatcher listens where WooCommerce actually confirms money changed hands,
 *    matching the hooks Automator's own WooCommerce purchase triggers use
 *    (`Woocommerce_Helpers::get_trigger_condition_labels()`):
 *
 *    - `woocommerce_payment_complete` — every gateway that charges the customer,
 *      plus zero-total orders, which `WC_Checkout` completes the same way.
 *    - `woocommerce_order_status_completed` — offline gateways (cash on delivery,
 *      bank transfer, cheque) never call `payment_complete()`; their orders fire
 *      when the order is marked completed.
 *
 *    Both hooks pass the order ID first (`WC_Order::payment_complete()` and
 *    `WC_Order::status_transition()`), so one listener serves both.
 *
 * 2. A single order can reach both hooks. `payment_complete()` saves the order —
 *    firing `woocommerce_order_status_completed` when the next status is
 *    `completed` — and only then fires `woocommerce_payment_complete`
 *    (`WC_Order::payment_complete()`). A card order therefore hits this dispatcher
 *    twice in one request, and a cash-on-delivery order hits it again weeks later
 *    when an admin completes it. Neither may run the recipe twice, so the guard
 *    has to outlive the request: it is stamped on the order itself.
 *
 * 3. Optin steps also create orders. A CartFlows optin submission produces a
 *    WooCommerce order carrying `_wcf_flow_id` too
 *    (`Cartflows_Optin_Markup::save_optin_fields()`), so filtering on the flow meta alone
 *    would fire the checkout triggers on optin submissions. Only optin orders
 *    carry `_wcf_optin_id`. CartFlows uses that same test internally
 *    (`Cartflows_Tracking::prepare_purchase_data_fb_response()`).
 *
 * All three are resolved here, and one normalized action is emitted:
 *
 *   `automator_cartflows_checkout_completed( int $order_id, int $flow_id, int $checkout_id )`
 *
 * @package Uncanny_Automator\Integrations\Cartflows\Dispatchers
 */
class Cartflows_Checkout_Dispatcher {

	/**
	 * Order meta stamped once the normalized event has been emitted for an order.
	 * Durable on purpose — see note 2 in the class docblock.
	 */
	const ORDER_DISPATCHED_META = '_automator_cartflows_checkout_dispatched';

	/**
	 * Whether the upstream listeners have been registered.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Order IDs already dispatched this request. The order meta is the guard
	 * that matters; this is a cheap in-request companion so a second hook in the
	 * same request cannot slip through on a stale cached order object.
	 *
	 * @var array<int,bool>
	 */
	private static $dispatched = array();

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

		// Gateway charge succeeded — ( $order_id, $transaction_id ).
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_order_paid' ), 20, 1 );

		// Offline gateways and manual completion — ( $order_id, $order, $transition ).
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_paid' ), 20, 1 );
	}

	/**
	 * Clear the boot guard and unregister the upstream listeners.
	 *
	 * Test seam only — a WPUnit case that removes hooks in tearDown would
	 * otherwise leave the guard set and no listeners attached, so the next test
	 * in the process would silently observe nothing.
	 *
	 * @return void
	 */
	public static function reset_boot() {

		remove_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_order_paid' ), 20 );
		remove_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_paid' ), 20 );

		self::$booted     = false;
		self::$dispatched = array();
	}

	/**
	 * An order was paid for, or marked completed.
	 *
	 * @param int $order_id The order.
	 *
	 * @return void
	 */
	public static function on_order_paid( $order_id ) {
		self::maybe_dispatch( absint( $order_id ) );
	}

	/**
	 * Emit the normalized event, once per order, when the order really came
	 * through a CartFlows checkout step.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return void
	 */
	private static function maybe_dispatch( $order_id ) {

		if ( 0 === $order_id || isset( self::$dispatched[ $order_id ] ) ) {
			return;
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$flow_id = absint( $order->get_meta( Cartflows_Helpers::ORDER_FLOW_META ) );

		if ( 0 === $flow_id ) {
			return;
		}

		// Optin submissions also produce a flow-stamped order — not a checkout.
		if ( '' !== (string) $order->get_meta( Cartflows_Helpers::ORDER_OPTIN_META ) ) {
			return;
		}

		if ( '' !== (string) $order->get_meta( self::ORDER_DISPATCHED_META ) ) {
			return;
		}

		self::$dispatched[ $order_id ] = true;

		// save_meta_data(), not save(): this can run inside the order save that
		// fired woocommerce_order_status_completed, and a nested save() would
		// re-enter the status transition. Writing the meta alone does not.
		$order->update_meta_data( self::ORDER_DISPATCHED_META, '1' );
		$order->save_meta_data();

		do_action(
			'automator_cartflows_checkout_completed',
			$order_id,
			$flow_id,
			absint( $order->get_meta( Cartflows_Helpers::ORDER_CHECKOUT_META ) )
		);
	}
}
