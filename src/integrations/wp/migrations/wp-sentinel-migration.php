<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class Wp_Sentinel_Migration
 *
 * Pre-release WP triggers stored several different sentinels for "Any X":
 *   - WPTAXONOMIES:   '0'  (taxonomy was the odd one out)
 *   - WPPOSTTYPES:    '0'  on 3 pre-release callers (use_zero_as_default => true)
 *                     '-1' on the other 4 callers
 *   - WPTAXONOMYTERM: '0'  on the two taxonomy/term triggers
 *
 * The modern remote_data dropdowns standardised every "Any X" to '-1', so
 * validate() now compares against '-1'. Recipes saved on pre-release with the
 * legacy sentinel silently stop firing after the upgrade because the validate
 * code no longer recognises the value as "Any".
 *
 * Migration: rewrite '0' (and '') → '-1' on those three meta keys. Safe because
 *   - post type slugs are never literal '0' or ''
 *   - taxonomy slugs are never literal '0' or ''
 *   - term IDs are positive integers; '0' and '' are not valid
 *
 * Two execution modes:
 *
 *   - `migrate()` — site-wide one-shot, gated by OPTION_FLAG, snapshots
 *     originals into OPTION_BACKUP for rollback().
 *   - `migrate_for_post_ids( [ID, ...] )` — targeted, scoped to the given
 *     post IDs. Bypasses OPTION_FLAG so newly-imported recipes get patched
 *     even after the site-wide pass has already completed.
 *
 * Both modes share a single rewrite implementation; only the WHERE clause
 * differs. The targeted mode is the listener attached to the shared
 * `automator_migrate_recipe_part_meta` action — see `register_listeners()`.
 *
 * v1 of this migration shipped as Wp_Taxonomy_Sentinel_Migration and only
 * covered WPTAXONOMIES. v2 (this class) bumps the option flag so sites that
 * already ran v1 re-execute and catch the keys v1 missed.
 *
 * @package Uncanny_Automator
 */
class Wp_Sentinel_Migration {

	const OPTION_FLAG   = 'uap_wp_sentinel_migrated_v2';
	const OPTION_BACKUP = 'uap_wp_sentinel_migration_v2_backup';

	const KEYS = array( 'WPTAXONOMIES', 'WPPOSTTYPES', 'WPTAXONOMYTERM' );

	/**
	 * Subscribe the targeted variant to the shared importer-driven action.
	 *
	 * Called from Wp_Integration::setup(). The action contract is documented
	 * in Import_Recipe::import_recipe_json() — fired once per import with the
	 * list of newly-created recipe + child post IDs.
	 *
	 * @return void
	 */
	public static function register_listeners() {
		add_action( 'automator_migrate_recipe_part_meta', array( __CLASS__, 'migrate_for_post_ids' ) );
	}

	/**
	 * Site-wide one-shot migration. Runs once per site, snapshots originals
	 * for rollback().
	 *
	 * @return void
	 */
	public static function migrate() {

		if ( automator_get_option( self::OPTION_FLAG, false ) ) {
			return;
		}

		$rewritten = self::rewrite( null );

		automator_update_option( self::OPTION_FLAG, time() );

		automator_log(
			sprintf( 'WP sentinel migration v2 complete: rewrote %d postmeta rows site-wide', $rewritten ),
			'wp_sentinel_migration'
		);
	}

	/**
	 * Targeted migration. Rewrites only postmeta rows belonging to the given
	 * post IDs. Bypasses OPTION_FLAG so it runs on imports regardless of
	 * whether the site-wide pass has completed.
	 *
	 * @param int[] $post_ids Recipe + child post IDs to rewrite.
	 *
	 * @return void
	 */
	public static function migrate_for_post_ids( $post_ids ) {

		$post_ids = self::sanitize_ids( $post_ids );
		if ( empty( $post_ids ) ) {
			return;
		}

		$rewritten = self::rewrite( $post_ids );

		if ( $rewritten > 0 ) {
			automator_log(
				sprintf(
					'WP sentinel migration (targeted): rewrote %d postmeta rows across %d post IDs',
					$rewritten,
					count( $post_ids )
				),
				'wp_sentinel_migration'
			);
		}
	}

