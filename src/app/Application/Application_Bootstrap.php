<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Application;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Completion_Schedule;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Backoff;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Outcomes;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Site_Address_Drift;
use Uncanny_Automator\App\Presentation\Admin\Site_Restart_Reconnect_Notice;
use Uncanny_Automator\App\Infrastructure\Connections\Restart_Disconnections;
use Uncanny_Automator\App\Application\Services_Bootstrap;
use Uncanny_Automator\App\Infrastructure\Async_Jobs\Inbox_Drainer;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\BackCompat;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Client_Timeout_Header_Injector;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Clock_Skew_Monitor;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Connect_Timeout_Injector;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Default_Timeout;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Headers_Injector;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Request_Signature_Injector;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Challenge_Controller;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Rejection_Observer;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_License_Refusal_Observer;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Webhooks\Integrations_Webhook_Controller;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Webhooks\Receiver_Header;
use Uncanny_Automator\App\Presentation\Admin\App_Connections_Actions;
use Uncanny_Automator\App\Presentation\Admin\Ai_Intro_Notice;
use Uncanny_Automator\App\Presentation\Admin\App_Credits_Notice;
use Uncanny_Automator\App\Presentation\Admin\Cache_Flushed_Notice;
use Uncanny_Automator\App\Presentation\Admin\Ai_Tool_Use_Limit_Notice;
use Uncanny_Automator\App\Presentation\Admin\App_Platform_Status_Tab;
use Uncanny_Automator\App\Presentation\Admin\Background_App_Actions_Tab;
use Uncanny_Automator\App\Presentation\Admin\Load_Errors_Notice;
use Uncanny_Automator\App\Presentation\Admin\Log_Size_Notice;
use Uncanny_Automator\App\Presentation\Admin\Clock_Skew_Notice;
use Uncanny_Automator\App\Presentation\Admin\Platform_Upgrade_Required_Notice;
use Uncanny_Automator\App\Presentation\Admin\Pro_Version_Notice;
use Uncanny_Automator\App\Presentation\Admin\Recipe_Import_Notice;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Notices\Loop_Not_Migrated_Notice;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Notices\Recipe_Imported_Notice;
use Uncanny_Automator\App\Presentation\Admin\Review_Notice;
use Uncanny_Automator\App\Presentation\Admin\Site_Address_Drift_Notice;
use Uncanny_Automator\App\Presentation\Admin\Site_Copy_Connections_Notice;
use Uncanny_Automator\App\Presentation\Admin\Registration_Recheck_Control;
use Uncanny_Automator\App\Presentation\Admin\Registration_Recheck_Outcome_Notice;
use Uncanny_Automator\App\Presentation\Admin\Site_Moved_Notice;
use Uncanny_Automator\App\Presentation\Admin\Site_Registration_Status_Notice;
use Uncanny_Automator\App\Presentation\Admin\Site_Reverification_Notice;
use Uncanny_Automator\App\Presentation\Admin\Default_Connection_Chooser;
use Uncanny_Automator\App\Presentation\Admin\Switched_Off_Connection_Hint;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Custom_Item_Name_Field;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Node_Tests_Bootstrap;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Schema_Bootstrap;
use Uncanny_Automator\App\Presentation\Recipe_View\Recipe_View_Preference_Service;
use Uncanny_Automator\App\Events\Recipe_Change_Record;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Bootstrap;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Bootstrap;
use Uncanny_Automator\App\Transports\Restful\Restful_Bootstrap;
use Uncanny_Automator\App\Transports\Abilities\Automator_Abilities;

use function Uncanny_Automator\App\Infrastructure\automator_connection_adoption;
use function Uncanny_Automator\App\Infrastructure\automator_connection_error_handler;
use function Uncanny_Automator\App\Infrastructure\automator_connection_registry;
use function Uncanny_Automator\App\Infrastructure\automator_connection_sync;
use function Uncanny_Automator\App\Infrastructure\automator_credential_lift_observer;
use function Uncanny_Automator\App\Infrastructure\automator_disconnect_service;
use function Uncanny_Automator\App\Infrastructure\automator_legacy_upgrade_observer;
use function Uncanny_Automator\App\Infrastructure\automator_file_serve_controller;
use function Uncanny_Automator\App\Infrastructure\automator_platform_connections_api;
use function Uncanny_Automator\App\Infrastructure\automator_platform_hmac_validator;
use function Uncanny_Automator\App\Infrastructure\automator_platform_site_registration;
use function Uncanny_Automator\App\Infrastructure\automator_site_credentials_store;
use function Uncanny_Automator\App\Infrastructure\automator_site_heartbeat_cron;
use function Uncanny_Automator\App\Infrastructure\automator_webhook_router;

