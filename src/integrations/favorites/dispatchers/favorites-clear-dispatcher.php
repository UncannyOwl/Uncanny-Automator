<?php

namespace Uncanny_Automator\Integrations\Favorites;

/**
 * Class Favorites_Clear_Dispatcher
 *
 * The plugin fires its two clear hooks whether or not the user had anything to
 * clear, and neither carries what was removed. This snapshots the list before
 * the clear and fires a single normalized event after it, only when the clear
 * actually removed something.
 *
 * @package Uncanny_Automator
 */
class Favorites_Clear_Dispatcher {

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Post IDs captured before the clear, keyed on user and site.
	 *
	 * @var array<string,int[]>
	 */
	private static $pending = array();

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

		add_action( 'favorites_before_clear', array( __CLASS__, 'on_before_clear' ), 10, 2 );
		add_action( 'favorites_after_clear', array( __CLASS__, 'on_after_clear' ), 10, 2 );
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
	 * Test seam.
	 *
	 * @return void
	 */
	public static function reset_pending() {
		self::$pending = array();
	}

	/**
	 * Capture what the clear is about to remove.
	 *
	 * @param int   $site_id The site being cleared.
	 * @param mixed $user    The user ID, or null for a guest.
	 *
	 * @return void
	 */
	public static function on_before_clear( $site_id, $user ) {

		if ( empty( $user ) ) {
			return;
		}

		$helpers = new Favorites_Helpers();

		self::$pending[ self::key( $site_id, $user ) ] = $helpers->get_user_favorite_ids( $user, $site_id );
	}

	/**
	 * Fire the normalized event when the clear removed something.
	 *
	 * @param int   $site_id The site that was cleared.
	 * @param mixed $user    The user ID, or null for a guest.
	 *
	 * @return void
	 */
	public static function on_after_clear( $site_id, $user ) {

		if ( empty( $user ) ) {
			return;
		}

		$key      = self::key( $site_id, $user );
		$post_ids = isset( self::$pending[ $key ] ) ? self::$pending[ $key ] : array();

		unset( self::$pending[ $key ] );

		if ( empty( $post_ids ) ) {
			return;
		}

		do_action( 'automator_favorites_favourites_cleared', absint( $site_id ), absint( $user ), $post_ids );
	}

	/**
	 * @param int   $site_id The site ID.
	 * @param mixed $user    The user ID.
	 *
	 * @return string
	 */
	private static function key( $site_id, $user ) {
		return absint( $user ) . ':' . absint( $site_id );
	}
}
