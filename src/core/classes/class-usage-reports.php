<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Automator_System_Report;

/**
 * Class Usage_Reports.
 *
 * @package Uncanny_Automator
 */
class Usage_Reports {

	public $system_report;
	public $recipes_data;
	public $report;
	public $nonce = 'automator_report_nonce';

	public $retry_interval = DAY_IN_SECONDS;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->report = $this->initialize_report();

		add_action( 'rest_api_init', array( $this, 'rest_api_endpoint' ) );

		add_action( 'shutdown', array( $this, 'maybe_report' ) );

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

		$next_report = get_option( 'automator_next_report', 0 );

		parse_str( $request->get_body(), $result );

		if ( empty( $result['next_report'] ) ) {
			return false;
		}

		return $next_report == $result['next_report'];
	}

	/**
	 * initialize_report
	 *
	 * @return void
	 */
	public function initialize_report() {
		return array(
			'server'         		=> array(),
			'wp'             		=> array(),
			'automator'      		=> array(),
			'license'        		=> array(),
			'active_plugins' 		=> array(),
			'theme'          		=> array(),
			'integrations'   		=> array(),
			'integrations_array' 	=> array(),
			'recipes'        		=> array(
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
	 * maybe_report
	 *
	 * @return bool
	 */
	public function maybe_report() {

		if ( ! $this->reporting_enabled() ) {
			return false;
		}

		if ( ! $this->time_to_report() ) {
			return false;
		}

		return $this->async_report();

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

		$next_report = get_option( 'automator_next_report', 0 );

		if ( $next_report < time() ) {
			return true;
		}

		return false;
	}

	/**
	 * async_report
	 *
	 * @return string
	 */
	public function async_report() {

		$next_report = time() + $this->retry_interval;
		// Update the option early to prevent multiple simultaneous calls
		update_option( 'automator_next_report', $next_report );

		$url = get_rest_url() . 'uap/v2/async_report/';

		// Call the endpoint to make sure that the process runs at the background
		$response = wp_remote_post(
			$url,
			array(
				'timeout'  => 0.01,
				'blocking' => false,
				'body'     => array(
					'next_report' => $next_report,
				),

			)
		);

		return $url;
	}

	/**
	 * get_data
	 *
	 * @return mixed
	 */
	public function get_data() {

		$started_at = microtime( true );

		$Automator_System_Report = Automator_System_Report::get_instance();

		$this->system_report = $Automator_System_Report->get();
		$this->recipes_data  = Automator()->get_recipes_data( false );

		$this->get_server_info();
		$this->get_wp_info();
		$this->get_theme_info();
		$this->get_license_info();
		$this->report['active_plugins'] = $this->get_plugins_info( $this->system_report['active_plugins'] );
		$this->get_automator_info();
		$this->get_recipes_info();

		$finished_at = microtime( true );

		$this->report['get_data_took'] = round( ( $finished_at - $started_at ) * 1000 );

		return $this->report;
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
		$wp['timezone_offset'] = date( 'P' );
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

		if ( empty( $this->recipes_data ) ) {
			return;
		}

		foreach ( $this->recipes_data as $recipe_data ) {

			if ( $recipe_data['post_status'] !== 'publish' ) {
				$this->report['recipes']['unpublished_recipes_count'] ++;
				continue;
			}

			$this->process_recipe_data( $recipe_data );
			$this->process_items( 'triggers', $recipe_data );
			$this->process_items( 'actions', $recipe_data );

		}

		$this->report['integrations_array'] = array_values( $this->report['integrations_array'] );
		
		$this->report['recipes']['total_integrations_used'] = count( $this->report['integrations'] );

		$this->report['recipes']['completed_recipes'] = Automator()->get->total_completed_runs();

		$report_frequency                                       = get_option( 'automator_report_frequency', WEEK_IN_SECONDS );
		$this->report['recipes']['completed_recipes_last_week'] = Automator()->get->completed_runs( $report_frequency );

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

		if ( $recipe_data['recipe_type'] === 'user' ) {
			$this->report['recipes']['user_recipes_count'] ++;
		} elseif ( $recipe_data['recipe_type'] === 'anonymous' ) {
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

			if ( $data['post_status'] !== 'publish' ) {
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
			if ( $data['meta']['async_mode'] == 'delay' ) {
				$this->report['recipes']['delayed_actions_count'] ++;
			} elseif ( $data['meta']['async_mode'] == 'schedule' ) {
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
	 * call_api
	 *
	 * @return void
	 */
	public function call_api() {

		$api_url = apply_filters( 'automator_api_url', AUTOMATOR_API_URL ) . 'v2/report';

		$data = $this->get_data();

		$response = wp_remote_post(
			$api_url,
			array(
				'method' => 'POST',
				'body'   => array(
					'action' => 'save',
					'data'   => $data,
				),
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->schedule_next_report( $body );

		return;

	}

	/**
	 * schedule_next_report
	 *
	 * @param mixed $body
	 *
	 * @return void
	 */
	public function schedule_next_report( $body ) {

		if ( ! is_wp_error( $body ) && is_array( $body ) && isset( $body['data']['report_frequency'] ) ) {
			$frequency = (int) $body['data']['report_frequency'];
			update_option( 'automator_next_report', time() + $frequency );
			update_option( 'automator_report_frequency', $frequency );
			return;
		}

	}
}
