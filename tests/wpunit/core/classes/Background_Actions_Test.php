<?php

use Uncanny_Automator\Background_Actions;
use Uncanny_Automator\WP_CREATEPOST;

use \Codeception\Stub\Expected;

class Background_Actions_Test extends AutomatorTestCase {

	public function setUp(): void {
		// Before...
		parent::setUp();

		add_filter( 'https_ssl_verify', '__return_false' );

		$this->bg_actions = new Background_Actions();

		$this->sample_action = array(
			'user_id'     => 1,
			'action_data' =>
			array(
				'ID'            => 13,
				'post_status'   => 'publish',
				'meta'          =>
				array(
					'code'                         => 'CREATEPOST',
					'integration'                  => 'WP',
					'uap_action_version'           => '4.1',
					'integration_name'             => 'WordPress',
					'sentence'                     => 'Create {{a post:CREATEPOST}}',
					'sentence_human_readable'      => 'Create {{Post}}',
					'sentence_human_readable_html' => '<div><span class="item-title__normal">Create </span><span class="item-title__token" data-token-id="CREATEPOST" data-options-id="CREATEPOST">Post</span></div>',
					'CREATEPOST_readable'          => 'Post',
					'CREATEPOST'                   => 'post',
					'WPCPOSTSTATUS_readable'       => 'Published',
					'WPCPOSTSTATUS'                => 'publish',
					'WPCPOSTAUTHOR'                => '{{admin_email}}',
					'WPCPOSTTITLE'                 => 'Unit test 42',
					'TERM'                         => '',
					'WPCPOSTSLUG'                  => '',
					'WPCPOSTCONTENT'               => '<p>123</p>',
					'WPCPOSTEXCERPT'               => '123',
//					'PARENT_POST'                  => '0',
					'FEATURED_IMAGE_URL'           => '',
					'CPMETA_PAIRS'                 => '[]',
				),
				'recipe_log_id' => 126,
				'action_log_id' => 255,
				'args'          =>
				array(
					'code'             => 'ANONWPFSUBFORM',
					'meta'             => 'ANONWPFFORMS',
					'post_id'          => 6,
					'user_id'          => 1,
					'recipe_to_match'  => null,
					'trigger_to_match' => null,
					'ignore_post_id'   => false,
					'is_signed_in'     => true,
					'get_trigger_id'   => 126,
					'trigger_log_id'   => 126,
					'recipe_id'        => 5,
					'trigger_id'       => 12,
					'recipe_log_id'    => 126,
					'run_number'       => '18',
				),
			),
			'recipe_id'   => 5,
			'args'        =>
			array(
				'code'             => 'ANONWPFSUBFORM',
				'meta'             => 'ANONWPFFORMS',
				'post_id'          => 6,
				'user_id'          => 1,
				'recipe_to_match'  => null,
				'trigger_to_match' => null,
				'ignore_post_id'   => false,
				'is_signed_in'     => true,
				'get_trigger_id'   => 126,
				'trigger_log_id'   => 126,
				'recipe_id'        => 5,
				'trigger_id'       => 12,
				'recipe_log_id'    => 126,
				'run_number'       => '18',
			),
		);

	}

	public function tearDown(): void {
		// Your tear down methods here.

		// Then...
		parent::tearDown();
	}

	public function test_construct() {
		$this->assertTrue( 10 === has_action( 'rest_api_init', array( $this->bg_actions, 'register_rest_endpoint' ) ) );
		$this->assertTrue( 200 === has_action( 'automator_before_action_executed', array( $this->bg_actions, 'maybe_send_to_background' ) ) );
		$this->assertTrue( 10 === has_action( 'admin_init', array( $this->bg_actions, 'register_setting' ) ) );
		$this->assertTrue( 10 === has_action( 'automator_settings_advanced_tab_view', array( $this->bg_actions, 'settings_output' ) ) );
		$this->assertTrue( 10 === has_action( 'automator_activation_before', array( $this->bg_actions, 'add_option' ) ) );
	}

	public function test_maybe_send_to_background_fail() {

		$mock = $this->make(
			'Uncanny_Automator\Background_Actions',
			array(
				'send_to_background' => Expected::never(),
			)
		);

		$mock->maybe_send_to_background( $this->sample_action );

	}

	public function test_maybe_send_to_background() {

		// Enable BG actions
		update_option( $this->bg_actions::OPTION_NAME, '1' );

		$mock = $this->make(
			'Uncanny_Automator\Background_Actions',
			array(
				'send_to_background' => Expected::once( true ),
			)
		);

		$this->sample_action['action_data']['meta']['code'] = 'GOOGLESHEETADDRECORD';
		$mock->maybe_send_to_background( $this->sample_action );

	}