/**
 * Application Bootstrap.
 *
 * Central bootstrap class for all API applications.
 * Initializes services, MCP and RESTful transport layers, and wires the
 * platform-alignment components (T1–T10, T12) that ship as of this release.
 *
 * T8 (Recipe_Runner resume_after_callback) and T13 (Background_Actions
 * per-connection gate) have their own wiring added when those tasks land.
 *
 * @since 7.0
 */
class Application_Bootstrap {

	/**
	 * WP-Cron hook fired daily for maintenance — currently prunes the
	 * expired previous signing secret once the 24h rotation grace has
	 * elapsed. Owned by the platform alignment work (T11 wiring).
	 */
	private const CRON_PRUNE_HOOK = 'automator_platform_daily_maintenance';

	/**
	 * Initialize all API applications.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function init() {

		// Register back-compat aliases + hook forwarders BEFORE any service
		// or integration loads — third-party code may reference the legacy
		// Api_Client FQCNs at autoload time.
		BackCompat::register();

		// Initialize services layer (hooks, legacy compatibility).
		( new Services_Bootstrap() )->init();

		Node_Tests_Bootstrap::register();
		Background_Bootstrap::register();

		// Initialize transport layers.
		( new Mcp_Bootstrap() )->init();
		( new Restful_Bootstrap() )->init();
		( new Automator_Abilities() )->init();

		$this->wire_recipe_schema_bootstrap();
		$this->wire_recipe_change_record();
		$this->wire_recipe_view_default();
		$this->wire_field_registry_cache_invalidation();
		$this->wire_custom_item_name_token_parser();
		$this->wire_recipe_notices();

		// Platform alignment wiring (T1–T10, T12).
		$this->wire_platform_headers_injector();
		$this->wire_platform_webhooks();
		// Durable ingress drain. It consumes vendor_event rows and the
		// async_completion rows of background app actions.
		$this->wire_platform_inbox_drainer();
		$this->wire_crons();
		$this->wire_daily_maintenance();
		$this->wire_connect_timeout_injector();
		$this->wire_platform_default_timeout();
		$this->wire_connection_error_handler();
		$this->wire_credential_lift_observer();
		$this->wire_legacy_upgrade_observer();
		$this->wire_site_registration();
		$this->wire_connection_sync();
		$this->wire_site_registration_notices();
		$this->wire_app_connections_admin();
		$this->wire_background_app_actions_admin();
		$this->wire_notice_center();
		$this->wire_file_serve();
	}

	/**
	 * Register the Recipe_Schema_Bootstrap recipe-save subscribers so the
	 * cached schema projection rebuilds on every recipe write.
	 *
	 * @return void
	 */
	private function wire_recipe_schema_bootstrap(): void {

		( new Recipe_Schema_Bootstrap() )->register();
	}

	/**
	 * Let Recipe_Change_Record note every status change of a recipe or one of its
	 * posts, so the fan-out after `recipe_changed` can report it.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function wire_recipe_change_record(): void {

		add_action( 'transition_post_status', array( Recipe_Change_Record::class, 'note_status' ), 10, 3 );
	}

	/**
	 * Let the recipe view preference settle a person's default view when
	 * they create a recipe and have none settled yet. Creating a recipe
	 * inserts it before the builder first reads the preference, so the read
	 * alone would count the new recipe as one the person already had.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function wire_recipe_view_default(): void {

		add_action( 'save_post_' . AUTOMATOR_POST_TYPE_RECIPE, array( Recipe_View_Preference_Service::class, 'on_recipe_saved' ), 10, 3 );
	}

	/**
	 * Wire Field_Registry_Service per-request cache invalidation to the
	 * `automator_recipe_completed` action so long-running workers (WP-CLI,
	 * Action Scheduler) don't accumulate materialized field-config arrays
	 * across recipes.
	 *
	 * Mirrors the legacy Fields_Shared_Callable::clear() lifecycle.
	 */
	private function wire_field_registry_cache_invalidation(): void {
		add_action(
			'automator_recipe_completed',
			array( Field_Registry_Service::instance(), 'clear_cache' )
		);
	}

