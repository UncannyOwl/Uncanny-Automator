<?php

namespace Uncanny_Automator\Actionify_Triggers;

/**
 * Trigger Query - finds active triggers from WordPress database.
 *
 * @package Uncanny_Automator\Actionify_Triggers
 * @since 6.7
 */
class Trigger_Query {

	/**
	 * Transient key for caching active triggers query result.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'automator_actionified_triggers';

	/**
	 * Cache TTL in seconds.
	 *
	 * Kept short (60s) as a safety net — primary invalidation happens via
	 * Automator_Cache_Handler::remove() on recipe/trigger save, status
	 * change, plugin activation, and cache flush.
	 *
	 * @var int
	 */
	const CACHE_TTL = 60;

	/**
	 * Get all active triggers from the WordPress database.
	 *
	 * Queries the WordPress database for all published triggers associated with
	 * published recipes. Returns a structured array of trigger configurations.
	 *
	 * A code with one single-hook trigger maps to a flat `{hook, code}` entry; a
	 * code whose triggers listen on more than one hook maps to a list of entries.
	 * Several triggers can share a code with different stored hooks (a recipe
	 * saved before a hook was added, a copy that kept fewer hooks), so every row's
	 * hooks are added to the code's entries: none replaces another.
	 *
	 * @since 8.0.4 A single-hook row no longer replaces the hooks earlier rows of the same code added.
	 *
	 * @return array Array of active triggers indexed by trigger code.
	 */
	public function get_active_triggers() {

		$triggers = $this->find_triggers();

		if ( empty( $triggers ) ) {
			return array();
		}

		$active_triggers = array();

		foreach ( $triggers as $trigger ) {

			$action_hook = automator_safe_unserialize( $trigger['action_hook'] );

			foreach ( (array) $action_hook as $hook ) {
				$this->add_entry( $active_triggers, (string) $trigger['trigger_code'], (string) $hook, is_array( $action_hook ) );
			}
		}

		return $active_triggers;
	}

	/**
	 * Add one hook for a trigger code, keeping every hook the code already has.
	 *
	 * The first single-hook entry of a code stays a flat `{hook, code}` array.
	 * Once the code has a second hook, or its row stores a list of hooks, the
	 * code's value becomes a list of entries. An entry for the same hook is not
	 * added twice.
	 *
	 * @since 8.0.4
	 *
	 * @param array  $active_triggers Map being built, by reference.
	 * @param string $code            Trigger code.
	 * @param string $hook            Hook name.
	 * @param bool   $from_list       Whether the row stores a list of hooks.
	 *
	 * @return void
	 */
	private function add_entry( array &$active_triggers, $code, $hook, $from_list ) {

		$entry = array(
			'hook' => $hook,
			'code' => $code,
		);

		if ( ! isset( $active_triggers[ $code ] ) ) {
			$active_triggers[ $code ] = $from_list ? array( $entry ) : $entry;
			return;
		}

		// A flat single-hook entry becomes the first item of the code's list.
		$entries = isset( $active_triggers[ $code ]['hook'] ) ? array( $active_triggers[ $code ] ) : $active_triggers[ $code ];

		if ( in_array( $hook, array_column( $entries, 'hook' ), true ) ) {
			return;
		}

		$entries[]                = $entry;
		$active_triggers[ $code ] = $entries;
	}

	/**
	 * Find triggers from WordPress database.
	 *
	 * When caching is enabled, a short transient avoids running the 4-table JOIN on
	 * every request (invalidated when the Automator cache handler clears
	 * `automator_actionified_triggers`). When caching is disabled the database is
	 * queried directly and no transient is read or written.
	 *
	 * @return array Array of triggers.
	 */
	private function find_triggers() {

		$cache_enabled = Automator()->cache->is_cache_enabled();

		if ( $cache_enabled ) {
			$cached = get_transient( self::CACHE_KEY );

			if ( false !== $cached ) {
				return (array) $cached;
			}
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Joins core posts and postmeta; no WordPress API returns this.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                    action_hook_meta.meta_value as action_hook,
                    code_meta.meta_value as trigger_code
                FROM $wpdb->postmeta action_hook_meta
                INNER JOIN $wpdb->postmeta code_meta
                    ON code_meta.post_id = action_hook_meta.post_id
                    AND code_meta.meta_key = 'code'
                INNER JOIN $wpdb->posts trigger_post
                    ON trigger_post.ID = action_hook_meta.post_id
                    AND trigger_post.post_status = %s
                    AND trigger_post.post_type = %s
                INNER JOIN $wpdb->posts recipe_post
                    ON recipe_post.ID = trigger_post.post_parent
                    AND recipe_post.post_status = %s
                    AND recipe_post.post_type = %s
                WHERE action_hook_meta.meta_key = 'add_action'",
				'publish',
				AUTOMATOR_POST_TYPE_TRIGGER,
				'publish',
				AUTOMATOR_POST_TYPE_RECIPE
			),
			ARRAY_A
		);

		$results = (array) $results;

		if ( $cache_enabled ) {
			set_transient( self::CACHE_KEY, $results, self::CACHE_TTL );
		}

		return $results;
	}
}
