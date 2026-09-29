<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Automator_System_Report;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Request;
use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;

use WP_REST_Response;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;
use function Uncanny_Automator\App\Infrastructure\automator_usage_reports_client;

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
	const  SCHEDULE_NAME = 'automator_report';
	/**
	 * Recurrence for SCHEDULE_NAME. Was 'weekly' until 7.7 — every site now
	 * reports once a day. Installs carrying the old weekly event are migrated
	 * by schedule_report(), which compares this against the live recurrence.
	 */
	const  SCHEDULE_RECURRENCE = 'daily';
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
	 * Per-key ceiling on the `events` buckets in STATS_OPTION_NAME, and on the
	 * length of a single recorded value. The row is cleared after every
	 * successful send, so these only bind inside one reporting period — they
	 * exist so a site that cannot reach the service can't grow a uap_options
	 * row without limit.
	 */
	const  MAX_EVENTS_PER_KEY = 500;
	/**
	 *
	 */
	const  MAX_EVENT_VALUE_LENGTH = 200;
	/**
	 * AI provider slugs whose connection state is reported. Each stores its key
	 * at `automator_{slug}_api_key`; only whether that key is set is ever sent.
	 * Mirrors the providers in src/core/lib/ai/provider/.
	 */
	const  AI_PROVIDERS = array( 'openai', 'claude', 'perplexity', 'grok', 'gemini', 'cohere', 'mistral' );
	/**
	 * How many distinct error codes the health block reports. A long tail of
	 * one-off codes is noise; the head is where a broken integration shows up.
	 */
	const  MAX_ERROR_CODES = 20;
	/**
	 * How many recipe-log ids to step the health boundary back by.
	 *
	 * Purely a PERFORMANCE bound, not part of any metric's meaning: the date
	 * predicate on each query decides what counts, and this only decides how
	 * much of the table the index has to walk to find it. It exists because a
	 * run that started before the window still writes rows inside it — a
	 * delayed action, a scheduled one, a background handler — and those rows
	 * carry a recipe_log_id below the window's own minimum.
	 *
	 * Generous on purpose. Over-reaching costs a slightly wider index range;
	 * under-reaching would silently drop a delayed action's row from the
	 * window it actually belongs to.
	 */
	const  BOUNDARY_LOOKBACK = 5000;
	/**
	 * Stable, UNTRANSLATED names for the statuses the health block groups by.
	 * Automator_Status::name() is translated, which would make the value depend
	 * on the reporting site's locale.
	 */
	const  STATUS_NAMES = array(
		0  => 'not_completed',
		1  => 'completed',
		2  => 'completed_with_errors',
		5  => 'in_progress',
		7  => 'cancelled',
		8  => 'skipped',
		9  => 'did_nothing',
		10 => 'completed_awaiting',
		11 => 'completed_with_notice',
		12 => 'queued',
		13 => 'in_progress_with_error',
		14 => 'failed',
	);
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

		add_action( 'automator_update_option_' . self::OPTION_NAME, array( $this, 'maybe_schedule_report' ), 100, 2 );
		add_action( 'automator_add_option_' . self::OPTION_NAME, array( $this, 'maybe_schedule_report' ), 100, 2 );

		// Daily, not weekly: this is what migrates a pre-7.7 install off the old
		// weekly recurrence, and on the weekly hook that took up to 7 days.
		add_action( 'automator_daily_healthcheck', array( $this, 'maybe_schedule_report' ) );

		add_action( 'automator_view_path', array( $this, 'count_integrations_view' ), 10, 2 );

		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );

		add_action( 'automator_settings_premium_integration_before_output', array( $this, 'count_premium_integration_view' ) );
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
	 * @return bool|\WP_Error
	 */
	public function validate_event( $request ) {

		$request_params = $request->get_params();

		if ( ! isset( $request_params['nonce'] ) || false === wp_verify_nonce( $request_params['nonce'], 'uncanny_automator' ) ) {
			return new \WP_Error( 'rest_forbidden', esc_html__( 'Invalid or missing nonce.', 'uncanny-automator' ), array( 'status' => 403 ) );
		}

		// Every event this records comes from an Automator admin screen (template
		// library, review banners), and the nonce it checks is only ever printed
		// on those screens. A nonce alone was still the whole gate, leaving an
		// authenticated write to a uap_options row available to any role that
		// got hold of one.
		if ( ! current_user_can( automator_get_capability() ) ) {
			return new \WP_Error( 'rest_forbidden', esc_html__( 'Insufficient permissions.', 'uncanny-automator' ), array( 'status' => 403 ) );
		}

		if ( empty( $request_params['event'] ) || ! is_string( $request_params['event'] ) ) {
			return new \WP_Error( 'rest_forbidden', esc_html__( 'Missing or invalid event parameter.', 'uncanny-automator' ), array( 'status' => 403 ) );
		}

		// `value` is either a scalar or the { banner, event } pair the review
		// tracker sends. Anything else — a nested array, an object — would be
		// cast to the string "Array" with a warning and stored as a junk bucket.
		if ( isset( $request_params['value'] ) && ! $this->is_valid_event_value( $request_params['value'] ) ) {
			return new \WP_Error( 'rest_forbidden', esc_html__( 'Invalid event value.', 'uncanny-automator' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Is this a shape sanitize_event_value() can store without losing meaning?
	 *
	 * A scalar, or the flat { banner, event } pair whose two members are
	 * themselves scalar. Rejecting here rather than casting in the writer keeps
	 * "Array" out of the payload entirely.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	private function is_valid_event_value( $value ) {

		if ( is_scalar( $value ) ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $key => $member ) {
			if ( ! in_array( $key, array( 'banner', 'event' ), true ) || ! is_scalar( $member ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function log_event( $request ) {

		// A site that opted out never sends, so it must not collect either:
		// nothing would ever clear what it accumulated.
		if ( ! $this->reporting_enabled() ) {
			return new WP_REST_Response( array(), 201 );
		}

		$data = $request->get_params();

		$event = (string) $data['event'];

		$usage_report_stats = automator_get_option( self::STATS_OPTION_NAME, array( 'events' => array() ) );

		if ( ! isset( $usage_report_stats['events'][ $event ] ) ) {
			$usage_report_stats['events'][ $event ] = array();
		}

		// Drop the event once the bucket is full rather than growing without
		// bound. The row is cleared on every successful send, so this only binds
		// within a single reporting period — but a stuck sender (or a site that
		// simply cannot reach the service) must not be able to bloat a
		// uap_options row indefinitely.
		if ( count( $usage_report_stats['events'][ $event ] ) >= self::MAX_EVENTS_PER_KEY ) {
			return new WP_REST_Response( array(), 201 );
		}

		$usage_report_stats['events'][ $event ][] = $this->sanitize_event_value( $data['value'] ?? '' );

		automator_update_option( self::STATS_OPTION_NAME, $usage_report_stats );

		return new WP_REST_Response( array(), 201 );
	}

	/**
	 * Normalize an event value to what the report actually consumes.
	 *
	 * The two producers send a search/import string or a
	 * `{ banner, event }` pair; get_template_library_info() lowercases and
	 * groups the former, get_review_tracking_info() reads two keys off the
	 * latter. Anything else was previously stored verbatim — an arbitrarily
	 * deep array from the request went straight into the option and then into
	 * the report payload.
	 *
	 * @param mixed $value
	 *
	 * @return string|array
	 */
	private function sanitize_event_value( $value ) {

		if ( is_array( $value ) ) {
			return array(
				'banner' => isset( $value['banner'] ) ? substr( sanitize_text_field( (string) $value['banner'] ), 0, self::MAX_EVENT_VALUE_LENGTH ) : '',
				'event'  => isset( $value['event'] ) ? substr( sanitize_text_field( (string) $value['event'] ), 0, self::MAX_EVENT_VALUE_LENGTH ) : '',
			);
		}

		return substr( sanitize_text_field( (string) $value ), 0, self::MAX_EVENT_VALUE_LENGTH );
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

		$scheduled = wp_get_scheduled_event( self::SCHEDULE_NAME );

		if ( false !== $scheduled && self::SCHEDULE_RECURRENCE === $scheduled->schedule ) {
			return;
		}

		// An event already exists on the wrong recurrence (the weekly schedule
		// every pre-7.7 install is carrying). wp_schedule_event() would refuse
		// it as a duplicate hook, so clear it first — otherwise those sites
		// stay weekly forever.
		if ( false !== $scheduled ) {
			$this->unschedule_report();
		}

		wp_schedule_event( $this->get_random_timestamp(), self::SCHEDULE_RECURRENCE, self::SCHEDULE_NAME );
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
	 * First run at a random point in the next 24 hours. The daily recurrence
	 * then keeps that time of day, so installs stay spread across the clock
	 * instead of all reporting on the hour.
	 *
	 * Always in the future — the old week-window version could land in the
	 * past, which WP fires on the next cron tick and then catches up from,
	 * bunching sites together.
	 *
	 * @return int timestamp
	 */
	public function get_random_timestamp() {
		return wp_rand( time(), time() + DAY_IN_SECONDS );
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

		// Absolute, and deliberately ahead of both the option and the filter: a
		// site owner who sets this in wp-config.php is not asking for a default.
		$override = $this->reporting_constant();

		if ( null !== $override ) {
			return $override;
		}

		if ( (bool) automator_get_option( self::OPTION_NAME, false ) === true ) {
			$reporting_enabled = true;
		}

		if ( is_automator_pro_active() ) {
			$reporting_enabled = true;
		}

		return apply_filters( self::OPTION_NAME, $reporting_enabled );
	}

	/**
	 * The AUTOMATOR_REPORTING wp-config override, or null when it is unset.
	 *
	 * Its own method purely so it can be substituted. A constant cannot be
	 * undefined once defined, so a test that reached for `define()` to cover the
	 * disabled branch would disable reporting for every test that ran after it
	 * in the same process.
	 *
	 * @return bool|null
	 */
	protected function reporting_constant() {
		return defined( 'AUTOMATOR_REPORTING' ) ? (bool) AUTOMATOR_REPORTING : null;
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
				// count_async() increments this for async_mode 'custom'. Without
				// the key here that was an undefined-array-key warning on every
				// report from a site using a custom delay.
				'custom_actions_count'      => 0,
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
		$this->get_dropins_mu_plugins();
		$this->get_automator_info();
		$this->get_recipes_info();
		$this->get_user_walkthrough_info();
		$this->get_date();
		$this->get_views();
		$this->get_template_library_info();
		$this->get_review_tracking_info();
		$this->get_settings();
		$this->get_health_metrics();
		$this->get_loops_info();
		$this->get_recipe_complexity();
		$this->get_never_run_recipes();
		$this->get_delay_units();

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
			// Already computed by Automator_System_Report on every report and
			// then dropped by this allowlist.
			//
			// log_directory_writable answers a whole class of "my logs are
			// empty" tickets on its own. permalink_structure correlates with
			// REST and webhook breakage. default_timezone against wp.timezone
			// offset catches scheduling drift.
			'log_directory_writable',
			'permalink_structure',
			'default_timezone',
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

		return isset( $usercount['total_users'] ) ? $usercount['total_users'] : esc_html__( 'Not set', 'uncanny-automator' );
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

		$license_manager = automator_license_manager();

		$license = array(
			'license_key'  => $license_manager->get_key(),
			'license_type' => $license_manager->get_type(),
			'item_name'    => $license_manager->get_item_name(),
			'site_name'    => $license_manager->get_site_name(),
		);

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
	 * Drop-ins and mu-plugins, which active_plugins structurally cannot see.
	 *
	 * object-cache.php and advanced-cache.php in particular change behaviour we
	 * get blamed for — a persistent object cache changes transient and option
	 * semantics — and an mu-plugin is exactly where a host puts the thing that
	 * makes one customer's site behave unlike every other.
	 *
	 * Names and versions only. No paths: they carry directory structure, and
	 * occasionally a customer's name in it.
	 *
	 * @return void
	 */
	public function get_dropins_mu_plugins() {

		$collected = isset( $this->system_report['dropins_mu_plugins'] )
			? (array) $this->system_report['dropins_mu_plugins']
			: array();

		$dropins = array();
		foreach ( (array) ( $collected['dropins'] ?? array() ) as $dropin ) {
			if ( ! empty( $dropin['plugin'] ) ) {
				// The filename IS the identity of a drop-in (object-cache.php),
				// and it is a fixed WordPress vocabulary, not a path.
				$dropins[] = basename( (string) $dropin['plugin'] );
			}
		}

		$mu_plugins = array();
		foreach ( (array) ( $collected['mu_plugins'] ?? array() ) as $mu_plugin ) {
			if ( empty( trim( (string) ( $mu_plugin['name'] ?? '' ) ) ) ) {
				continue;
			}

			$mu_plugins[] = array(
				'name'    => (string) $mu_plugin['name'],
				'version' => (string) ( $mu_plugin['version'] ?? '' ),
			);
		}

		$this->report['dropins']    = $dropins;
		$this->report['mu_plugins'] = $mu_plugins;
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
		$this->report['automator']['version'] = AUTOMATOR_PLUGIN_VERSION;

		if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			$this->report['automator']['pro_version'] = AUTOMATOR_PRO_PLUGIN_VERSION;
		}

		$this->report['automator']['database_version']                = $this->system_report['database']['automator_database_version'];
		$this->report['automator']['database_available_view_version'] = $this->system_report['database']['automator_database_available_view_version'];

		// The INSTALLED views version was missing while the available one was
		// sent, so view drift — the schema half most likely to be stale — was
		// undetectable. The table version already sent both halves.
		$this->report['automator']['database_views_version']     = $this->system_report['database']['automator_database_views_version'];
		$this->report['automator']['database_available_version'] = $this->system_report['database']['automator_database_available_version'];

		$this->get_schema_health();
		$this->get_table_sizes();
	}

	/**
	 * Whether this install's schema is actually intact.
	 *
	 * Automator_DB records what dbDelta could not create. Until now that was
	 * only visible by asking the customer to open Tools > Status, so a fleet of
	 * half-installed schemas was indistinguishable from a healthy one.
	 *
	 * @return void
	 */
	public function get_schema_health() {

		$missing_tables = automator_get_option( 'automator_schema_missing_tables', array() );
		$missing_views  = automator_get_option( 'automator_schema_missing_views', array() );

		$missing_tables = is_array( $missing_tables ) ? $missing_tables : array();
		$missing_views  = is_array( $missing_views ) ? $missing_views : array();

		$this->report['schema'] = array(
			// Names, not counts: which table is missing tells us whether it is a
			// permissions problem or one specific migration failing.
			'missing_tables'       => array_values( $missing_tables ),
			'missing_views'        => array_values( $missing_views ),
			'missing_tables_count' => count( $missing_tables ),
			'missing_views_count'  => count( $missing_views ),
			'healthy'              => empty( $missing_tables ) && empty( $missing_views ),
		);
	}

	/**
	 * Per-table data and index bytes for Automator's own tables.
	 *
	 * The system report already queries information_schema for this; the report
	 * threw it away, so "how big do these installs actually get" had no answer
	 * beyond the aggregate automator_db_size option.
	 *
	 * @return void
	 */
	public function get_table_sizes() {

		$tables = isset( $this->system_report['database']['database_tables']['automator'] )
			? (array) $this->system_report['database']['database_tables']['automator']
			: array();

		$sizes = array();
		$total = 0.0;

		foreach ( $tables as $name => $info ) {

			// A table that does not exist is `false` here — already reported by
			// get_schema_health(), so skip rather than record it as 0 MB.
			if ( ! is_array( $info ) ) {
				continue;
			}

			// The Automator VIEWS come back with null sizes; they hold no rows
			// of their own, so there is nothing to report for them.
			if ( null === $info['data'] && null === $info['index'] ) {
				continue;
			}

			// MB, to 2dp — that is the unit the information_schema query in
			// Automator_System_Report already rounds to. Not bytes.
			$data  = (float) ( $info['data'] ?? 0 );
			$index = (float) ( $info['index'] ?? 0 );

			// Strip the site prefix so wp_ and wp_7_ installs group together.
			$sizes[] = array(
				'name'   => $this->unprefix_table( (string) $name ),
				'data'   => $data,
				'index'  => $index,
				'engine' => (string) ( $info['engine'] ?? '' ),
			);

			$total += $data + $index;
		}

		$this->report['tables'] = array(
			'automator'    => $sizes,
			'automator_mb' => round( $total, 2 ),
			// The whole database, Automator's share of which is above. Gives the
			// ratio without us having to sum every other plugin's tables.
			'database_mb'  => round(
				(float) ( $this->system_report['database']['database_size']['data'] ?? 0 )
				+ (float) ( $this->system_report['database']['database_size']['index'] ?? 0 ),
				2
			),
		);
	}

	/**
	 * `wp_7_uap_recipe_log` -> `uap_recipe_log`, so the same table reported by
	 * two installs is comparable.
	 *
	 * @param string $table
	 *
	 * @return string
	 */
	private function unprefix_table( $table ) {

		$position = strpos( $table, 'uap_' );

		return false === $position ? $table : substr( $table, $position );
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

		// Non-overlapping delta for the daily schedule. completed_recipes_last_week
		// is a ROLLING 7-day window, so once a site reports daily, summing it
		// across reports counts the same run up to seven times.
		$this->report['recipes']['completed_recipes_last_day'] = Automator()->get->completed_runs( DAY_IN_SECONDS );

		if ( empty( $this->recipes_data ) ) {
			return;
		}

		foreach ( $this->recipes_data as $recipe_data ) {

			if ( 'publish' !== $recipe_data['post_status'] ) {
				++$this->report['recipes']['unpublished_recipes_count'];
				continue;
			}

			$this->process_recipe_data( $recipe_data );

		}

		$this->report['integrations_array'] = array_values( $this->report['integrations_array'] );

		$this->report['recipes']['total_integrations_used'] = count( $this->report['integrations'] );
	}

	/**
	 * get_user_walkthrough_info
	 *
	 * @return void
	 */
	public function get_user_walkthrough_info() {

		// Collect all user walkthroughs meta.
		global $wpdb;
		$results = $wpdb->get_results(
			"SELECT `meta_value` FROM {$wpdb->usermeta} WHERE meta_key = 'automator_walkthrough_progress'"
		);

		// Bail if no results.
		if ( empty( $results ) ) {
			$this->report['walkthroughs'] = array();
			return;
		}

		// Filter out valid data and organize.
		$walkthroughs = array();
		foreach ( $results as $result ) {
			$meta = empty( $result ) ? false : automator_safe_unserialize( $result->meta_value );
			if ( empty( $meta ) || ! is_array( $meta ) ) {
				continue;
			}

			foreach ( $meta as $id => $progress ) {
				if ( ! is_string( $id ) || ! is_array( $progress ) || empty( $progress['step'] ) ) {
					continue;
				}

				// Add walkthrough if not set.
				if ( ! isset( $walkthroughs[ $id ] ) ) {
					$walkthroughs[ $id ] = array();
				}

				// Initialize the step if not already set.
				if ( ! isset( $walkthroughs[ $id ][ $progress['step'] ] ) ) {
					$walkthroughs[ $id ][ $progress['step'] ] = array(
						'name'  => $progress['step'],
						'value' => 0,
					);
				}

				++$walkthroughs[ $id ][ $progress['step'] ]['value'];
			}
		}

		// Bail if no valid walkthroughs.
		if ( empty( $walkthroughs ) ) {
			$this->report['walkthroughs'] = array();
			return;
		}

		// Remove the keys from each walkthrough array.
		$walkthroughs = array_map( 'array_values', $walkthroughs );

		$this->report['walkthroughs'] = $walkthroughs;
	}

	/**
	 * get_completed_runs
	 *
	 * Counted from uap_recipe_log (completed = 1), NOT from a running option.
	 *
	 * The option this replaced was incremented on every
	 * `automator_recipe_completed`, and that hook fires on every recipe
	 * FINALIZATION — whatever the resolved status, including
	 * COMPLETED_WITH_ERRORS, FAILED and DID_NOTHING, and again each time a
	 * background action or loop re-finalizes the same run. It therefore never
	 * measured completions, and maintaining it cost an option read and write on
	 * the recipe-completion path.
	 *
	 * Caveat this swaps in: the query counts runs still IN the log, so a site
	 * with auto-prune enabled reports fewer than it has ever run, and the figure
	 * can fall between reports. The per-period deltas
	 * (completed_recipes_last_day / _last_week) are unaffected — they were
	 * always read from the same table.
	 *
	 * @return int
	 */
	public function get_completed_runs() {
		return absint( Automator()->get->total_completed_runs() );
	}

	/**
	 * @return int
	 */
	public function get_loop_recipes() {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p1.ID) as recipes
FROM $wpdb->posts p
    JOIN $wpdb->posts p1
        ON p.ID = p1.post_parent
WHERE p.post_type LIKE %s
  AND p1.post_type = %s",
				AUTOMATOR_POST_TYPE_RECIPE,
				AUTOMATOR_POST_TYPE_LOOP
			)
		);

		return absint( $result );
	}

	/**
	 * process_recipe_data
	 *
	 * @param mixed $recipe_data
	 *
	 * @return void
	 */
	public function process_recipe_data( $recipe_data ) {

		++$this->report['recipes']['live_recipes_count'];

		if ( 'user' === $recipe_data['recipe_type'] ) {
			++$this->report['recipes']['user_recipes_count'];
		} elseif ( 'anonymous' === $recipe_data['recipe_type'] ) {
			++$this->report['recipes']['everyone_recipes_count'];
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

			$meta = $item['meta'];

			if ( empty( $meta['integration_name'] ) || empty( $meta['code'] ) ) {
				continue;
			}

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
				$meta = $item['meta'];

				if ( empty( $meta['integration_name'] ) ) {
					continue;
				}

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

			++$this->report['recipes']['async_actions_count'];

			if ( 'delay' === $data['meta']['async_mode'] ) {

				++$this->report['recipes']['delayed_actions_count'];

			} elseif ( 'schedule' === $data['meta']['async_mode'] ) {

				++$this->report['recipes']['scheduled_actions_count'];

			} elseif ( 'custom' === $data['meta']['async_mode'] ) {

				++$this->report['recipes']['custom_actions_count'];

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
			++$this->report['integrations'][ $integration_code ][ $type ][ $item_code ];
		} else {
			$this->report['integrations'][ $integration_code ][ $type ][ $item_code ] = 1;
		}

		if ( isset( $this->report['integrations_array'][ $item_code ] ) ) {
			++$this->report['integrations_array'][ $item_code ]['usage'];
		} else {
			$this->report['integrations_array'][ $item_code ] = array(
				'integration_name' => $integration_name,
				'name'             => $item_code,
				'integration'      => $integration_code,
				'type'             => $type,
				'usage'            => 1,
			);
		}

		++$this->report['recipes'][ 'total_' . $type ];
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

		$stats = automator_get_option( self::STATS_OPTION_NAME, array() );

		if ( ! empty( $stats['page_views'] ) ) {
			$stats['page_views'] = array_values( $stats['page_views'] );
		}

		$this->report['stats'] = $stats;
	}

	/**
	 * get_template_library_info
	 *
	 * @return void
	 */
	public function get_template_library_info() {
		// Collect events stats.
		$stats   = automator_get_option( self::STATS_OPTION_NAME, array() );
		$stats   = isset( $stats['events'] ) ? $stats['events'] : array();
		$pre     = 'template-library-';
		$library = array(
			'search' => array(),
			'import' => array(),
		);

		// Bail if no stats.
		if ( empty( $stats ) ) {
			$this->report['template-library-search'] = array();
			$this->report['template-library-import'] = array();
			return;
		}

		// Loop through stats for library keys.
		foreach ( $library as $key => $data ) {
			// Check if 'search' or 'import' key exists in events stats.
			$data = isset( $stats[ "{$pre}{$key}" ] ) ? $stats[ "{$pre}{$key}" ] : array();
			if ( empty( $data ) ) {
				continue;
			}

			// Group results data and add counts.
			foreach ( $data as $term ) {
				// Lowercase search terms.
				$term = 'search' === $key ? strtolower( $term ) : $term;
				// Add term to results if not already set.
				if ( ! isset( $library[ $key ][ $term ] ) ) {
					$library[ $key ][ $term ] = array(
						$key    => $term,
						'count' => 0,
					);
				}
				// Increment count.
				++$library[ $key ][ $term ]['count'];
			}

			// Remove the array keys and convert to objects.
			$library[ $key ] = array_map(
				function ( $item ) {
					return (object) $item;
				},
				array_values( $library[ $key ] )
			);
		}

		$this->report['template-library-search'] = $library['search'];
		$this->report['template-library-import'] = $library['import'];
	}

	/**
	 * get_review_tracking_info
	 *
	 * @return void
	 */
	public function get_review_tracking_info() {
		// Set default.
		$this->report['review-banners'] = array();

		// Collect events stats.
		$stats   = automator_get_option( self::STATS_OPTION_NAME, array() );
		$stats   = isset( $stats['events'] ) ? $stats['events'] : array();
		$reviews = isset( $stats['review-tracking'] ) ? $stats['review-tracking'] : array();
		if ( empty( $reviews ) ) {
			return;
		}

		// Group results data and add counts.
		$data = array();
		foreach ( $reviews as $review ) {

			$banner = $review['banner'];
			$event  = $review['event'];

			// Add to results if not already set.
			if ( ! isset( $data[ $banner ] ) ) {
				$data[ $banner ] = array(
					'displayed'        => 0, // Amount of times banner was displayed.
					'first-dismissed'  => 0, // User dismissed the first banner.
					'first-positive'   => 0, // User clicked 'Yes, I love it'.
					'first-negative'   => 0, // User clicked 'No, I don't like it'.
					'second-dismissed' => 0, // User dismissed the second banner.
					'second-feedback'  => 0, // User clicked 'Send feedback'.
					'second-no'        => 0, // User clicked 'No thanks'.
					'second-review'    => 0, // User clicked 'Leave a review'.
					'second-later'     => 0, // User clicked 'Maybe later'.
					'second-done'      => 0, // User clicked 'I've done it'.
				);
			}

			// Increment valid banner events for the step.
			if ( isset( $data[ $banner ][ $event ] ) ) {
				++$data[ $banner ][ $event ];
			}
		}

		foreach ( $data as $banner => $results ) {
			// Filter out empty values and format the results
			$filtered_results = array();
			foreach ( $results as $name => $count ) {
				if ( $count > 0 ) {
					$filtered_results[] = array(
						'name'  => ucfirst( str_replace( '-', ' ', $name ) ),
						'count' => $count,
					);
				}
			}

			// Only add to the report if there are non-empty results
			if ( ! empty( $filtered_results ) ) {
				$name                                    = ucfirst( str_replace( '-', ' ', $banner ) );
				$this->report['review-banners'][ $name ] = $filtered_results;
			}
		}
	}

	/**
	 * send_report
	 *
	 * @return void
	 */
	public function send_report() {

		try {

			$request = new Api_Request(
				'v2/report',
				array(
					'data'   => wp_json_encode( $this->get_data() ),
					'action' => 'save_json',
				),
				'POST',
				10
			);

			$response = automator_usage_reports_client()->send( $request );

			if ( 201 !== $response->status_code() ) {
				throw new \Exception( esc_html__( 'Something went wrong', 'uncanny-automator' ) );
			}

			automator_delete_option( self::STATS_OPTION_NAME );

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
			'admin-integrations' . $s . 'archive.php'      => 'All integrations',
			'admin-settings' . $s . 'tab' . $s . 'general' . $s . 'improve-automator.php' => 'Improve Automator',
			'admin-settings' . $s . 'tab' . $s . 'general' . $s . 'logs.php' => 'Logs settings',
			'admin-settings' . $s . 'tab' . $s . 'general.php' => 'General settings',
			'admin-settings' . $s . 'tab' . $s . 'advanced' . $s . 'background-actions.php' => 'Background actions settings',
			'admin-settings' . $s . 'tab' . $s . 'advanced' . $s . 'automator-cache.php' => 'Automator cache settings',
			'admin-tools' . $s . 'tab' . $s . 'status.php' => 'Status',
			'admin-tools' . $s . 'tab' . $s . 'tools.php'  => 'Satus > Tools',
			'admin-tools' . $s . 'tab' . $s . 'debug.php'  => 'Status > Debug',
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

		// Same reason as log_event(): a site that never sends has no path that
		// clears this, so it must not start filling it.
		if ( ! $this->reporting_enabled() ) {
			return;
		}

		$user_id = get_current_user_id();

		$usage_report_stats = automator_get_option( self::STATS_OPTION_NAME, array( 'page_views' => array() ) );

		$page_views = array(
			'name'     => $page,
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

		++$page_views['per_user'][ $user_id ];

		++$page_views['total'];

		$total_users = count( $page_views['per_user'] );

		$page_views['average'] = $page_views['total'] / $total_users;

		$usage_report_stats['page_views'][ $page ] = $page_views;

		automator_update_option( self::STATS_OPTION_NAME, $usage_report_stats );
	}

	/**
	 * Counts the number of times a premium integration settings page is viewed.
	 *
	 * @param $settings_page - instance of the settings page class
	 *
	 * @return void
	 */
	public function count_premium_integration_view( $settings_page ) {
		$this->increment_view( $settings_page->get_name() . ' Settings' );
	}


	/**
	 * Failure and throughput signals for the reporting window.
	 *
	 * The report could say how many recipes a site HAS and nothing about
	 * whether any of them work. This is the half that turns "1,200 recipes"
	 * into "1,200 recipes, 8% of runs erroring".
	 *
	 * Every query here SEEKS on an indexed column and then filters by date
	 * within that range. uap_action_log and uap_error_log are the biggest
	 * tables Automator owns and index neither date_time — a bare windowed date
	 * scan across them would be a full table scan every single day, on exactly
	 * the high-volume sites we can least afford to slow down. So a leading-column
	 * seek on uap_recipe_log's date_completed index (date_time, completed)
	 * yields a boundary recipe_log_id, the rest range off the foreign keys that
	 * ARE indexed, and each then applies the same date predicate.
	 *
	 * Both parts are load-bearing. The id range alone bounds the SCAN but not
	 * the WINDOW — it would admit up to BOUNDARY_LOOKBACK runs of any age, so a
	 * "daily" error count could span weeks while the recipe counts spanned a
	 * day, and the error-rate ratio between them would be nonsense.
	 *
	 * uap_api_log is deliberately absent: it indexes only item_log_id and
	 * PRIMARY, so there is no cheap window into it. It needs a date_time index
	 * before it can be reported daily.
	 *
	 * @return void
	 */
	public function get_health_metrics() {

		/**
		 * Skip the health queries entirely. For an operator who would rather
		 * not pay for them at all. Resolved FIRST so opting out costs nothing.
		 *
		 * @param bool $enabled
		 */
		if ( ! apply_filters( 'automator_usage_report_health_enabled', true ) ) {
			return;
		}

		/**
		 * Filter the health window, in seconds. Matches the daily send.
		 *
		 * @param int $seconds
		 */
		$window = (int) apply_filters( 'automator_usage_report_health_window', DAY_IN_SECONDS );

		// Site-LOCAL, not UTC. uap_recipe_log.date_time is written with
		// current_time( 'mysql' ), so a UTC window silently widens or narrows by
		// the site's offset — a UTC+10 site would get a ~34 hour "day" and a
		// UTC-8 site ~16. Mirrors Automator_Get_Data::completed_runs().
		$since      = date_i18n( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $window );
		$boundary   = $this->get_boundary_recipe_log_id( $since );
		$started_at = microtime( true );

		$this->report['health'] = array(
			'window_seconds'  => $window,
			'recipe_statuses' => $this->count_recipe_statuses( $since ),
			'action_statuses' => $this->count_action_statuses( $boundary, $since ),
			'errors'          => $this->count_errors( $boundary, $since ),
			'stuck_recipes'   => $this->count_stuck_recipes(),
			'throttled_runs'  => $this->count_throttled_runs( $window ),
		);

		$this->report['health']['took'] = round( ( microtime( true ) - $started_at ) * 1000 );
	}

	/**
	 * The lowest recipe_log_id written inside the window.
	 *
	 * uap_recipe_log's `date_completed` index is (date_time, completed), so a
	 * bare `date_time >=` range is a leading-column seek — the only date index
	 * Automator has on a log table. Everything downstream ranges off the id
	 * this returns instead of scanning by date.
	 *
	 * The result is a LOWER bound on log ids, not a filter: a run that started
	 * before the window still has action and error rows written inside it
	 * (delayed, scheduled and background actions especially), so the boundary
	 * is deliberately pulled back by BOUNDARY_LOOKBACK to catch them.
	 *
	 * @param string $since
	 *
	 * @return int
	 */
	private function get_boundary_recipe_log_id( $since ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->recipe;

		$id = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(ID) FROM {$table} WHERE date_time >= %s", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// No run STARTED in the window. That does not mean nothing happened —
		// a delayed or background action belonging to an older run can still
		// have written rows today. Fall back to the newest run that exists,
		// pulled back by the same lookback, rather than to a boundary that
		// matches nothing.
		if ( null === $id ) {
			$id = $wpdb->get_var( "SELECT MAX(ID) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( null === $id ) {
			// Genuinely no runs on this site at all.
			return PHP_INT_MAX;
		}

		// Runs that began before the window still write action and error rows
		// inside it. Stepping the boundary back by a fixed number of log ids
		// catches those without reintroducing an unindexed date scan on the
		// action and error tables.
		return max( 1, (int) $id - self::BOUNDARY_LOOKBACK );
	}

	/**
	 * Recipe run outcomes in the window, by status.
	 *
	 * @param string $since
	 *
	 * @return array
	 */
	private function count_recipe_statuses( $since ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->recipe;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT completed, COUNT(*) AS total FROM {$table} WHERE date_time >= %s GROUP BY completed", $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->label_statuses( $rows );
	}

	/**
	 * Action outcomes in the window, by status. This is the one that shows
	 * WHICH failures are happening rather than just that a run went wrong.
	 *
	 * TWO predicates, and they do different jobs. The id range is the index
	 * seek — uap_action_log has no date_time index, so ranging on the indexed
	 * foreign key is what keeps this off a full scan. The date filter is the
	 * one that defines the window. Without it the id lookback leaks up to
	 * BOUNDARY_LOOKBACK runs of any age into a "daily" number, which would make
	 * this incomparable with count_recipe_statuses() and the error-rate ratio
	 * meaningless.
	 *
	 * date_time here is written with current_time( 'mysql' ), same site-local
	 * clock as uap_recipe_log, so $since applies unchanged.
	 *
	 * @param int    $boundary
	 * @param string $since
	 *
	 * @return array
	 */
	private function count_action_statuses( $boundary, $since ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->action;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT completed, COUNT(*) AS total FROM {$table} WHERE automator_recipe_log_id >= %d AND date_time >= %s GROUP BY completed", $boundary, $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->label_statuses( $rows );
	}

	/**
	 * Error volume, and which error CODES are producing it.
	 *
	 * Codes only. error_message and error_context are customer data — a failed
	 * API call routinely puts an email address, a token or a record id in
	 * there — and none of it belongs in telemetry.
	 *
	 * Same two-predicate shape as count_action_statuses(): the id range is the
	 * index seek, the date filter defines the window. uap_error_log's date_time
	 * is written with current_time( 'mysql' ) too.
	 *
	 * @param int    $boundary
	 * @param string $since
	 *
	 * @return array
	 */
	private function count_errors( $boundary, $since ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->error_log;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT error_code, item_type, COUNT(*) AS total FROM {$table} WHERE recipe_log_id >= %d AND date_time >= %s GROUP BY error_code, item_type ORDER BY total DESC LIMIT %d", $boundary, $since, self::MAX_ERROR_CODES ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$codes = array();
		$total = 0;

		foreach ( (array) $rows as $row ) {
			$count   = (int) $row['total'];
			$codes[] = array(
				'code'  => (string) $row['error_code'],
				'type'  => (string) $row['item_type'],
				'count' => $count,
			);
			$total  += $count;
		}

		return array(
			'top_codes'      => $codes,
			'top_codes_total' => $total,
		);
	}

	/**
	 * Runs still IN_PROGRESS long after anything could still be running.
	 *
	 * Not windowed on purpose — a run stuck three weeks ago is still stuck, and
	 * it is the backlog that matters, not today's additions. Uses the
	 * (completed, date_time) composite.
	 *
	 * @return int
	 */
	private function count_stuck_recipes() {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->recipe;

		/**
		 * Filter how old an IN_PROGRESS run must be to count as stuck.
		 *
		 * @param int $seconds
		 */
		$threshold = (int) apply_filters( 'automator_usage_report_stuck_threshold', DAY_IN_SECONDS );

		// Site-local, same reason as the window in get_health_metrics().
		$before = date_i18n( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $threshold );

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE completed = %d AND date_time < %s", Automator_Status::IN_PROGRESS, $before ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return absint( $count );
	}

	/**
	 * Throttled recipes that RAN inside the window.
	 *
	 * NOT "held back", which is what an earlier version of this claimed.
	 * User_Throttle_Strategy::is_interval_elapsed() only calls
	 * update_last_run() when the interval HAS elapsed and the run is allowed
	 * through; a run that is genuinely throttled leaves last_run untouched. So
	 * this column can only ever count passes, and the number means "recipes
	 * with throttling configured that ran during the window".
	 *
	 * Counting real blocks would require the strategy to record them — a
	 * runtime change, not in scope here.
	 *
	 * last_run is a UNIX timestamp written from time(), so it is compared
	 * against time(), unlike the site-local date_time columns.
	 *
	 * @param int $window
	 *
	 * @return int
	 */
	private function count_throttled_runs( $window ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->recipe_throttle;

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE last_run >= %d", time() - $window ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return absint( $count );
	}

	/**
	 * Attach a stable, UNTRANSLATED name to each status code.
	 *
	 * Automator_Status::name() returns a translated label, which would make the
	 * value depend on the reporting site's locale. The code is the real key; the
	 * name is a convenience for anyone reading a row by hand.
	 *
	 * @param array $rows
	 *
	 * @return array
	 */
	private function label_statuses( $rows ) {

		$output = array();

		foreach ( (array) $rows as $row ) {
			$status   = (int) $row['completed'];
			$output[] = array(
				'status' => $status,
				// An unmapped code keeps its number rather than collapsing into
				// one 'unknown' bucket — Pro and older data carry statuses core
				// does not define, and 'status_15' tells us which one to go and
				// look up. A shared 'unknown' would hide that entirely.
				'name'   => self::STATUS_NAMES[ $status ] ?? 'status_' . $status,
				'count'  => (int) $row['total'],
			);
		}

		return $output;
	}


	/**
	 * Loop adoption beyond "this recipe has one".
	 *
	 * loops_recipes_count already says how many recipes contain a loop. It does
	 * not say what they loop OVER, which is the part that decides what we build
	 * next, nor which filters people actually reach for.
	 *
	 * @return void
	 */
	public function get_loops_info() {

		$this->report['loops'] = array(
			'by_type' => $this->count_loop_types(),
			'filters' => $this->count_loop_filters(),
		);
	}

	/**
	 * Loops grouped by what they iterate — users, posts, tokens.
	 *
	 * The type lives inside the serialized `iterable_expression` meta, so it is
	 * read and unserialized here rather than grouped in SQL: the serialized
	 * bytes differ between an array and a stdClass payload for the same type,
	 * and grouping on them would split one bucket in two. Bounded by the number
	 * of loops on the site, which is small.
	 *
	 * @return array
	 */
	private function count_loop_types() {
		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value
				FROM {$wpdb->postmeta} pm
				JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND pm.meta_key = 'iterable_expression'",
				AUTOMATOR_POST_TYPE_LOOP
			)
		);

		$types = array();

		foreach ( (array) $rows as $row ) {

			// Never a bare unserialize() on stored meta — see the object
			// injection work that put automator_safe_unserialize() everywhere.
			$expression = automator_safe_unserialize( $row );
			$expression = is_object( $expression ) ? get_object_vars( $expression ) : $expression;

			if ( ! is_array( $expression ) || empty( $expression['type'] ) || ! is_string( $expression['type'] ) ) {
				continue;
			}

			$type = $expression['type'];

			$types[ $type ] = isset( $types[ $type ] ) ? $types[ $type ] + 1 : 1;
		}

		$output = array();

		foreach ( $types as $type => $count ) {
			$output[] = array(
				'type'  => $type,
				'count' => $count,
			);
		}

		return $output;
	}

	/**
	 * Which loop filters are in use, by integration and code.
	 *
	 * @return array
	 */
	private function count_loop_filters() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// LEFT JOINs: `code` is on every loop filter, the integration
				// metas are not, and an inner join silently dropped the ones
				// missing them.
				//
				// integration_code FIRST — that is the key WP_Filter_Store
				// writes and every other reader uses; `integration` is the
				// legacy twin kept for back-compat. Preferring the canonical one
				// means a filter carrying only it still lands under its real
				// integration rather than an empty bucket.
				"SELECT code.meta_value AS code,
				        COALESCE(integration_code.meta_value, integration.meta_value, '') AS integration,
				        COUNT(*) AS total
				FROM {$wpdb->posts} p
				JOIN {$wpdb->postmeta} code ON code.post_id = p.ID AND code.meta_key = 'code'
				LEFT JOIN {$wpdb->postmeta} integration_code ON integration_code.post_id = p.ID AND integration_code.meta_key = 'integration_code'
				LEFT JOIN {$wpdb->postmeta} integration ON integration.post_id = p.ID AND integration.meta_key = 'integration'
				WHERE p.post_type = %s
				GROUP BY code.meta_value, COALESCE(integration_code.meta_value, integration.meta_value, '')
				ORDER BY total DESC",
				AUTOMATOR_POST_TYPE_LOOP_FILTER
			),
			ARRAY_A
		);

		$output = array();

		foreach ( (array) $rows as $row ) {
			$output[] = array(
				'code'        => (string) $row['code'],
				'integration' => (string) $row['integration'],
				'count'       => (int) $row['total'],
			);
		}

		return $output;
	}

	/**
	 * The shape of the recipe estate, not just its average.
	 *
	 * An average hides the tail that actually stresses the runner: a site with
	 * one 60-action recipe and fifty 1-action recipes averages the same as fifty
	 * 2-action ones.
	 *
	 * Computed from recipe_items, which process_recipe_items() has already
	 * built, so it costs no query at all.
	 *
	 * @return void
	 */
	public function get_recipe_complexity() {

		$buckets = array(
			'1'     => 0,
			'2-3'   => 0,
			'4-5'   => 0,
			'6-10'  => 0,
			'11-20' => 0,
			'21+'   => 0,
		);

		$triggers_buckets = array(
			'1'   => 0,
			'2'   => 0,
			'3+'  => 0,
		);

		foreach ( (array) $this->report['recipe_items'] as $recipe ) {

			$actions = count( (array) ( $recipe['actions'] ?? array() ) );

			if ( $actions > 0 ) {
				++$buckets[ $this->complexity_bucket( $actions ) ];
			}

			$triggers = count( (array) ( $recipe['triggers'] ?? array() ) );

			if ( $triggers > 0 ) {
				$key = $triggers >= 3 ? '3+' : (string) $triggers;
				++$triggers_buckets[ $key ];
			}
		}

		$this->report['complexity'] = array(
			'actions_per_recipe'  => $this->labelled_buckets( $buckets ),
			'triggers_per_recipe' => $this->labelled_buckets( $triggers_buckets ),
		);
	}

	/**
	 * @param int $actions
	 *
	 * @return string
	 */
	private function complexity_bucket( $actions ) {

		if ( 1 === $actions ) {
			return '1';
		}

		if ( $actions <= 3 ) {
			return '2-3';
		}

		if ( $actions <= 5 ) {
			return '4-5';
		}

		if ( $actions <= 10 ) {
			return '6-10';
		}

		if ( $actions <= 20 ) {
			return '11-20';
		}

		return '21+';
	}

	/**
	 * Buckets as a list of {bucket, count}. A map keyed by label would need the
	 * consumer to address every bucket by name.
	 *
	 * @param array $buckets
	 *
	 * @return array
	 */
	private function labelled_buckets( $buckets ) {

		$output = array();

		foreach ( $buckets as $label => $count ) {
			$output[] = array(
				'bucket' => (string) $label,
				'count'  => (int) $count,
			);
		}

		return $output;
	}

	/**
	 * Published recipes that have never produced a run.
	 *
	 * Built and left, which no other field can show: they are live, so they
	 * count toward live_recipes_count, and they have no log rows, so they are
	 * invisible to every completion metric. A high number is a trigger that
	 * never fires — a configuration people believed was working.
	 *
	 * NOT EXISTS against the indexed automator_recipe_id, so it is a lookup per
	 * recipe rather than a scan of the log.
	 *
	 * @return void
	 */
	public function get_never_run_recipes() {
		global $wpdb;

		$log = $wpdb->prefix . Automator()->db->tables->recipe;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				WHERE p.post_type = %s
				  AND p.post_status = 'publish'
				  AND NOT EXISTS ( SELECT 1 FROM {$log} l WHERE l.automator_recipe_id = p.ID )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				AUTOMATOR_POST_TYPE_RECIPE
			)
		);

		$this->report['recipes']['never_run_count'] = absint( $count );
	}

	/**
	 * How long people actually delay for.
	 *
	 * delayed_actions_count says how many actions are delayed; the unit says
	 * whether the feature is used for a 5-minute nudge or a 30-day follow-up,
	 * which are different products with the same switch.
	 *
	 * @return void
	 */
	public function get_delay_units() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS unit, COUNT(*) AS total
				FROM {$wpdb->postmeta} pm
				JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = 'async_delay_unit'
				GROUP BY pm.meta_value",
				AUTOMATOR_POST_TYPE_ACTION
			),
			ARRAY_A
		);

		$output = array();

		foreach ( (array) $rows as $row ) {
			if ( empty( $row['unit'] ) ) {
				continue;
			}

			$output[] = array(
				'unit'  => (string) $row['unit'],
				'count' => (int) $row['total'],
			);
		}

		$this->report['delays'] = $output;
	}

	/**
	 * @return void
	 */
	public function get_settings() {

		// These labels are the keys the report is consumed by, so renaming one
		// silently breaks whatever reads it — add alongside, never rewrite.
		$settings_to_report = array(
			'Background actions'                 => $this->toggle_label( '1' === automator_get_option( Background_Actions::OPTION_NAME, '' ) ),
			'Automator cache'                    => $this->toggle_label( '1' === automator_get_option( Automator_Cache_Handler::OPTION_NAME, '' ) ),

			// Both default to ON, so the only signal worth having is who turned
			// them OFF — which nothing could see until now.
			'Uncanny Agent'                      => $this->toggle_label( Admin_Settings_Uncanny_Agent_General::get_setting( Admin_Settings_Uncanny_Agent_General::ENABLED_KEY ) ),
			'Uncanny Agent top bar button'       => $this->toggle_label( Admin_Settings_Uncanny_Agent_General::get_setting( Admin_Settings_Uncanny_Agent_General::TOP_BAR_BUTTON_ENABLED_KEY ) ),
			'Page Builder'                       => $this->toggle_label( ( new Page_Builder_Settings() )->is_enabled() ),

			// Debug left on in production is a recurring support cause, and was
			// only ever visible by asking the customer to open Tools > Status.
			'Debug mode'                         => $this->toggle_label( automator_get_option( 'automator_settings_debug_enabled', false ) ),
			'Debug notices'                      => $this->toggle_label( automator_get_option( 'automator_settings_debug_notices_enabled', false ) ),

			// Data lifecycle. Explains a site whose logs look empty, and gives
			// the retention posture without having to ask for it.
			'Purge records on recipe completion' => $this->toggle_label( automator_get_option( 'automator_delete_recipe_records_on_completion', false ) ),
			'Purge user records on user delete'  => $this->toggle_label( automator_get_option( 'automator_delete_user_records_on_user_delete', false ) ),
			'Delete data on uninstall'           => $this->toggle_label( automator_get_option( 'automator_delete_data_on_uninstall', false ) ),
			'Manual purge (days)'                => (int) automator_get_option( 'automator_manual_purge_days', 0 ),
			// Yes/No, not Enabled/Disabled: this is a fact about the site rather
			// than a switch. Safe to differ from the others because the key is
			// new, so nothing already reads it.
			'Has ever manually pruned'           => empty( automator_get_option( 'automator_last_manual_prune_date', '' ) ) ? 'No' : 'Yes',

			// 'Not set' rather than '' so the value is always addressable.
			'Notifications audience'             => $this->string_or_not_set( automator_get_option( 'automator_notifications_audience', '' ) ),
		);

		if ( is_automator_pro_active() ) {
			$settings_to_report['Auto-prune activity logs']        = empty( as_next_scheduled_action( 'uapro_auto_purge_logs' ) ) ? 'Disabled' : 'Enabled';
			$settings_to_report['Auto-prune activity logs (days)'] = (int) automator_get_option( 'uap_automator_purge_days', 0 );
		}

		$this->report['settings'] = $settings_to_report;

		$this->get_ai_providers();
	}

	/**
	 * The Enabled/Disabled convention every existing settings chart groups by.
	 *
	 * @param mixed $enabled
	 *
	 * @return string
	 */
	private function toggle_label( $enabled ) {
		return $enabled ? 'Enabled' : 'Disabled';
	}

	/**
	 * A chart-safe label for a free-text setting that may be unset.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function string_or_not_set( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		return '' === $value ? 'Not set' : $value;
	}

	/**
	 * Which AI providers the site has connected — slugs only, never keys.
	 *
	 * Mirrors the providers Provider_Factory registers, but read from a fixed
	 * list rather than that registry: the report runs on cron, where the AI
	 * stack may not have registered anything yet, and an unregistered provider
	 * would then read as "not connected" rather than "not loaded".
	 *
	 * @return void
	 */
	public function get_ai_providers() {

		$connected = array();

		foreach ( self::AI_PROVIDERS as $provider ) {
			if ( ! empty( automator_get_option( 'automator_' . $provider . '_api_key', '' ) ) ) {
				$connected[] = $provider;
			}
		}

		$this->report['ai'] = array(
			'providers'       => $connected,
			'providers_count' => count( $connected ),
		);
	}
}
