<?php

namespace Uncanny_Automator\Migrations;

/**
 * Abstract class Migration.
 *
 * @package Uncanny_Automator
 */
abstract class Migration {

	const OPTION_NAME        = 'automator_migrations';
	const AUTOMATOR_PATH     = 'uncanny-automator/uncanny-automator.php';
	const AUTOMATOR_PRO_PATH = 'uncanny-automator-pro/uncanny-automator-pro.php';

	/**
	 * Prefix of the MySQL advisory lock one request holds while it runs a migration.
	 *
	 * The lock itself is lock_name(): one per site.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const LOCK_NAME = 'automator_migrations';

	/**
	 * name
	 *
	 * Required parameter
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Register the migration to run at the end of the request.
	 *
	 * @param string $name Unique migration name, recorded once it completes.
	 *
	 * @return void
	 */
	public function __construct( $name ) {

		$this->name = $name;

		add_action( 'shutdown', array( $this, 'maybe_run_migration' ) );
	}

	/**
	 * complete
	 *
	 * Run this function when the migration successfully completed. This will make sure it will never run again.
	 *
	 * @return void
	 */
	public function complete() {
		// Read fresh: another request may have stamped a migration since this one cached the option.
		$migrations                = automator_get_option( self::OPTION_NAME, array(), true );
		$migrations[ $this->name ] = time();
		automator_update_option( self::OPTION_NAME, $migrations );
		automator_log( 'Migration completed', $this->name, true );
	}

	/**
	 * maybe_run_migration
	 *
	 * Runs the migration if it should run.
	 *
	 * Every request runs migrations at shutdown until they complete, so two can
	 * reach the same one at once and interleave their writes. One request at a
	 * time runs them; a request that cannot take the lock leaves the work to a
	 * later request.
	 *
	 * @since 8.0.0 Runs under a lock shared by every migration.
	 *
	 * @return void
	 */
	public function maybe_run_migration() {

		if ( ! $this->conditions_met() ) {
			return;
		}

		// If the migration was completed before, bail.
		if ( $this->migration_completed_before() ) {
			return;
		}

		if ( ! $this->acquire_lock() ) {
			return;
		}

		try {
			// The request that held the lock may have completed it since the check above.
			if ( $this->migration_completed_before( true ) ) {
				return;
			}

			automator_log( 'Starting the migration', $this->name, true );

			// Run the migration;
			$this->migrate();
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Take the migrations lock without waiting.
	 *
	 * @since 8.0.0
	 *
	 * @return bool Whether this request holds the lock.
	 */
	private function acquire_lock() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock, nothing to cache.
		return '1' === $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', self::lock_name() ) );
	}

	/**
	 * Release the migrations lock.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function release_lock() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock, nothing to cache.
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', self::lock_name() ) );
	}

	/**
	 * The migrations lock of this site.
	 *
	 * MySQL named locks are shared by every database on the server, so one name for
	 * every site made sites on a shared MySQL host skip each other's migrations. The
	 * database name and table prefix make it one lock per site (and per subsite of a
	 * network, whose Automator tables are its own). Hashed to stay within MySQL's
	 * 64-character limit.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public static function lock_name() {
		global $wpdb;

		$database = defined( 'DB_NAME' ) ? DB_NAME : '';

		return self::LOCK_NAME . '_' . md5( $database . '|' . $wpdb->prefix );
	}

	/**
	 * migration_status
	 *
	 * Checks if the migration has completed previously.
	 *
	 * @since 8.0.0 Accepts $skip_cache.
	 *
	 * @param bool $skip_cache Whether to read the option from the database instead of the cache.
	 *
	 * @return mixed
	 */
	public function migration_completed_before( $skip_cache = false ) {

		$past_migrations = automator_get_option( self::OPTION_NAME, array(), $skip_cache );

		if ( ! empty( $past_migrations[ $this->name ] ) ) {
			return $past_migrations[ $this->name ];
		}

		return false;
	}

	/**
	 * conditions_met
	 *
	 * Override this method if you need to check some conditions before migrating.
	 *
	 * @return void
	 */
	public function conditions_met() {
		return true;
	}

	/**
	 * migrate
	 *
	 * Override this method and perform the migration in it.
	 *
	 * @return void
	 */
	abstract public function migrate();
}