	public function test_not_scheduled() {
		$this->bg_actions->action = $this->sample_action;

		// No exception should be thrown
		$this->bg_actions->not_scheduled();

		$this->sample_action['action_data']['meta']['async_mode'] = 'delay';
		$this->bg_actions->action                                 = $this->sample_action;

		$this->expectException( \Exception::class );

		$this->bg_actions->not_scheduled();

	}

	public function test_can_process_further() {

		$this->bg_actions->action = $this->sample_action;

		// No exception should be thrown
		$this->bg_actions->can_process_further();

		// Prevent the action from processing further
		$this->sample_action['process_further'] = false;
		$this->bg_actions->action               = $this->sample_action;

		$this->expectException( \Exception::class );

		$this->bg_actions->can_process_further();
	}

	public function test_should_process_in_background() {

		$this->bg_actions->action      = $this->sample_action;
		$this->bg_actions->action_code = 'GOOGLESHEETADDRECORD';

		// Enable BG actions
		update_option( $this->bg_actions::OPTION_NAME, '1' );

		// No exception should be thrown
		$this->bg_actions->should_process_in_background();

		add_filter( 'automator_action_should_process_in_background', '__return_false' );

		$this->expectException( \Exception::class );

		// An exception should be thrown
		$this->bg_actions->should_process_in_background();

		remove_filter( 'automator_action_should_process_in_background', '__return_false' );

	}

	public function test_should_process_on_cron() {
		$this->bg_actions->action      = $this->sample_action;
		$this->bg_actions->action_code = 'GOOGLESHEETADDRECORD';

		// No exception should be thrown
		$this->bg_actions->is_not_doing_cron();

		add_filter( 'wp_doing_cron', '__return_true' );

		$this->expectException( \Exception::class );

		// An exception should be thrown
		$this->bg_actions->is_not_doing_cron();

	}

	public function test_bg_actions_enabled() {

		// Enable BG actions
		update_option( $this->bg_actions::OPTION_NAME, '1' );

		// BG actions should be enabled by default
		$this->assertTrue( $this->bg_actions->bg_actions_enabled() );

		// Disable them
		update_option( $this->bg_actions::OPTION_NAME, '0' );

		// BG actions should disabled now
		$this->assertFalse( $this->bg_actions->bg_actions_enabled() );
	}

	public function test_is_bg_action() {

		$this->bg_actions->action = $this->sample_action;

		$this->bg_actions->action_code = 'CREATEPOST';

		$this->assertNotTrue( $this->bg_actions->is_bg_action() );

		$this->bg_actions->action_code = 'GOOGLESHEETADDRECORD';

		$this->assertTrue( $this->bg_actions->is_bg_action() );

		add_filter( 'automator_is_background_action', '__return_false' );

		$this->assertNotTrue( $this->bg_actions->is_bg_action() );
	}

	public function test_send_to_background() {

		$mock = $this->make(
			'Uncanny_Automator\Background_Actions',
			array(
				'remote_post' => Expected::once( true ),
			)
		);

		$mock->action = $this->sample_action;
		$mock->send_to_background();

	}

	public function test_validate_rest_call() {

		$request = new \WP_REST_Request( 'POST', 'uap/v2/async_report/' );

		$request->set_body_params( $this->sample_action );

		$result = $this->bg_actions->validate_rest_call( $request );

		$this->assertTrue( $result );

		unset( $this->sample_action['action_data'] );

		$request->set_body_params( $this->sample_action );

		$result = $this->bg_actions->validate_rest_call( $request );

		$this->assertNotTrue( $result );

	}

	public function test_background_action_rest() {

		$request = new \WP_REST_Request( 'POST', 'uap/v2/async_report/' );

		$this->sample_action['process_further'] = false;

		$request->set_body_params( $this->sample_action );

		$this->bg_actions->background_action_rest( $request );

		$this->assertNotTrue( $this->post_created() );

		unset( $this->sample_action['process_further'] );

		$request->set_body_params( $this->sample_action );

		$this->bg_actions->background_action_rest( $request );

		$this->assertTrue( $this->post_created() );

	}

	public function test_run_action() {

		// The action should process even if process further was set to false
		$this->sample_action['process_further'] = false;

		// Test running the WP_CREATEPOST action
		$this->bg_actions->run_action( $this->sample_action );

		// Check that the post was created
		$posts     = get_posts();
		$last_post = array_shift( $posts );
		$this->assertSame( $this->sample_action['action_data']['meta']['WPCPOSTTITLE'], $last_post->post_title );

	}

