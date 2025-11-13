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
	 * @return array Array of triggers.
	 */
	private function find_triggers() {

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
                    AND trigger_post.post_type = 'uo-trigger'
                INNER JOIN $wpdb->posts recipe_post 
                    ON recipe_post.ID = trigger_post.post_parent
                    AND recipe_post.post_status = %s
                    AND recipe_post.post_type = 'uo-recipe'
                WHERE action_hook_meta.meta_key = 'add_action'",
				'publish',
				'publish'
			),
			ARRAY_A
		);

		return (array) $results;
	}
}
