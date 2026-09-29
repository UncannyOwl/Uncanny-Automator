<?php

namespace Uncanny_Automator\Integrations\Eventin\Dispatchers;

use Uncanny_Automator\Integrations\Eventin\Eventin_Helpers;

/**
 * Class Eventin_Checkin_Dispatcher
 *
 * Eventin emits no hook when an attendee is checked in. The Pro QR scanner
 * writes the state straight to post meta —
 *
 *   update_post_meta( $attendee_id, 'etn_attendeee_ticket_status', 'used' );
 *   update_post_meta( $attendee_id, 'scanner_update_time', $formatted_time );
 *
 * — inside Hooks::check_ticket_id() (eventin-pro/core/attendee/hooks.php:982-983),
 * with no do_action anywhere in the method. The admin REST route does the same
 * through prepare_item_for_database() (core/Attendee/Api/AttendeeController.php:817).
 *
 * WordPress' own meta hooks are the only listenable signal, and they are
 * site-wide: every meta write on every post type passes through them. Doing that
 * filtering inside a trigger's validate() would put an unguarded post-type
 * lookup on every meta write in WordPress. Instead this dispatcher owns the
 * guard once and emits a narrow, normalized event:
 *
 *   `automator_eventin_attendee_checked_in( int $attendee_id, int $event_id )`
 *
 * Both `updated_post_meta` AND `added_post_meta` are monitored, deliberately.
 * `update_post_meta()` fires `added_post_meta` when no row exists yet, and
 * whether a row exists depends on how the attendee was created:
 *
 * - Checkout path — OrderController::create_attendees() writes 'unused'
 *   explicitly (core/Order/OrderController.php:1754), so a row exists and
 *   `updated_post_meta` fires.
 * - WooCommerce path — same (core/woocommerce/hooks.php:1446).
 * - Admin REST path — prepare_item_for_database() writes the key ONLY when the
 *   request supplied it (core/Attendee/Api/AttendeeController.php:817), so an
 *   attendee added from the admin screens has NO row and the first check-in
 *   fires `added_post_meta` instead.
 *
 * Listening on one hook only would silently miss that third case.
 *
 * @package Uncanny_Automator\Integrations\Eventin\Dispatchers
 */
class Eventin_Checkin_Dispatcher {

	/**
	 * The meta value that means "checked in".
	 */
	const CHECKED_IN_VALUE = 'used';

	/**
	 * Whether the upstream listeners have been registered.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Attendee IDs already dispatched this request, so a double meta write
	 * cannot fire the trigger twice.
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

		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_written' ), 20, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_written' ), 20, 4 );
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

		remove_action( 'updated_post_meta', array( __CLASS__, 'on_meta_written' ), 20 );
		remove_action( 'added_post_meta', array( __CLASS__, 'on_meta_written' ), 20 );

		self::$booted     = false;
		self::$dispatched = array();
	}

	/**
	 * A post meta row was written.
	 *
	 * @param int    $meta_id    The meta row ID.
	 * @param int    $object_id  The post ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 *
	 * @return void
	 */
	public static function on_meta_written( $meta_id, $object_id, $meta_key, $meta_value ) {

		unset( $meta_id );

		// Cheapest possible guard first — this callback runs on every post meta
		// write in WordPress, so the key comparison must short-circuit before
		// anything touches the database.
		if ( Eventin_Helpers::ATTENDEE_TICKET_STATUS_META !== $meta_key ) {
			return;
		}

		if ( self::CHECKED_IN_VALUE !== (string) $meta_value ) {
			return;
		}

		$attendee_id = absint( $object_id );

		if ( 0 === $attendee_id || isset( self::$dispatched[ $attendee_id ] ) ) {
			return;
		}

		if ( Eventin_Helpers::ATTENDEE_POST_TYPE !== get_post_type( $attendee_id ) ) {
			return;
		}

		self::$dispatched[ $attendee_id ] = true;

		do_action(
			'automator_eventin_attendee_checked_in',
			$attendee_id,
			absint( get_post_meta( $attendee_id, Eventin_Helpers::ATTENDEE_EVENT_META, true ) )
		);
	}
}