	/**
	 * Resolve the global custom-label token for triggers.
	 *
	 * The field is declared on every item, so the token resolver publishes a
	 * chip per named item and something has to answer it. Registered at
	 * PHP_INT_MAX so this runs last and owns the value for its own token; it
	 * passes through anything that is not the trigger form, so it never
	 * overrides another handler's answer for a token it does not own.
	 */
	private function wire_custom_item_name_token_parser(): void {
		add_filter(
			'automator_maybe_parse_token',
			array( Custom_Item_Name_Field::class, 'parse_token' ),
			PHP_INT_MAX,
			2
		);
	}

	/**
	 * Phase 2 Task 5a — register the signed-fetch endpoint that the
	 * platform GETs/HEADs to stream source bytes for upload-class
	 * actions. Listens on `init` priority 1 for `?automator_serve=1`;
	 * any other request short-circuits cheaply.
	 *
	 * @return void
	 */
	private function wire_file_serve(): void {

		automator_file_serve_controller()->register_hooks();
	}

	/**
	 * Attach the Legacy_Upgrade_Observer as a passive observer on
	 * `automator_platform_response` (MVP plan G.7, Contract H §"v3
	 * promotion envelope"). Detects the post-promotion envelope and
	 * UPDATEs the local `legacy_*` row in place to the new v3 shape.
	 *
	 * Spec-only on platform side in MVP per the plan's scope note —
	 * Drive + Dropbox are greenfield /v3, no /v2 → /v3 promotion
	 * traffic in this rollout. Observer is harmless until traffic
	 * exists (no envelope → no-op).
	 *
	 * @return void
	 */
	private function wire_legacy_upgrade_observer(): void {

		automator_legacy_upgrade_observer()->register();
	}

	/**
	 * Attach the Credential_Lift_Observer as a passive observer on
	 * `automator_platform_response` (MVP plan C.1, Contract H).
	 *
	 * On every /v2/{integration} response carrying
	 * `X-Automator-Credentials-Lifted: 1`, the observer upserts a
	 * `wp_uap_connections` row keyed on UNIQUE(integration, identity_id)
	 * and wipes the integration's pre-lift credentials option. Idempotent
	 * — repeats on every post-lift response are no-ops.
	 *
	 * Live activation: platform tasks P2.4 (lazy-lift) + P3.8 (signal
	 * headers) shipped 2026-04-29 and gate emission on
	 * `Item-Version >= min_plugin_version` (config 7.5.0). Observer is
	 * harmless before that — every non-lift response misses the header
	 * check and short-circuits.
	 *
	 * @return void
	 */
	private function wire_credential_lift_observer(): void {

		automator_credential_lift_observer()->register();
	}

	/**
	 * Plugin-initiated site registration (MVP plan A.3, Contract A).
	 * Idempotent — `register_if_needed()` no-ops when a signing_secret
	 * is already on file, and respects a 60s backoff on failure. Hooked
	 * on `init`, but acts only on wp-admin page loads and WP-Cron (see
	 * Platform_Site_Registration::register_on_request()) — a visitor's
	 * request never makes the blocking platform call.
	 *
	 * Replaces the killed Credentials_Handshake_Service flow (A.6).
	 *
	 * @return void
	 */
	private function wire_site_registration(): void {

		$run = static function (): void {
			$service = automator_platform_site_registration();
			if ( null === $service ) {
				return;
			}
			$service->register_if_needed();
		};

		// Steady-state path: `init` fires on every request, but
		// register_on_request() only acts on wp-admin page loads and WP-Cron —
		// never a visitor's page load or admin-ajax. Registration is a blocking
		// platform round trip that is only needed before the first app
		// connection (a wp-admin action), so the front end must never pay for
		// it. There, the license-active gate short-circuits when status is not
		// yet 'valid' and the persisted-secret check short-circuits once the
		// site is registered — an O(1) option read in the common case.
		add_action(
			'init',
			static function (): void {
				$service = automator_platform_site_registration();
				if ( null !== $service ) {
					$service->register_on_request();
				}
			},
			20
		);

		// Activation path: listen on the per-key action the
		// `Automator_Options::update_option()` layer emits on every
		// write (`automator_update_option_{$key}`). Universal
		// coverage — fires for Pro, free, ops scripts, EDD callbacks,
		// any caller that goes through the option layer. The plugin's
		// custom `uap_options` table doesn't trigger WordPress's
		// `update_option_*` hooks, but its own option layer does
		// emit a parallel set, which is what we hook here.
		//
		// register_if_needed() is idempotent: short-circuits when a
		// signing_secret is already on file or when license status
		// isn't yet 'valid'. So firing on every write is safe — only
		// the actual transition writes a registration call.
		// A license activated in wp-admin is an admin action (handoff R2): a
		// 202 is completed within that request on the attempt's ladder. A
		// write from WP-Cron or a remote callback keeps the cron-driven
		// completion — no one is waiting on it.
		$on_license_status_write = static function ( $key, $value ) use ( $run ): void {
			if ( ! is_string( $value ) || 'valid' !== $value ) {
				return;
			}
			$service = automator_platform_site_registration();
			if ( null === $service ) {
				return;
			}
			( new Registration_Backoff() )->clear_backoff();
			if ( is_admin() && ! wp_doing_cron() ) {
				$service->confirm();
				return;
			}
			$run();
		};

		add_action( 'automator_update_option_uap_automator_pro_license_status', $on_license_status_write, 10, 2 );
		add_action( 'automator_update_option_uap_automator_free_license_status', $on_license_status_write, 10, 2 );

		// Completion path: a first registration that answered 202 schedules
		// this single event on the attempt's own poll ladder
		// (Completion_Schedule::sync()). WP-Cron only
		// spawns when an event is due, and the nearest other events are 15
		// minutes and 6 hours out while the attempt lives 600 seconds — so
		// without it the handshake finishes only if an admin happens to load
		// another page. The connect button is a REST request, which the
		// `init@20` entry point deliberately skips.
		add_action( Completion_Schedule::CRON_COMPLETE, $run );
	}

