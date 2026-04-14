<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

use Uncanny_Automator\Migrations\Migration;

/**
 * Class Microsoft_Teams_Channel_Type_Migration
 *
 * Renames the TYPE option_code to CHANNEL_TYPE for the CREATE_CHANNEL action.
 *
 * The option_code "TYPE" collides with the framework's internal "type" meta key
 * (free/pro/elite), causing the saved value to overwrite the framework property
 * and breaking the recipe builder UI.
 *
 * @package Uncanny_Automator\Integrations\Microsoft_Teams
 */
class Microsoft_Teams_Channel_Type_Migration extends Migration {

	/**
	 * Perform the migration.
	 *
	 * @return void
	 */
	public function migrate() {

		global $wpdb;

		// Find all CREATE_CHANNEL action post IDs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$action_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm_code.post_id
				FROM {$wpdb->postmeta} pm_code
				INNER JOIN {$wpdb->postmeta} pm_int
					ON pm_code.post_id = pm_int.post_id
					AND pm_int.meta_key = 'integration'
					AND pm_int.meta_value = %s
				WHERE pm_code.meta_key = 'code'
				AND pm_code.meta_value = %s",
				'MICROSOFT_TEAMS',
				'CREATE_CHANNEL'
			)
		);

		if ( empty( $action_ids ) ) {
			$this->complete();
			return;
		}

		foreach ( $action_ids as $post_id ) {
			$this->migrate_action( absint( $post_id ) );
		}

		$this->complete();
	}

	/**
	 * Migrate a single CREATE_CHANNEL action post.
	 *
	 * @param int $post_id The action post ID.
	 *
	 * @return void
	 */
	private function migrate_action( $post_id ) {

		// Get the current TYPE value from the corrupted lowercase 'type' meta.
		$channel_type = get_post_meta( $post_id, 'type', true );

		// If the type meta is a framework value (free/pro/elite), there's nothing to migrate.
		if ( empty( $channel_type ) || in_array( $channel_type, array( 'free', 'pro', 'elite' ), true ) ) {
			// Still restore type to 'free' if missing.
			update_post_meta( $post_id, 'type', 'free' );
			return;
		}

		// The 'type' meta contains the channel membership type value — migrate it.
		// 1. Add CHANNEL_TYPE with the actual value.
		update_post_meta( $post_id, 'CHANNEL_TYPE', $channel_type );

		// 2. Add CHANNEL_TYPE_readable with ucfirst label.
		update_post_meta( $post_id, 'CHANNEL_TYPE_readable', ucfirst( $channel_type ) );

		// 3. Restore the framework's type meta to 'free'.
		update_post_meta( $post_id, 'type', 'free' );

		// 4. Delete the legacy TYPE_readable meta.
		delete_post_meta( $post_id, 'TYPE_readable' );
	}
}
