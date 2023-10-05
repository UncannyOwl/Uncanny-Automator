<?php

namespace Uncanny_Automator\Migrations;

/**
 * Class Migrate_Triggers.
 *
 * @package Uncanny_Automator
 */
class Migrate_Triggers {

	/**
	 * The option key to determine if triggers has already been migrated.
	 *
	 * @var string
	 */
	const MIGRATED_FLAG = 'automator_triggers_has_migrated';

	/**
	 * The name of the cron.
	 *
	 * @var string
	 */
	const CRON_HOOK_NAME = 'automator_47_migrate_triggers';

	/**
	 * Migrate triggers.
	 *
	 * Perform the migration steps in this method
	 *
	 * @return void
	 */
	public function migrate() {

		$this->fill_missing_hook_name_records();

		update_option( self::MIGRATED_FLAG, time(), true );

	}

	/**
	 * Get specific trigger object by trigger code.
	 *
	 * @param string $trigger_code The trigger code.
	 *
	 * @return array The trigger.
	 */
	private function get_trigger( $trigger_code = '' ) {

		if ( empty( $trigger_code ) ) {
			return null;
		}

		$trigger = array_filter(
			Automator()->get_triggers(),
			function ( $item ) use ( $trigger_code ) {
				return $trigger_code === $item['code'];
			}
		);

		$trigger = is_array( $trigger ) && ! empty( $trigger ) ? end( $trigger ) : array();

		return $trigger;

	}

	/**
	 * Retrieves all the triggers regardless of post_status.
	 *
	 * @return array The list of all triggers.
	 */
	public function get_triggers() {

		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT posts.ID , pm.meta_value as trigger_code
				FROM $wpdb->posts as posts
					JOIN $wpdb->postmeta pm
						ON posts.ID = pm.post_id and pm.meta_key = 'code'
				WHERE posts.post_type = %s
					AND NOT EXISTS (
						SELECT * FROM $wpdb->postmeta
						WHERE $wpdb->postmeta.meta_key = 'add_action'
							AND $wpdb->postmeta.post_id=posts.ID
						)
				ORDER BY pm.meta_value ASC",
				'uo-trigger'
			),
			ARRAY_A
		);

	}

	/**
	 * Iterates through every trigger and update the trigger action hook record if there is none.
	 *
	 * @return boolean True, always.
	 */
	private function fill_missing_hook_name_records() {

		$triggers = $this->get_triggers();

		if ( empty( $triggers ) ) {
			return true;
		}

		foreach ( $triggers as $trigger ) {

			$current_trigger = $this->get_trigger( $trigger['trigger_code'] );

			// Do not touch recipe that have in-active plugin dependency.
			if ( ! empty( $current_trigger['action'] ) ) {

				update_post_meta( $trigger['ID'], 'add_action', $current_trigger['action'] );

			}
		}

		return true;

	}

}

// Used `admin_init` to make sure migration happens after Automator has fully loaded.
add_action(
	'admin_init',
	function () {

		// Early bail if already migrated.
		$migrate = new Migrate_Triggers();

		if ( false !== automator_get_option( $migrate::MIGRATED_FLAG, false ) ) {
			return;
		}

		// Otherwise, migrate.
		$migrate->migrate();

	}
);

// Used `wp` to make sure migration happens after Automator has fully loaded
// and if forced migration is required.
add_action(
	'wp',
	function () {
		// Only let admin invoke this behaviour.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( automator_filter_has_var( 'automator_migrate' ) ) {
			( new Migrate_Triggers() )->migrate();
		}
	},
	PHP_INT_MAX
);

// Fallback to add `add_action` if a trigger exists on plugin activation.
add_action(
	'activated_plugin',
	function ( $plugin, $network_wide ) {
		// Check if any trigger is left for migration
		$triggers = ( new Migrate_Triggers() )->get_triggers();
		if ( empty( $triggers ) ) {
			return;
		}
		wp_schedule_single_event( time() + 2, \Uncanny_Automator\Migrations\Migrate_Triggers::CRON_HOOK_NAME );
	},
	PHP_INT_MAX,
	2
);


// Fallback to add `add_action` if a trigger exists on plugin activation -- cron hook.
add_action(
	\Uncanny_Automator\Migrations\Migrate_Triggers::CRON_HOOK_NAME,
	function () {
		( new Migrate_Triggers() )->migrate();
	}
);


