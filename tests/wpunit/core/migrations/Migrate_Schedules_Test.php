<?php

use Uncanny_Automator\Migrations\Migration;
use Uncanny_Automator\Migrations\Migrate_Schedules;

class Migrate_Schedules_Test extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		// Before...
		parent::setUp();

		if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			define( 'AUTOMATOR_PRO_PLUGIN_VERSION', '4.1' );
		}

		$this->migrate_schedules = new Migrate_Schedules();
	}

	public function tearDown(): void {
		// Your tear down methods here.

		// Then...
		parent::tearDown();
	}

	public function test_migration_construct() {

		$this->assertTrue( 10 === has_filter( 'pre_option_date_format', array( $this->migrate_schedules, 'intercept_date_format_option_calls' ) ) );

	}

	public function test_migration() {

		$future_date = mktime( 0, 0, 0, date( 'm' ), date( 'd' ), date( 'Y' ) + 1 );

		$date = date( get_option( 'date_format' ), $future_date );

		$action_id = $this->create_scheduled_action( $date );
		$this->create_delayed_action( $date );

		// Update cached recipes
		Automator()->get_recipes_data( true );

		$this->migrate_schedules->migrate();

		$schedule = get_post_meta( $action_id, 'async_schedule_date', true );

		$this->assertSame( date( 'Y-m-d', strtotime( $date ) ), $schedule );

	}

	public function test_convert_date_exception() {

		$this->expectException( \Exception::class );
		$this->migrate_schedules->convert_date( '16/06/2032' );

	}

	public function test_called_by_automator_pro() {

		$function = array(
			'class'    => 'Some\\Class',
			'function' => 'get_schedule_seconds',
		);

		$expected = false;
		$actual   = $this->migrate_schedules->called_by_automator_pro( $function );
		$this->assertSame( $expected, $actual );

		$function = array(
			'class'    => 'Uncanny_Automator_Pro\\Some_Class',
			'function' => 'get_schedule_seconds',
		);

		$expected = false;
		$actual   = $this->migrate_schedules->called_by_automator_pro( $function );
		$this->assertSame( $expected, $actual );

		$function = array(
			'class'    => 'Uncanny_Automator_Pro\\Async_Actions',
			'function' => 'get_schedule_seconds',
		);

		$expected = true;
		$actual   = $this->migrate_schedules->called_by_automator_pro( $function );
		$this->assertSame( $expected, $actual );

	}

	public function create_delayed_action( $date ) {

		$recipe_id = $this->create_recipe();

		$action_id = $this->create_action( $recipe_id );

		update_post_meta( $action_id, 'async_mode', 'delay' );

		return $action_id;

	}

	public function create_scheduled_action( $date ) {

		$recipe_id = $this->create_recipe();

		$action_id = $this->create_action( $recipe_id );

		update_post_meta( $action_id, 'async_mode', 'schedule' );
		update_post_meta( $action_id, 'async_schedule_date', $date );

		return $action_id;

	}

	public function create_recipe() {
		$args = array(
			'post_title'  => 'Test Recipe',
			'post_type'   => 'uo-recipe',
			'post_status' => 'publish',
		);

		return self::factory()->post->create( $args );
	}

	public function create_action( $recipe_id ) {
		$args = array(
			'post_title'  => 'Test Action',
			'post_type'   => 'uo-action',
			'post_status' => 'publish',
			'post_parent' => $recipe_id,
		);

		return self::factory()->post->create( $args );
	}

}

