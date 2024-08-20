<?php

use Uncanny_Automator\Automator_Recipe_Process_User;
use Uncanny_Automator\Automator_Get_Data;
use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Utilities;

use \Codeception\Stub\Expected;

class Automator_Recipe_Process_User_Test extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->Automator_Get_Data  = Automator_Get_Data::$instance;
		$this->Automator_Functions = Automator_Functions::$instance;

		$this->mocked_result = array(
			11 =>
			array(
				'ID'                        => '11',
				'post_status'               => 'publish',
				'recipe_type'               => 'anonymous',
				'triggers'                  =>
				array(
					0 =>
					array(
						'ID'          => '12',
						'post_status' => 'publish',
						'menu_order'  => '0',
						'meta'        =>
						array(
							'code'                         => 'WPVIEWPOSTTYPE',
							'integration'                  => 'WP',
							'uap_trigger_version'          => '3.4.0.2',
							'add_action'                   => 'template_redirect',
							'can_log_in_new_user'          => 'true',
							'integration_name'             => 'WordPress',
							'sentence'                     => 'A {{specific type of post:WPPOSTTYPES}} is viewed',
							'sentence_human_readable'      => 'A {{Any post type}} is viewed',
							'sentence_human_readable_html' => '<div><span class="item-title__normal">A </span><span class="item-title__token" data-token-id="WPPOSTTYPES" data-options-id="WPPOSTTYPES">Any post type</span><span class="item-title__normal"> is viewed</span></div>',
							'WPPOSTTYPES_readable'         => 'Any post type',
							'WPPOSTTYPES'                  => '-1',
						),
						'tokens'      =>
						array(),
					),
				),
				'actions'                   =>
				array(
					0 =>
					array(
						'ID'          => '13',
						'post_status' => 'publish',
						'menu_order'  => '0',
						'meta'        =>
						array(
							'code'                         => 'CREATEPOST',
							'integration'                  => 'WP',
							'uap_action_version'           => '3.4.0.2',
							'integration_name'             => 'WordPress',
							'sentence'                     => 'Create {{a post:CREATEPOST}}',
							'sentence_human_readable'      => 'Create {{Post}}',
							'sentence_human_readable_html' => '<div><span class="item-title__normal">Create </span><span class="item-title__token" data-token-id="CREATEPOST" data-options-id="CREATEPOST">Post</span></div>',
							'CREATEPOST_readable'          => 'Post',
							'CREATEPOST'                   => 'post',
							'WPCPOSTSTATUS_readable'       => 'Published',
							'WPCPOSTSTATUS'                => 'publish',
							'WPCPOSTAUTHOR'                => '{{admin_email}}',
							'WPCPOSTTITLE'                 => '123 {{current_date}} {{current_time}}',
							'WPCPOSTSLUG'                  => '',
							'WPCPOSTCONTENT'               => '',
							'FEATURED_IMAGE_URL'           => '',
							'CPMETA_PAIRS'                 => '[]',
						),
					),
				),
				'closures'                  =>
				array(),
				'completed_by_current_user' => false,
			),
		);
	}

	public function tearDown(): void {

		Automator_Get_Data::$instance  = $this->Automator_Get_Data;
		Automator_Functions::$instance = $this->Automator_Functions;

		parent::tearDown();
	}

	public function count_trigger_entries() {
		global $post, $wpdb;

		$rowcount = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}uap_trigger_log" );

		return $rowcount;
	}

	public function test_maybe_add_trigger_entry() {

		$this->rpu = new Automator_Recipe_Process_User();

		$args = array();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		$this->assertNull( $actual );

		$args = array(
			'code' => 'WPVIEWPOSTTYPE',
		);

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'recipes_from_trigger_code' => $this->mocked_result,
			)
		);

		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that now we have one entry in the trigger log DB table
		$this->assertEquals( $count_triggers_before + 1, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_webhook() {

		$args = array(
			'code' => 'WPVIEWPOSTTYPE',
		);

		$args['is_webhook'] = true;

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'recipes_from_trigger_code' => $this->mocked_result,
			)
		);

		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that we have one entry now
		$this->assertEquals( $count_triggers_before + 1, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_drafted_recipe() {

		$args = array(
			'code' => 'WPVIEWPOSTTYPE',
		);

		$drafted_recipe = $this->mocked_result;

		$drafted_recipe[11]['post_status'] = 'draft';

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'recipes_from_trigger_code' => $drafted_recipe,
			)
		);

		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that we stil have no entries
		$this->assertEquals( $count_triggers_before, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_user_recipe() {

		$args = array(
			'code' => 'WPVIEWPOSTTYPE',
		);

		$user_recipe = $this->mocked_result;

		$user_recipe[11]['recipe_type'] = 'user';

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'recipes_from_trigger_code' => $user_recipe,
			)
		);

		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that we still have have two entries in the trigger log DB table
		$this->assertEquals( $count_triggers_before, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_trigger_to_match() {

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'trigger_to_match' => 15,
		);

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'recipes_from_trigger_code' => $this->mocked_result,
			)
		);

		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that we still have have two entries in the trigger log DB table
		$this->assertEquals( $count_triggers_before, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_drafted_trigger() {

		$args = array(
			'code' => 'WPVIEWPOSTTYPE',
		);

		$drafted_trigger                                   = $this->mocked_result;
		$drafted_trigger[11]['triggers'][0]['post_status'] = 'draft';

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'recipes_from_trigger_code' => $drafted_trigger,
			)
		);

		//Check that we have zero entries
		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that we still have have two entries in the trigger log DB table
		$this->assertEquals( $count_triggers_before, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_recipe_completed() {

		$args = array(
			'code' => 'WPVIEWPOSTTYPE',
		);

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'is_recipe_completed'       => true,
				'recipes_from_trigger_code' => $this->mocked_result,
			)
		);

		//Check that we have zero entries
		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that we still have have two entries in the trigger log DB table
		$this->assertEquals( $count_triggers_before, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_false_trigger_id() {

		$args = array(
			'code'           => 'WPVIEWPOSTTYPE',
			'ignore_post_id' => true,
		);

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'get_trigger_id'            => array( 'result' => false ),
				'recipes_from_trigger_code' => $this->mocked_result,
			)
		);

		//Check that we have zero entries
		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that we still have have two entries in the trigger log DB table
		$this->assertEquals( $count_triggers_before, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_existing_recipe() {

		$args = array(
			'code' => 'WPVIEWPOSTTYPE',
		);

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'maybe_create_recipe_log_entry' =>
				array(
					'existing'      => true,
					'recipe_log_id' => 42,
				),
				'recipes_from_trigger_code'     => $this->mocked_result,
			)
		);

		//Check that we have zero entries
		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that we still have have two entries in the trigger log DB table
		$this->assertEquals( $count_triggers_before + 1, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_steps_completed() {

		$args = array(
			'code'    => 'WPVIEWPOSTTYPE',
			'meta'    => 'WPPOSTTYPES',
			'post_id' => 42,
		);

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'maybe_trigger_num_times_completed' => array( 'result' => true ),
				'maybe_trigger_add_any_option_meta' => array( 'result' => false ),
				'recipes_from_trigger_code'         => $this->mocked_result,
			)
		);

		//Check that we have zero entries
		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args );

		//Check that we still have have two entries in the trigger log DB table
		$this->assertEquals( $count_triggers_before + 1, $this->count_trigger_entries() );

	}

	public function test_maybe_add_trigger_entry_mark_completed() {

		$args = array(
			'code'    => 'WPVIEWPOSTTYPE',
			'meta'    => 'WPPOSTTYPES',
			'post_id' => 42,
		);

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'maybe_trigger_num_times_completed' => array( 'result' => true ),
				'recipes_from_trigger_code'         => $this->mocked_result,
			)
		);

		//Check that we have zero entries
		$count_triggers_before = $this->count_trigger_entries();

		$actual = $this->rpu->maybe_add_trigger_entry( $args, false );

		//Check that we still have have two entries in the trigger log DB table
		$this->assertEquals( $count_triggers_before + 1, $this->count_trigger_entries() );

	}

	public function test_maybe_create_recipe_log_entry() {

		$this->rpu = new Automator_Recipe_Process_User();

		$recipe_id        = 42;
		$user_id          = 1;
		$create_recipe    = true;
		$args             = array();
		$maybe_simulate   = true;
		$maybe_add_log_id = 24;

		$actual = $this->rpu->maybe_create_recipe_log_entry( $recipe_id, $user_id, $create_recipe, $args, $maybe_simulate, $maybe_add_log_id );

		$expected = array(
			'existing'      => false,
			'recipe_log_id' => 1342,
		);

		$this->assertFalse( $actual['existing'] );
		$this->assertArrayHasKey( 'recipe_log_id', $actual );

	}

	public function test_maybe_create_recipe_log_entry_existing() {

		$mock = $this->make( 'Uncanny_Automator\Automator_Recipe_Process_User', array( 'wpdb_get_var' => 42 ) );

		$recipe_id        = 42;
		$user_id          = 1;
		$create_recipe    = true;
		$args             = array();
		$maybe_simulate   = true;
		$maybe_add_log_id = 24;

		$actual = $mock->maybe_create_recipe_log_entry( $recipe_id, $user_id, $create_recipe, $args, $maybe_simulate, $maybe_add_log_id );

		$expected = array(
			'existing'      => true,
			'recipe_log_id' => 42,
		);

		$this->assertSame( $expected, $actual );
	}

	public function test_maybe_create_recipe_log_entry_non_existing() {

		$mock = $this->make( 'Uncanny_Automator\Automator_Recipe_Process_User', array( 'wpdb_get_var' => false ) );

		$recipe_id        = 42;
		$user_id          = 1;
		$create_recipe    = false;
		$args             = array();
		$maybe_simulate   = false;
		$maybe_add_log_id = 24;

		$actual = $mock->maybe_create_recipe_log_entry( $recipe_id, $user_id, $create_recipe, $args, $maybe_simulate, $maybe_add_log_id );

		$expected = array(
			'existing'      => false,
			'recipe_log_id' => null,
		);

		$this->assertSame( $expected, $actual );
	}

	public function test_insert_recipe_log() {

		$this->rpu = $this->make( 'Uncanny_Automator\Automator_Recipe_Process_User', array( 'recipe_number_times_completed' => 100 ) );

		$recipe_id        = 42;
		$user_id          = 1;
		$maybe_add_log_id = null;

		$actual = $this->rpu->insert_recipe_log( $recipe_id, $user_id, $maybe_add_log_id = null );

		$this->assertNull( $actual );

	}

	public function test_get_trigger_id() {

		$this->rpu = new Automator_Recipe_Process_User();

		$args                = array();
		$trigger             = 1;
		$recipe_id           = 2;
		$maybe_recipe_log_id = 3;
		$ignore_post_id      = true;

		$actual = $this->rpu->get_trigger_id( $args, $trigger, $recipe_id, $maybe_recipe_log_id, $ignore_post_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'required', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'missing', $actual['error'] );

	}

	public function test_maybe_validate_trigger_without_postid_recipe_not_matched() {

		$this->rpu = new Automator_Recipe_Process_User();

		$trigger = $this->mocked_result[11]['triggers'][0];

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'meta'             => 'sentence',
			'user_id'          => 1,
			'recipe_to_match'  => 22,
			'trigger_to_match' => 3,
		);

		$recipe_id = 42;

		$actual = $this->rpu->maybe_validate_trigger_without_postid( $args, $trigger, $recipe_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'not', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'matched', $actual['error'] );

	}

	public function test_maybe_validate_trigger_without_postid_trigger_doesnt_match() {

		$this->rpu = new Automator_Recipe_Process_User();

		$trigger = $this->mocked_result[11]['triggers'][0];

		$args = array(
			'code'             => 'DOESNTMATCH',
			'meta'             => 'sentence',
			'user_id'          => 1,
			'recipe_to_match'  => 22,
			'trigger_to_match' => 3,
		);

		$recipe_id = 42;

		$actual = $this->rpu->maybe_validate_trigger_without_postid( $args, $trigger, $recipe_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'not', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'matched', $actual['error'] );

	}

	public function test_maybe_validate_trigger_without_postid_meta_not_found() {

		$this->rpu = new Automator_Recipe_Process_User();

		$trigger = $this->mocked_result[11]['triggers'][0];

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'meta'             => 'Unexisting meta',
			'user_id'          => 1,
			'recipe_to_match'  => 42,
			'trigger_to_match' => 3,
		);

		$recipe_id = 42;

		$actual = $this->rpu->maybe_validate_trigger_without_postid( $args, $trigger, $recipe_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'meta', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'not', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'found', $actual['error'] );

	}

	public function test_maybe_validate_trigger_without_postid() {

		$this->rpu = new Automator_Recipe_Process_User();

		$trigger = $this->mocked_result[11]['triggers'][0];

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'meta'             => 'sentence',
			'user_id'          => 1,
			'recipe_to_match'  => 42,
			'trigger_to_match' => 3,
		);

		$recipe_id = 42;

		$actual = $this->rpu->maybe_validate_trigger_without_postid( $args, $trigger, $recipe_id );

		$this->assertTrue( $actual['result'] );

	}

	public function test_maybe_validate_trigger_without_postid_integration_not_active() {

		$this->rpu = new Automator_Recipe_Process_User();

		$trigger                        = $this->mocked_result[11]['triggers'][0];
		$trigger['meta']['integration'] = 'LD';

		$args = array(
			'code'             => 'COURSEDONE',
			'meta'             => 'sentence',
			'user_id'          => 1,
			'recipe_to_match'  => 22,
			'trigger_to_match' => 3,
		);

		$recipe_id = 42;

		$actual = $this->rpu->maybe_validate_trigger_without_postid( $args, $trigger, $recipe_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'not', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'active', $actual['error'] );

	}

	public function test_maybe_validate_trigger_without_postid_trigger_is_completed() {

		$this->rpu = new Automator_Recipe_Process_User();

		$trigger = $this->mocked_result[11]['triggers'][0];

		$this->rpu = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'is_trigger_completed' => true,
			)
		);

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'meta'             => 'sentence',
			'user_id'          => 1,
			'recipe_to_match'  => 22,
			'trigger_to_match' => 3,
		);

		$recipe_id = 42;

		$actual = $this->rpu->maybe_validate_trigger_without_postid( $args, $trigger, $recipe_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'completed', $actual['error'] );

	}

	public function test_maybe_get_trigger_id() {

		$this->rpu = new Automator_Recipe_Process_User();

		$actual = $this->rpu->maybe_get_trigger_id( null, null, null );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'missing', $actual['error'] );

	}

	public function test_maybe_validate_trigger() {

		$this->rpu = new Automator_Recipe_Process_User();

		$args          = array();
		$trigger       = null;
		$recipe_id     = null;
		$recipe_log_id = null;

		$actual = $this->rpu->maybe_validate_trigger( $args, $trigger, $recipe_id, $recipe_log_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'missing', $actual['error'] );

	}

	public function test_maybe_validate_trigger_integration_plugin_not_active() {

		$mock = $this->make( 'Uncanny_Automator\Automator_Recipe_Process_User', array( 'is_trigger_completed' => true ) );

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'meta'             => 'sentence',
			'user_id'          => 1,
			'recipe_to_match'  => 22,
			'trigger_to_match' => 3,
			'post_id'          => 42,
		);

		$trigger       = $this->mocked_result[11]['triggers'][0];
		$recipe_id     = 42;
		$recipe_log_id = null;

		$actual = $mock->maybe_validate_trigger( $args, $trigger, $recipe_id, $recipe_log_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'completed', $actual['error'] );
	}

	public function test_maybe_validate_trigger_integration_trigger_completed() {

		$mock = $this->make( 'Uncanny_Automator\Automator_Recipe_Process_User', array( 'get_plugin_status' => 0 ) );

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'meta'             => 'sentence',
			'user_id'          => 1,
			'recipe_to_match'  => 22,
			'trigger_to_match' => 3,
			'post_id'          => 42,
		);

		$trigger       = $this->mocked_result[11]['triggers'][0];
		$recipe_id     = 42;
		$recipe_log_id = null;

		$actual = $mock->maybe_validate_trigger( $args, $trigger, $recipe_id, $recipe_log_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'not', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'active', $actual['error'] );
	}

	public function test_maybe_validate_trigger_integration_trigger_code_doesnt_match() {

		$this->rpu = new Automator_Recipe_Process_User();

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'meta'             => 'sentence',
			'user_id'          => 1,
			'recipe_to_match'  => 22,
			'trigger_to_match' => 3,
			'post_id'          => 42,
		);

		$trigger = $this->mocked_result[11]['triggers'][0];

		$trigger['meta']['code'] = 'DOESNTMATCH';

		$recipe_id     = 42;
		$recipe_log_id = null;

		$actual = $this->rpu->maybe_validate_trigger( $args, $trigger, $recipe_id, $recipe_log_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'not', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'matched', $actual['error'] );
	}

	public function test_maybe_validate_trigger_integration_trigger_trigger_not_matched() {

		$this->rpu = new Automator_Recipe_Process_User();

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'meta'             => 'NUMERIC',
			'user_id'          => 1,
			'recipe_to_match'  => 22,
			'trigger_to_match' => 3,
			'post_id'          => 42,
		);

		$trigger = $this->mocked_result[11]['triggers'][0];

		$trigger['meta']['NUMERIC'] = '99';

		$recipe_id     = 42;
		$recipe_log_id = null;

		$actual = $this->rpu->maybe_validate_trigger( $args, $trigger, $recipe_id, $recipe_log_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'not', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'matched', $actual['error'] );
	}

	public function test_maybe_validate_trigger_integration_trigger_trigger_not_matched_string_id() {

		$this->rpu = new Automator_Recipe_Process_User();

		$args = array(
			'code'             => 'WPVIEWPOSTTYPE',
			'meta'             => 'NOTNUMERIC',
			'user_id'          => 1,
			'recipe_to_match'  => 22,
			'trigger_to_match' => 3,
			'post_id'          => 'hello',
		);

		$trigger = $this->mocked_result[11]['triggers'][0];

		$trigger['meta']['NOTNUMERIC'] = '55';

		$recipe_id     = 42;
		$recipe_log_id = null;

		$actual = $this->rpu->maybe_validate_trigger( $args, $trigger, $recipe_id, $recipe_log_id );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'not', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'matched', $actual['error'] );
	}

	public function test_maybe_trigger_num_times_completed_empty_args() {

		$this->rpu = new Automator_Recipe_Process_User();

		$times_args = array();

		$actual = $this->rpu->maybe_trigger_num_times_completed( $times_args );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'missing', $actual['error'] );
	}

	public function test_maybe_trigger_num_times_completed_not_the_first_run() {

		$mock = $this->make( 'Uncanny_Automator\Automator_Recipe_Process_User', array( 'get_trigger_meta' => 2 ) );

		$times_args = array(
			'trigger_id' => 77,
			'trigger'    => $this->mocked_result[11]['triggers'][0],
			'user_id'    => 1,
		);

		$actual = $mock->maybe_trigger_num_times_completed( $times_args );

		$this->assertTrue( $actual['result'] );
		$this->assertArrayHasKey( 'run_number', $actual );
	}

	public function test_maybe_trigger_num_times_completed_update_trigger_sentence() {

		$mock = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'get_trigger_sentence' => 'Hello',
				'insert_trigger_meta'  => Expected::atLeastOnce( null ),
			)
		);

		$times_args = array(
			'trigger_id' => 77,
			'trigger'    => $this->mocked_result[11]['triggers'][0],
			'user_id'    => 1,
		);

		$actual = $mock->maybe_trigger_num_times_completed( $times_args );

		$this->assertTrue( $actual['result'] );
		$this->assertArrayHasKey( 'run_number', $actual );
	}

	public function test_maybe_trigger_num_times_completed_not_enough_times() {

		$mock = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'get_trigger_meta' => 10,
			)
		);

		$trigger                     = $this->mocked_result[11]['triggers'][0];
		$trigger['meta']['NUMTIMES'] = 42;

		$times_args = array(
			'trigger_id' => 77,
			'trigger'    => $trigger,
			'user_id'    => 1,
		);

		$actual = $mock->maybe_trigger_num_times_completed( $times_args );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'number', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'times', $actual['error'] );
	}

	public function test_maybe_trigger_add_any_option_meta_no_option_meta() {

		$this->rpu = new Automator_Recipe_Process_User();

		$option_meta     = array();
		$save_for_option = null;

		$actual = $this->rpu->maybe_trigger_add_any_option_meta( $option_meta, $save_for_option );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'meta', $actual['error'] );
	}

	public function test_maybe_trigger_add_any_option_meta_missing_args() {

		$this->rpu = new Automator_Recipe_Process_User();

		$option_meta = array(
			'recipe_id' => 42,
		);

		$save_for_option = 'hello';

		$actual = $this->rpu->maybe_trigger_add_any_option_meta( $option_meta, $save_for_option );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'missing', $actual['error'] );
	}

	public function test_maybe_trigger_add_any_option_meta_update_existing() {

		$mock = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'maybe_get_meta_id_from_trigger_log' => 47,
			)
		);

		$option_meta = array(
			'recipe_id'  => 42,
			'trigger_id' => 422,
			'trigger'    => $this->mocked_result[11]['triggers'][0],
			'user_id'    => 1,
		);

		$save_for_option = 'hello';

		$actual = $mock->maybe_trigger_add_any_option_meta( $option_meta, $save_for_option );

		$this->assertTrue( 0 === $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'updat', $actual['error'] );
	}

	public function test_maybe_trigger_add_any_option_meta_no_action() {

		$mock = $this->make(
			'Uncanny_Automator\Automator_Recipe_Process_User',
			array(
				'maybe_get_meta_id_from_trigger_log' => 'hello',
			)
		);

		$option_meta = array(
			'recipe_id'  => 42,
			'trigger_id' => 422,
			'trigger'    => $this->mocked_result[11]['triggers'][0],
			'user_id'    => 1,
		);

		$save_for_option = 'world';

		$actual = $mock->maybe_trigger_add_any_option_meta( $option_meta, $save_for_option );

		$this->assertFalse( $actual['result'] );
		$this->assertStringContainsStringIgnoringCase( 'no', $actual['error'] );
		$this->assertStringContainsStringIgnoringCase( 'action', $actual['error'] );
	}

	public function test_update_trigger_meta_null_user() {

		$this->rpu = new Automator_Recipe_Process_User();

		$user_id        = null;
		$trigger_id     = null;
		$meta_key       = null;
		$meta_value     = '';
		$trigger_log_id = null;

		$actual = $this->rpu->update_trigger_meta( $user_id, $trigger_id, $meta_key, $meta_value, $trigger_log_id );

		$this->assertNull( $actual );

	}

	public function test_update_trigger_meta_no_trigger_id() {

		$this->rpu = new Automator_Recipe_Process_User();

		$user_id        = 1;
		$trigger_id     = null;
		$meta_key       = null;
		$meta_value     = '';
		$trigger_log_id = null;

		$actual = $this->rpu->update_trigger_meta( $user_id, $trigger_id, $meta_key, $meta_value, $trigger_log_id );

		$this->assertNull( $actual );

	}

	public function test_update_trigger_meta_no_meta_key() {

		$this->rpu = new Automator_Recipe_Process_User();

		$user_id        = 1;
		$trigger_id     = 42;
		$meta_key       = null;
		$meta_value     = '';
		$trigger_log_id = null;

		$actual = $this->rpu->update_trigger_meta( $user_id, $trigger_id, $meta_key, $meta_value, $trigger_log_id );

		$this->assertNull( $actual );

	}

	public function test_update_trigger_meta() {

		$this->rpu = new Automator_Recipe_Process_User();

		$user_id        = 1;
		$trigger_id     = 42;
		$meta_key       = 'test';
		$meta_value     = '';
		$trigger_log_id = 77;

		$actual = $this->rpu->update_trigger_meta( $user_id, $trigger_id, $meta_key, $meta_value, $trigger_log_id );

		$this->assertTrue( 0 === $actual );

	}

	public function test_maybe_trigger_complete() {

		$this->rpu = new Automator_Recipe_Process_User();

		$args = array();

		$actual = $this->rpu->maybe_trigger_complete( $args );

		$this->assertFalse( $actual );
	}

	public function test_trigger_meta_id_not_logged_in() {

		$this->rpu = new Automator_Recipe_Process_User();

		$user_id       = null;
		$trigger_id    = null;
		$meta_key      = null;
		$recipe_log_id = null;

		$actual = $this->rpu->trigger_meta_id( $user_id, $trigger_id, $meta_key, $recipe_log_id );

		$this->assertNull( $actual );
	}

	public function test_trigger_meta_id_no_trigger_id() {

		$this->rpu = new Automator_Recipe_Process_User();

		$user_id       = 1;
		$trigger_id    = null;
		$meta_key      = null;
		$recipe_log_id = null;

		$actual = $this->rpu->trigger_meta_id( $user_id, $trigger_id, $meta_key, $recipe_log_id );

		$this->assertNull( $actual );
	}

	public function test_trigger_meta_id_no_meta_key() {

		$this->rpu = new Automator_Recipe_Process_User();

		$user_id       = 1;
		$trigger_id    = 42;
		$meta_key      = null;
		$recipe_log_id = null;

		$actual = $this->rpu->trigger_meta_id( $user_id, $trigger_id, $meta_key, $recipe_log_id );

		$this->assertNull( $actual );
	}

	public function test_trigger_meta_id_null_results() {

		$this->rpu = new Automator_Recipe_Process_User();

		$user_id       = 1;
		$trigger_id    = 42;
		$meta_key      = 'test';
		$recipe_log_id = null;

		$actual = $this->rpu->trigger_meta_id( $user_id, $trigger_id, $meta_key, $recipe_log_id );

		$this->assertNull( $actual );
	}

	public function test_trigger_meta_id() {

		$mock = $this->make( 'Uncanny_Automator\Automator_Recipe_Process_User', array( 'wpdb_get_var' => 55 ) );

		$user_id       = 1;
		$trigger_id    = 42;
		$meta_key      = 'test';
		$recipe_log_id = null;

		$actual = $mock->trigger_meta_id( $user_id, $trigger_id, $meta_key, $recipe_log_id );

		$this->assertEquals( 55, $actual );
	}


}

