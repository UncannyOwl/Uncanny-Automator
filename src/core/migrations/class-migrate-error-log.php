<?php

namespace Uncanny_Automator\Migrations;

use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Action_Error_Store;

/**
 * Backfills uap_error_log from existing uap_action_log error messages.
 *
 * Runs in batches — each shutdown processes up to 500 rows.
 * If rows remain, schedules a cron to continue on the next request.
 * Idempotent via LEFT JOIN (safe to re-run).
 *
 * @package Uncanny_Automator\Migrations
 * @since   7.3
 */
class Migrate_Error_Log extends Migration {

	/**
	 * Cron hook for continued batch processing.
	 */
	const CRON_HOOK = 'automator_migrate_error_log_batch';

	/**
	 * Rows per batch.
	 */
	const BATCH_SIZE = 500;

	/**
	 * @return void
	 */
	public function __construct() {
		parent::__construct( '73_error_log_backfill' );

		add_action( self::CRON_HOOK, array( $this, 'run_batch' ) );
	}

	/**
	 * Check if the uap_error_log table exists before running.
	 *
	 * @return bool
	 */
	public function conditions_met() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
				$wpdb->prefix . 'uap_error_log'
			)
		);

		return '1' === $table_exists;
	}

	/**
	 * Entry point — called by the migration system on shutdown.
	 *
	 * @return void
	 */
	public function migrate() {
		$this->run_batch();
	}

	/**
	 * Process one batch. Schedules next batch if rows remain.
	 *
	 * @return void
	 */
	public function run_batch() {

		$store    = \Uncanny_Automator\App\Infrastructure\Database\Database::get_action_error_store();
		$migrated = $store->migrate_legacy_errors( self::BATCH_SIZE );

		if ( $migrated >= self::BATCH_SIZE ) {
			// More rows likely remain — schedule next batch.
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( time() + 10, self::CRON_HOOK );
			}
			return;
		}

		// All rows processed — mark migration complete.
		$this->complete();
	}
}

new Migrate_Error_Log();
