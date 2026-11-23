<?php

namespace Uncanny_Automator\Integrations\Userswp;

use WP_User;

/**
 * Class Userswp_Login_Dispatcher
 *
 * Fires `automator_userswp_user_logged_in` for the account UsersWP's own
 * `wp_signon()` authenticated.
 *
 * `uwp_after_process_login` carries only the posted form, and `wp_signon()`
 * never sets the current user, so the user is the `WP_User` that `wp_signon()`
 * passes to `wp_login`. It is captured at priority 1, the last capture wins,
 * and it is cleared once the event fires. Logins that end outside
 * `uwp_after_process_login` (registration's auto-login, WP2FA and Wordfence
 * two-factor completions) fire `wp_login` only, so nothing fires for them.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 */
class Userswp_Login_Dispatcher {

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * The user the last `wp_login` of this request authenticated.
	 *
	 * @var WP_User|null
	 */
	private static $captured = null;

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

		add_action( 'wp_login', array( __CLASS__, 'capture' ), 1, 2 );
		add_action( 'uwp_after_process_login', array( __CLASS__, 'dispatch' ), 1, 1 );
	}

	/**
	 * Test seam.
	 *
	 * @return void
	 */
	public static function reset_boot() {
		remove_action( 'wp_login', array( __CLASS__, 'capture' ), 1 );
		remove_action( 'uwp_after_process_login', array( __CLASS__, 'dispatch' ), 1 );
		self::$booted   = false;
		self::$captured = null;
	}

	/**
	 * @param string $user_login
	 * @param mixed  $user
	 *
	 * @return void
	 */
	public static function capture( $user_login, $user ) {
		self::$captured = $user instanceof WP_User ? $user : null;
	}

	/**
	 * @param mixed $data The posted login form.
	 *
	 * @return void
	 */
	public static function dispatch( $data ) {

		$user           = self::$captured;
		self::$captured = null;

		if ( ! $user instanceof WP_User || 0 >= (int) $user->ID ) {
			return;
		}

		$entered = is_array( $data ) && is_scalar( $data['username'] ?? null ) ? sanitize_text_field( (string) $data['username'] ) : '';

		do_action( 'automator_userswp_user_logged_in', (int) $user->ID, $entered );
	}
}