	public function test_register_setting() {

		$all_settings = get_registered_settings();

		$this->assertEmpty( $all_settings );
		$this->bg_actions->register_setting();

		$all_settings = get_registered_settings();

		$this->assertArrayHasKey( $this->bg_actions::OPTION_NAME, $all_settings );

	}

	public function test_settings_output() {
		ob_start();

		$this->bg_actions->settings_output( 'uncanny_automator_advanced' );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="uap-field uap-spacing-top--small">', $output );
		$this->assertStringContainsString( '<div class="uap-field-description">', $output );
	}

	public function test_maybe_show_error() {

		add_settings_error( $this->bg_actions::OPTION_NAME, $this->bg_actions::OPTION_NAME, 'testj34h534v' );

		ob_start();

		$this->bg_actions->maybe_show_error();

		$output = ob_get_clean();

		$this->assertStringContainsString( 'error', $output );
		$this->assertStringContainsString( 'testj34h534v', $output );
		$this->assertStringContainsString( 'uo-alert', $output );
	}

	public function test_test_endpoint() {

		// Break the REST calls
		$this->fake_next_http_response( new \WP_Error( 444, 'Some random error' ) );

		// Attempt to disable the BG actions
		$actual = $this->bg_actions->test_endpoint( '0' );

		$this->assertSame( '0', $actual );

		// There should be no response saved as no calls were supposed to made
		$this->assertNull( $this->last_response );

		// Npw try to send the enabling value (the WP_Error should still be waiting to be returned)
		$actual = $this->bg_actions->test_endpoint( '1' );

		$this->assertSame( '0', $actual );

		// Test the non broken flow
		$this->fake_next_http_response(
			array(
				'response' => array(
					'code' => 401,
				),
				'body'     => 'This is an error without a message',
			)
		);

		$actual = $this->bg_actions->test_endpoint( '1' );

		$this->assertSame( '0', $actual );

		// Test the non broken flow
		$this->fake_next_http_response(
			array(
				'response' => array(
					'code' => 401,
				),
				'body'     => json_encode(
					array(
						'message' => 'This is an error with a message',
					)
				),
			)
		);

		$actual = $this->bg_actions->test_endpoint( '1' );

		$this->assertSame( '0', $actual );

		// Test the non broken flow
		$this->fake_next_http_response(
			array(
				'response' => array(
					'code' => 200,
				),
				'body'     => 'This is not an error',
			)
		);

		$actual = $this->bg_actions->test_endpoint( '1' );

		$this->assertSame( '1', $actual );

	}

	public function test_get_action_code() {

		$action = null;

		$result = $this->bg_actions->get_action_code( $action );

		$this->assertNull( $result );

		$actual = $this->bg_actions->get_action_code( $this->sample_action );

		$this->assertSame( $this->sample_action['action_data']['meta']['code'], $actual );
	}

	public function test_add_option_rest_works() {

		$mock = $this->make(
			'Uncanny_Automator\Background_Actions',
			array(
				'test_endpoint' => '1',
			)
		);

		delete_option( $mock::OPTION_NAME );

		// In normal case, the option should be created with a value of 1
		$mock->add_option();
		$this->assertSame( '1', get_option( $mock::OPTION_NAME ) );

	}

	public function test_add_option_rest_doesnt_work() {

		$mock = $this->make(
			'Uncanny_Automator\Background_Actions',
			array(
				'test_endpoint' => '0',
			)
		);

		delete_option( $mock::OPTION_NAME );

		// In normal case, the option should be created with a value of 1
		$mock->add_option();
		$this->assertSame( '0', get_option( $mock::OPTION_NAME ) );

	}

	public function test_add_option_rest_stopped_working() {

		$mock = $this->make(
			'Uncanny_Automator\Background_Actions',
			array(
				'test_endpoint' => '0',
			)
		);

		update_option( $mock::OPTION_NAME, '1' );

		// In normal case, the option should be created with a value of 1
		$mock->add_option();
		$this->assertSame( '0', get_option( $mock::OPTION_NAME ) );

	}

	public function test_add_rest_api_exception() {

		$exceptions = array(
			'contact-form-7',
			'wordfence',
			'elementor',
		);

		$exceptions = $this->bg_actions->add_rest_api_exception( $exceptions );

		$this->assertContains( 'uap', $exceptions );

	}

	public function post_created() {
		$posts     = get_posts();
		$last_post = array_shift( $posts );

		if ( empty( $last_post->post_title ) ) {
			return false;
		}

		return $this->sample_action['action_data']['meta']['WPCPOSTTITLE'] === $last_post->post_title;
	}

}
