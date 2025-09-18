<?php

namespace Uncanny_Automator\Integrations\EDD_SL;

/**
 * Class EDD_SL_Hook_Migration.
 *
 * Migrates EDD SL triggers from old hook 'edd_sl_post_set_expiration' to new hook 'edd_sl_post_set_status'.
 *
 * @package Uncanny_Automator
 */
class EDD_SL_Hook_Migration {

	/**
	 * Migrate EDD SL triggers from old hook to new hook.
	 *
	 * @return void
	 */
	public static function migrate() {

		// Check if migration has already been done
		if ( automator_get_option( 'uap_edd_sl_hook_migrated', false ) ) {
			return;
		}

		global $wpdb;

		// Direct MySQL query to find and update the old hook
		$result = $wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => 'edd_sl_post_set_status' ),
			array(
				'meta_key' => 'add_action',
				'meta_value' => 'edd_sl_post_set_expiration',
			),
			array( '%s' ),
			array( '%s', '%s' )
		);

		if ( false !== $result ) {
			// Mark migration as complete
			automator_update_option( 'uap_edd_sl_hook_migrated', time() );

			automator_log(
				sprintf( 'EDD SL hook migration complete: Updated %d trigger records from old hook to new hook', $result ),
				'edd_sl_hook_migration'
			);
		}
	}
}