	/**
	 * Staging copies and connection sync — the platform's list of this site's
	 * connections is the truth the local rows are rebuilt from (R5), and a
	 * staging copy attaches to the live site's accounts when its admin chose
	 * that (R3). Both listen on every request: their retries are cron events,
	 * and the moments they follow (a recovery, the end of a copy, the
	 * heartbeat, an out-of-sync platform answer) happen on any request.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function wire_connection_sync(): void {

		automator_connection_sync()->register();
		automator_connection_adoption()->register();
		// L247 — a local site's restart takes its own connections down and
		// lists the apps to connect again (connection sync never does).
		( new Restart_Disconnections( automator_connection_registry() ) )->register();
	}

	/**
	 * The recipe notices every recipe wire read carries. Registered on every
	 * request: the builder's REST calls and MCP are not admin requests.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function wire_recipe_notices(): void {
		automator_recipe_notice_center()->add( new Recipe_Imported_Notice() );
		automator_recipe_notice_center()->add( new Loop_Not_Migrated_Notice() );
	}

	/**
	 * The one notice center every Automator admin notice runs through.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function wire_notice_center(): void {

		if ( ! is_admin() ) {
			return;
		}

		automator_notice_center()->register();
		automator_notice_center()->add( new Ai_Intro_Notice() );
		automator_notice_center()->add( new Ai_Tool_Use_Limit_Notice() );
		automator_notice_center()->add( new Log_Size_Notice() );
		automator_notice_center()->add( new Recipe_Import_Notice() );
		automator_notice_center()->add( new Load_Errors_Notice() );
		automator_notice_center()->add( new Pro_Version_Notice() );
		automator_notice_center()->add( new App_Credits_Notice() );
		automator_notice_center()->add( new Cache_Flushed_Notice() );
		automator_notice_center()->add( new Review_Notice() );
	}

	/**
	 * Settings > Advanced > Background app actions: the switch's own tab.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function wire_background_app_actions_admin(): void {

		if ( ! is_admin() ) {
			return;
		}

		( new Background_App_Actions_Tab() )->register();
	}

	/**
	 * The admin screens of the same work: the copy notice, the App Platform
	 * status tab (registration, sharing switch, copy state, Repair), the hint
	 * on an app's settings page, and the answers they post.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function wire_app_connections_admin(): void {

		if ( ! is_admin() ) {
			return;
		}

		$registration = automator_platform_site_registration();
		$disconnect   = automator_disconnect_service();
		if ( null === $registration || null === $disconnect ) {
			return;
		}

		$actions = new App_Connections_Actions(
			automator_connection_adoption(),
			automator_connection_sync(),
			automator_platform_connections_api(),
			automator_connection_registry(),
			$disconnect
		);
		$actions->register();

		( new App_Platform_Status_Tab( $actions, automator_connection_adoption(), automator_platform_connections_api(), automator_connection_registry(), $registration, new Registration_Recheck_Control( $registration, automator_connection_sync() ) ) )->register();
		automator_notice_center()->add( new Site_Copy_Connections_Notice( $actions, automator_connection_adoption(), new Site_Address_Drift() ) );
		( new Switched_Off_Connection_Hint( automator_connection_registry() ) )->register();
		// Two or more accounts, none chosen: the app's settings page asks the
		// admin to choose one, while Multiple_Connections_Feature is on (not in
		// 8.0); this takes the choice.
		( new Default_Connection_Chooser( automator_connection_registry() ) )->register();
		automator_notice_center()->add( new Site_Restart_Reconnect_Notice( new Restart_Disconnections( automator_connection_registry() ) ) );
	}

	/**
	 * The wp-admin notices about this site's registration with the platform.
	 *
	 * Site identity spec §8 — production URL drift is asked, never assumed. The
	 * platform's other "a human must act" message (the plugin is too old), and
	 * the registration being re-verified because the callback receiver refused
	 * a delivery (callback delivery contract §6), share the surface and the owner.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function wire_site_registration_notices(): void {
		if ( ! is_admin() ) {
			return;
		}
		$registration = automator_platform_site_registration();
		if ( null === $registration ) {
			return;
		}
		$drift = new Site_Address_Drift_Notice( $registration, automator_site_credentials_store() );
		$drift->register();
		automator_notice_center()->add( $drift );
		automator_notice_center()->add( new Platform_Upgrade_Required_Notice() );
		automator_notice_center()->add( new Site_Reverification_Notice( $registration ) );
		// Handoff R1 — where the registration stands, when an attempt failed or
		// connections are stuck out of sync, with the one repair control.
		$recheck = new Registration_Recheck_Control( $registration, automator_connection_sync() );
		$recheck->register();
		automator_notice_center()->add( new Site_Registration_Status_Notice( $registration, automator_connection_sync(), $recheck ) );
		automator_notice_center()->add( new Registration_Recheck_Outcome_Notice( $recheck ) );
		automator_notice_center()->add( new Site_Moved_Notice( new Registration_Outcomes() ) );
	}

	/**
	 * Attach the Connection_Error_Handler as a passive observer on
	 * `automator_platform_response` (plan-P WI11). The observer
	 * inspects every platform answer, pulls the outbound Request's
	 * connection_id, and applies what the answer's error.type does to the
	 * stored connection (platform answers contract 4.3): IDENTITY_REVOKED
	 * marks it revoked, SCOPE_INSUFFICIENT marks it for re-authorization,
	 * and the three CONNECTION_* types leave it untouched and fire
	 * `automator_platform_connection_out_of_sync` for connection sync.
	 * Every other answer changes nothing.
	 *
	 * @return void
	 */
	private function wire_connection_error_handler(): void {

		automator_connection_error_handler()->register();
	}

