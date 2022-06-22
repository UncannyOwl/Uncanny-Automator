<?php

namespace Uncanny_Automator\Migrations;

/**
 * Class Sample_Migration.
 *
 * @package Uncanny_Automator
 */
class Sample_Migration extends Migration {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		// Assign a unique migration name. Better to use Automator version number in the name to make sure we can reuse the same name in future versions
		parent::__construct( '45_sample_migration' );

	}

	/**
	 * conditions_met
	 *
	 * If needed, perfomr any condition checks in this method (e.g. if pro is enabled), otherwise delete it.
	 *
	 * @return void
	 */
	public function conditions_met() {
		return true;
	}

	/**
	 * migrate
	 *
	 * Perform the migration steps in this method
	 *
	 * @return void
	 */
	public function migrate() {

		// Do not hesitate to log the migration steps
		automator_log( 'Something to log', $this->name, true );

		// Call this function after migration was successfully completed and it will never run again
		$this->complete();
	}

}

new Sample_Migration();
