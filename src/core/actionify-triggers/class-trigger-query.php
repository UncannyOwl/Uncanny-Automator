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
	 * @return array Array of active triggers indexed by trigger code.
	 */
	public function get_active_triggers() {

		$triggers = $this->find_triggers();

		if ( empty( $triggers ) ) {
			return array();
		}

		$active_triggers = array();

		foreach ( $triggers as $trigger ) {

			$action_hook = maybe_unserialize( $trigger['action_hook'] );

			if ( is_array( $action_hook ) ) {
				foreach ( $action_hook as $hook ) {
					$active_triggers[ $trigger['trigger_code'] ][] = array(
						'hook' => (string) $hook,
						'code' => $trigger['trigger_code'],
					);
				}
				continue;
			}

			$active_triggers[ $trigger['trigger_code'] ] = array(
				'hook' => (string) $action_hook,
				'code' => $trigger['trigger_code'],
			);
		}

		return $active_triggers;
	}

	/**
	 * Find triggers from WordPress database.
	 *
	 * Uses a transient cache to avoid running the 4-table JOIN on every request.
	 * Cache is invalidated when the Automator cache handler clears `automator_actionified_triggers`.
	 *
	 * @return array Array of triggers.
	 */
	private function find_triggers() {

		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			return (array) $cached;
		}

		global $wpdb;

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

		set_transient( self::CACHE_KEY, $results, self::CACHE_TTL );

		return $results;
	}
}
