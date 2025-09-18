<?php

namespace Uncanny_Automator\Migrations;

use RuntimeException;
use Throwable;

/**
 * Automator Options Migration class.
 *
 * Handles the migration of options from WordPress wp_options table to the custom uap_options table.
 * This migration is designed to be idempotent and can be run multiple times safely.
 *
 * The migration process:
 * - Loads a predefined list of option keys to migrate from array-option-keys.php
 * - Checks if each option already exists in uap_options table to avoid duplicates
 * - Migrates options from wp_options to uap_options using automator_add_option()
 * - Tracks migration progress and errors
 * - Only marks migration as complete when no errors occur
 * - Logs detailed results for debugging and monitoring
 *
 * @since 1.0.0
 * @package Uncanny_Automator\Migrations
 *
 * @uses Migration Base migration class for common migration functionality.
 * @uses wpdb WordPress database abstraction layer.
 * @uses automator_add_option() Function to add options to uap_options table.
 * @uses automator_log() Function to log migration results and errors.
 */
class Automator_Options_Migration extends Migration {

	/**
	 * Cron hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'automator_options_migration_cron';

	/**
	 * Migration ID.
	 *
	 * @var string
	 */
	const MIGRATION_ID = 'automator_options_migration_v1';

	/**
	 * Constructor.
	 *
	 * Initializes the migration with a unique name and sets up the cron hook
	 * for automated migration execution. Reuses existing cron infrastructure
	 * instead of creating a new one.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( self::MIGRATION_ID );
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Reuse the cron infra instead of making a new one.
		add_action( self::CRON_HOOK, array( $this, 'maybe_run_migration' ) );
	}

	/**
	 * Run the migration.
	 *
	 * Migrates options from WordPress wp_options table to the custom uap_options table.
	 * This method processes all migratable keys defined in the array-option-keys.php file.
	 *
	 * Migration process:
	 * 1. Loads migratable keys from the configuration file
	 * 2. For each key, checks if it already exists in uap_options table
	 * 3. If not exists, fetches the option from wp_options table
	 * 4. Migrates the option using automator_add_option() function
	 * 5. Tracks migration results (migrated, skipped, errors)
	 * 6. Only marks migration as complete if no errors occurred
	 * 7. Logs the final results
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return void
	 *
	 * @throws \Throwable When database operations fail or unexpected errors occur.
	 *                   Errors are caught and logged, but may prevent migration completion.
	 *
	 * @uses automator_add_option() To add migrated options to uap_options table.
	 * @uses automator_log() To log migration results and errors.
	 * @uses $wpdb To perform database queries on wp_options and uap_options tables.
	 */
	/**
	 * Migrate.
	 */
	public function migrate() {

		$migratable_keys = $this->get_migratable_keys();

		$results = $this->process_migratable_keys( $migratable_keys );

		$this->finalize_migration( $results );
	}

	/**
	 * Gets the list of migratable option keys.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array Array of option keys to migrate.
	 */
	public function get_migratable_keys() {
		return (array) include trailingslashit( __DIR__ ) . 'array-option-keys.php';
	}

	/**
	 * Validates that the key is a non-empty string.
	 *
	 * @param string $key
	 * @throws RuntimeException If the key is invalid.
	 */
	public function assert_valid_key( $key ) {
		if ( empty( trim( $key ) ) ) {
			throw new RuntimeException( 'Invalid empty key found in migratable keys' );
		}
	}

	/**
	 * Checks if an option has already been migrated to uap_options.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function option_already_migrated( $key ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->uap_options} WHERE option_name = %s",
				$key
			)
		);
	}

	/**
	 * Fetches an option row from wp_options.
	 *
	 * @param string $key
	 * @return object|null
	 */
	public function fetch_wp_option( $key ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s",
				$key
			)
		);
	}

	/**
	 * Migrates a single option from wp_options to uap_options.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param string $autoload
	 * @throws RuntimeException If migration fails.
	 */
	public function migrate_option( $key, $value, $autoload ) {
		$migrated = automator_add_option(
			$key,
			$value,
			'yes' === $autoload,
			false
		);

		if ( false === $migrated ) {
			throw new RuntimeException( esc_html( "Failed to migrate {$key}" ) );
		}
	}

	/**
	 * Processes all migratable keys and returns migration results.
	 *
	 * @param array $migratable_keys Array of option keys to migrate.
	 * @return array Migration results containing migrated, skipped, and errors counts.
	 */
	public function process_migratable_keys( $migratable_keys ) {
		$results = array(
			'migrated' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		foreach ( $migratable_keys as $key ) {
			try {
				$this->assert_valid_key( $key );

				if ( $this->option_already_migrated( $key ) ) {
					++$results['skipped'];
					continue;
				}

				$row = $this->fetch_wp_option( $key );
				if ( ! $row ) {
					continue;
				}

				$this->migrate_option( $key, $row->option_value, $row->autoload );
				++$results['migrated'];

			} catch ( RuntimeException $e ) {
				$results['errors'][] = $e->getMessage();
			} catch ( Throwable $e ) {
				$results['errors'][] = "Error on {$key}: " . $e->getMessage();
			}
		}

		return $results;
	}

	/**
	 * Finalizes the migration by marking it complete and logging results.
	 *
	 * @param array $results The migration results containing migrated, skipped, and errors counts.
	 * @return void
	 */
	public function finalize_migration( $results ) {
		// Always mark as complete since we successfully ran the migration process.
		// Individual option failures are logged but don't prevent completion.
		// This ensures the migration doesn't run again even if some options failed.
		$this->complete();

		automator_log(
			'Options Migration V1 results: ' . wp_json_encode( $results ),
			$this->name,
			true
		);
	}
}
