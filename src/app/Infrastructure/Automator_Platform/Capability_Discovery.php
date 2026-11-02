<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * E.1 — Plugin-side capability discovery (Contract K).
 *
 * Fetches the platform's `GET /app/v1/integrations` once per heartbeat
 * cycle (6h piggyback off `Site_Heartbeat_Cron`); persists the per-
 * integration capability matrix to a transient. Plugin code reads the
 * cached row to gate UX (recipe builder triggers, settings-page toggles)
 * and dispatch behavior (D.4 dynamic polling cap).
 *
 * Wire shape per Contract K (P4.2):
 *   {
 *     "integrations": [
 *       {
 *         "slug": "dropbox",
 *         "wire_version": "v3",
 *         "supports_async_actions": true,
 *         "supports_triggers": true,
 *         "supports_webhook_ingress": true,
 *         "max_handler_backoff_seconds": 31170
 *       },
 *       ...
 *     ],
 *     "platform": {
 *       "site_migration": true
 *     }
 *   }
 *
 * Cache: 24h TTL on the transient as a hard floor; heartbeat-cron's 6h
 * refresh is the live update path. If both fail (cron stuck + transient
 * expired), accessors fall through to conservative defaults so plugin
 * UX never blocks on platform reachability.
 *
 * @since 8.0.0
 */
final class Capability_Discovery {

	private const CACHE_KEY          = 'automator_platform_capabilities';
	private const PLATFORM_CACHE_KEY = 'automator_platform_capabilities_platform';
	private const CACHE_TTL          = 24 * HOUR_IN_SECONDS;

	/**
	 * @var Platform_Http|object|null
	 */
	private $http;

	/**
	 * @var License_Provider_Interface|null
	 */
	private $license;

	/**
	 * @param Platform_Http|object|null       $http
	 * @param License_Provider_Interface|null $license
	 */
	public function __construct( $http = null, ?License_Provider_Interface $license = null ) {

		$this->http    = $http ?? new Platform_Http();
		$this->license = $license;
	}

	/**
	 * Refresh the cache by hitting `GET /app/v1/integrations`. Called from
	 * {@see Site_Heartbeat_Cron::ping()} after the alive ping succeeds —
	 * one round trip per 6h cycle.
	 *
	 * Returns true on a successful refresh (cache updated), false on
	 * transport error / unreadable response. Stale cache survives a
	 * failed refresh so accessors keep returning the last-known-good
	 * matrix.
	 *
	 * @return bool
	 */
	public function refresh(): bool {

		$license     = $this->license ?? automator_license_manager();
		$license_key = null === $license ? '' : $license->get_key();
		if ( '' === $license_key ) {
			return false;
		}

		if ( null === $this->http || ! method_exists( $this->http, 'get' ) ) {
			return false;
		}

		// License headers (license-key / site-name / item-name) are
		// auto-injected by Platform_Headers_Injector on http_request_args
		// for any URL matching AUTOMATOR_API_URL — pass an empty headers
		// array.
		$response = (array) $this->http->get( $this->integrations_url(), array() );

		if ( ! isset( $response['integrations'] ) || ! is_array( $response['integrations'] ) ) {
			return false;
		}

		$indexed = array();
		foreach ( $response['integrations'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = isset( $row['slug'] ) ? strtolower( (string) $row['slug'] ) : '';
			if ( '' === $slug ) {
				continue;
			}
			$indexed[ $slug ] = array(
				'wire_version'                => (string) ( $row['wire_version'] ?? 'v2' ),
				'supports_async_actions'      => (bool) ( $row['supports_async_actions'] ?? false ),
				'supports_triggers'           => (bool) ( $row['supports_triggers'] ?? false ),
				'supports_webhook_ingress'    => (bool) ( $row['supports_webhook_ingress'] ?? false ),
				'max_handler_backoff_seconds' => (int) ( $row['max_handler_backoff_seconds'] ?? 0 ),
			);
		}

		// Absent/malformed `platform` stores an empty array rather than
		// leaving the prior value in place — a platform that stopped
		// advertising a flag must not leave a stale positive behind.
		$platform = isset( $response['platform'] ) && is_array( $response['platform'] )
			? $response['platform']
			: array();

		set_transient( self::PLATFORM_CACHE_KEY, $platform, self::CACHE_TTL );
		set_transient( self::CACHE_KEY, $indexed, self::CACHE_TTL );

		return true;
	}

	/**
	 * Read the full cached matrix. Returns an empty array when the
	 * transient is missing — callers should fall back to conservative
	 * defaults rather than block on platform reachability.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all(): array {

		$cached = get_transient( self::CACHE_KEY );
		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * @param string $slug
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( string $slug ): ?array {

		$slug = strtolower( $slug );
		$all  = $this->get_all();

		return $all[ $slug ] ?? null;
	}

	/**
	 * Convenience: per-integration `supports_async_actions`. Defaults to
	 * `false` when capability data is missing — conservative posture
	 * means a plugin running ahead of platform deploy doesn't accidentally
	 * mark unrelated integrations as async-eligible.
	 */
	public function is_async_supported( string $slug ): bool {

		$row = $this->get( $slug );
		return null !== $row && (bool) ( $row['supports_async_actions'] ?? false );
	}

	/**
	 * Convenience: per-integration `supports_triggers`.
	 */
	public function is_triggers_supported( string $slug ): bool {

		$row = $this->get( $slug );
		return null !== $row && (bool) ( $row['supports_triggers'] ?? false );
	}

	/**
	 * Global `max_handler_backoff_seconds` across all known integrations,
	 * used by D.4 as the polling-cap floor for `Async_Job_Recovery`.
	 *
	 * Per-integration precision intentionally NOT shipped in MVP — would
	 * require persisting `integration` on `wp_uap_async_jobs` and a
	 * Tracker-side resolution step. Global max guarantees we never give
	 * up on a job before ANY integration's worst-case ladder elapses,
	 * which is the customer-data-correctness invariant. Per-integration
	 * tightening is a follow-up when async traffic spans enough vendors
	 * that the loose cap matters.
	 *
	 * @return int Seconds. Zero when no capability data is cached —
	 *             callers should max() with their own conservative floor.
	 */
	public function max_handler_backoff_seconds_global(): int {

		$max = 0;
		foreach ( $this->get_all() as $row ) {
			$candidate = (int) ( $row['max_handler_backoff_seconds'] ?? 0 );
			if ( $candidate > $max ) {
				$max = $candidate;
			}
		}

		return $max;
	}

	/**
	 * Platform-level (not per-integration) capability flags, e.g.
	 * `site_migration`. Conservative: false until a refresh has stored a
	 * truthy flag, so a plugin running ahead of the platform deploy never
	 * assumes an undeclared capability.
	 *
	 * @param string $flag
	 *
	 * @return bool
	 *
	 * @since 8.0.0
	 */
	public function platform_supports( string $flag ): bool {

		$platform = get_transient( self::PLATFORM_CACHE_KEY );

		return is_array( $platform ) && ! empty( $platform[ $flag ] );
	}

	/**
	 * @return string
	 */
	private function integrations_url(): string {

		$base = defined( 'AUTOMATOR_API_URL' )
			? rtrim( (string) AUTOMATOR_API_URL, '/' )
			: 'https://api.automatorplugin.com';

		return (string) Dispatcher::filter(
			'automator_platform_integrations_url',
			$base . AUTOMATOR_PLATFORM_API_PATH . '/integrations'
		);
	}
}
