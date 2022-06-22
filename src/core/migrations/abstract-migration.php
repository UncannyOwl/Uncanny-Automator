<?php

namespace Uncanny_Automator\Migrations;

/**
 * Abstract class Migration.
 *
 * @package Uncanny_Automator
 */
abstract class Migration {

	const OPTION_NAME = 'automator_migrations';

	/**
	 * name
	 *
	 * Required parameter
	 *
	 * @var string
	 */
	public $name;

	public function __construct( $name ) {

		$this->name = $name;

		if ( $this->conditions_met() ) {
			add_action( 'shutdown', array( $this, 'maybe_run_migration' ) );
		}

	}

	/**
	 * complete
	 *
	 * Run this function when the migration successfully completed. This will make sure it will never run again.
	 *
	 * @return void
	 */
	public function complete() {
		$migrations                = get_option( self::OPTION_NAME, array() );
		$migrations[ $this->name ] = time();
		update_option( self::OPTION_NAME, $migrations );
		automator_log( 'Migration completed', $this->name, true );
	}

	/**
	 * maybe_run_migration
	 *
	 * Runs the migration if it should run.
	 *
	 * @return void
	 */
	public function maybe_run_migration() {

		// If the migration was completed before, bail.
		if ( $this->migration_completed_before() ) {
			return;
		}

		automator_log( 'Starting the migration', $this->name, true );

		// Run the migration;
		$this->migrate();

	}

	/**
	 * migration_status
	 *
	 * Checks if the migration has completed previously.
	 *
	 * @return mixed
	 */
	public function migration_completed_before() {

		$past_migrations = get_option( self::OPTION_NAME, array() );

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