	/**
	 * Shared rewrite implementation.
	 *
	 * Snapshots affected rows to OPTION_BACKUP, then rewrites '0'/'' → '-1'
	 * for the tracked meta keys. The snapshot key is suffixed in targeted
	 * mode so a per-import backup never overwrites the site-wide one.
	 *
	 * @param int[]|null $post_ids null = whole site; array = scoped.
	 *
	 * @return int Number of rows rewritten.
	 */
	private static function rewrite( $post_ids ) {

		global $wpdb;

		$key_placeholders = "'" . implode( "','", self::KEYS ) . "'";

		// Build the post_id scope clause + the snapshot bucket. Two-mode:
		// site-wide writes to OPTION_BACKUP; targeted appends a hash so
		// concurrent imports don't stomp the previous snapshot.
		if ( null === $post_ids ) {
			$scope_clause = '';
			$backup_key   = self::OPTION_BACKUP;
		} else {
			$ids_csv      = implode( ',', array_map( 'absint', $post_ids ) );
			$scope_clause = " AND post_id IN ({$ids_csv})";
			$backup_key   = self::OPTION_BACKUP . '_' . md5( $ids_csv . '|' . microtime( true ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- KEYS is a class constant whitelist; post IDs are absint'd above.
		$backup = $wpdb->get_results(
			"SELECT meta_id, post_id, meta_key, meta_value
			   FROM {$wpdb->postmeta}
			  WHERE meta_key IN ({$key_placeholders})
			    AND meta_value IN ('0', '')
			        {$scope_clause}"
		);

		if ( ! empty( $backup ) ) {
			automator_update_option(
				$backup_key,
				array(
					'timestamp' => time(),
					'scope'     => null === $post_ids ? 'site' : 'targeted',
					'post_ids'  => null === $post_ids ? array() : array_values( array_map( 'absint', $post_ids ) ),
					'rows'      => $backup,
				),
				false
			);
		}

		$rewritten = 0;
		foreach ( self::KEYS as $meta_key ) {
			foreach ( array( '0', '' ) as $legacy_value ) {
				if ( null === $post_ids ) {
					$updated = $wpdb->update(
						$wpdb->postmeta,
						array( 'meta_value' => '-1' ),
						array(
							'meta_key'   => $meta_key,
							'meta_value' => $legacy_value,
						),
						array( '%s' ),
						array( '%s', '%s' )
					);
				} else {
					$ids_csv = implode( ',', array_map( 'absint', $post_ids ) );
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- post IDs absint'd; meta_key/meta_value are parameters.
					$updated = $wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->postmeta}
							   SET meta_value = %s
							 WHERE meta_key   = %s
							   AND meta_value = %s
							   AND post_id IN ({$ids_csv})",
							'-1',
							$meta_key,
							$legacy_value
						)
					);
				}
				if ( false !== $updated ) {
					$rewritten += (int) $updated;
				}
			}
		}

		// Bust the post_meta cache for any touched IDs so subsequent
		// get_post_meta() in the same request sees the rewritten values.
		if ( null !== $post_ids ) {
			foreach ( $post_ids as $pid ) {
				wp_cache_delete( (int) $pid, 'post_meta' );
			}
		}

		return $rewritten;
	}

	/**
	 * Restore the original postmeta values from the site-wide v2 backup.
	 *
	 * Only restores the site-wide snapshot stored under OPTION_BACKUP.
	 * Targeted-mode snapshots are kept under suffixed keys for forensic
	 * lookup but are not rolled back here — imports are user-driven and
	 * easy to redo.
	 *
	 * @return int Number of rows restored. 0 if no backup exists.
	 */
	public static function rollback() {

		$backup = automator_get_option( self::OPTION_BACKUP, array() );

		if ( empty( $backup['rows'] ) ) {
			return 0;
		}

		global $wpdb;
		$restored = 0;

		foreach ( $backup['rows'] as $row ) {
			$updated = $wpdb->update(
				$wpdb->postmeta,
				array( 'meta_value' => $row->meta_value ),
				array( 'meta_id' => $row->meta_id ),
				array( '%s' ),
				array( '%d' )
			);
			if ( false !== $updated ) {
				$restored += $updated;
			}
		}

		automator_delete_option( self::OPTION_FLAG );
		automator_delete_option( self::OPTION_BACKUP );

		automator_log(
			sprintf( 'WP sentinel migration v2 rolled back: restored %d rows from backup', $restored ),
			'wp_sentinel_migration'
		);

		return $restored;
	}

	/**
	 * Coerce the action payload to a clean array of positive integers.
	 *
	 * @param mixed $post_ids
	 *
	 * @return int[]
	 */
	private static function sanitize_ids( $post_ids ) {
		if ( ! is_array( $post_ids ) ) {
			return array();
		}
		$out = array();
		foreach ( $post_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$out[ $id ] = $id;
			}
		}
		return array_values( $out );
	}
}
