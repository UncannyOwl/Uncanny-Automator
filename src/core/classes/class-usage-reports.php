<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Automator_System_Report;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Request;
use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use Uncanny_Automator\App\Presentation\Recipe_View\Recipe_View;
use Uncanny_Automator\App\Presentation\Recipe_View\Recipe_View_Preference_Service;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Utilities\Delay_Schedule_Wire_Mapper;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Delay_Block;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;

use Uncanny_Automator\Services\Action_Scheduler;
use WP_REST_Response;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;
use function Uncanny_Automator\App\Infrastructure\automator_usage_reports_client;

/**
 * Builds the daily usage report and sends it to the Usage Reports Service.
 *
 * @package Uncanny_Automator
 */
class Usage_Reports {

	/**
	 * Option that stores the site's opt-in to usage reporting.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'automator_reporting';

	/**
	 * Cron hook the report is scheduled on and sent from.
	 *
	 * @var string
	 */
	const SCHEDULE_NAME = 'automator_report';

	/**
	 * WP-Cron recurrence the report is sent on.
	 *
	 * Recurrence for SCHEDULE_NAME. Was 'weekly' until 7.7 — every site now
	 * reports once a day. Installs carrying the old weekly event are migrated
	 * by schedule_report(), which compares this against the live recurrence.
	 *
	 * @var string
	 */
	const SCHEDULE_RECURRENCE = 'daily';

	/**
	 * Plugin basename of Automator Free, for its activation and deactivation hooks.
	 *
	 * @var string
	 */
	const AUTOMATOR_PATH = 'uncanny-automator/uncanny-automator.php';

	/**
	 * Plugin basename of Automator Pro, for its activation and deactivation hooks.
	 *
	 * @var string
	 */
	const AUTOMATOR_PRO_PATH = 'uncanny-automator-pro/uncanny-automator-pro.php';

	/**
	 * Option that collects page views and admin events between two sends.
	 *
	 * @var string
	 */
	const STATS_OPTION_NAME = 'usage_report_stats';

	/**
	 * Most values one `events` key may hold before further events are dropped.
	 *
	 * Per-key ceiling on the `events` buckets in STATS_OPTION_NAME, and on the
	 * length of a single recorded value. The row is cleared after every
	 * successful send, so these only bind inside one reporting period — they
	 * exist so a site that cannot reach the service can't grow a uap_options
	 * row without limit.
	 *
	 * @var int
	 */
	const MAX_EVENTS_PER_KEY = 500;

	/**
	 * Longest stored event value, in characters; longer values are truncated.
	 *
	 * @var int
	 */
	const MAX_EVENT_VALUE_LENGTH = 200;

	/**
	 * Provider slugs get_ai_providers() checks for a stored API key.
	 *
	 * AI provider slugs whose connection state is reported. Each stores its key
	 * at `automator_{slug}_api_key`; only whether that key is set is ever sent.
	 * Mirrors the providers in src/core/lib/ai/provider/.
	 *
	 * @var string[]
	 */
	const AI_PROVIDERS = array( 'openai', 'claude', 'perplexity', 'grok', 'gemini', 'cohere', 'mistral' );

	/**
	 * Cap on the error code rows count_errors() returns, most frequent first.
	 *
	 * How many distinct error codes the health block reports. A long tail of
	 * one-off codes is noise; the head is where a broken integration shows up.
	 *
	 * @var int
	 */
	const MAX_ERROR_CODES = 20;

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
	 *
	 * @var int
	 */
	const BOUNDARY_LOOKBACK = 5000;

