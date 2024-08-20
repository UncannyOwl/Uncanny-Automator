<?php

class Automator_Functions_Test extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		// Before...
		parent::setUp();
		// Your set up methods here.
	}

	public function tearDown(): void {
		// Your tear down methods here.

		// Then...
		parent::tearDown();
	}

	// Tests
	public function test_triggers_count() {

		$items = Automator()->get_triggers();

		$this->assertGreaterThanOrEqual( 27, count( $items ) );
	}

	public function test_actions_count() {

		$items = Automator()->get_actions();

		$this->assertGreaterThanOrEqual( 112, count( $items ) );
	}

	public function test_closures_count() {

		$items = Automator()->get_closures();

		$this->assertGreaterThanOrEqual( 1, count( $items ) );
	}

	public function test_recipe_items_count() {

		$items = Automator()->get_recipe_items();

		$this->assertGreaterThanOrEqual( 36, count( $items ) );
	}

	public function test_integrations_count() {

		$items = Automator()->get_integrations();

		$this->assertGreaterThanOrEqual( 38, count( $items ) );
	}

	public function test_all_integrations_count() {

		$items = Automator()->get_all_integrations();

		$this->assertGreaterThanOrEqual( 102, count( $items ) );
	}

	public function test_get_recipe_types() {
		$items = Automator()->get_recipe_types();

		$this->assertGreaterThanOrEqual( 2, $items );
	}

	public function test_get_option_unexisting() {

		// At this point the option should not exist in the database and it should return false
		$expected = false;
		$actual   = automator_get_option( '12345', false );

		$this->assertEquals( $expected, $actual );
	}

	public function test_get_option_existing() {

		update_option( 'some_option', 12345 );

		$expected = 12345;
		$actual   = automator_get_option( 'some_option', false );

		$this->assertEquals( $expected, $actual );

		delete_option( 'some_option' );

		$expected = false;
		$actual   = automator_get_option( 'some_option', false );

		$this->assertEquals( $expected, $actual );
	}
}
