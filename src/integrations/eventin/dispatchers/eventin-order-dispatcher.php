<?php

namespace Uncanny_Automator\Integrations\Eventin\Dispatchers;

use Uncanny_Automator\Integrations\Eventin\Eventin_Helpers;

/**
 * Class Eventin_Order_Dispatcher
 *
 * Eventin completes an order down two mutually exclusive pipelines, and only
 * one of them emits the hooks the purchase and attendee triggers were bound to.
 *
 * 1. Payment pipeline — `eventin_order_completed`, fired by
 *    PaymentController::update_payment() (core/Order/PaymentController.php:419),
 *    five WooCommerce paths (core/woocommerce/hooks.php:395, :444, :510, :765,
 *    :776) and Eventin Pro's Stripe webhook. `OrderAttendee` is wired to it
 *    (core/Order/OrderAttendee.php:18), writes `etn_status = success` on each
 *    attendee (:43) and fans out `eventin_attendee_payment_completed` (:46).
 *
 * 2. Admin pipeline — `eventin_order_status_completed`, fired when an order's
 *    status is changed to Completed through the admin screens
 *    (core/Order/OrderController.php:885). This route writes
 *    `etn_status = success` INLINE at :869 instead of going through
 *    `OrderAttendee`, so it emits NEITHER `eventin_order_completed` NOR
 *    `eventin_attendee_payment_completed`.
 *
 * Binding a trigger to the pipeline-1 hooks alone therefore never fires for an
 * order completed from the admin — which is how most sites both test and
 * reconcile offline payments. This dispatcher owns the normalization once and
 * emits two narrow events that cover both pipelines:
 *
 *   `automator_eventin_order_completed( int $order_id )`
 *   `automator_eventin_attendee_registered( int $attendee_id, int $event_id )`
 *
 * Registered at priority 20 deliberately: on pipeline 1 that puts it after
 * `OrderAttendee` (priority 10), and on pipeline 2 the inline attendee loop at
 * :867-873 has already run by :885 — so on either path every attendee is
 * already `success` before the fan-out is observed.
 *
 * @package Uncanny_Automator\Integrations\Eventin\Dispatchers
 */
class Eventin_Order_Dispatcher {

	/**
	 * The order status that means "completed".
	 */
	const COMPLETED_STATUS = 'completed';

	/**
	 * The attendee meta key holding the owning order's post ID.
	 *
	 * `OrderModel::get_attendees()` looks attendees up by this key
	 * (core/Order/OrderModel.php:118).
	 */
	const ATTENDEE_ORDER_META = 'eventin_order_id';

	/**
	 * Whether the upstream listeners have been registered.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Order IDs already dispatched this request.
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

		add_action( 'eventin_order_completed', array( __CLASS__, 'on_order_completed' ), 20, 1 );
		add_action( 'eventin_order_status_completed', array( __CLASS__, 'on_order_completed' ), 20, 1 );
	}

	/**
	 * Clear the boot guard and unregister the upstream listeners.
	 *
	 * Test seam only — see Eventin_Checkin_Dispatcher::reset_boot().
	 *
	 * @return void
	 */
	public static function reset_boot() {

		remove_action( 'eventin_order_completed', array( __CLASS__, 'on_order_completed' ), 20 );
		remove_action( 'eventin_order_status_completed', array( __CLASS__, 'on_order_completed' ), 20 );

		self::$booted     = false;
		self::$dispatched = array();
	}

	/**
	 * An order reached `completed` down either pipeline.
	 *
	 * @param mixed $order The OrderModel, or an order post ID.
	 *
	 * @return void
	 */
	public static function on_order_completed( $order ) {

		// The model's magic __get() throws on any key outside its own map and it
		// declares no __isset(), so `$order->foo ?? ''` silently yields the
		// fallback (base/post-model.php:73). Take the ID and read the rest from
		// post meta.
		$order_id = is_object( $order ) ? absint( $order->id ?? 0 ) : absint( $order );

		if ( 0 === $order_id || isset( self::$dispatched[ $order_id ] ) ) {
			return;
		}

		if ( Eventin_Helpers::ORDER_POST_TYPE !== get_post_type( $order_id ) ) {
			return;
		}

		// Both pipelines write the status before firing, so this is
		// authoritative on either path.
		if ( self::COMPLETED_STATUS !== (string) get_post_meta( $order_id, 'status', true ) ) {
			return;
		}

		self::$dispatched[ $order_id ] = true;

		do_action( 'automator_eventin_order_completed', $order_id );

		foreach ( self::get_order_attendee_ids( $order_id ) as $attendee_id ) {
			do_action(
				'automator_eventin_attendee_registered',
				$attendee_id,
				absint( get_post_meta( $attendee_id, Eventin_Helpers::ATTENDEE_EVENT_META, true ) )
			);
		}
	}

	/**
	 * The order's attendee post IDs.
	 *
	 * A direct query rather than `OrderModel::get_attendees()`: that hydrates a
	 * full Attendee_Model per row to hand back an ID this already has, and it
	 * would make the dispatcher depend on an Eventin class at a point where only
	 * post meta is needed.
	 *
	 * @param int $order_id The order post ID.
	 *
	 * @return array<int,int>
	 */
	private static function get_order_attendee_ids( $order_id ) {

		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				 WHERE p.post_type = %s
				   AND pm.meta_key = %s
				   AND pm.meta_value = %d
				 ORDER BY p.ID ASC",
				Eventin_Helpers::ATTENDEE_POST_TYPE,
				self::ATTENDEE_ORDER_META,
				absint( $order_id )
			)
		);

		return array_map( 'absint', (array) $ids );
	}
}