	/**
	 * Attach CURLOPT_CONNECTTIMEOUT to platform-bound HTTP requests so
	 * a dead connection fails in seconds instead of pinning a worker
	 * for the full 90s response timeout (plan-P WI4).
	 *
	 * @return void
	 */
	private function wire_connect_timeout_injector(): void {

		( new Connect_Timeout_Injector() )->register();
	}

	/**
	 * Give a platform-bound request that names no timeout the platform's
	 * default instead of WordPress's five seconds. (Formerly registered by
	 * Api_Server's constructor, and so only once that class had loaded.)
	 *
	 * @return void
	 */
	private function wire_platform_default_timeout(): void {

		( new Platform_Default_Timeout() )->register();
	}

	/**
	 * Register the platform integrations webhook controller + router routes
	 * on `rest_api_init`. Deferred to that hook because WP-REST route
	 * registration must happen there (REST server exists; wp_rewrite is
	 * ready).
	 *
	 * MVP: only Integrations_Webhook_Controller is wired here. The
	 * platform-pushed lifecycle webhook (Platform_Webhook_Controller) was
	 * dropped per MVP plan A.6 — plugin-initiated POST /app/v1/sites/register
	 * (A.3) replaces it.
	 *
	 * Every answer of its route carries `X-Automator-Receiver` (callback
	 * delivery contract §4), added once on `rest_request_after_callbacks`.
	 *
	 * @return void
	 */
	private function wire_platform_webhooks(): void {

		( new Receiver_Header() )->register_hooks();

		add_action(
			'rest_api_init',
			static function (): void {
				$store     = automator_site_credentials_store();
				$validator = automator_platform_hmac_validator();
				$router    = automator_webhook_router();

				$router->register( new Integrations_Webhook_Controller( $store, $validator ) );
				$router->register_routes();

				// C1 dedicated pre-registration challenge endpoint (868jw791t) —
				// public route the platform issues a first-registration challenge
				// to; signs it with the local attempt secret.
				( new Registration_Challenge_Controller() )->register_routes();
			}
		);
	}

