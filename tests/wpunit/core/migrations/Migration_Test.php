<?php

use Uncanny_Automator\Migrations\Migration;

class Migration_Test extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		// Before...
		parent::setUp();

		require_once UA_ABSPATH . '/src/core/migrations/class-sample-migration.php';

		$this->test_migration = new Uncanny_Automator\Migrations\Sample_Migration();

	}

	public function tearDown(): void {
		// Your tear down methods here.

		// Then...
		parent::tearDown();
	}

	public function test_migration_attached_to_hook() {

		$this->assertTrue( 10 === has_filter( 'activate_' . $this->test_migration::AUTOMATOR_PATH, array( $this->test_migration, 'maybe_run_migration' ) ) );
		$this->assertTrue( 10 === has_filter( 'activate_' . $this->test_migration::AUTOMATOR_PRO_PATH, array( $this->test_migration, 'maybe_run_migration' ) ) );

	}

	public function test_conditions_met() {
		$this->assertTrue( $this->test_migration->conditions_met() );
	}

	public function test_migration_complete() {

		$past_migrations = get_option( Migration::OPTION_NAME, array() );

		$this->assertTrue( empty( $past_migrations ) );

		$this->test_migration->maybe_run_migration();

		$past_migrations = get_option( Migration::OPTION_NAME, array() );

		$this->assertTrue( $past_migrations[ $this->test_migration->name ] > 0 );

		// Store the previous migration timestamp
		$past_migration_timestamp = $past_migrations[ $this->test_migration->name ];

		$this->test_migration->maybe_run_migration();

		$past_migrations = get_option( Migration::OPTION_NAME, array() );

		// Make sure the timestamp is still the same
		$this->assertTrue( $past_migration_timestamp === $past_migrations[ $this->test_migration->name ] );

	}

}

