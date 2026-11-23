<?php

namespace Uncanny_Automator\Integrations\Sportspress;

/**
 * Class Sportspress_Meta_Dispatcher
 *
 * Derives "An event is postponed or cancelled" from SportsPress's `sp_status`
 * writes: the edit screen's save and the bulk Postpone and Cancel actions
 * (R17). WordPress fires no meta hook for an unchanged value, so a re-save
 * never fires again.
 *
 * Only Published or Scheduled events count. An event postponed while still a
 * draft and published later never fires, because publishing doesn't change
 * `sp_status`.
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 */
class Sportspress_Meta_Dispatcher {

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Idempotent: load_shared_hooks() may run more than once per request.
	 *
	 * @return void
	 */
	public static function boot() {

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_written' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_written' ), 10, 4 );
	}

	/**
	 * Test seam.
	 *
	 * @return void
	 */
	public static function reset_boot() {
		self::$booted = false;
	}

	/**
	 * @param int    $meta_id
	 * @param int    $object_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 *
	 * @return void
	 */
	public static function on_meta_written( $meta_id, $object_id, $meta_key, $meta_value ) {

		if ( 'sp_status' !== $meta_key || ! in_array( $meta_value, array( 'postponed', 'cancelled' ), true ) ) {
			return;
		}

		$object_id = (int) $object_id;

		// Calendars keep an sp_status of their own.
		if ( Sportspress_Helpers::EVENT !== get_post_type( $object_id ) || Sportspress_Post_Dispatcher::is_duplicating() ) {
			return;
		}

		if ( ! in_array( get_post_status( $object_id ), array( 'publish', 'future' ), true ) ) {
			return;
		}

		do_action( 'automator_sportspress_event_postponed_or_cancelled', $object_id, $meta_value );
	}
}
