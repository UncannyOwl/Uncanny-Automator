<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Automator_System_Report;

use WP_REST_Response;

/**
 * Class Usage_Reports.
 *
 * @package Uncanny_Automator
 */
class Usage_Reports {

	/**
	 *
	 */
	const  OPTION_NAME = 'automator_reporting';
	/**
	 *
	 */
	const  RECIPE_COUNT_OPTION = 'automator_completed_recipes';
	/**
	 *
	 */
	const  SCHEDULE_NAME = 'automator_report';
	/**
	 *
	 */
	const  AUTOMATOR_PATH = 'uncanny-automator/uncanny-automator.php';
	/**
	 *
	 */
	const  AUTOMATOR_PRO_PATH = 'uncanny-automator-pro/uncanny-automator-pro.php';
	/**
	 *
	 */
	const  STATS_OPTION_NAME = 'usage_report_stats';
	/**
	 * @var
	 */
	public $system_report;
	/**
	 * @var
	 */
	public $recipes_data;
	/**
	 * @var
	 */
	public $report;
	/**
	 * @var bool
	 */
	private $forced = false;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		// Check the schedule when plugins are activated.
		add_action( 'activate_' . self::AUTOMATOR_PATH, array( $this, 'maybe_schedule_report' ) );
		add_action( 'activate_' . self::AUTOMATOR_PRO_PATH, array( $this, 'maybe_schedule_report' ) );

		// Unschedule the report when Automator Free is deactivated.
		add_action( 'deactivate_' . self::AUTOMATOR_PATH, array( $this, 'unschedule_report' ) );

		// Postpone unscheduling if Pro is deactivated.
		add_action( 'deactivate_' . self::AUTOMATOR_PRO_PATH, array( $this, 'pro_deactivated' ) );
		add_action( 'after_automator_pro_deactivated', array( $this, 'maybe_schedule_report' ) );

		add_action( self::SCHEDULE_NAME, array( $this, 'maybe_send_report' ) );