	/**
	 * Status code to stable name map that label_statuses() applies to health rows.
	 *
	 * Stable, UNTRANSLATED names for the statuses the health block groups by.
	 * Automator_Status::name() is translated, which would make the value depend
	 * on the reporting site's locale.
	 *
	 * @var array<int, string>
	 */
	const STATUS_NAMES = array(
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
	 * The system report this report copies server, theme, plugin and database facts from.
	 *
	 * @var array
	 */
	public $system_report;

	/**
	 * Catalog display names resolved during this report, keyed by integration code.
	 *
	 * @var array<string, string>
	 */
	private $integration_names = array();

	/**
	 * Published item rows per report item type (triggers, actions), read once per report.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private $item_rows = array();

	/**
	 * Published block rows per block type, read once per report.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private $block_rows = array();

	/**
	 * Every delay point in a published recipe, or null until first read.
	 *
	 * @var array<int, array{recipe_id: int, mode: string, unit: string}>|null
	 */
	private $delay_points = null;

	/**
	 * The report being assembled, keyed as the Usage Reports Service receives it.
	 *
	 * @var array
	 */
	public $report;

	/**
	 * Whether the send was forced rather than scheduled; sent as the report's `forced` key.
	 *
	 * @var bool
	 */
	private $forced = false;

	/**
	 * Register the hooks that schedule, send and feed the report.
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
	 * Register the REST route the admin screens record usage events through.
	 *
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
	 * Permission check for the log-event route: nonce, capability, then the event and value shape.
	 *
	 * @param \WP_REST_Request $request The log-event request.
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
	 * Whether an event value is a shape sanitize_event_value() can store without losing meaning.
	 *
	 * A scalar, or the flat { banner, event } pair whose two members are
	 * themselves scalar. Rejecting here rather than casting in the writer keeps
	 * "Array" out of the payload entirely.
	 *
	 * @param mixed $value The `value` request parameter.
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
	 * Record one admin event in the stats option for the next report.
	 *
	 * @param \WP_REST_Request $request The log-event request.
	 *
	 * @return WP_REST_Response
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
	 * @param mixed $value The validated `value` request parameter.
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
	 * Schedule the report when reporting is enabled, otherwise remove the schedule.
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
	 * Schedule the report event, replacing one left on another recurrence.
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
	 * Remove the next scheduled report event, if there is one.
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
	 * Defer the schedule check until Automator Pro has finished deactivating.
	 *
	 * Because the deactivation hook runs when a plugin is still active, we need to wait a little, before we try to unschedule the report after Automator Pro is deactivated.
	 *
	 * @return void
	 */
	public function pro_deactivated() {
		wp_schedule_single_event( time() + 10, 'after_automator_pro_deactivated' );
	}

	/**
	 * Pick the first send time at random within the next 24 hours.
	 *
	 * First run at a random point in the next 24 hours. The daily recurrence
	 * then keeps that time of day, so installs stay spread across the clock
	 * instead of all reporting on the hour.
	 *
	 * Always in the future — the old week-window version could land in the
	 * past, which WP fires on the next cron tick and then catches up from,
	 * bunching sites together.
	 *
	 * @return int
	 */
	public function get_random_timestamp() {
		return wp_rand( time(), time() + DAY_IN_SECONDS );
	}

	/**
	 * Send the report when reporting is enabled.
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
	 * Whether this site sends usage reports: the wp-config constant, else the opt-in option or an active Pro, through the `automator_reporting` filter.
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

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The hook name is a class constant that starts with the automator_ prefix.
		return apply_filters( self::OPTION_NAME, $reporting_enabled );
	}

	/**
	 * Whether the site shows the `Allow usage tracking` switch: Automator Free
	 * without Pro, with no account connected. A connected site is opted in when
	 * it connects (Admin_Menu::auto_optin_users()) and a Pro site reports while
	 * Pro is active, so neither has a switch to turn.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public static function has_opt_out_switch() {
		return ! is_automator_pro_active() && '' === automator_license_manager()->get_type();
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
	 * Reset the per-report caches and set the report to its empty shape.
	 *
	 * @return void
	 */
	public function initialize_report() {

		$this->integration_names = array();
		$this->item_rows         = array();
		$this->block_rows        = array();
		$this->delay_points      = null;

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
				'async_actions_count'       => 0,
				'delayed_actions_count'     => 0,
				'scheduled_actions_count'   => 0,
				'custom_actions_count'      => 0,
				'unpublished_recipes_count' => 0,
				// Published recipes the 8.0.0 migrators have not reached. The two
				// legacy legs in this class retire when this reads zero.
				'legacy_storage_count'      => 0,
			),
			'blocks'             => array(
				Block_Type::FILTER         => 0,
				Block_Type::DELAY_SCHEDULE => 0,
				Block_Type::LOOP           => 0,
			),
		);
	}

	/**
	 * Assemble the full report payload and record how long it took.
	 *
	 * @return array
	 */
	public function get_data() {

		$this->initialize_report();

		$started_at = microtime( true );

		$automator_system_report = Automator_System_Report::get_instance();

		$this->system_report = $automator_system_report->get();

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
		$this->get_recipe_view_info();
		$this->get_date();
		$this->get_views();
		$this->get_template_library_info();
		$this->get_review_tracking_info();
		$this->get_settings();
		$this->get_health_metrics();
		$this->get_loops_info();
		$this->get_blocks_info();
		$this->get_recipe_complexity();
		$this->get_never_run_recipes();
		$this->get_delay_units();

		$finished_at = microtime( true );

		$this->report['get_data_took'] = round( ( $finished_at - $started_at ) * 1000 );

		$this->report['forced'] = $this->forced;

		return $this->report;
	}

	/**
	 * Add the MD5 hash of the site URL to the report.
	 *
	 * The hash is unsalted, so it identifies the site rather than hiding it, and
	 * the report also sends the home URL itself in `license.site_name`.
	 *
	 * @return void
	 */
	public function get_unique_site_hash() {

		$site_url                  = get_site_url();
		$site_hash                 = md5( $site_url );
		$this->report['site_hash'] = $site_hash;
	}

	/**
	 * Copy the server and environment facts the report sends from the system report.
	 *
	 * @return void
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
	 * Copy the named environment keys from the system report into the report's server section.
	 *
	 * @param string[] $keys Environment keys to copy.
	 *
	 * @return void
	 */
	public function import_from_system_report( $keys ) {
		foreach ( $keys as $key ) {
			$this->report['server'][ $key ] = $this->system_report['environment'][ $key ];
		}
	}

	/**
	 * Add multisite, site count, user count, timezone and locale to the report.
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
	 * Total registered users, or a translated 'Not set' when WordPress cannot count them.
	 *
	 * @return int|string
	 */
	public function get_user_count() {
		$usercount = count_users();

		return isset( $usercount['total_users'] ) ? $usercount['total_users'] : esc_html__( 'Not set', 'uncanny-automator' );
	}

	/**
	 * Add the active theme's name and version to the report.
	 *
	 * @return void
	 */
	public function get_theme_info() {

		$theme['name']    = $this->system_report['theme']['name'];
		$theme['version'] = $this->system_report['theme']['version'];

		$this->report['theme'] = $theme;
	}

	/**
	 * Add the license key, type, item name and site name to the report.
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
	 * Names and versions of the given plugins, skipping any without a name.
	 *
	 * @param array $plugins Active plugins from the system report.
	 *
	 * @return array
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
	 * Number of sites in the network, or 'Not set' when get_blog_count() is unavailable.
	 *
	 * @return int|string
	 */
	public function sites_count() {

		$blog_count = 'Not set';

		if ( function_exists( 'get_blog_count' ) ) {

			$blog_count = get_blog_count();

		}

		return $blog_count;
	}

	/**
	 * Add Automator and database versions, schema health and table sizes to the report.
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
	 * Strip the site's table prefix from a table name.
	 *
	 * `wp_7_uap_recipe_log` -> `uap_recipe_log`, so the same table reported by
	 * two installs is comparable.
	 *
	 * @param string $table Table name with its site prefix.
	 *
	 * @return string
	 */
	private function unprefix_table( $table ) {

		$position = strpos( $table, 'uap_' );

		return false === $position ? $table : substr( $table, $position );
	}

	/**
	 * Add completion counts and the recipe census: recipe counters, per-recipe items, integrations and conditions.
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

		$recipes = automator_get_recipe_store()->census_rows();

		if ( empty( $recipes ) ) {
			return;
		}

		// Newest first, the order the 7.7 report folded recipes in, so the three
		// lists keyed by first appearance come out as the service has always seen them.
		$recipes = array_reverse( $recipes );

		$items      = $this->items_by_recipe();
		$delays     = $this->delay_points_by_recipe();
		$conditions = $this->conditions_by_recipe();

		foreach ( $recipes as $recipe ) {

			if ( 'publish' !== $recipe['post_status'] ) {
				++$this->report['recipes']['unpublished_recipes_count'];
				continue;
			}

			$this->process_recipe(
				$recipe,
				$items[ $recipe['id'] ] ?? array(),
				$delays[ $recipe['id'] ] ?? array(),
				$conditions[ $recipe['id'] ] ?? array()
			);
		}

		$this->report['integrations_array'] = array_values( $this->report['integrations_array'] );

		$this->report['recipes']['total_integrations_used'] = count( $this->report['integrations'] );
	}

	/**
	 * Count the users at each step of each walkthrough.
	 *
	 * @return void
	 */
	public function get_user_walkthrough_info() {

		// Collect all user walkthroughs meta.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over core usermeta for the usage report; no WordPress API returns this.
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
	 * Count how people view recipes on this site: who picked the diagram or
	 * the list, who can open the builder and never picked (by their own
	 * default view), and who dismissed the diagram introduction.
	 *
	 * The values are user options, so they sit under this site's blog-prefixed
	 * meta key, and each query counts this site's members only. A default that
	 * is not settled yet is read as the rule gives it today and never stored:
	 * the report settles nobody. Left out when a default cannot be read,
	 * rather than reported as zeros.
	 *
	 * @return void
	 */
	public function get_recipe_view_info() {

		global $wpdb;

		$view_key  = $wpdb->get_blog_prefix() . Recipe_View_Preference_Service::VIEW_USER_OPTION;
		$intro_key = $wpdb->get_blog_prefix() . Recipe_View_Preference_Service::DIAGRAM_INTRO_USER_OPTION;

		$never_picked = new \WP_User_Query(
			array(
				'capability' => automator_get_capability(),
				'meta_query' => array(
					array(
						'key'     => $view_key,
						'compare' => 'NOT EXISTS',
					),
				),
				'fields'     => 'ID',
			)
		);
		$user_ids     = array_map( 'intval', $never_picked->get_results() );

		update_meta_cache( 'user', $user_ids );

		$not_chosen = array(
			Recipe_View::LIST    => 0,
			Recipe_View::DIAGRAM => 0,
		);

		foreach ( $user_ids as $user_id ) {
			$default_view = automator_get_recipe_default_view( $user_id );

			if ( is_wp_error( $default_view ) ) {
				return;
			}

			++$not_chosen[ $default_view ];
		}

		$this->report['recipe_view'] = array(
			Recipe_View::DIAGRAM      => $this->count_users_with( $view_key, Recipe_View::DIAGRAM ),
			Recipe_View::LIST         => $this->count_users_with( $view_key, Recipe_View::LIST ),
			'not_chosen'              => $not_chosen,
			'diagram_intro_dismissed' => $this->count_users_with( $intro_key, '1' ),
		);
	}

	/**
	 * How many users of this site hold a meta value, counted without loading them.
	 *
	 * @param string $meta_key   The meta key, blog prefix included.
	 * @param string $meta_value The value.
	 *
	 * @return int
	 */
	private function count_users_with( $meta_key, $meta_value ) {

		$query = new \WP_User_Query(
			array(
				'meta_query'  => array(
					array(
						'key'   => $meta_key,
						'value' => $meta_value,
					),
				),
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		);

		return (int) $query->get_total();
	}

	/**
	 * Count every completed run still in the recipe log.
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
	 * Loop blocks in published recipes, at any depth.
	 *
	 * @return int
	 */
	public function get_loop_recipes() {
		return count( $this->block_rows( Block_Type::LOOP ) );
	}

	/**
	 * Fold one published recipe into the counters and append its recipe_items entry.
	 *
	 * @param array $recipe     Census row: id, post_status, recipe_type, version.
	 * @param array $items      The recipe's item rows, triggers then actions, each with `type`.
	 * @param array $delays     The recipe's delay points, each with `mode` and `unit`.
	 * @param array $conditions The recipe's condition strings, `Name/CODE`.
	 *
	 * @return void
	 */
	private function process_recipe( $recipe, $items, $delays, $conditions ) {

		++$this->report['recipes']['live_recipes_count'];

		if ( 'user' === $recipe['recipe_type'] ) {
			++$this->report['recipes']['user_recipes_count'];
		} elseif ( 'anonymous' === $recipe['recipe_type'] ) {
			++$this->report['recipes']['everyone_recipes_count'];
		}

		if ( version_compare( (string) $recipe['version'], Legacy_Storage_Migration::BLOCK_STORAGE_SINCE, '<' ) ) {
			++$this->report['recipes']['legacy_storage_count'];
		}

		$entry = array(
			'triggers'     => array(),
			'actions'      => array(),
			'integrations' => array(),
		);

		foreach ( $items as $item ) {

			$name = $this->integration_name( $item['integration_code'] );

			$entry[ $item['type'] ][] = $name . '/' . $item['code'];
			$entry['integrations'][]  = $name;

			$this->count_integration(
				array(
					'integration'      => $item['integration_code'],
					'integration_name' => $name,
					'code'             => $item['code'],
				),
				$item['type']
			);
		}

		// Keys are kept on purpose: the service receives the shape it has always received.
		$entry['integrations'] = array_unique( $entry['integrations'] );

		$modes = array_count_values( array_column( $delays, 'mode' ) );

		$entry['async_actions_count']     = count( $delays );
		$entry['delayed_actions_count']   = $modes['delay'] ?? 0;
		$entry['scheduled_actions_count'] = $modes['schedule'] ?? 0;

		foreach ( $delays as $delay ) {
			$this->count_delay_point( $delay['mode'] );
		}

		$entry['type'] = $recipe['recipe_type'];

		$entry['actions_conditions'] = $conditions;

		$this->report['recipe_items'][] = $entry;
	}

	/**
	 * Count one delay point: a delay block, or an async action on a recipe the delay migrator has not reached.
	 *
	 * @param string $mode Wire-vocabulary mode: delay, schedule or custom.
	 *
	 * @return void
	 */
	public function count_delay_point( $mode ) {

		++$this->report['recipes']['async_actions_count'];

		if ( 'delay' === $mode ) {
			++$this->report['recipes']['delayed_actions_count'];
		} elseif ( 'schedule' === $mode ) {
			++$this->report['recipes']['scheduled_actions_count'];
		} elseif ( 'custom' === $mode ) {
			++$this->report['recipes']['custom_actions_count'];
		}
	}

	/**
	 * The catalog display name for an integration code, or the code when the catalog has no entry.
	 *
	 * @param string $code Integration code.
	 *
	 * @return string
	 */
	private function integration_name( $code ) {

		if ( isset( $this->integration_names[ $code ] ) ) {
			return $this->integration_names[ $code ];
		}

		$integration = Integration_Query_Service::get_instance()->get_integration( $code );
		$name        = null === $integration ? '' : trim( $integration->get_name()->get_value() );

		$this->integration_names[ $code ] = '' === $name ? $code : $name;

		return $this->integration_names[ $code ];
	}

	/**
	 * Published item rows of one report type, read once per report.
	 *
	 * @param string $type One of triggers, actions.
	 *
	 * @return array<int, array{recipe_id: int, post_id: int, parent_id: int, integration_code: string, code: string}>
	 */
	private function item_rows( $type ) {

		if ( isset( $this->item_rows[ $type ] ) ) {
			return $this->item_rows[ $type ];
		}

		$rows = 'triggers' === $type
			? automator_get_recipe_trigger_store()->usage_rows()
			: automator_get_action_store()->usage_rows();

		$this->item_rows[ $type ] = $rows;

		return $rows;
	}

	/**
	 * Published items grouped by recipe id, triggers then actions, each tagged with its report type.
	 *
	 * @return array<int, array<int, array{type: string, post_id: int, integration_code: string, code: string}>>
	 */
	private function items_by_recipe() {

		$map = array();

		foreach ( array( 'triggers', 'actions' ) as $type ) {
			foreach ( $this->item_rows( $type ) as $row ) {
				$map[ $row['recipe_id'] ][] = array(
					'type'             => $type,
					'post_id'          => $row['post_id'],
					'integration_code' => $row['integration_code'],
					'code'             => $row['code'],
				);
			}
		}

		return $map;
	}

	/**
	 * Published blocks of one type in published recipes, read once per report.
	 *
	 * @param string $block_type A Block_Type constant.
	 *
	 * @return array<int, array{block_id: int, recipe_id: int, legacy_group_id: string, fields: array, iterable_expression: array}>
	 */
	private function block_rows( $block_type ) {

		if ( ! isset( $this->block_rows[ $block_type ] ) ) {
			$this->block_rows[ $block_type ] = automator_get_block_store()->rows_in_published_recipes( $block_type );
		}

		return $this->block_rows[ $block_type ];
	}

	/**
	 * Every delay point in a published recipe.
	 *
	 * A delay block is one point, read through the block's own field vocabulary and translated
	 * to the wire modes this report has always sent. On a recipe the delay migrator has not
	 * reached, each action still carrying `async_mode` is one point; the migrator renames those
	 * keys, so the two legs never count the same recipe.
	 *
	 * @return array<int, array{recipe_id: int, mode: string, unit: string}>
	 */
	private function delay_points() {

		if ( null !== $this->delay_points ) {
			return $this->delay_points;
		}

		$points = array();
		$mapper = new Delay_Schedule_Wire_Mapper();
		$known  = array( Delay_Block::MODE_DELAY, Delay_Block::MODE_SCHEDULE, Delay_Block::MODE_TOKEN );

		foreach ( $this->block_rows( Block_Type::DELAY_SCHEDULE ) as $row ) {

			$fields = Fields_Bag::from_array( $row['fields'] );
			$mode   = strtolower( $fields->scalar( Delay_Block::FIELD_MODE ) );
			$unit   = trim( $fields->scalar( Delay_Block::FIELD_UNIT ) );

			// A mode outside the block's vocabulary (absent, empty, or one this build predates)
			// counts as `delay`, the radio's default; a configured wait is never dropped.
			$points[] = array(
				'recipe_id' => $row['recipe_id'],
				'mode'      => $mapper->block_mode_to_wire_mode( in_array( $mode, $known, true ) ? $mode : Delay_Block::MODE_DELAY ),
				'unit'      => '' === $unit ? 'minutes' : $unit,
			);
		}

		$action_rows    = $this->item_rows( 'actions' );
		$action_recipes = array_column( $action_rows, 'recipe_id', 'post_id' );
		$action_parents = array_column( $action_rows, 'parent_id', 'post_id' );
		$delay_blocks   = array_fill_keys( array_column( $this->block_rows( Block_Type::DELAY_SCHEDULE ), 'block_id' ), true );

		foreach ( $this->legacy_async_rows( array_keys( $action_recipes ) ) as $action_id => $meta ) {

			// Still inside a delay block with live async_* meta: a wrap the migrator left before
			// the drain. The block above already counted it.
			if ( isset( $delay_blocks[ $action_parents[ $action_id ] ?? 0 ] ) ) {
				continue;
			}

			$points[] = array(
				'recipe_id' => $action_recipes[ $action_id ],
				'mode'      => $meta['async_mode'],
				'unit'      => $meta['async_delay_unit'],
			);
		}

		$this->delay_points = $points;

		return $points;
	}

	/**
	 * Delay points grouped by recipe id.
	 *
	 * @return array<int, array<int, array{recipe_id: int, mode: string, unit: string}>>
	 */
	private function delay_points_by_recipe() {

		$map = array();

		foreach ( $this->delay_points() as $point ) {
			$map[ $point['recipe_id'] ][] = $point;
		}

		return $map;
	}

	/**
	 * `async_mode` and `async_delay_unit` for the given actions, keyed by action id, where a mode is set.
	 *
	 * The delay migrator renames these keys to `_legacy_async_*` when it wraps an action in a
	 * delay block, so a hit is an action on a recipe it has not reached.
	 *
	 * @param int[] $action_ids Published actions in published recipes.
	 *
	 * @return array<int, array{async_mode: string, async_delay_unit: string}>
	 */
	private function legacy_async_rows( $action_ids ) {
		global $wpdb;

		if ( empty( $action_ids ) ) {
			return array();
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_key, pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = %s AND p.post_status = %s
				 WHERE pm.meta_key IN ( 'async_mode', 'async_delay_unit' )",
				AUTOMATOR_POST_TYPE_ACTION,
				'publish'
			),
			ARRAY_A
		);

		$wanted = array_fill_keys( $action_ids, true );
		$meta   = array();

		foreach ( (array) $rows as $row ) {
			$action_id = (int) $row['post_id'];
			if ( isset( $wanted[ $action_id ] ) ) {
				$meta[ $action_id ][ $row['meta_key'] ] = (string) $row['meta_value'];
			}
		}

		$output = array();

		foreach ( $meta as $action_id => $keys ) {
			$mode = trim( $keys['async_mode'] ?? '' );
			if ( '' === $mode ) {
				continue;
			}
			$output[ $action_id ] = array(
				'async_mode'       => $mode,
				'async_delay_unit' => trim( $keys['async_delay_unit'] ?? '' ),
			);
		}

		return $output;
	}

	/**
	 * Condition strings per published recipe: filter-condition modifiers, then the stored JSON on recipes the condition migrator has not reached.
	 *
	 * @return array<int, string[]>
	 */
	private function conditions_by_recipe() {

		$map = array();

		foreach ( automator_get_block_modifier_store()->rows_in_published_recipes( Block_Type::FILTER ) as $row ) {
			$map[ $row['recipe_id'] ][] = $this->integration_name( $row['integration_code'] ) . '/' . $row['code'];
		}

		$migrated = array();
		foreach ( $this->block_rows( Block_Type::FILTER ) as $row ) {
			if ( '' !== $row['legacy_group_id'] ) {
				$migrated[ $row['recipe_id'] ][ $row['legacy_group_id'] ] = true;
			}
		}

		foreach ( $this->legacy_conditions_rows() as $recipe_id => $json ) {
			foreach ( $this->legacy_conditions( $json ) as $group ) {

				// A group whose filter block already exists is a pass the migrator left before
				// moving the JSON. The modifier rows above already counted it.
				if ( isset( $migrated[ $recipe_id ][ $group['id'] ] ) ) {
					continue;
				}

				foreach ( $group['conditions'] as $condition ) {
					$map[ $recipe_id ][] = $this->integration_name( $condition['integration'] ) . '/' . $condition['code'];
				}
			}
		}

		return $map;
	}

	/**
	 * Non-empty `actions_conditions` JSON per published recipe.
	 *
	 * The condition migrator moves this key to `_legacy_actions_conditions`, so a hit is a
	 * recipe it has not reached. The join bounds the read to published recipes.
	 *
	 * @return array<int, string>
	 */
	private function legacy_conditions_rows() {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} r ON r.ID = pm.post_id AND r.post_type = %s AND r.post_status = %s
				 WHERE pm.meta_key = 'actions_conditions' AND pm.meta_value != ''",
				AUTOMATOR_POST_TYPE_RECIPE,
				'publish'
			),
			ARRAY_A
		);

		$output = array();

		foreach ( (array) $rows as $row ) {
			$output[ (int) $row['post_id'] ] = (string) $row['meta_value'];
		}

		return $output;
	}

	/**
	 * The groups of a legacy `actions_conditions` document, each with the id the condition migrator would stamp on its filter block (`actions_conditions[i].id`, else `group-<index>` where the index is the raw key of the decoded document, as the migrator uses it) and its conditions' integration and condition codes. Names are not read from the document.
	 *
	 * @param string $json Stored JSON.
	 *
	 * @return array<int, array{id: string, conditions: array<int, array{integration: string, code: string}>}>
	 */
	public function legacy_conditions( $json ) {

		$groups = json_decode( $json, true );

		if ( ! is_array( $groups ) ) {
			return array();
		}

		$output = array();

		foreach ( $groups as $index => $group ) {

			if ( ! is_array( $group ) || ! is_array( $group['conditions'] ?? null ) ) {
				continue;
			}

			$conditions = array();

			foreach ( $group['conditions'] as $condition ) {

				$integration = is_array( $condition ) ? trim( (string) ( $condition['integration'] ?? '' ) ) : '';
				$code        = is_array( $condition ) ? trim( (string) ( $condition['condition'] ?? '' ) ) : '';

				if ( '' === $integration || '' === $code ) {
					continue;
				}

				$conditions[] = array(
					'integration' => $integration,
					'code'        => $code,
				);
			}

			// The id the condition migrator stamps on the group's filter block, with its fallback.
			$id = (string) ( $group['id'] ?? '' );

			$output[] = array(
				'id'         => '' !== $id ? $id : 'group-' . $index,
				'conditions' => $conditions,
			);
		}

		return $output;
	}

	/**
	 * Count one item under its integration in `integrations`, `integrations_array` and the matching `total_*` counter.
	 *
	 * @param array  $meta Item identity: `integration`, `integration_name` and `code`.
	 * @param string $type Report item type: triggers or actions.
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
	 * Stamp the report with the UTC calendar fields of the send.
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
	 * Add the collected page views and events to the report as `stats`.
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
	 * Group the template library search and import events by term, with a count for each.
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
	 * Count each review banner's events and add the non-zero ones to the report.
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
	 * Build the report, POST it to the Usage Reports Service, and clear the stats option once it is accepted.
	 *
	 * @return bool
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
	 * Count a view of one of the tracked admin screens, passing the views directory through unchanged.
	 *
	 * @param string $views_directory Directory the view is loaded from.
	 * @param string $file_name       View file path relative to that directory.
	 *
	 * @return string
	 */
	public function count_integrations_view( $views_directory, $file_name ) {

		$s = DIRECTORY_SEPARATOR;

		$views_to_count = array(
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
	 * Add one view of an admin page to the stats option, per user and in total.
	 *
	 * @param string $page Page label the view is counted under.
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
	 * @param object $settings_page Premium integration settings page; its get_name() labels the view.
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
	 * @param string $since Window start, site-local `Y-m-d H:i:s`.
	 *
	 * @return int
	 */
	private function get_boundary_recipe_log_id( $since ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->recipe;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(ID) FROM `' . esc_sql( $table ) . '` WHERE date_time >= %s', $since ) );

		// No run STARTED in the window. That does not mean nothing happened —
		// a delayed or background action belonging to an older run can still
		// have written rows today. Fall back to the newest run that exists,
		// pulled back by the same lookback, rather than to a boundary that
		// matches nothing.
		if ( null === $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
			$id = $wpdb->get_var( 'SELECT MAX(ID) FROM `' . esc_sql( $table ) . '`' );
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
	 * @param string $since Window start, site-local `Y-m-d H:i:s`.
	 *
	 * @return array
	 */
	private function count_recipe_statuses( $since ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->recipe;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT completed, COUNT(*) AS total FROM `' . esc_sql( $table ) . '` WHERE date_time >= %s GROUP BY completed', $since ), ARRAY_A );

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
	 * @param int    $boundary Lowest recipe log id the scan starts from.
	 * @param string $since Window start, site-local `Y-m-d H:i:s`.
	 *
	 * @return array
	 */
	private function count_action_statuses( $boundary, $since ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->action;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT completed, COUNT(*) AS total FROM `' . esc_sql( $table ) . '` WHERE automator_recipe_log_id >= %d AND date_time >= %s GROUP BY completed', $boundary, $since ), ARRAY_A );

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
	 * @param int    $boundary Lowest recipe log id the scan starts from.
	 * @param string $since Window start, site-local `Y-m-d H:i:s`.
	 *
	 * @return array
	 */
	private function count_errors( $boundary, $since ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->error_log;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT error_code, item_type, COUNT(*) AS total FROM `' . esc_sql( $table ) . '` WHERE recipe_log_id >= %d AND date_time >= %s GROUP BY error_code, item_type ORDER BY total DESC LIMIT %d', $boundary, $since, self::MAX_ERROR_CODES ), ARRAY_A );

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE completed = %d AND date_time < %s', Automator_Status::IN_PROGRESS, $before ) );

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
	 * @param int $window Window length in seconds.
	 *
	 * @return int
	 */
	private function count_throttled_runs( $window ) {
		global $wpdb;

		$table = $wpdb->prefix . Automator()->db->tables->recipe_throttle;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . esc_sql( $table ) . '` WHERE last_run >= %d', time() - $window ) );

		return absint( $count );
	}

	/**
	 * Attach a stable, UNTRANSLATED name to each status code.
	 *
	 * Automator_Status::name() returns a translated label, which would make the
	 * value depend on the reporting site's locale. The code is the real key; the
	 * name is a convenience for anyone reading a row by hand.
	 *
	 * @param array $rows Rows carrying `completed` and `total`.
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
	 * Published blocks by type in published recipes.
	 *
	 * @return void
	 */
	public function get_blocks_info() {

		foreach ( array( Block_Type::FILTER, Block_Type::DELAY_SCHEDULE, Block_Type::LOOP ) as $block_type ) {
			$this->report['blocks'][ $block_type ] = count( $this->block_rows( $block_type ) );
		}
	}

	/**
	 * Loops grouped by what they iterate — users, posts, tokens.
	 *
	 * @return array
	 */
	private function count_loop_types() {

		$types = array();

		foreach ( $this->block_rows( Block_Type::LOOP ) as $row ) {

			$type = $row['iterable_expression']['type'] ?? '';

			if ( ! is_string( $type ) || '' === $type ) {
				continue;
			}

			$types[ $type ] = ( $types[ $type ] ?? 0 ) + 1;
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
	 * Which loop filters are in use, by integration and code, most used first.
	 *
	 * @return array
	 */
	private function count_loop_filters() {

		$counts = automator_get_block_modifier_store()->count_usage_by_parent_block_type( Block_Type::LOOP );

		arsort( $counts );

		$output = array();

		foreach ( $counts as $key => $count ) {

			list( $integration, $code ) = array_pad( explode( ':', $key, 2 ), 2, '' );

			$output[] = array(
				'code'        => (string) $code,
				'integration' => (string) $integration,
				'count'       => (int) $count,
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
	 * Computed from recipe_items, which process_recipe() has already
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
	 * Bucket label for a recipe's action count.
	 *
	 * @param int $actions Number of actions in the recipe, at least one.
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
	 * @param int[] $buckets Counts keyed by bucket label.
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table joined to core posts; no WordPress API returns this, and the read must be live.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				WHERE p.post_type = %s
				  AND p.post_status = 'publish'
				  AND NOT EXISTS ( SELECT 1 FROM `" . esc_sql( $log ) . '` l WHERE l.automator_recipe_id = p.ID )',
				AUTOMATOR_POST_TYPE_RECIPE
			)
		);

		$this->report['recipes']['never_run_count'] = absint( $count );
	}

	/**
	 * How long people actually delay for.
	 *
	 * delayed_actions_count says how many delay points exist; the unit says
	 * whether the feature is used for a 5-minute nudge or a 30-day follow-up,
	 * which are different products with the same switch.
	 *
	 * @return void
	 */
	public function get_delay_units() {

		$units = array();

		foreach ( $this->delay_points() as $point ) {
			if ( '' === $point['unit'] ) {
				continue;
			}
			$units[ $point['unit'] ] = ( $units[ $point['unit'] ] ?? 0 ) + 1;
		}

		$output = array();

		foreach ( $units as $unit => $count ) {
			$output[] = array(
				'unit'  => (string) $unit,
				'count' => (int) $count,
			);
		}

		$this->report['delays'] = $output;
	}

	/**
	 * Add the reported plugin settings, then the connected AI providers.
	 *
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
			$settings_to_report['Auto-prune activity logs']        = empty( Action_Scheduler::next_scheduled_action( 'uapro_auto_purge_logs' ) ) ? 'Disabled' : 'Enabled';
			$settings_to_report['Auto-prune activity logs (days)'] = (int) automator_get_option( 'uap_automator_purge_days', 0 );
		}

		$this->report['settings'] = $settings_to_report;

		$this->get_ai_providers();
	}

	/**
	 * The Enabled/Disabled convention every existing settings chart groups by.
	 *
	 * @param mixed $enabled Setting value, read as a boolean.
	 *
	 * @return string
	 */
	private function toggle_label( $enabled ) {
		return $enabled ? 'Enabled' : 'Disabled';
	}

	/**
	 * A chart-safe label for a free-text setting that may be unset.
	 *
	 * @param mixed $value Stored setting value.
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
