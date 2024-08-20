<?php

use Uncanny_Automator\Automator_Get_Data;
use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Recipe_Process_Complete;
use Uncanny_Automator\Recipe_Post_Rest_Api;

/**
 *
 */
class Automator_Get_Data_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		// Before...
		parent::setUp();
		//Automator_Get_Data::$instance = null;
		// Your set up methods here.
		$this->get_data = Automator_Get_Data::get_instance();
	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		// Your tear down methods here.

		// Then...
		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function test_get_instance() {
		Automator_Get_Data::$instance = null;
		// Generate a new instance
		$get_data_one = Automator_Get_Data::get_instance();
		$this->assertTrue( is_a( $get_data_one, 'Uncanny_Automator\Automator_Get_Data' ) );

		// Second time, we should get the existing instance
		$get_data_two = Automator_Get_Data::get_instance();

		$this->assertTrue( $get_data_one === $get_data_two );
	}

	/**
	 * @return void
	 */
	public function test_item_code_from_item_id_when_there_is_no_recipe_data() {

		$test = $this->get_data->item_code_from_item_id( null );
		$this->assertNull( $test );
	}

	/**
	 * @return void
	 */
	public function test_item_code_from_item_id() {

		// Prepare the mock array that get_recipes_data() function will return
		$recipes_data_mock = array(
			array(
				'triggers' => array(
					array(
						'ID'   => 1,
						'meta' => array(
							'code' => 'TESTTRIGGER1',
						),
					),
					array(
						'ID'   => 2,
						'meta' => array(
							'code' => 'TESTTRIGGER2',
						),
					),
				),
				'actions'  => array(
					array(
						'ID'   => 3,
						'meta' => array(
							'code' => 'TESTACTION1',
						),
					),
					array(
						'ID'   => 4,
						'meta' => array(
							'code' => 'TESTACTION2',
						),
					),
				),
				'closures' => array(
					array(
						'ID'   => 5,
						'meta' => array(
							'code' => 'TESTCLOSURE1',
						),
					),
					array(
						'ID'   => 6,
						'meta' => array(
							'code' => 'TESTCLOSURE2',
						),
					),
				),
			),

		);

		// Create a mock for the Automator_Functions class.
		$mock = $this->getMockBuilder( Automator_Functions::class )
					 ->onlyMethods( array( 'get_recipes_data' ) )
					 ->getMock();

		$mock->method( 'get_recipes_data' )
			 ->willReturn( $recipes_data_mock );

		// Store original object
		$Automator = Automator_Functions::$instance;
		// Swap the actual instance with the mock
		Automator_Functions::$instance = $mock;

		$test = $this->get_data->item_code_from_item_id( 1 );
		$this->assertTrue( $test === 'TESTTRIGGER1' );

		$test = $this->get_data->item_code_from_item_id( 3 );
		$this->assertTrue( $test === 'TESTACTION1' );

		$test = $this->get_data->item_code_from_item_id( 6 );
		$this->assertTrue( $test === 'TESTCLOSURE2' );

		// Revert back to the original object
		Automator_Functions::$instance = $Automator;

	}

	/**
	 * @return void
	 */
	public function test_trigger_actions_from_trigger_code() {

		// Should return null if no string passed
		$test = $this->get_data->trigger_actions_from_trigger_code( 123 );
		$this->assertNull( $test );

		// Now try a real value
		$test = $this->get_data->trigger_actions_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, 'template_redirect' );

		// Try unexisting
		$test = $this->get_data->trigger_actions_from_trigger_code( 'UNEXISTING' );
		$this->assertNull( $test );

		// When there are no triggers

		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->trigger_actions_from_trigger_code( 'VIEWPAGE' );
		$this->assertNull( $test );

		// Return the triggers back
		$Automator->triggers = $triggers;

	}

	/**
	 * @return void
	 */
	public function test_trigger_meta_from_trigger_code() {

		// Should return null if no string passed
		$test = $this->get_data->trigger_meta_from_trigger_code( 123 );
		$this->assertNull( $test );

		// THe VIEWPAGE trigger has no meta
		$test = $this->get_data->trigger_meta_from_trigger_code( 'VIEWPAGE' );
		$this->assertNull( $test );

		// The USERROLEUPDATED trigger has WPROLE meta
		$test = $this->get_data->trigger_meta_from_trigger_code( 'USERROLEUPDATED' );
		$this->assertsame( $test, 'WPROLE' );

		// Try unexisting meta
		$test = $this->get_data->trigger_meta_from_trigger_code( 'UNEXISTING' );
		$this->assertNull( $test );

		// Test when there are no triggers
		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->trigger_meta_from_trigger_code( 'VIEWPAGE' );
		$this->assertNull( $test );

		// Return the triggers back
		$Automator->triggers = $triggers;

	}

	/**
	 * @return void
	 */
	public function test_trigger_title_from_trigger_code() {

		// Should return null if no string passed
		$test = $this->get_data->trigger_title_from_trigger_code( 123 );
		$this->assertNull( $test );

		// The VIEWPAGE trigger title
		$test = $this->get_data->trigger_title_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, 'A user views a page' );

		// The USERROLEUPDATED trigger title
		$test = $this->get_data->trigger_title_from_trigger_code( 'USERROLEUPDATED' );
		$this->assertSame( $test, "A user's role changes to a specific role" );

		// Try unexisting trigger
		$test = $this->get_data->trigger_title_from_trigger_code( 'UNEXISTING' );
		$this->assertNull( $test );

		// Test when there are no triggers
		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->trigger_title_from_trigger_code( 'VIEWPAGE' );
		$this->assertNull( $test );

		// Return the triggers back
		$Automator->triggers = $triggers;

	}

	/**
	 * @return void
	 */
	public function test_action_title_from_action_code() {

		// Should return null if no string passed
		$test = $this->get_data->action_title_from_action_code( 123 );
		$this->assertNull( $test );

		// Test the AC_ANNON_ADD_CODE action title
		$test = $this->get_data->action_title_from_action_code( 'AC_ANNON_ADD_CODE' );
		$this->assertSame( $test, 'Add a contact to ActiveCampaign' );

		// The ZOOMREGISTERUSER action title
		$test = $this->get_data->action_title_from_action_code( 'ZOOMREGISTERUSER' );
		$this->assertSame( $test, 'Add the user to a meeting' );

		// Try unexisting action
		$test = $this->get_data->action_title_from_action_code( 'UNEXISTING' );
		$this->assertNull( $test );

		// Test when there are no actions
		// Temporary remove actions
		$Automator = Automator_Functions::get_instance();

		$actions            = $Automator->actions;
		$Automator->actions = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->action_title_from_action_code( 'AC_ANNON_ADD_CODE' );
		$this->assertNull( $test );

		// Return the triggers back
		$Automator->actions = $actions;

	}

	/**
	 * @return void
	 */
	public function test_action_sentence() {

		$test = $this->get_data->action_sentence( 1 );
		$this->assertTrue( is_array( $test ) );

		$test = $this->get_data->action_sentence( 0 );
		$this->assertTrue( '' === $test );

		$test = $this->get_data->action_sentence( 1, 'test' );
		$this->assertTrue( is_array( $test ) );

		add_filter(
			'automator_get_action_sentence',
			function () {
				return array( 'test' => 42 );
			}
		);

		$test = $this->get_data->action_sentence( 1, 'test' );
		$this->assertTrue( 42 === $test );
	}

	/**
	 * @return void
	 */
	public function test_trigger_validation_function_from_trigger_code() {

		$test = $this->get_data->trigger_validation_function_from_trigger_code();
		$this->assertNull( $test );

		$test = $this->get_data->trigger_validation_function_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test[1], 'view_page' );

		$test = $this->get_data->trigger_validation_function_from_trigger_code( 'DOESNTEXIST' );
		$this->assertNull( $test );

		// Test when there are no triggers
		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->trigger_validation_function_from_trigger_code( 'VIEWPAGE' );
		$this->assertNull( $test );

		// Return the triggers back
		$Automator->triggers = $triggers;

	}

	/**
	 * @return void
	 */
	public function test_trigger_integration_from_trigger_code() {

		$test = $this->get_data->trigger_integration_from_trigger_code();
		$this->assertNull( $test );

		$test = $this->get_data->trigger_integration_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, 'WP' );

		$test = $this->get_data->trigger_integration_from_trigger_code( 'WPFSUBFORM' );
		$this->assertNull( $test );

		// Test when there are no triggers
		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->trigger_integration_from_trigger_code( 'VIEWPAGE' );
		$this->assertNull( $test );

		// Return the triggers back
		$Automator->triggers = $triggers;

	}

	/**
	 * @return void
	 */
	public function test_action_integration_from_action_code() {

		$test = $this->get_data->action_integration_from_action_code();
		$this->assertNull( $test );

		$test = $this->get_data->action_integration_from_action_code( 'CREATEPOST' );
		$this->assertSame( $test, 'WP' );

		$test = $this->get_data->action_integration_from_action_code( 'ENRLCOURSE-A' );
		$this->assertNull( $test );

		// Test when there are no actions
		// Temporary remove actions
		$Automator = Automator_Functions::get_instance();

		$actions            = $Automator->actions;
		$Automator->actions = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->action_integration_from_action_code( 'CREATEPOST' );
		$this->assertNull( $test );

		// Return the actions back
		$Automator->actions = $actions;

	}

	/**
	 * @return void
	 */
	public function test_closure_integration_from_closure_code() {

		$test = $this->get_data->closure_integration_from_closure_code();
		$this->assertNull( $test );

		$test = $this->get_data->closure_integration_from_closure_code( 'REDIRECT' );
		$this->assertSame( $test, 'WP' );

		$test = $this->get_data->closure_integration_from_closure_code( 'ENRLCOURSE-A' );
		$this->assertNull( $test );

		// Test when there are no actions
		// Temporary remove actions
		$Automator = Automator_Functions::get_instance();

		$closures            = $Automator->closures;
		$Automator->closures = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->closure_integration_from_closure_code( 'CREATEPOST' );
		$this->assertNull( $test );

		// Return the closures back
		$Automator->closures = $closures;

	}

	/**
	 * @return void
	 */
	public function test_action_execution_function_from_action_code() {

		$test = $this->get_data->action_execution_function_from_action_code();
		$this->assertNull( $test );

		$test = $this->get_data->action_execution_function_from_action_code( 'CREATEPOST' );
		$this->assertTrue( 'create_post' === $test[1] );

		$test = $this->get_data->action_execution_function_from_action_code( 'ENRLCOURSE-A' );
		$this->assertNull( $test );

		// Test when there are no actions
		// Temporary remove actions
		$Automator = Automator_Functions::get_instance();

		$actions            = $Automator->actions;
		$Automator->actions = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->action_execution_function_from_action_code( 'CREATEPOST' );
		$this->assertNull( $test );

		// Return the actions back
		$Automator->actions = $actions;

	}

	/**
	 * @return void
	 */
	public function test_closure_execution_function_from_closure_code() {

		$test = $this->get_data->closure_execution_function_from_closure_code();
		$this->assertNull( $test );

		$test = $this->get_data->closure_execution_function_from_closure_code( 'REDIRECT' );
		$this->assertSame( $test[1], 'redirect' );

		$test = $this->get_data->closure_execution_function_from_closure_code( 'UNEXISTING' );
		$this->assertNull( $test );

		// Test when there are no actions
		// Temporary remove actions
		$Automator = Automator_Functions::get_instance();

		$closures            = $Automator->closures;
		$Automator->closures = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->closure_execution_function_from_closure_code( 'CREATEPOST' );
		$this->assertNull( $test );

		// Return the closures back
		$Automator->closures = $closures;

	}

	/**
	 * @return void
	 */
	public function test_value_from_trigger_meta() {

		// Test missing trigger code
		$test = $this->get_data->value_from_trigger_meta();
		$this->assertNull( $test );

		// Test missing meta
		$test = $this->get_data->value_from_trigger_meta( 'UNEXISTING' );
		$this->assertNull( $test );

		// Test unexisting meta
		$test = $this->get_data->value_from_trigger_meta( 'UNEXISTING', 'unexisting' );
		$this->assertNull( $test );

		// Test the actual meta
		$test = $this->get_data->value_from_trigger_meta( 'VIEWPAGE', 'author' );
		$this->assertSame( $test, 'Uncanny Owl' );

		// Test when there are no triggers
		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->value_from_trigger_meta( 'VIEWPAGE', 'author' );
		$this->assertNull( $test );

		// Return the triggers back
		$Automator->triggers = $triggers;

	}

	/**
	 * @return void
	 */
	public function test_value_from_action_meta() {

		// Test missing action code
		$test = $this->get_data->value_from_action_meta();
		$this->assertNull( $test );

		// Test missing action meta
		$test = $this->get_data->value_from_action_meta( 'UNEXISTING' );
		$this->assertNull( $test );

		// Test unexisting meta
		$test = $this->get_data->value_from_action_meta( 'UNEXISTING', 'unexisting' );
		$this->assertNull( $test );

		// Test the actual meta
		$test = $this->get_data->value_from_action_meta( 'CREATEPOST', 'author' );
		$this->assertSame( $test, 'Uncanny Owl' );

		// Test when there are no actions
		// Temporary remove actions
		$Automator = Automator_Functions::get_instance();

		$actions            = $Automator->actions;
		$Automator->actions = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->value_from_action_meta( 'CREATEPOST', 'author' );
		$this->assertNull( $test );

		// Return the actions back
		$Automator->actions = $actions;

	}

	/**
	 * @return void
	 */
	public function test_trigger_priority_from_trigger_code() {

		// Test missing trigger code
		$test = $this->get_data->trigger_priority_from_trigger_code();
		$this->assertNull( $test );

		// Test existing trigger code
		$test = $this->get_data->trigger_priority_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, 90 );

		// Test unexisting trigger code
		$test = $this->get_data->trigger_priority_from_trigger_code( 'UNEXISTING' );
		$this->assertSame( $test, 10 );

		// Test when there are no triggers
		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->trigger_priority_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, 10 );

		// Return the triggers back
		$Automator->triggers = $triggers;
	}

	/**
	 * @return void
	 */
	public function test_trigger_tokens_from_trigger_code() {

		// Test missing trigger code
		$test = $this->get_data->trigger_tokens_from_trigger_code();
		$this->assertNull( $test );

		// Test existing trigger code
		$test = $this->get_data->trigger_tokens_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, array() );

		// Test unexisting trigger code
		$test = $this->get_data->trigger_tokens_from_trigger_code( 'UNEXISTING' );
		$this->assertSame( $test, array() );

		// Test when there are no triggers
		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		$test = $this->get_data->trigger_tokens_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, array() );

		// Return the triggers back
		$Automator->triggers = $triggers;

	}

	/**
	 * @return void
	 */
	public function test_trigger_accepted_args_from_trigger_code() {

		// Test missing trigger code
		$test = $this->get_data->trigger_accepted_args_from_trigger_code();
		$this->assertNull( $test );

		// Test missing trigger code
		$test = $this->get_data->trigger_accepted_args_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, 1 );

		// Test missing trigger code
		$test = $this->get_data->trigger_accepted_args_from_trigger_code( 'UNEXISTING' );
		$this->assertSame( $test, 1 );

		// Test when there are no triggers
		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		// Test missing trigger code
		$test = $this->get_data->trigger_accepted_args_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, 1 );

		// Return the triggers back
		$Automator->triggers = $triggers;

	}

	/**
	 * @return void
	 */
	public function test_trigger_options_from_trigger_code() {

		// Test missing trigger code
		$test = $this->get_data->trigger_options_from_trigger_code( 7 );
		$this->assertNull( $test );

		// Test missing trigger code
		$test = $this->get_data->trigger_options_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, 1 );

		// Test missing trigger code
		$test = $this->get_data->trigger_options_from_trigger_code( 'UNEXISTING' );
		$this->assertSame( $test, array() );

		// Test when there are no triggers
		// Temporary remove triggers
		$Automator = Automator_Functions::get_instance();

		$triggers            = $Automator->triggers;
		$Automator->triggers = null;

		Automator_Functions::$instance = $Automator;

		// Test missing trigger code
		$test = $this->get_data->trigger_options_from_trigger_code( 'VIEWPAGE' );
		$this->assertSame( $test, array() );

		// Return the triggers back
		$Automator->triggers = $triggers;

	}

	/**
	 * @return void
	 */
	public function test_trigger_log_id() {

		$test = $this->get_data->trigger_log_id( null );
		$this->assertNull( $test );

		$test = $this->get_data->trigger_log_id( null, 1 );
		$this->assertNull( $test );

		$test = $this->get_data->trigger_log_id( null, 1, 1 );
		$this->assertNull( $test );

		$user_id       = 42;
		$trigger_id    = 42;
		$recipe_id     = 42;
		$completed     = 1;
		$recipe_log_id = 42;

		$trigger_log_id = Automator()->db->trigger->add( $user_id, $trigger_id, $recipe_id, $completed, $recipe_log_id );

		$test = $this->get_data->trigger_log_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );
		$this->assertSame( $test, $trigger_log_id );

	}

	/**
	 * @return void
	 */
	public function test_trigger_meta() {

		$test = $this->get_data->trigger_meta( null );
		$this->assertNull( $test );

		$test = $this->get_data->trigger_meta( null, 1 );
		$this->assertNull( $test );

		$test = $this->get_data->trigger_meta( null, 1, 1 );
		$this->assertNull( $test );

		// $user_id = 42;
		// $trigger_id = 42;
		// $recipe_id = 42;
		// $completed = 1;
		// $recipe_log_id = 42;

		// $trigger_log_id = Automator()->db->trigger->add( $user_id, $trigger_id, $recipe_id, $completed, $recipe_log_id );

		// $run_number = 1;
		// $args = array(
		//     'user_id' => $user_id,
		//     'meta_key' => 'test_key',
		//     'meta_value' => 'test_value'
		// );

		// Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );

		// $test = $this->get_data->trigger_meta( $user_id, $trigger_id, 'test_key', $trigger_log_id );

		// $this->assertSame( 'test_value', $test );

	}

	/**
	 * @return void
	 */
	public function test_next_run_number() {

		$recipe_id     = 42;
		$user_id       = 42;
		$fetch_current = false;

		$test = $this->get_data->next_run_number( $recipe_id, $user_id, $fetch_current );
		$this->assertSame( 1, $test );

		$trigger_log_id = Automator()->db->recipe->add( $user_id, $recipe_id, 1, 2 );

		$test = $this->get_data->next_run_number( $recipe_id, $user_id, $fetch_current );
		$this->assertSame( 3, $test );

	}

	/**
	 * @return void
	 */
	public function test_trigger_sentence() {

		$trigger_id = 42;

		$test = $this->get_data->trigger_sentence( $trigger_id );
		$this->assertSame( array(), $test );

		$new_recipe_id = $this->create_recipe();

		$default_meta = array(
			'NUMTIMES'                     => '1',
			'integration_name'             => 'WordPress',
			'integration'                  => 'WP',
			'sentence'                     => 'A user views {{a page:%1$s}} {{a number of:%2$s}} {{TEST:TEST}} time(s)',
			'sentence_human_readable'      => 'Human readable sentence',
			'sentence_human_readable_html' => 'Human readable sentence HTML',
			'TEST'                         => '42',
		);

		$trigger_id = $this->add_trigger( $new_recipe_id, 'VIEWPAGE', $default_meta );

		$test = $this->get_data->trigger_sentence( $trigger_id );
		$this->assertSame( 'A user views {{a page:%1$s}} {{a number of:%2$s}} {{42:42}} time(s)', $test['complete_sentence'] );
		$this->assertSame( 'Human readable sentence', $test['sentence_human_readable'] );
		$this->assertSame( 'Human readable sentence HTML', $test['sentence_human_readable_html'] );

		$test = $this->get_data->trigger_sentence( $trigger_id, 'code' );
		$this->assertSame( 'VIEWPAGE', $test );

	}

	/**
	 * @return void
	 */
	public function test_get_trigger_action_sentence() {

		$test = $this->get_data->get_trigger_action_sentence( 0 );
		$this->assertSame( array(), $test );

		$new_recipe_id = $this->create_recipe();
		$default_meta  = array( 'integration' => 'WP' );
		$trigger_id    = $this->add_trigger( $new_recipe_id, 'VIEWPAGE', $default_meta );

		$test = $this->get_data->get_trigger_action_sentence( $trigger_id );
		$this->assertSame( array(), $test );

	}

	/**
	 * @return void
	 */
	public function test_trigger_run_number() {

		$trigger_id     = 42;
		$trigger_log_id = 124;
		$user_id        = 0;

		$test = $this->get_data->trigger_run_number( $trigger_id, $trigger_log_id, $user_id );
		$this->assertSame( 1, $test );

		$user_id = 33;

		$test = $this->get_data->trigger_run_number( $trigger_id, $trigger_log_id, $user_id );
		$this->assertSame( 1, $test );

		$run_number = 77;

		$args = array(
			'user_id'    => $user_id,
			'meta_key'   => 'test',
			'meta_value' => 42,
		);

		Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );

		$run_number ++;

		Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );

		$test = $this->get_data->trigger_run_number( $trigger_id, $trigger_log_id, $user_id );
		$this->assertSame( '78', $test );
	}

	/**
	 * @return void
	 */
	public function test_recipes_from_trigger_code() {

		// Missing trigger code
		$test = $this->get_data->recipes_from_trigger_code();
		$this->assertSame( array(), $test );

		$check_trigger_code = 'VIEWPAGE';

		// No recipe ID
		$test = $this->get_data->recipes_from_trigger_code( $check_trigger_code );
		$this->assertSame( array(), $test );

		// Create an actual but unpublished recipe with a trigger
		$new_recipe_id = $this->create_recipe();
		$default_meta  = array( 'integration' => 'WP' );
		$this->add_trigger( $new_recipe_id, 'VIEWPAGE', $default_meta );

		$test = $this->get_data->recipes_from_trigger_code( $check_trigger_code, $new_recipe_id );
		$this->assertSame( array(), $test );

		// Publish the recipe
		$this->publish_recipe( $new_recipe_id );

		// Add another trigger
		$default_meta = array( 'integration' => 'BP' );
		$this->add_trigger( $new_recipe_id, 'BPACCACTIVATE', $default_meta );

		$test = $this->get_data->recipes_from_trigger_code( $check_trigger_code, $new_recipe_id );

		$found = array_shift( $test );
		$this->assertSame( $new_recipe_id, $found['ID'] );

		// Run the same test again to test the caching
		$test = $this->get_data->recipes_from_trigger_code( $check_trigger_code, $new_recipe_id );

		$found = array_shift( $test );
		$this->assertSame( $new_recipe_id, $found['ID'] );

	}

	/**
	 * @return mixed
	 */
	public function create_recipe() {

		$recipe_api = new Recipe_Post_Rest_Api();

		$request = new WP_REST_Request();

		$request->set_param( 'action', 'create' );

		$response = $recipe_api->create( $request );

		return $response->data['post_ID'];

	}

	/**
	 * @param $recipe_id
	 * @param $trigger_code
	 * @param $default_meta
	 *
	 * @return mixed
	 */
	public function add_trigger( $recipe_id, $trigger_code, $default_meta = array() ) {

		$recipe_api = new Recipe_Post_Rest_Api();

		$request = new WP_REST_Request();

		$request->set_param( 'recipePostID', $recipe_id );
		$request->set_param( 'action', 'add-new-trigger' );
		$request->set_param( 'item_code', $trigger_code );
		$request->set_param( 'default_meta', $default_meta );

		$response = $recipe_api->add( $request );

		return $response->data['post_ID'];
	}

	/**
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function publish_recipe( $recipe_id ) {

		$recipe_api = new Recipe_Post_Rest_Api();

		$request = new WP_REST_Request();

		$request->set_param( 'post_ID', $recipe_id );
		$request->set_param( 'post_status', 'publish' );

		$recipe_api->change_post_status( $request );
	}

	/**
	 * @return void
	 */
	public function test_meta_from_recipes() {

		// Empty recipes
		$recipes = array();
		$test    = $this->get_data->meta_from_recipes( $recipes );
		$this->assertSame( array(), $test );

		// No trigger meta
		$recipes = array( 1, 2 );
		$test    = $this->get_data->meta_from_recipes( $recipes );
		$this->assertSame( array(), $test );

		$recipes = array(

			33 => array(
				'ID'       => 33,
				'triggers' => array(
					array(
						'ID'   => 108,
						'meta' => array(
							'SOME_META'        => 'automator_custom_value',
							'SOME_META_custom' => 42,
						),
					),
				),
			),

		);

		$expected = array(
			33 => array(
				108 => 42,
			),
		);

		$test = $this->get_data->meta_from_recipes( $recipes, 'SOME_META' );
		$this->assertSame( $expected, $test );
	}

	/**
	 * @return void
	 */
	public function test_maybe_get_meta_id_from_trigger_log() {

		$test = $this->get_data->maybe_get_meta_id_from_trigger_log();
		$this->assertNull( $test );

		$run_number     = 1;
		$trigger_id     = 42;
		$trigger_log_id = 142;
		$meta_key       = 'TEST';
		$user_id        = 1;

		$args = array(
			'user_id'    => $user_id,
			'meta_key'   => $meta_key,
			'meta_value' => 42,
		);

		Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );

		$test = $this->get_data->maybe_get_meta_id_from_trigger_log( $run_number, $trigger_id, $trigger_log_id, $meta_key, $user_id );
		$this->assertNotNull( $test );
	}

	/**
	 * @return void
	 */
	public function test_maybe_get_meta_value_from_trigger_log() {

		$test = $this->get_data->maybe_get_meta_value_from_trigger_log();
		$this->assertNull( $test );

		$meta_key       = 'TEST';
		$meta_value     = '42';
		$trigger_id     = 42;
		$trigger_log_id = 142;
		$run_number     = 1;
		$user_id        = 1;

		$args = array(
			'user_id'    => $user_id,
			'meta_key'   => $meta_key,
			'meta_value' => $meta_value,
		);

		Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );

		$test = $this->get_data->maybe_get_meta_value_from_trigger_log( $meta_key, $trigger_id, $trigger_log_id, $run_number, $user_id );
		$this->assertSame( $meta_value, $test );
	}

	/**
	 * @return void
	 */
	public function test_get_trigger_log_meta() {

		$test = $this->get_data->get_trigger_log_meta();
		$this->assertNull( $test );

		$meta_key       = 'TEST';
		$meta_value     = '42';
		$trigger_id     = 42;
		$trigger_log_id = 142;
		$run_number     = 1;
		$user_id        = 1;

		$test = $this->get_data->get_trigger_log_meta( $meta_key, $trigger_id, $trigger_log_id, $run_number, $user_id );
		$this->assertNull( $test );

		$args = array(
			'user_id'    => $user_id,
			'meta_key'   => $meta_key,
			'meta_value' => $meta_value,
		);

		Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );

		$test = $this->get_data->get_trigger_log_meta( $meta_key, $trigger_id, $trigger_log_id, $run_number, $user_id );
		$this->assertSame( $meta_value, $test );

	}

	/**
	 * @return void
	 */
	public function test_maybe_get_recipe_id() {

		$test = $this->get_data->maybe_get_recipe_id( null );
		$this->assertSame( 0, $test );

		$test = $this->get_data->maybe_get_recipe_id( new stdClass() );
		$this->assertSame( 0, $test );

		$new_recipe_id = $this->create_recipe();
		$test          = $this->get_data->maybe_get_recipe_id( $new_recipe_id );
		$this->assertSame( $new_recipe_id, $test );

		$default_meta = array( 'integration' => 'WP' );
		$trigger_id   = $this->add_trigger( $new_recipe_id, 'VIEWPAGE', $default_meta );
		$test         = $this->get_data->maybe_get_recipe_id( $trigger_id );
		$this->assertSame( $new_recipe_id, $test );

		$test = $this->get_data->maybe_get_recipe_id( 999 );
		$this->assertSame( 0, $test );

	}

	/**
	 * @return void
	 */
	public function test_get_recipe_requires_user() {

		$new_recipe_id = $this->create_recipe();

		$test = $this->get_data->get_recipe_requires_user( $new_recipe_id );
		$this->assertTrue( $test );

		update_post_meta( $new_recipe_id, 'uap_recipe_version', '3.2' );

		$test = $this->get_data->get_recipe_requires_user( $new_recipe_id );
		$this->assertFalse( $test );

		update_post_meta( $new_recipe_id, 'recipe_requires_user', '42' );

		$test = $this->get_data->get_recipe_requires_user( $new_recipe_id );
		$this->assertSame( '42', $test );

	}

	/**
	 * @return void
	 */
	public function test_mayabe_get_token_meta_value_from_trigger_log() {

		$trigger_id    = 42;
		$run_number    = 1;
		$recipe_id     = 37;
		$meta_key      = 'TEST';
		$meta_value    = '77';
		$user_id       = 1;
		$recipe_log_id = 33;
		$completed     = 1;

		$trigger_log_id = Automator()->db->trigger->add( $user_id, $trigger_id, $recipe_id, $completed, $recipe_log_id );

		$args = array(
			'user_id'    => $user_id,
			'meta_key'   => $meta_key,
			'meta_value' => $meta_value,
		);

		Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );

		$test = $this->get_data->mayabe_get_token_meta_value_from_trigger_log( $trigger_id, $run_number, $recipe_id, $meta_key, $user_id, $recipe_log_id );
		$this->assertSame( $meta_value, $test );
	}

	/**
	 * @return void
	 */
	public function test_mayabe_get_real_trigger_log_id() {

		$trigger_id    = 42;
		$run_number    = 1;
		$recipe_id     = 33;
		$user_id       = 1;
		$recipe_log_id = 77;
		$completed     = 1;

		$trigger_log_id = Automator()->db->trigger->add( $user_id, $trigger_id, $recipe_id, $completed, $recipe_log_id );

		$args = array(
			'user_id'    => $user_id,
			'meta_key'   => 'TEST',
			'meta_value' => '108',
		);

		Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );

		$test = $this->get_data->mayabe_get_real_trigger_log_id( $trigger_id, $run_number, $recipe_id, $user_id, $recipe_log_id );
		$this->assertEquals( $trigger_log_id, $test );
	}

	/**
	 * @return void
	 */
	public function test_recipe_completed_times() {

		$user_id    = 1;
		$recipe_id  = 42;
		$completed  = 1;
		$run_number = 1;

		// RUn the recipe three times with different users
		Automator()->db->recipe->add( $user_id, $recipe_id, $completed, $run_number );
		Automator()->db->recipe->add( $user_id ++, $recipe_id, $completed, $run_number ++ );
		Automator()->db->recipe->add( $user_id ++, $recipe_id, $completed, $run_number ++ );

		$test = $this->get_data->recipe_completed_times( $recipe_id );
		$this->assertEquals( 3, $test );

	}

	/**
	 * @return void
	 */
	public function test_recipe_completed_times_by_user() {

		$user_id    = 1;
		$recipe_id  = 42;
		$completed  = 1;
		$run_number = 1;

		// Run the recipe twice with user 1 and once with user 2
		Automator()->db->recipe->add( $user_id, $recipe_id, $completed, $run_number );
		Automator()->db->recipe->add( $user_id, $recipe_id, $completed, $run_number ++ );
		Automator()->db->recipe->add( $user_id + 1, $recipe_id, $completed, $run_number ++ );

		$test = $this->get_data->recipe_completed_times_by_user( $recipe_id, $user_id );
		$this->assertEquals( 2, $test );

	}
}
