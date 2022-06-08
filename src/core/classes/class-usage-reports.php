<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Automator_System_Report;

/**
 * Class Usage_Reports.
 *
 * @package Uncanny_Automator
 */
class Usage_Reports {

	const   RECIPE_COUNT_OPTION    = 'automator_completed_recipes';
	const   LAST_REPORT_OPTION     = 'automator_last_json_report';
	const   REPORT_SCHEDULE_OPTION = 'automator_reporting_daytime';
	const   ONGOING_REPORT_OPTION  = 'automator_report_is_ongoing';
	public $system_report;
	public $recipes_data;
	public $report;
	private $forced = false;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->report            = $this->initialize_report();
		$this->current_hourstamp = $this->hourstamp();
		$this->current_weekstamp = $this->weekstamp();

		add_action( 'rest_api_init', array( $this, 'rest_api_endpoint' ) );

		add_action( 'shutdown', array( $this, 'maybe_report' ) );

		add_action( 'automator_recipe_completed', array( $this, 'count_recipe_completion' ) );

	}


	/**
	 * weekstamp
	 *
	 * Will return an integer weekstamp like 202201 for the first week in 2022 and 202252 for the last one
	 *
	 * @return void
	 */
	public function weekstamp() {

		$year = gmdate( 'o' );
		$week = $this->leading_zeros( gmdate( 'W' ) );

		return absint( $year . $week );
	}

	/**
	 * hourstamp
	 *
	 * Will return an integer hourstamp like 202201307 for 7 am on Wednesday of the first week in 2022
	 * or 2022520522 for 10PM Friday, last week of 2022
	 *
	 * @return void
	 */
	public function hourstamp() {

		$day  = gmdate( 'N' );
		$hour = gmdate( 'H' );

		return absint( $this->weekstamp() . $day . $hour );
	}

	/**
	 * leading_zeros
	 *
	 * @param  mixed $value
	 * @param  mixed $length
	 * @return void
	 */
	public function leading_zeros( $value, $length = 2 ) {
		return str_pad( $value, $length, '0', STR_PAD_LEFT );
	}

	/**
	 * initialize_report
	 *
	 * @return void
	 */
	public function initialize_report() {
		return array(
			'server'             => array(),
			'wp'                 => array(),
			'automator'          => array(),
			'license'            => array(),
			'active_plugins'     => array(),
			'theme'              => array(),
			'integrations'       => array(),
			'integrations_array' => array(),
			'recipes'            => array(
				'live_recipes_count'        => 0,
				'user_recipes_count'        => 0,
				'everyone_recipes_count'    => 0,
				'total_actions'             => 0,
				'total_triggers'            => 0,
				'async_actions_count'       => 0,
				'delayed_actions_count'     => 0,
				'scheduled_actions_count'   => 0,
				'unpublished_recipes_count' => 0,
			),
		);
	}

	/**
	 * Method maybe_report
	 *
	 * @return bool
	 */
	public function maybe_report() {

		if ( $this->is_forced() ) {
			return $this->async_report();
		}

		if ( ! $this->reporting_enabled() ) {
			return false;
		}

		if ( $this->report_is_ongoing() ) {
			return false;
		}

		if ( ! $this->time_to_report() ) {
			return false;
		}

		return $this->async_report();

	}

	/**
	 * is_forced
	 *
	 * @return void
	 */
	public function is_forced() {

		if ( is_admin() && automator_filter_has_var( 'automator_force_report' ) && '42' === automator_filter_input( 'automator_force_report' ) ) {
			$this->forced = true;
			return true;
		}

		return false;
	}

	/**
	 * report_is_ongoing
	 *
	 * Prevents duplicated reports or sending a report when another one failed recently
	 *
	 * @return bool
	 */
	public function report_is_ongoing() {
		return get_transient( self::ONGOING_REPORT_OPTION );
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

		if ( is_automator_pro_active() ) {
			$reporting_enabled = true;
		}

		if ( (bool) get_option( 'automator_reporting', false ) === true ) {
			$reporting_enabled = true;
		}

		return apply_filters( 'automator_reporting', $reporting_enabled );
	}

	/**
	 * time_to_report
	 *
	 * @return bool
	 */
	public function time_to_report() {

		$last_report_weekstamp = $this->get_last_report();

		$schedule_day_hour = $this->get_schedule();

		$last_report_hourstamp = absint( $last_report_weekstamp . $schedule_day_hour );

		return $this->week_passed_since_last_report( $last_report_hourstamp );
	}

	/**
	 * week_passed
	 *
	 * An hourstamp has a format of 202233417
	 * Where 2022 is the year
	 * 33 is the week number in that year
	 * 4 stands for Thursday
	 * 17 stands for hours, or 5PM
	 *
	 * Therefore, each week adds 1000 to the hourstamp
	 *
	 * @return void
	 */
	public function week_passed_since_last_report( $hourstamp ) {

		if ( $this->current_hourstamp - $hourstamp >= 1000 ) {
			return true;
		}

		return false;

	}

	/**
	 * get_last_report
	 *
	 * @return void
	 */
	public function get_last_report() {

		$last_report = get_option( self::LAST_REPORT_OPTION, 0 );

		if ( 0 === $last_report ) {

			$last_report = $this->current_weekstamp - 1;
			// Pretend that the last report was sent last week
			$this->schedule_next_report( $last_report );

		}

		return $last_report;

	}

	/**
	 * get_schedule
	 *
	 * @return void
	 */
	public function get_schedule() {

		$schedule = get_option( self::REPORT_SCHEDULE_OPTION, 0 );

		if ( 0 === $schedule ) {

			$schedule = $this->generate_report_schedule();

			update_option( self::REPORT_SCHEDULE_OPTION, $schedule );

		}

		return absint( $schedule );
	}

	/**
	 * generate_report_schedule
	 *
	 * Will return an integer from 100 (Monday midnight) to 723 (Sunday 11PM)
	 *
	 * If too many sites will report at the same time, the API may be overloaded.
	 * Therefore we need to make sure the reports are sent at random times to distribute the load evenly across the week.
	 *
	 * @return void
	 */
	public function generate_report_schedule() {

		$random_day  = wp_rand( 1, 7 );
		$random_hour = wp_rand( 0, 23 );

		$report_day_hour = absint( $random_day . $this->leading_zeros( $random_hour ) );

		return $report_day_hour;
	}

	/**
	 * get_data
	 *
	 * @return mixed
	 */
	public function get_data() {

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
	 * @param  mixed $keys
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
		$wp['timezone_offset'] = wp_timezone_string();
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
			$this->process_items( 'triggers', $recipe_data );
			$this->process_items( 'actions', $recipe_data );

		}

		$this->report['integrations_array'] = array_values( $this->report['integrations_array'] );

		$this->report['recipes']['total_integrations_used'] = count( $this->report['integrations'] );

	}

	/**
	 * get_completed_runs
	 *
	 * @return void
	 */
	public function get_completed_runs() {
		return absint( get_option( self::RECIPE_COUNT_OPTION, Automator()->get->total_completed_runs() ) );
	}

	/**
	 * count_recipe_completion
	 *
	 * @return void
	 */
	public function count_recipe_completion() {
		$completed_runs = absint( get_option( self::RECIPE_COUNT_OPTION, Automator()->get->total_completed_runs() - 1 ) );
		update_option( self::RECIPE_COUNT_OPTION, ++$completed_runs );
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

	}

	/**
	 * get_triggers_info
	 *
	 * @return void
	 */
	public function process_items( $type, $recipe_data ) {

		if ( empty( $recipe_data[ $type ] ) ) {
			return;
		}

		foreach ( $recipe_data[ $type ] as $data ) {

			if ( 'publish' !== $data['post_status'] ) {
				continue;
			}

			$this->process_async_actions( $data );

			$this->count_integration( $data['meta'], $type );

		}

	}

	/**
	 * process_async_actions
	 *
	 * @param mixed $data
	 *
	 * @return void
	 */
	public function process_async_actions( $data ) {

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
	 * async_report
	 *
	 * @return string
	 */
	public function async_report() {

		// Block any subsequent report attempts for the next hour
		set_transient( self::ONGOING_REPORT_OPTION, true, HOUR_IN_SECONDS );

		$url = get_rest_url() . AUTOMATOR_REST_API_END_POINT . '/async_report/';

		// Call the endpoint to make sure that the process runs at the background
		$response = wp_remote_post(
			$url,
			array(
				'blocking' => false,
				'body'     => array(
					'forced' => $this->forced,
				),
			)
		);

		return $url;
	}

	/**
	 * rest_api_endpoint
	 * Create an endpoint so that the process can run at background
	 * https://site_domain/wp-json/uap/v2/async_report/
	 *
	 * @return void
	 */
	public function rest_api_endpoint() {
		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/async_report/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'call_api' ),
				'permission_callback' => array( $this, 'validate_rest_call' ),
			)
		);
	}

	/**
	 * validate_rest_call
	 *
	 * @param  mixed $request
	 * @return void
	 */
	public function validate_rest_call( $request ) {
		return get_transient( self::ONGOING_REPORT_OPTION );
	}

	/**
	 * call_api
	 *
	 * @return void
	 */
	public function call_api( $request ) {

		try {

			$incoming_params = $request->get_body_params();

			if ( ! empty( $incoming_params['forced'] ) ) {
				$this->forced = true;
			}

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

			$this->schedule_next_report( $this->current_weekstamp );

		} catch ( \Exception $e ) {
			// Return without deleting the transient
			return;
		}

		delete_transient( self::ONGOING_REPORT_OPTION );

	}

	/**
	 * schedule_next_report
	 *
	 * @return void
	 */
	public function schedule_next_report( $datestamp ) {
		update_option( self::LAST_REPORT_OPTION, $datestamp );
	}
}
