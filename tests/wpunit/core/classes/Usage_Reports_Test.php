<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Usage_Reports;
use Uncanny_Automator\Automator_System_Report;

/**
 *
 */
class Usage_Reports_Tests extends \AutomatorTestCase {

	/**
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * @var
	 */
	public static $get_blog_count_exists;

	/**
	 * @var \Usage_Reports
	 */
	protected $usage_reports;

	/**
	 * @var string
	 */
	protected $namespaced_route = '/uap/v2/async_report';

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function setUp(): void {
		// Before...
		parent::setUp();

		$this->usage_reports = new Usage_Reports();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$this->server = $wp_rest_server = new \WP_REST_Server();

		/**
		 * Updating test double <https://developer.wordpress.org/reference/hooks/rest_api_init/>
		 *
		 * Action hook 'rest_api_init' requires 'WP_REST_Server' parameter.
		 */
		do_action( 'rest_api_init', $this->server );

		$this->usage_reports->initialize_report();
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
	 * @group Full_Coverage
	 *
	 */
	public function test_construct() {

		$this->usage_reports = new Usage_Reports();

		//Check that the object is avaiable
		$this->assertTrue( $this->usage_reports instanceof Usage_Reports );

		// Check that actions are attached
		$has_action = has_action(
			'activate_' . $this->usage_reports::AUTOMATOR_PATH,
			array(
				$this->usage_reports,
				'maybe_schedule_report',
			)
		);
		$this->assertTrue( $has_action == 10 );

		// Check that actions are attached
		$has_action = has_action(
			'activate_' . $this->usage_reports::AUTOMATOR_PRO_PATH,
			array(
				$this->usage_reports,
				'maybe_schedule_report',
			)
		);
		$this->assertTrue( $has_action == 10 );

		// Check that actions are attached
		$has_action = has_action(
			'deactivate_' . $this->usage_reports::AUTOMATOR_PATH,
			array(
				$this->usage_reports,
				'unschedule_report',
			)
		);
		$this->assertTrue( $has_action == 10 );

		// Check that actions are attached
		$has_action = has_action(
			'deactivate_' . $this->usage_reports::AUTOMATOR_PRO_PATH,
			array(
				$this->usage_reports,
				'pro_deactivated',
			)
		);
		$this->assertTrue( $has_action == 10 );

		// Check that actions are attached
		$has_action = has_action(
			$this->usage_reports::SCHEDULE_NAME,
			array(
				$this->usage_reports,
				'maybe_send_report',
			)
		);
		$this->assertTrue( $has_action == 10 );

		// Check that actions are attached
		$has_action = has_action(
			'automator_recipe_completed',
			array(
				$this->usage_reports,
				'count_recipe_completion',
			)
		);
		$this->assertTrue( $has_action == 10 );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 *
	 */
	public function test_maybe_schedule_report() {

		// No report is scheduled by default
		$this->usage_reports->maybe_schedule_report();

		$timestamp = wp_next_scheduled( $this->usage_reports::SCHEDULE_NAME );

		$this->assertNotTrue( $timestamp );

		// Enable reporting
		update_option( $this->usage_reports::OPTION_NAME, true );

		$failed = false;

		// Test the random timestamp generation 1000 times.
		for ( $i = 0; $i < 1000; $i ++ ) {

			$this->usage_reports->maybe_schedule_report();
			$timestamp = wp_next_scheduled( $this->usage_reports::SCHEDULE_NAME );

			// The schedule should happen within this week
			if ( $timestamp < strtotime( 'last Monday' ) || $timestamp > strtotime( 'next Monday' ) ) {
				$failed = true;
			}
		}

		$this->assertFalse( $failed );

		// Disable reporting
		update_option( $this->usage_reports::OPTION_NAME, false );

		$this->usage_reports->maybe_schedule_report();

		$timestamp = wp_next_scheduled( $this->usage_reports::SCHEDULE_NAME );

		$this->assertNotTrue( $timestamp );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 *
	 */
	public function test_sites_count() {
		self::$get_blog_count_exists = false;
		$sites_count                 = $this->usage_reports->sites_count();
		$this->assertSame( 'Not set', $sites_count );

		self::$get_blog_count_exists = true;
		$sites_count                 = $this->usage_reports->sites_count();
		$this->assertSame( 42, $sites_count );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_data() {
		$data = $this->usage_reports->get_data();

		$this->assertArrayHasKey( 'server', $data );
		$this->assertArrayHasKey( 'wp', $data );
		$this->assertArrayHasKey( 'automator', $data );
		$this->assertArrayHasKey( 'license', $data );
		$this->assertArrayHasKey( 'active_plugins', $data );
		$this->assertArrayHasKey( 'theme', $data );
		$this->assertArrayHasKey( 'recipes', $data );
		$this->assertArrayHasKey( 'integrations', $data );
		$this->assertArrayHasKey( 'get_data_took', $data );
		$this->assertArrayHasKey( 'settings', $data );
		$this->assertArrayHasKey( 'stats', $data );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_plugins_info() {

		$Automator_System_Report = Automator_System_Report::get_instance();

		$system_report = $Automator_System_Report->get();

		$active_plugins = $system_report['active_plugins'];

		$active_plugins[] = array(
			'name'    => ' ',
			'version' => '',
		);

		$active_plugins[] = array(
			'name'    => ' ',
			'version' => '',
		);

		$active_plugins[] = array(
			'name'    => 'Some plugin',
			'version' => '2.4',
		);

		$plugins_info = $this->usage_reports->get_plugins_info( $active_plugins );

		$this->assertTrue( 2 === count( $plugins_info ) );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_recipes_info() {

		// There should be no recipes by default
		$this->assertEquals( 0, $this->usage_reports->report['recipes']['live_recipes_count'] );
		$this->assertEquals( 0, $this->usage_reports->report['recipes']['unpublished_recipes_count'] );

		// Add some draft recipes
		$this->usage_reports->recipes_data = $this->dummy_draft_recipes();

		$this->usage_reports->get_recipes_info();

		// There should still be no public recipes but one draft
		$this->assertEquals( 0, $this->usage_reports->report['recipes']['live_recipes_count'] );
		$this->assertEquals( 1, $this->usage_reports->report['recipes']['unpublished_recipes_count'] );

		// Add some recipe data
		$this->usage_reports->recipes_data = $this->dummy_recipes();

		$this->usage_reports->get_recipes_info();

		$this->assertTrue( $this->usage_reports->report['recipes']['total_integrations_used'] > 0 );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_count_recipe_completion() {

		// Check that the option doesn't exist
		$this->assertTrue( false === get_option( $this->usage_reports::RECIPE_COUNT_OPTION, false ) );

		$this->usage_reports->count_recipe_completion();

		$recipe_completion_count_before = get_option( $this->usage_reports::RECIPE_COUNT_OPTION, false );

		$this->assertTrue( is_numeric( $recipe_completion_count_before ) );

		// Count 3 recipe completions
		$this->usage_reports->count_recipe_completion();
		$this->usage_reports->count_recipe_completion();
		$this->usage_reports->count_recipe_completion();

		$recipe_completion_count_after = get_option( $this->usage_reports::RECIPE_COUNT_OPTION, false );

		$this->assertTrue( 3 === $recipe_completion_count_after - $recipe_completion_count_before );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_process_recipe_data() {

		$live_recipes_count_before     = $this->usage_reports->report['recipes']['live_recipes_count'];
		$user_recipes_count_before     = $this->usage_reports->report['recipes']['user_recipes_count'];
		$everyone_recipes_count_before = $this->usage_reports->report['recipes']['everyone_recipes_count'];

		$recipe_data = array(
			'post_status' => 'publish',
			'recipe_type' => 'user',
		);

		$this->usage_reports->process_recipe_data( $recipe_data );

		$recipe_data = array(
			'post_status' => 'publish',
			'recipe_type' => 'anonymous',
		);

		$this->usage_reports->process_recipe_data( $recipe_data );

		$live_recipes_count_after     = $this->usage_reports->report['recipes']['live_recipes_count'];
		$user_recipes_count_after     = $this->usage_reports->report['recipes']['user_recipes_count'];
		$everyone_recipes_count_after = $this->usage_reports->report['recipes']['everyone_recipes_count'];

		$this->assertTrue( $live_recipes_count_after - $live_recipes_count_before == 2 );
		$this->assertTrue( $user_recipes_count_after - $user_recipes_count_before == 1 );
		$this->assertTrue( $everyone_recipes_count_after - $everyone_recipes_count_before == 1 );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_count_async() {
		$data = array();

		$this->assertTrue( $this->usage_reports->report['recipes']['delayed_actions_count'] == 0 );

		$data['meta']['async_mode'] = 'delay';
		$this->usage_reports->count_async( $data );

		$this->assertTrue( $this->usage_reports->report['recipes']['delayed_actions_count'] == 1 );

		$this->assertTrue( $this->usage_reports->report['recipes']['scheduled_actions_count'] == 0 );

		$data['meta']['async_mode'] = 'schedule';
		$this->usage_reports->count_async( $data );

		$this->assertTrue( $this->usage_reports->report['recipes']['scheduled_actions_count'] == 1 );

		$this->assertTrue( $this->usage_reports->report['recipes']['async_actions_count'] == 2 );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_count_integration() {

		$meta = array();

		$type = 'triggers';

		$this->assertNull( $this->usage_reports->count_integration( $meta, $type ) );
		$this->assertTrue( 0 === count( $this->usage_reports->report['integrations_array'] ) );

		$meta = array(
			'integration'      => 'WP',
			'integration_name' => 'WordPress',
			'code'             => 'VIEWPAGE',
		);

		$type = 'triggers';

		$this->usage_reports->count_integration( $meta, $type );

		$meta = array(
			'integration'      => 'LD',
			'integration_name' => 'LearnDash',
			'code'             => 'ENROLL',
		);

		$type = 'actions';

		$this->usage_reports->count_integration( $meta, $type );
		$this->usage_reports->count_integration( $meta, $type );

		$this->assertTrue( $this->usage_reports->report['integrations'][ $meta['integration'] ][ $type ][ $meta['code'] ] == 2 );

		$this->assertTrue( 2 === count( $this->usage_reports->report['integrations_array'] ) );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_maybe_send_report() {

		$report_sent = $this->usage_reports->maybe_send_report();

		$this->assertFalse( $report_sent );

		// Enable reporting
		update_option( $this->usage_reports::OPTION_NAME, true );

		$report_sent = $this->usage_reports->maybe_send_report();

		$this->assertTrue( $report_sent );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_call_api_non_201_response() {

		// Prevent WordPress from sending actual requests
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {

				if ( AUTOMATOR_API_URL . 'v2/report' !== $url ) {
					return $preempt;
				}

				$this->last_request = array(
					'parsed_args' => $parsed_args,
					'url'         => $url,
				);

				return array(
					'response' => array(
						'code' => 200,
					),
				);

			},
			10,
			3
		);

		$report_sent = $this->usage_reports->send_report( new \WP_REST_Request() );

		$this->assertFalse( $report_sent );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_get_automator_info() {

		// GEt the report and make sure that the Pro version is not set
		$data = $this->usage_reports->get_data();
		$this->assertFalse( isset( $data['automator']['pro_version'] ) );

		// Now we will immitade that Pro is enabled
		if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			define( 'AUTOMATOR_PRO_PLUGIN_VERSION', '4' );
		}

		$data = $this->usage_reports->get_data();
		$this->assertSame( '4', $data['automator']['pro_version'] );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_reporting_enabled_when_pro() {

		// Make sure that we successfully faked Pro
		$this->assertTrue( is_automator_pro_active() );

		$reporting_enabled = $this->usage_reports->reporting_enabled();

		$this->assertTrue( $reporting_enabled );

		// Now we will disable reporting completely even on Pro
		define( 'AUTOMATOR_REPORTING', false );

		$reporting_enabled = $this->usage_reports->reporting_enabled();
		$this->assertNotTrue( $reporting_enabled );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_pro_deactivated() {
		$timestamp = wp_next_scheduled( 'after_automator_pro_deactivated' );
		$this->assertFalse( $timestamp );
		$this->usage_reports->pro_deactivated();

		$timestamp = wp_next_scheduled( 'after_automator_pro_deactivated' );
		$this->assertTrue( $timestamp > time() );
	}

	/**
	 * @return array[]
	 * @group Full_Coverage
	 */
	public function test_increment_view() {

		$s = DIRECTORY_SEPARATOR;

		wp_set_current_user( 1 );

		$file_name_1 = 'admin-integrations' . $s . 'archive.php';
		$this->usage_reports->count_integrations_view( '', $file_name_1 );

		$file_name_2 = 'admin-settings' . $s . 'tab' . $s . 'advanced' . $s . 'background-actions.php';
		$this->usage_reports->count_integrations_view( '', $file_name_2 );
		$this->usage_reports->count_integrations_view( '', $file_name_2 );

		$file_name_3 = 'not-valid' . $s . 'page.php';
		$this->usage_reports->count_integrations_view( '', $file_name_3 );

		$id = wp_create_user( 'unit-tester', '12345', 'unit-tester@unit.test' );

		wp_set_current_user( $id );

		$this->usage_reports->count_integrations_view( '', $file_name_1 );
		$this->usage_reports->count_integrations_view( '', $file_name_1 );
		$this->usage_reports->count_integrations_view( '', $file_name_2 );

		$expected = array(
			'page_views' => array(
				'All integrations'            => array(
					'total'    => 3,
					'average'  => 1.5,
					'per_user' => array(
						1   => 1,
						$id => 2,
					),
				),
				'Background actions settings' => array(
					'total'    => 3,
					'average'  => 1.5,
					'per_user' => array(
						1   => 2,
						$id => 1,
					),
				),
			),
		);

		$result = get_option( $this->usage_reports::STATS_OPTION_NAME );

		$this->assertEquals( $expected, $result );

	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_log_event_missing_nonce() {

		$endpoint = '/' . AUTOMATOR_REST_API_END_POINT . '/log-event';

		wp_set_current_user( 1 );

		$request = new \WP_REST_Request( 'POST', $endpoint, array() );

		$request->set_body_params(
			array(
				'event' => 'integration-search',
				'value' => 'Some query',
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_log_event_missing_event() {

		$endpoint = '/' . AUTOMATOR_REST_API_END_POINT . '/log-event';

		wp_set_current_user( 1 );

		$request = new \WP_REST_Request( 'POST', $endpoint, array() );

		$request->set_body_params(
			array(
				'nonce' => wp_create_nonce( 'uncanny_automator' ),
				'value' => 'Some query',
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 */
	public function test_log_event_invalid_nonce() {

		$endpoint = '/' . AUTOMATOR_REST_API_END_POINT . '/log-event';

		wp_set_current_user( 1 );

		$request = new \WP_REST_Request( 'POST', $endpoint, array() );

		$request->set_body_params(
			array(
				'nonce' => wp_create_nonce( 'random' ), // Generate an invalid nonce to test if the endpoint is secure
				'event' => 'integration-search',
				'value' => 'Some query',
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @return void
	 * @group Full_Coverage
	 *
	 */
	public function test_log_event() {

		$endpoint = '/' . AUTOMATOR_REST_API_END_POINT . '/log-event';

		wp_set_current_user( 1 );

		$request = new \WP_REST_Request( 'POST', $endpoint, array() );

		$query = 'Some query ' . $this->random_string( '5' );

		$request->set_body_params(
			array(
				'nonce' => wp_create_nonce( 'uncanny_automator' ),
				'event' => 'integration-search',
				'value' => $query,
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$result = get_option( $this->usage_reports::STATS_OPTION_NAME );

		$this->assertContains( $query, $result['events']['integration-search'] );
	}

	/**
	 * @return array[]
	 */
	public function dummy_recipes() {
		return array(
			0 => array(
				'post_status'               => 'publish',
				'recipe_type'               => 'user',
				'triggers'                  =>
					array(
						0 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'VIEWPAGE',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
								'tokens'      =>
									array(),
							),
					),
				'actions'                   =>
					array(
						0 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'CREATEPOST',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
						1 =>
							array(
								'post_status' => 'draft',
								'meta'        =>
									array(
										'code'             => 'CREATEPOST',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
					),
				'closures'                  =>
					array(),
				'completed_by_current_user' => false,
				'actions_conditions'        => json_encode(
					array(
						0 =>
							array(
								'actions'    =>
									array(
										0 => '19',
									),
								'mode'       => 'any',
								'conditions' =>
									array(
										0 =>
											array(
												'integration' => 'GEN',
												'condition' => 'TOKEN_MEETS_CONDITION',
												'fields' =>
													array(
														'TOKEN'             => 'fff',
														'CRITERIA'          => 'is',
														'CRITERIA_readable' => 'is',
														'VALUE'             => 'fff',
													),
												'backup' =>
													array(
														'nameDynamic'     => '{{A token:TOKEN}} {{matches:CRITERIA}} {{a value:VALUE}}',
														'integrationName' => 'General',
													),
											),
									),
							),
					)
				),
			),
			1 => array(
				'post_status'               => 'draft',
				'recipe_type'               => 'user',
				'triggers'                  =>
					array(
						0 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'UOAERRORS',
										'integration'      => 'UOA',
										'integration_name' => 'Automator Core',
									),
							),
						1 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'LOGIN',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
						2 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'WPSUBMITCOMMENT',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
						3 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'WPSUBMITCOMMENT',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
					),
				'actions'                   =>
					array(
						0 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'CREATEPOST',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
						1 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'INTSENDWEBHOOK',
										'integration'      => 'INTEGROMAT',
										'integration_name' => 'Integromat',
									),
							),
						2 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'SENDWEBHOOK',
										'integration'      => 'ZAPIER',
										'integration_name' => 'Zapier',
									),
							),
					),
				'closures'                  =>
					array(),
				'completed_by_current_user' => false,
			),
		);
	}

	/**
	 * @return array[]
	 * @group Full_Coverage
	 */
	public function dummy_draft_recipes() {
		return array(
			0 => array(
				'post_status'               => 'draft',
				'recipe_type'               => 'user',
				'triggers'                  =>
					array(
						0 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'UOAERRORS',
										'integration'      => 'UOA',
										'integration_name' => 'Automator Core',
									),
							),
						1 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'LOGIN',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
						2 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'WPSUBMITCOMMENT',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
						3 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'WPSUBMITCOMMENT',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
					),
				'actions'                   =>
					array(
						0 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'CREATEPOST',
										'integration'      => 'WP',
										'integration_name' => 'WordPress',
									),
							),
						1 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'INTSENDWEBHOOK',
										'integration'      => 'INTEGROMAT',
										'integration_name' => 'Integromat',
									),
							),
						2 =>
							array(
								'post_status' => 'publish',
								'meta'        =>
									array(
										'code'             => 'SENDWEBHOOK',
										'integration'      => 'ZAPIER',
										'integration_name' => 'Zapier',
									),
							),
					),
				'closures'                  =>
					array(),
				'completed_by_current_user' => false,
			),
		);
	}

}

/**
 * @param $function
 *
 * @return bool
 */
function function_exists( $function ) {

	if ( 'get_blog_count' === $function ) {
		return Usage_Reports_Tests::$get_blog_count_exists;
	}

	return \function_exists( $function );
}

// Mock the default get_blog_count function that exists only on multisites
/**
 * @return int
 */
function get_blog_count() {
	return 42;
}

// Mock the input filters
/**
 * @param $var
 *
 * @return bool
 */
function automator_filter_has_var( $var ) {

	if ( 'automator_force_report' === $var ) {
		return isset( $_GET[ $var ] );
	}

	return false;
}

/**
 * @param $var
 *
 * @return mixed|null
 */
function automator_filter_input( $var ) {

	if ( 'automator_force_report' === $var ) {
		return $_GET[ $var ];
	}

	return null;
}

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	/**
	 * @param $func
	 *
	 * @return bool|int
	 */
	function as_next_scheduled_action( $func ) {
		if ( 'uapro_auto_purge_logs' === $func ) {
			return true;
		}

		return \as_next_scheduled_action( $func );
	}
}