	/**
	 * Durable ingress drain — consumes `uap_platform_inbox` rows landed by
	 * Integrations_Webhook_Controller and re-fires the per-payload-type
	 * dispatch (`automator_{integration}_event_received` for vendor events;
	 * `automator_platform_async_completed` for async completions, which
	 * Background_Job_Completion_Subscriber answers). Listens on
	 * `automator_platform_inbox_drain`; the Stuck_Recipe_Recovery sweep
	 * re-fires that hook for crash-recovered rows.
	 *
	 * @return void
	 */
	private function wire_platform_inbox_drainer(): void {

		( new Inbox_Drainer() )->register_hooks();
	}

	/**
	 * Auto-inject license-identifying headers (`license-key`, `site-name`,
	 * `item-name`) on every outbound HTTP request whose URL begins with
	 * `AUTOMATOR_API_URL`. Centralizes what was previously copy-pasted
	 * into each direct platform caller (heartbeat, capability discovery).
	 *
	 * Filter priority is 9999 so any third-party header customization
	 * happens BEFORE the injector fills the blanks.
	 *
	 * The same `http_request_args` chokepoint also signs every site-signed
	 * request (`/app/v1` and `/v3`) and then declares every platform request's
	 * client timeout, both last (PHP_INT_MAX, in that order; the signature
	 * covers no header); `http_response` hears credential rejections, clock
	 * skew and replays.
	 *
	 * @return void
	 */
	private function wire_platform_headers_injector(): void {

		( new Platform_Headers_Injector() )->register_hooks();

		// Runs last on the same chokepoint: every site-signed request (`/app/v1/*`
		// and `/v3/*`) leaves with a per-site signature, whichever transport
		// sent it; a request altered after signing is not sent; a
		// REQUEST_REPLAYED answer is sent again once, signed afresh.
		( new Platform_Request_Signature_Injector() )->register_hooks();

		// Runs last on the same chokepoint: every platform request (/v2, /v3,
		// /app/v1) declares how long this client waits for the answer, read
		// from the final `timeout` arg, so the platform can refuse queued work
		// its client has already abandoned.
		( new Client_Timeout_Header_Injector() )->register_hooks();

		// The response side of the same chokepoint (`http_response`): when the
		// platform rejects this install's site credentials on any site-signed
		// response, the next registration tick re-registers (wire contract W4).
		( new Site_Credentials_Rejection_Observer() )->register_hooks();

		// Same chokepoint: the platform's refusal of this site's license is
		// remembered so the registration status tells the license first, and
		// forgotten once the platform accepts a licensed request (decision k).
		( new Site_License_Refusal_Observer() )->register_hooks();

		// Same chokepoint: a CLOCK_SKEW answer is measured for the admin, and
		// the measurement clears once a signed request is accepted again.
		$this->wire_clock_skew( new Clock_Skew_Monitor() );
	}

	/**
	 * Measure a clock the platform refused (CLOCK_SKEW) on every site-signed
	 * response, and tell the admin in wp-admin.
	 *
	 * @since 8.0.0
	 *
	 * @param Clock_Skew_Monitor $monitor The measurement's owner.
	 *
	 * @return void
	 */
	private function wire_clock_skew( Clock_Skew_Monitor $monitor ): void {

		$monitor->register_hooks();

		if ( is_admin() ) {
			automator_notice_center()->add( new Clock_Skew_Notice( $monitor ) );
		}
	}

	/**
	 * Register the heartbeat cron, which owns its own interval, hook and
	 * scheduling.
	 *
	 * @return void
	 */
	private function wire_crons(): void {

		automator_site_heartbeat_cron()->register();
	}

	/**
	 * Daily maintenance cron — prunes the expired previous signing secret
	 * once the 24h rotation grace has elapsed. Uses WP's built-in `daily`
	 * schedule so no custom interval registration is needed.
	 *
	 * @return void
	 */
	private function wire_daily_maintenance(): void {

		add_action(
			self::CRON_PRUNE_HOOK,
			static function (): void {
				automator_site_credentials_store()->prune_expired_previous();
			}
		);

		add_action(
			'init',
			static function (): void {
				if ( ! wp_next_scheduled( self::CRON_PRUNE_HOOK ) ) {
					wp_schedule_event( time(), 'daily', self::CRON_PRUNE_HOOK );
				}
			}
		);
	}
}
