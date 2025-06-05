<?php

namespace Uncanny_Automator\Migrations;

use wpdb;

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
	const MIGRATED_FLAG   = 'automator_option_types_migration';
	const BATCH_SIZE      = 5000;
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
	 * Performs the migration: moves _type rows to the type column and deletes the _type rows, in batches.
	 *
	 * @return void
	 */
	public function migrate() {
		global $wpdb;
		$table = $wpdb->prefix . 'uap_options';

		// First, check total count of _type rows
		$total_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%_type'
			)
		);

		// If count is less than batch size, process all at once
		if ( $total_count <= self::BATCH_SIZE ) {
			$this->process_type_rows();
			return;
		}

		// For larger datasets, use batching
		$offset = (int) automator_get_option( self::PROGRESS_OPTION, 0 );

		// Get up to BATCH_SIZE _type rows, ordered by option_id for consistency
		$type_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value FROM $table WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%_type',
				self::BATCH_SIZE,
				$offset
			)
		);

		$processed = 0;
		foreach ( $type_rows as $type_row ) {
			$option_name = $type_row->option_name;
			if ( substr( $option_name, -5 ) !== '_type' ) {
				continue;
			}
			$main_option = substr( $option_name, 0, -5 );

			// Verify that the main option exists before updating
			$main_option_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table WHERE option_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$main_option
				)
			);

			if ( ! $main_option_exists ) {
				continue;
			}

			// Update the main row's type column
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table SET type = %s WHERE option_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$type_row->option_value,
					$main_option
				)
			);
			// Delete the _type row
			$wpdb->delete( $table, array( 'option_name' => $option_name ), array( '%s' ) );
			++$processed;
		}

		// If we processed a full batch, increment offset and continue next time
		if ( self::BATCH_SIZE === $processed ) {
			automator_update_option( self::PROGRESS_OPTION, $offset + self::BATCH_SIZE, true );
		} else {
			// Migration complete
			automator_update_option( self::MIGRATED_FLAG, time(), true );
			automator_delete_option( self::PROGRESS_OPTION );
			$this->complete();
		}
	}

	/**
	 * Process all type rows at once for smaller datasets
	 *
	 * @return void
	 */
	private function process_type_rows() {
		global $wpdb;
		$table = $wpdb->prefix . 'uap_options';

		// Get all _type rows
		$type_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value FROM $table WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%_type'
			)
		);

		foreach ( $type_rows as $type_row ) {
			$option_name = $type_row->option_name;
			if ( substr( $option_name, -5 ) !== '_type' ) {
				continue;
			}
			$main_option = substr( $option_name, 0, -5 );

			// Verify that the main option exists before updating
			$main_option_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table WHERE option_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$main_option
				)
			);

			if ( ! $main_option_exists ) {
				continue;
			}

			// Update the main row's type column
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table SET type = %s WHERE option_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$type_row->option_value,
					$main_option
				)
			);
			// Delete the _type row
			$wpdb->delete( $table, array( 'option_name' => $option_name ), array( '%s' ) );
		}

		// Mark migration as complete
		automator_update_option( self::MIGRATED_FLAG, time(), true );
		automator_delete_option( self::PROGRESS_OPTION );
		$this->complete();
	}
}

/**
 * Instantiates the migration class to hook into shutdown.
 */
new Migrate_Option_Types( '66_migrate_option_types' );
