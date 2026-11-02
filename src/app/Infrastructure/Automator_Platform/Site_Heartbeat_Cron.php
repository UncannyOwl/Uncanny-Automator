<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;
use function Uncanny_Automator\App\Infrastructure\automator_site_credentials_store;

/**
 * Pings `/app/v1/sites/alive` every 6 hours so the platform can track which
 * sites are actively running the plugin.
 *
 * Skips when no license key is present — free / unlicensed sites don't
 * register. Sends no `alive` while the site holds no signing secret: the call
 * is site-signed (recipe R1), and an unsigned one is only a `401` the platform
 * logs as a signature gap; registration, not the heartbeat, gets the site a
 * secret. The capability refresh still runs then — `GET /app/v1/integrations`
 * is answered on the license alone.
 *
 * The platform uses the license_key + site_name + timestamp to mark the site
 * as alive; the only thing consumed from the response is the
 * `site_uuid` the platform echoes back (the rest of the credentials channel
 * is owned by {@see Platform_Site_Registration}). A `404 SITE_NOT_REGISTERED`
 * or `401 VERIFICATION_ERROR` answer is heard on `http_response` by
 * {@see Site_Credentials_Rejection_Observer}, like every platform-plane
 * response, so the next registration tick re-registers the site.
 *
 * @since 8.0.0
 */
final class Site_Heartbeat_Cron {

	/**
	 * WP-Cron hook name. Public so work that rides the same 6-hour cadence (the
	 * connection sync) can listen on it.
	 */
	public const CRON_HOOK = 'automator_platform_site_heartbeat';

	/**
	 * Cron interval name (custom, registered by this class).
	 */
	private const INTERVAL_NAME = 'automator_every_six_hours';

	/**
	 * Interval length.
	 */
	private const INTERVAL_SECONDS = 6 * HOUR_IN_SECONDS;

	/**
	 * @var object|null
	 */
	private $http;

	/**
	 * @var Capability_Discovery|null
	 */
	private $capabilities;

	/**
	 * @var License_Provider_Interface|null
	 */
	private $license;

	/**
	 * @var Site_Credentials_Store|null
	 */
	private $store;

	/**
	 * @param object|null                     $http         Transport. Defaults to {@see Platform_Http}.
	 * @param Capability_Discovery|null       $capabilities Capability refresh piggybacked on the ping.
	 * @param License_Provider_Interface|null $license      License provider. Defaults to the license manager.
	 * @param Site_Credentials_Store|null     $store        Credentials store the alive `site_uuid` lands in.
	 */
	public function __construct( ?object $http = null, ?Capability_Discovery $capabilities = null, ?License_Provider_Interface $license = null, ?Site_Credentials_Store $store = null ) {

		$this->http         = $http ?? new Platform_Http();
		$this->capabilities = $capabilities;
		$this->license      = $license;
		$this->store        = $store;
	}

	/**
	 * Register cron wiring. Idempotent.
	 *
	 * @return void
	 */
	public function register(): void {

		add_filter( 'cron_schedules', array( $this, 'ensure_interval' ) );
		add_action( 'init', array( $this, 'schedule_event' ) );
		add_action( self::CRON_HOOK, array( $this, 'ping' ) );
	}

	/**
	 * @param array<string, array{interval:int,display:string}> $schedules
	 *
	 * @return array<string, array{interval:int,display:string}>
	 */
	public function ensure_interval( array $schedules ): array {

		if ( ! isset( $schedules[ self::INTERVAL_NAME ] ) ) {
			$schedules[ self::INTERVAL_NAME ] = array(
				'interval' => self::INTERVAL_SECONDS,
				'display'  => esc_html__( 'Every Six Hours', 'uncanny-automator' ),
			);
		}

		return $schedules;
	}

	/**
	 * @return void
	 */
	public function schedule_event(): void {

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::INTERVAL_NAME, self::CRON_HOOK );
		}
	}

	/**
	 * POST the heartbeat. Cron callback.
	 *
	 * @return void
	 */
	public function ping(): void {

		$license     = $this->license ?? automator_license_manager();
		$license_key = null === $license ? '' : $license->get_key();
		if ( '' === $license_key ) {
			return;
		}

		if ( null === $this->http || ! method_exists( $this->http, 'post' ) ) {
			return;
		}

		$store = $this->store ?? automator_site_credentials_store();
		if ( null !== $store && $store->has() ) {
			$this->send_alive( $store );
		}

		// E.1 — Capability_Discovery piggyback. One round trip per 6h cycle
		// keeps the per-integration capability matrix fresh on the same
		// cadence as the alive ping. Refresh failure is non-fatal: stale
		// transient data survives until the next tick.
		$capabilities = $this->capabilities ?? new Capability_Discovery();
		$capabilities->refresh();
	}

	/**
	 * POST `alive`, signed with the secret on file, and adopt the `site_uuid`
	 * a 200 echoes.
	 *
	 * @since 8.0.0
	 *
	 * @param Site_Credentials_Store $store Holds the secret the request is signed with; the `site_uuid` lands in it.
	 *
	 * @return void
	 */
	private function send_alive( Site_Credentials_Store $store ): void {

		// `license-key` / `site-name` / `item-name` are auto-injected by
		// Platform_Headers_Injector on `http_request_args` for any URL
		// matching AUTOMATOR_API_URL — no manual header construction
		// needed here.
		$response = (array) $this->http->post( $this->alive_url(), array(), array() );

		automator_update_option( 'automator_platform_last_heartbeat_at', time() );

		// Site identity spec Phase 0 — every live site learns its platform
		// site_uuid within one heartbeat cycle, without re-registering. Only a
		// 200 envelope is an answer: an error body can still carry a `data`
		// bag, and adopting a uuid out of one would overwrite a good slot with
		// whatever a failing edge returned. Same discipline as the register
		// dispatch.
		$is_ok     = 200 === ( isset( $response['statusCode'] ) ? (int) $response['statusCode'] : 0 );
		$payload   = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
		$site_uuid = isset( $payload['site_uuid'] ) ? (string) $payload['site_uuid'] : '';

		if ( $is_ok && '' !== $site_uuid && $site_uuid !== (string) $store->site_uuid() ) {
			$store->store( array( 'site_uuid' => $site_uuid ) );
		}
	}

	/**
	 * @return string
	 */
	private function alive_url(): string {

		$base = defined( 'AUTOMATOR_API_URL' )
			? rtrim( (string) AUTOMATOR_API_URL, '/' )
			: 'https://api.automatorplugin.com';

		return (string) Dispatcher::filter(
			'automator_platform_alive_url',
			$base . AUTOMATOR_PLATFORM_API_PATH . '/sites/alive'
		);
	}
}