		add_action( 'automator_recipe_completed', array( $this, 'count_recipe_completion' ) );

		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'maybe_schedule_report' ), 100, 3 );
		add_action( 'add_option_' . self::OPTION_NAME, array( $this, 'maybe_schedule_report' ), 100, 3 );

		add_action( 'automator_weekly_healthcheck', array( $this, 'maybe_schedule_report' ) );

		add_action( 'automator_view_path', array( $this, 'count_integrations_view' ), 10, 2 );

		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );

	}

	/**
	 * @return void
	 */
	public function register_rest_endpoints() {
		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/log-event/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'log_event' ),
				'args'                => array(),
				'permission_callback' => array( $this, 'validate_event' ),
			)
		);
	}

	/**
	 * @param $request
	 *
	 * @return bool
	 */
	public function validate_event( $request ) {

		$request_params = $request->get_params();

		if ( ! isset( $request_params['nonce'] ) ) {
			return false;
		}

		if ( false === wp_verify_nonce( $request_params['nonce'], 'uncanny_automator' ) ) {
			return false;
		}

		if ( empty( $request_params['event'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function log_event( $request ) {

		$data = $request->get_params();

		$event = $data['event'];

		$usage_report_stats = automator_get_option( self::STATS_OPTION_NAME, array( 'events' => array() ) );

		if ( ! isset( $usage_report_stats['events'][ $event ] ) ) {
			$usage_report_stats['events'][ $event ] = array();
		}

		$usage_report_stats['events'][ $event ][] = $data['value'];

		update_option( self::STATS_OPTION_NAME, $usage_report_stats );

		return new WP_REST_Response( array(), 201 );
	}

	/**
	 * maybe_schedule_report
	 *
	 * @return void
	 */
	public function maybe_schedule_report() {

		if ( ! $this->reporting_enabled() ) {
			$this->unschedule_report();

			return;
		}

		$this->schedule_report();

	}

	/**
	 * schedule_report
	 *
	 * @return void
	 */
	public function schedule_report() {
		if ( ! wp_next_scheduled( self::SCHEDULE_NAME ) ) {
			wp_schedule_event( $this->get_random_timestamp(), 'weekly', self::SCHEDULE_NAME );
		}
	}

	/**
	 * unschedule_report
	 *
	 * @return void
	 */
	public function unschedule_report() {

		$timestamp = wp_next_scheduled( self::SCHEDULE_NAME );

		if ( false === $timestamp ) {
			return;
		}

		wp_unschedule_event( $timestamp, self::SCHEDULE_NAME );

	}

	/**
	 * pro_deactivated
	 *
	 * Because the deactivation hook runs when a plugin is still active, we need to wait a little, before we try to unschedule the report after Automator Pro is deactivated.
	 *
	 * @return void
	 */
	public function pro_deactivated() {
		wp_schedule_single_event( time() + 10, 'after_automator_pro_deactivated' );
	}

	/**
	 * get_random_timestamp
	 *
	 * Will generate a random timestamp within the current week.
	 *
	 * @return int timestamp
	 */
	public function get_random_timestamp() {

		$last_monday = strtotime( 'last Monday' );
		$next_monday = strtotime( 'next Monday' );

		return wp_rand( $last_monday, $next_monday );

	}

	/**
	 * Method maybe_send_report
	 *
	 * @return bool
	 */
	public function maybe_send_report() {

		if ( ! $this->reporting_enabled() ) {
			return false;
		}

		return $this->send_report();

	}

	/**
	 * reporting_enabled
	 *
	 * @return bool
	 */
	public function reporting_enabled() {

		$reporting_enabled = false;

		if ( defined( 'AUTOMATOR_REPORTING' ) ) {
			return AUTOMATOR_REPORTING;
		}

		if ( (bool) get_option( self::OPTION_NAME, false ) === true ) {
			$reporting_enabled = true;
		}

		if ( is_automator_pro_active() ) {
			$reporting_enabled = true;
		}

		return apply_filters( self::OPTION_NAME, $reporting_enabled );
	}

	/**
	 * initialize_report
	 *
	 * @return void
	 */
	public function initialize_report() {
		$this->report = array(
			'server'             => array(),
			'wp'                 => array(),
			'automator'          => array(),
			'license'            => array(),
			'active_plugins'     => array(),
			'theme'              => array(),
			'integrations'       => array(),
			'integrations_array' => array(),
			'recipe_items'       => array(),
			'recipes'            => array(
				'live_recipes_count'        => 0,
				'user_recipes_count'        => 0,
				'everyone_recipes_count'    => 0,
				'loops_recipes_count'       => 0,
				'total_actions'             => 0,
				'total_triggers'            => 0,
				'total_closures'            => 0,
				'async_actions_count'       => 0,
				'delayed_actions_count'     => 0,
				'scheduled_actions_count'   => 0,
				'unpublished_recipes_count' => 0,
			),
		);
	}

	/**
	 * get_data
	 *
	 * @return mixed
	 */
	public function get_data() {

		$this->initialize_report();

		$started_at = microtime( true );

		$automator_system_report = Automator_System_Report::get_instance();

		$this->system_report = $automator_system_report->get();
		$this->recipes_data  = Automator()->get_recipes_data( false );

		$this->get_unique_site_hash();
		$this->get_server_info();
		$this->get_wp_info();
		$this->get_theme_info();
		$this->get_license_info();
		$this->report['active_plugins'] = $this->get_plugins_info( $this->system_report['active_plugins'] );
		$this->get_automator_info();
		$this->get_recipes_info();
		$this->get_date();
		$this->get_views();
		$this->get_settings();

		$finished_at = microtime( true );

		$this->report['get_data_took'] = round( ( $finished_at - $started_at ) * 1000 );

		$this->report['forced'] = $this->forced;

		return $this->report;
	}

	/**
	 * get_unique_site_hash
	 *
	 * Generate a unique site hash. We can't send the site URL without owner's consent due to GDPR.
	 *
	 * @return void
	 */
	public function get_unique_site_hash() {

		$site_url                  = get_site_url();
		$site_hash                 = md5( $site_url );
		$this->report['site_hash'] = $site_hash;

	}

	/**
	 * get_server_info
	 */
	public function get_server_info() {

		$keys = array(
			'wp_version',
			'wp_memory_limit',
			'wp_debug_mode',
			'wp_cron',
			'external_object_cache',
			'server_info',
			'php_version',
			'php_post_max_size',
			'php_max_execution_time',
			'php_max_input_vars',
			'curl_version',
			'max_upload_size',
			'mysql_version',
			'mbstring_enabled',
			'remote_post_response',
			'remote_get_response',
		);

		$this->import_from_system_report( $keys );

	}

	/**
	 * import_from_system_report
	 *
	 * @param mixed $keys
	 *
	 * @return void
	 */
	public function import_from_system_report( $keys ) {
		foreach ( $keys as $key ) {
			$this->report['server'][ $key ] = $this->system_report['environment'][ $key ];
		}
	}

	/**
	 * get_wp_info
	 *
	 * @return void
	 */
	public function get_wp_info() {

		$wp['multisite']       = $this->system_report['environment']['wp_multisite'];
		$wp['sites']           = $wp['multisite'] ? $this->sites_count() : 1;
		$wp['user_count']      = $this->get_user_count();
		$wp['timezone_offset'] = Automator()->get_timezone_string();
		$wp['locale']          = get_locale();

		$this->report['wp'] = $wp;
	}

	/**
	 * get_user_count
	 *
	 * @return void
	 */
	public function get_user_count() {
		$usercount = count_users();

		return isset( $usercount['total_users'] ) ? $usercount['total_users'] : __( 'Not set', 'uncanny-automator' );
	}

	/**
	 * get_theme_info
	 *
	 * @return void
	 */
	public function get_theme_info() {

		$theme['name']    = $this->system_report['theme']['name'];
		$theme['version'] = $this->system_report['theme']['version'];

		$this->report['theme'] = $theme;
	}

	/**
	 * get_license_info
	 *
	 * @return void
	 */
	public function get_license_info() {

		$license = array();

		$license['license_key']  = Api_Server::get_license_key();
		$license['license_type'] = Api_Server::get_license_type();
		$license['item_name']    = Api_Server::get_item_name();
		$license['site_name']    = Api_Server::get_site_name();

		$this->report['license'] = $license;
	}

	/**
	 * get_plugins_info
	 *
	 * @return void
	 */
	public function get_plugins_info( $plugins ) {

		$active_plugins = array();

		foreach ( $plugins as $plugin ) {

			if ( empty( trim( $plugin['name'] ) ) ) {
				continue;
			}

			array_push(
				$active_plugins,
				array(
					'name'    => $plugin['name'],
					'version' => $plugin['version'],
				)
			);
		}

		return $active_plugins;

	}

	/**
	 * sites_count
	 *
	 * @return void
	 */
	public function sites_count() {

		$blog_count = 'Not set';

		if ( function_exists( 'get_blog_count' ) ) {

			$blog_count = get_blog_count();

		}

		return $blog_count;
	}

	/**
	 * get_automator_info
	 *
	 * @return void
	 */
	public function get_automator_info() {
		$this->report['automator']['version'] = $this->system_report['environment']['version'];

		if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			$this->report['automator']['pro_version'] = AUTOMATOR_PRO_PLUGIN_VERSION;
		}

		$this->report['automator']['database_version']                = $this->system_report['database']['automator_database_version'];
		$this->report['automator']['database_available_view_version'] = $this->system_report['database']['automator_database_available_view_version'];
	}

	/**
	 * get_recipes_info
	 *
	 * @return void
	 */
	public function get_recipes_info() {

		$this->report['recipes']['loops_recipes_count'] = $this->get_loop_recipes();

		$this->report['recipes']['completed_recipes'] = $this->get_completed_runs();

		$this->report['recipes']['completed_recipes_last_week'] = Automator()->get->completed_runs( WEEK_IN_SECONDS );

		if ( empty( $this->recipes_data ) ) {
			return;
		}

		foreach ( $this->recipes_data as $recipe_data ) {

			if ( 'publish' !== $recipe_data['post_status'] ) {
				$this->report['recipes']['unpublished_recipes_count'] ++;
				continue;
			}

			$this->process_recipe_data( $recipe_data );

		}

		$this->report['integrations_array'] = array_values( $this->report['integrations_array'] );

		$this->report['recipes']['total_integrations_used'] = count( $this->report['integrations'] );

	}

	/**
	 * get_completed_runs
	 *
	 * @return int
	 */
	public function get_completed_runs() {
		return absint( get_option( self::RECIPE_COUNT_OPTION, Automator()->get->total_completed_runs() ) );
	}

	/**
	 * @return int
	 */
	public function get_loop_recipes() {
		global $wpdb;

		$qry = $wpdb->prepare(
			"SELECT COUNT(p1.ID) as recipes
FROM $wpdb->posts p
    JOIN $wpdb->posts p1
        ON p.ID = p1.post_parent
WHERE p.post_type LIKE %s
  AND p1.post_type = %s",
			'uo-recipe',
			'uo-loop'
		);

		$result = $wpdb->get_var( $qry );

		return absint( $result );
	}

	/**
	 * count_recipe_completion
	 *
	 * @return void
	 */
	public function count_recipe_completion() {
		$completed_runs = absint( get_option( self::RECIPE_COUNT_OPTION, Automator()->get->total_completed_runs() - 1 ) );
		update_option( self::RECIPE_COUNT_OPTION, ++ $completed_runs );
	}

	/**
	 * process_recipe_data
	 *
	 * @param mixed $recipe_data
	 *
	 * @return void
	 */
	public function process_recipe_data( $recipe_data ) {

		$this->report['recipes']['live_recipes_count'] ++;

		if ( 'user' === $recipe_data['recipe_type'] ) {
			$this->report['recipes']['user_recipes_count'] ++;
		} elseif ( 'anonymous' === $recipe_data['recipe_type'] ) {
			$this->report['recipes']['everyone_recipes_count'] ++;
		}

		$this->process_recipe_items( $recipe_data );

	}

	/**
	 * process_recipe_items
	 *
	 * @param mixed $recipe
	 *
	 * @return void
	 */
	public function process_recipe_items( $recipe_data ) {

		$recipes_stats = $this->report['recipes'];

		$snapshot = array(
			'async'     => $recipes_stats['async_actions_count'],
			'delayed'   => $recipes_stats['delayed_actions_count'],
			'scheduled' => $recipes_stats['scheduled_actions_count'],
		);

		$recipe                 = array();
		$recipe['triggers']     = $this->get_recipe_items( $recipe_data, 'triggers' );
		$recipe['actions']      = $this->get_recipe_items( $recipe_data, 'actions' );
		$recipe['closures']     = $this->get_recipe_items( $recipe_data, 'closures' );
		$recipe['integrations'] = $this->get_recipe_integrations( $recipe_data );

		$recipes_stats = $this->report['recipes'];

		$recipe['async_actions_count']     = $recipes_stats['async_actions_count'] - $snapshot['async'];
		$recipe['delayed_actions_count']   = $recipes_stats['delayed_actions_count'] - $snapshot['delayed'];
		$recipe['scheduled_actions_count'] = $recipes_stats['scheduled_actions_count'] - $snapshot['scheduled'];

		$recipe['type'] = $recipe_data['recipe_type'];

		$recipe['actions_conditions'] = $this->get_actions_conditions( $recipe_data );

		$this->report['recipe_items'][] = $recipe;

	}

	/**
	 * get_actions_conditions
	 *
	 * @param mixed $recipe_data
	 *
	 * @return array
	 */
	public function get_actions_conditions( $recipe_data ) {

		$output = array();

		if ( empty( $recipe_data['actions_conditions'] ) ) {
			return $output;
		}

		$actions_conditions = json_decode( $recipe_data['actions_conditions'], true );

		foreach ( $actions_conditions as $conditon_group ) {

			foreach ( $conditon_group['conditions'] as $condition ) {

				$integration_name = empty( $condition['backup']['integrationName'] ) ? $condition['integration_name'] : $condition['backup']['integrationName'];

				$output[] = $integration_name . '/' . $condition['condition'];

			}
		}

		return $output;

	}

	/**
	 * get_recipe_items
	 *
	 * @param mixed $recipe
	 * @param mixed $type
	 *
	 * @return array
	 */
	public function get_recipe_items( $recipe, $type ) {

		$recipe_items = array();

		if ( empty( $recipe[ $type ] ) ) {
			return $recipe_items;
		}

		foreach ( $recipe[ $type ] as $item ) {

			if ( 'publish' !== $item['post_status'] ) {
				continue;
			}

			$meta           = $item['meta'];
			$recipe_items[] = $meta['integration_name'] . '/' . $meta['code'];

			$this->count_async( $item );

			$this->count_integration( $meta, $type );
		}

		return $recipe_items;
	}

	/**
	 * get_recipe_integrations
	 *
	 * @param mixed $recipe
	 *
	 * @return array
	 */
	public function get_recipe_integrations( $recipe ) {

		$output = array();
		$types  = array( 'triggers', 'actions', 'closures' );

		foreach ( $types as $type ) {

			if ( empty( $recipe[ $type ] ) ) {
				continue;
			}

			foreach ( $recipe[ $type ] as $item ) {
				if ( 'publish' !== $item['post_status'] ) {
					continue;
				}
				$meta     = $item['meta'];
				$output[] = $meta['integration_name'];
			}
		}

		return array_unique( $output );

	}

	/**
	 * count_async
	 *
	 * @param mixed $data
	 *
	 * @return void
	 */
	public function count_async( $data ) {

		if ( isset( $data['meta']['async_mode'] ) ) {

			$this->report['recipes']['async_actions_count'] ++;

			if ( 'delay' === $data['meta']['async_mode'] ) {

				$this->report['recipes']['delayed_actions_count'] ++;

			} elseif ( 'schedule' === $data['meta']['async_mode'] ) {

				$this->report['recipes']['scheduled_actions_count'] ++;

			}
		}

	}

	/**
	 * count_integration
	 *
	 * @param mixed $integration
	 * @param mixed $type
	 * @param mixed $code
	 *
	 * @return void
	 */
	public function count_integration( $meta, $type ) {

		if ( empty( $meta['integration'] ) || empty( $meta['integration_name'] ) || empty( $meta['code'] ) ) {
			return;
		}

		$integration_code = $meta['integration'];
		$integration_name = $meta['integration_name'];
		$item_code        = $meta['code'];

		if ( isset( $this->report['integrations'][ $integration_code ][ $type ][ $item_code ] ) ) {
			$this->report['integrations'][ $integration_code ][ $type ][ $item_code ] ++;
		} else {
			$this->report['integrations'][ $integration_code ][ $type ][ $item_code ] = 1;
		}

		if ( isset( $this->report['integrations_array'][ $item_code ] ) ) {
			$this->report['integrations_array'][ $item_code ]['usage'] ++;
		} else {
			$this->report['integrations_array'][ $item_code ] = array(
				'integration_name' => $integration_name,
				'name'             => $item_code,
				'integration'      => $integration_code,
				'type'             => $type,
				'usage'            => 1,
			);
		}

		$this->report['recipes'][ 'total_' . $type ] ++;
	}

	/**
	 * get_date
	 *
	 * @return void
	 */
	public function get_date() {
		$this->report['week']    = gmdate( 'W' );
		$this->report['year']    = gmdate( 'o' );
		$this->report['month']   = gmdate( 'n' );
		$this->report['day']     = gmdate( 'z' );
		$this->report['weekday'] = gmdate( 'N' );
	}

	/**
	 * get_views
	 *
	 * @return void
	 */
	public function get_views() {
		$this->report['stats'] = automator_get_option( self::STATS_OPTION_NAME, array() );
	}

	/**
	 * send_report
	 *
	 * @return void
	 */
	public function send_report() {

		try {

			$body = array(
				'data'   => wp_json_encode( $this->get_data() ),
				'action' => 'save_json',
			);

			$params = array(
				'endpoint' => 'v2/report',
				'body'     => $body,
				'timeout'  => 10,
			);

			$response = Api_Server::api_call( $params );

			if ( 201 !== $response['statusCode'] ) {
				throw new \Exception( __( 'Something went wrong', 'uncanny-automator' ) );
			}

			delete_option( self::STATS_OPTION_NAME );

			return true;
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'Could not send report' );
		}

		return false;
	}

	/**
	 * @param $views_directory
	 * @param $file_name
	 *
	 * @return mixed
	 */
	public function count_integrations_view( $views_directory, $file_name ) {

		$s = DIRECTORY_SEPARATOR;

		$views_to_count = array(
			'admin-integrations' . $s . 'archive.php' => 'All integrations',
			'admin-settings' . $s . 'tab' . $s . 'general' . $s . 'improve-automator.php' => 'Improve Automator',
			'admin-settings' . $s . 'tab' . $s . 'general' . $s . 'logs.php' => 'Logs settings',
			'admin-settings' . $s . 'tab' . $s . 'general.php' => 'General settings',
			'admin-settings' . $s . 'tab' . $s . 'premium-integrations.php' => 'Premium integrations settings',
			'admin-settings' . $s . 'tab' . $s . 'advanced' . $s . 'background-actions.php' => 'Background actions settings',
			'admin-settings' . $s . 'tab' . $s . 'advanced' . $s . 'automator-cache.php' => 'Automator cache settings',
		);

		if ( isset( $views_to_count[ $file_name ] ) ) {

			$this->increment_view( $views_to_count[ $file_name ] );
		}

		return $views_directory;
	}

	/**
	 * @param $page
	 *
	 * @return void
	 */
	public function increment_view( $page ) {

		$user_id = get_current_user_id();

		$usage_report_stats = automator_get_option( self::STATS_OPTION_NAME, array( 'page_views' => array() ) );

		$page_views = array(
			'total'    => 0,
			'per_user' => array( $user_id => 0 ),
			'average'  => 0,
		);

		if ( isset( $usage_report_stats['page_views'][ $page ] ) ) {
			$page_views = $usage_report_stats['page_views'][ $page ];
		}

		if ( ! isset( $page_views['per_user'][ $user_id ] ) ) {
			$page_views['per_user'][ $user_id ] = 0;
		}

		$page_views['per_user'][ $user_id ] ++;

		$page_views['total'] ++;

		$total_users = count( $page_views['per_user'] );

		$page_views['average'] = $page_views['total'] / $total_users;

		$usage_report_stats['page_views'][ $page ] = $page_views;

		update_option( self::STATS_OPTION_NAME, $usage_report_stats );
	}

	/**
	 * @return void
	 */
	public function get_settings() {

		$settings_to_report = array(
			'Background actions' => '1' === get_option( Background_Actions::OPTION_NAME, '' ) ? 'Enabled' : 'Disabled',
			'Automator cache'    => '1' === get_option( Automator_Cache_Handler::OPTION_NAME, '' ) ? 'Enabled' : 'Disabled',
		);

		if ( is_automator_pro_active() ) {
			$settings_to_report['Auto-prune activity logs']        = empty( as_next_scheduled_action( 'uapro_auto_purge_logs' ) ) ? 'Disabled' : 'Enabled';
			$settings_to_report['Auto-prune activity logs (days)'] = (int) get_option( 'uap_automator_purge_days', 0 );
		}

		$this->report['settings'] = $settings_to_report;
	}
}
