<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum\Dispatchers;

use AsgarosForumUserGroups;

/**
 * Class Usergroup_Dispatcher
 *
 * Asgaros announces a membership change on a hook named after the group,
 * `asgarosforum_usergroup_{id}_add_user` / `_remove_user`. This dispatcher
 * listens on those hooks for the groups that published recipes select (every
 * group while one selects "Any"), and re-fires each event on the literal hook
 * its trigger declares:
 *
 *   - "A user is added to {{a usergroup}}" (Free):
 *     `automator_asgaros_forum_user_added_to_usergroup`
 *   - "A user is removed from {{a usergroup}}" (Pro):
 *     `automator_asgaros_forum_user_removed_from_usergroup`
 *
 * Asgaros registers its usergroup taxonomy on `init` 10, after integrations
 * load on `init` 1, so the groups are read on `init` 11.
 *
 * @package Uncanny_Automator
 */
class Usergroup_Dispatcher {

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * The Asgaros hooks `register()` added, hook name => callback method.
	 *
	 * @var array<string,string>
	 */
	private static $registered = array();

	/**
	 * Idempotent: `load_shared_hooks()` may run more than once per request.
	 *
	 * @return void
	 */
	public static function boot() {

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		// Asgaros registers its usergroup taxonomy on init 10.
		if ( did_action( 'init' ) && ! doing_action( 'init' ) ) {
			self::register();
			return;
		}

		add_action( 'init', array( __CLASS__, 'register' ), 11 );
	}

	/**
	 * Test seam: undo `boot()` and everything `register()` added.
	 *
	 * @return void
	 */
	public static function reset_boot() {

		foreach ( self::$registered as $hook => $callback ) {
			remove_action( $hook, array( __CLASS__, $callback ), 10 );
		}

		remove_action( 'init', array( __CLASS__, 'register' ), 11 );

		self::$registered = array();
		self::$booted     = false;
	}

	/**
	 * Listen on the Asgaros hooks of every group a recipe selects.
	 *
	 * @return void
	 */
	public static function register() {

		$triggers = array(
			'ASGAROS_FORUM_USER_ADDED_TO_USERGROUP'     => array( 'add_user', 'on_added' ),
			'ASGAROS_FORUM_USER_REMOVED_FROM_USERGROUP' => array( 'remove_user', 'on_removed' ),
		);

		foreach ( $triggers as $code => $hook ) {

			list( $suffix, $callback ) = $hook;

			foreach ( self::selected_usergroup_ids( $code ) as $usergroup_id ) {

				$name = 'asgarosforum_usergroup_' . $usergroup_id . '_' . $suffix;

				add_action( $name, array( __CLASS__, $callback ), 10, 2 );

				self::$registered[ $name ] = $callback;
			}
		}
	}

	/**
	 * @param mixed $user_id      The member's user ID.
	 * @param mixed $usergroup_id The usergroup term ID.
	 *
	 * @return void
	 */
	public static function on_added( $user_id, $usergroup_id ) {

		if ( ! self::is_id( $user_id ) || ! self::is_id( $usergroup_id ) ) {
			return;
		}

		do_action( 'automator_asgaros_forum_user_added_to_usergroup', (int) $user_id, (int) $usergroup_id );
	}

	/**
	 * @param mixed $user_id      The member's user ID.
	 * @param mixed $usergroup_id The usergroup term ID.
	 *
	 * @return void
	 */
	public static function on_removed( $user_id, $usergroup_id ) {

		if ( ! self::is_id( $user_id ) || ! self::is_id( $usergroup_id ) ) {
			return;
		}

		do_action( 'automator_asgaros_forum_user_removed_from_usergroup', (int) $user_id, (int) $usergroup_id );
	}

	/**
	 * The usergroup IDs the published recipes on one trigger select.
	 *
	 * @param string $trigger_code The trigger code.
	 *
	 * @return int[]
	 */
	private static function selected_usergroup_ids( $trigger_code ) {

		$recipes = Automator()->get->recipes_from_trigger_code( $trigger_code );
		$ids     = array();

		foreach ( Automator()->get->meta_from_recipes( $recipes, 'ASGAROS_FORUM_USERGROUP' ) as $per_trigger ) {
			foreach ( (array) $per_trigger as $value ) {

				if ( '-1' === (string) $value ) {
					return self::all_usergroup_ids();
				}

				if ( self::is_id( $value ) ) {
					$ids[] = (int) $value;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @return int[]
	 */
	private static function all_usergroup_ids() {

		if ( ! class_exists( 'AsgarosForumUserGroups' ) ) {
			return array();
		}

		return array_map( 'absint', wp_list_pluck( (array) AsgarosForumUserGroups::getUserGroups(), 'term_id' ) );
	}

	/**
	 * A positive integer or numeric string (the bulk action passes a string);
	 * never an array, object or `WP_Error` (R1).
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	private static function is_id( $value ) {
		return ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) && 0 < (int) $value;
	}
}
