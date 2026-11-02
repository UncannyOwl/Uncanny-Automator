<?php

namespace Uncanny_Automator\Migrations;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrate_Option_Types
 *
 * Migrates option type information from _type rows to the type column in the main row, in batches.
 *
 * @package Uncanny_Automator\Migrations
 */
class Migrate_Option_Types extends Migration {

	/**
	 * The option key to determine if option types have already been migrated.
	 *
	 * @var string
	 */
	const MIGRATED_FLAG = 'automator_option_types_migration';

	/**
	 * Rows per request.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 5000;

	/**
	 * Highest `_type` row ID processed so far.
	 *
	 * Each batch deletes the rows it moved, so a numeric OFFSET steps over rows
	 * that were never read. The batches page on the row ID instead.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const CURSOR_OPTION = 'automator_option_types_migration_cursor';

	/**
	 * The OFFSET the batches kept before they paged on the row ID; deleted when the migration finishes.
	 *
	 * @var string
	 */
	const PROGRESS_OPTION = 'automator_option_types_migration_offset';

	/**
	 * Checks if the migration should run.
	 *
	 * @return bool True if migration should run, false otherwise.
	 */
	public function conditions_met() {
		// Only run if not already migrated
		return empty( automator_get_option( self::MIGRATED_FLAG, '' ) );
	}

	/**
	 * Move one batch of `_type` rows into the type column of their options.
	 *
	 * A batch shorter than BATCH_SIZE has read every row after the cursor, so it
	 * finishes the migration; a full one records the cursor for the next request.
	 *
	 * @since 8.0.0 Pages on the row ID, and finishes only when no row is left.
	 *
	 * @return void
	 */
	public function migrate() {
		global $wpdb;

		$table  = $wpdb->prefix . 'uap_options';
		$cursor = (int) automator_get_option( self::CURSOR_OPTION, 0, true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration.
		$type_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_id, option_name, option_value FROM `' . esc_sql( $table ) . '` WHERE option_name LIKE %s AND option_id > %d ORDER BY option_id ASC LIMIT %d',
				'%' . $wpdb->esc_like( '_type' ),
				$cursor,
				self::BATCH_SIZE
			)
		);
		if ( ! is_array( $type_rows ) || '' !== $wpdb->last_error ) {
			return;
		}

		foreach ( $type_rows as $type_row ) {
			if ( ! $this->move_type_row( $table, $type_row ) ) {
				return;
			}
			$cursor = (int) $type_row->option_id;
		}

		if ( count( (array) $type_rows ) < self::BATCH_SIZE ) {
			$this->finish();

			return;
		}

		automator_update_option( self::CURSOR_OPTION, $cursor, true );
	}

	/**
	 * Move one `_type` row into its option's type column and delete it.
	 *
	 * A row whose option no longer exists is left where it is.
	 *
	 * @since 8.0.0
	 *
	 * @param string    $table    The uap_options table.
	 * @param \stdClass $type_row The row: option_id, option_name, option_value.
	 *
	 * @return bool Whether the row was handled without a failed query.
	 */
	private function move_type_row( $table, $type_row ) {
		global $wpdb;

		$option_name = (string) $type_row->option_name;

		// LIKE ignores case; the companion suffix is exactly `_type`.
		if ( '_type' !== substr( $option_name, -5 ) ) {
			return true;
		}

		$main_option = substr( $option_name, 0, -5 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration.
		$main_option_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE option_name = %s',
				$main_option
			)
		);
		if ( '' !== $wpdb->last_error || null === $main_option_exists ) {
			return false;
		}

		if ( ! $main_option_exists ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration.
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE `' . esc_sql( $table ) . '` SET type = %s WHERE option_name = %s',
				$type_row->option_value,
				$main_option
			)
		);
		if ( false === $updated ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration deletes the obsolete companion row.
		return false !== $wpdb->delete( $table, array( 'option_id' => (int) $type_row->option_id ), array( '%d' ) );
	}

	/**
	 * Flag the migration done and clear its progress.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function finish() {
		automator_update_option( self::MIGRATED_FLAG, time(), true );
		automator_delete_option( self::CURSOR_OPTION );
		automator_delete_option( self::PROGRESS_OPTION );
		$this->complete();
	}
}

/**
 * Instantiates the migration class to hook into shutdown.
 */
new Migrate_Option_Types( '66_migrate_option_types' );
