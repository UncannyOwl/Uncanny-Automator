<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\License;

use Uncanny_Automator\App\Application\Mcp\Mcp_License_Provider_Interface;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Request;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Client_Interface;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Error_Exception;
use Uncanny_Automator\App\Infrastructure\Exceptions\Api_Exception;
use Uncanny_Automator\App\Transient\Domain\License_Transient_Keys;

/**
 * Class License_Manager
 *
 * Reads license state from wp_options and provides API-dependent
 * license validation and data retrieval.
 *
 * Replaces the license-related static methods from Api_Server.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\License
 */
class License_Manager implements License_Provider_Interface, Mcp_License_Provider_Interface {

	/**
	 * Transient key for cached license data.
	 *
	 * @var string
	 */
	const TRANSIENT_LICENSE = 'automator_api_license';

	/**
	 * Transient key for failed license check marker.
	 *
	 * @var string
	 */
	const TRANSIENT_LICENSE_FAILED = 'automator_license_check_failed';

	/** Consecutive temporary failures, retained across retry windows. */
	const TRANSIENT_LICENSE_FAILURE_STREAK = License_Transient_Keys::LICENSE_FAILURE_STREAK;

	/**
	 * Cache duration in seconds (12 hours).
	 *
	 * @var int
	 */
	const CACHE_DURATION = HOUR_IN_SECONDS * 12;

	/**
	 * Permanent failure cache and maximum temporary backoff (1 hour).
	 *
	 * @var int
	 */
	const FAILED_CACHE_DURATION = HOUR_IN_SECONDS;

	/**
	 * API client for license data fetches, when one was set explicitly.
	 * Set via set_api_client() after construction to break circular dependency;
	 * without it a fetch uses the shared platform client (platform_client()).
	 *
	 * @var Client_Interface|null
	 */
	private $api_client = null;

	/**
	 * In-memory cache for license data within the current request.
	 *
	 * @var array|null
	 */
	private $license_cache = null;

	/**
	 * Set the API client (called after both License_Manager and Api_Client are constructed).
	 *
	 * @param Client_Interface $api_client The API client instance.
	 *
	 * @return void
	 */
	public function set_api_client( Client_Interface $api_client ): void {
		$this->api_client = $api_client;
	}

	/**
	 * Get the license type based on wp_options.
	 *
	 * Checks Pro first (the Pro plugin loaded AND its license activated), then
	 * Free. The one rule for which license this site speaks with: the platform
	 * registration and every request's license headers read it (handoff R3) —
	 * a Pro key that is present but not activated is never sent.
	 *
	 * @return string 'pro', 'free', or empty string.
	 */
	public function get_type(): string {
		if ( $this->is_pro_loaded() && 'valid' === automator_get_option( 'uap_automator_pro_license_status' ) ) {
			return 'pro';
		}

		if ( 'valid' === automator_get_option( 'uap_automator_free_license_status' ) ) {
			return 'free';
		}

		return '';
	}

	/**
	 * Whether the Pro plugin's code is loaded — its license counts only then.
	 * A stale Pro key left by a removed Pro install must not outrank the free
	 * license. Filterable (`automator_platform_pro_is_present`) because the
	 * constant cannot be toggled per test.
	 *
	 * @return bool
	 */
	private function is_pro_loaded(): bool {
		return (bool) Dispatcher::filter( 'automator_platform_pro_is_present', defined( 'AUTOMATOR_PRO_FILE' ) );
	}

	/**
	 * Get the license key from wp_options.
	 *
	 * @return string The license key, or empty string if no valid type.
	 */
	public function get_key(): string {
		$type = $this->get_type();

		if ( '' === $type ) {
			return '';
		}

		$key = automator_get_option( 'uap_automator_' . $type . '_license_key' );

		return is_string( $key ) ? $key : '';
	}

	/**
	 * Get the site name (home URL without protocol).
	 *
	 * @return string
	 */
	public function get_site_name(): string {
		return preg_replace( '#^https?://#', '', get_home_url() );
	}

	/**
	 * Get the item name from the appropriate constant.
	 *
	 * @return string The item name, or empty string if unavailable.
	 */
	public function get_item_name(): string {
		$type = $this->get_type();

		if ( '' === $type ) {
			return '';
		}

		$license_type = strtoupper( $type );

		if ( 'PRO' === $license_type ) {
			if ( defined( 'AUTOMATOR_' . $license_type . '_ITEM_NAME' ) ) {
				return (string) constant( 'AUTOMATOR_' . $license_type . '_ITEM_NAME' );
			}

			if ( defined( 'AUTOMATOR_AUTOMATOR_' . $license_type . '_ITEM_NAME' ) ) {
				return (string) constant( 'AUTOMATOR_AUTOMATOR_' . $license_type . '_ITEM_NAME' );
			}
		}

		$constant_name = 'AUTOMATOR_' . $license_type . '_ITEM_NAME';

		if ( defined( $constant_name ) ) {
			return (string) constant( $constant_name );
		}

		return '';
	}

	/**
	 * Get the license plan.
	 *
	 * Tries license data first, falls back to 'basic'/'lite' based on type.
	 *
	 * @return string The plan identifier, or empty string if no license.
	 */
	public function get_plan(): string {
		try {
			$license = $this->get_license_data();

			if ( is_array( $license ) && isset( $license['license_plan'] ) ) {
				return (string) $license['license_plan'];
			}

			// Try a force refresh if license exists but plan is missing.
			if ( null !== $license ) {
				$refreshed = $this->get_license_data( true );

				if ( is_array( $refreshed ) && isset( $refreshed['license_plan'] ) ) {
					return (string) $refreshed['license_plan'];
				}
			}
		} catch ( \Exception $e ) {
			unset( $e ); // Fall through to fallback logic.
		}

		$type = $this->get_type();

		if ( '' === $type ) {
			return '';
		}

		return 'pro' === $type ? 'basic' : 'lite';
	}

	/**
	 * Get the human-readable plan label shipped by the API
	 * (e.g. "Plus AI + Automation Monthly", "Plus (Legacy)").
	 *
	 * Falls back to the license_plan slug when the cached payload predates
	 * plan_name shipping or the API has not yet synced the field.
	 *
	 * @return string
	 */
	public function get_plan_name(): string {
		try {
			$license = $this->get_license_data();

			if ( is_array( $license ) && ! empty( $license['plan_name'] ) ) {
				return (string) $license['plan_name'];
			}

			if ( null !== $license ) {
				$refreshed = $this->get_license_data( true );

				if ( is_array( $refreshed ) && ! empty( $refreshed['plan_name'] ) ) {
					return (string) $refreshed['plan_name'];
				}
			}
		} catch ( \Exception $e ) {
			unset( $e );
		}

		return $this->get_plan();
	}

	/**
	 * Whether the Pro plugin is currently loaded.
	 *
	 * Distinct from get_type() === 'pro' — that checks the locally stored
	 * license status option; this checks the plugin code is actually active.
	 *
	 * @return bool
	 */
	public function is_pro_active(): bool {
		return defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' );
	}

	/**
	 * Plan slug ready for display/CTA logic.
	 *
	 * - Pro plugin not active                          → 'lite'
	 * - No license / invalid license                   → 'basic'
	 * - Otherwise the API license_plan (basic/plus/elite), or 'basic' if
	 *   the payload carries an unrecognised value.
	 *
	 * @return string
	 */
	public function get_resolved_plan(): string {
		if ( ! $this->is_pro_active() ) {
			return 'lite';
		}

		$license = $this->get_license_data();

		if (
			! is_array( $license )
			|| empty( $license['license_key'] )
			|| 'valid' !== ( $license['license'] ?? '' )
		) {
			return 'basic';
		}

		$plan = $this->get_plan();

		return in_array( $plan, array( 'basic', 'plus', 'elite' ), true ) ? $plan : 'basic';
	}

	/**
	 * Display name for the resolved plan.
	 *
	 * Empty when Pro isn't active or the license isn't valid. Otherwise
	 * the API-supplied plan_name (e.g. "Plus AI + Automation Monthly"),
	 * with a fallback to the license_plan slug while the storefront
	 * back-fills plan_name on older licenses.
	 *
	 * @return string
	 */
	public function get_resolved_plan_name(): string {
		if ( ! $this->is_pro_active() ) {
			return '';
		}

		$license = $this->get_license_data();

		if (
			! is_array( $license )
			|| empty( $license['license_key'] )
			|| 'valid' !== ( $license['license'] ?? '' )
		) {
			return '';
		}

		return $this->get_plan_name();
	}

	/**
	 * Get the formatted renewal/expiry date.
	 *
	 * @return string Formatted date like "January 1, 2026", or empty string.
	 */
	public function get_renewal_date(): string {
		$type = $this->get_type();

		if ( '' === $type ) {
			return '';
		}

		$expiry = automator_get_option( 'uap_automator_' . $type . '_license_expiry', '' );

		if ( empty( $expiry ) || 'lifetime' === $expiry ) {
			return '';
		}

		try {
			$date = new \DateTime( $expiry, wp_timezone() );
			return $date->format( 'F j, Y' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Get license data, using transient cache with optional force refresh.
	 *
	 * A cached license is served whatever the failure marker says: the marker
	 * holds back the next fetch, not a read. A forced refresh replaces the
	 * cached license only when it succeeds and drops it only on a verdict on
	 * the license (see is_verdict()). On a temporary failure the last good
	 * license stays, and is what the forced refresh answers. A forced refresh
	 * does not fetch through a temporary backoff either: it answers the kept
	 * license, so a caller that forces whenever a field is missing (get_plan())
	 * cannot defeat the backoff. After a verdict it does fetch, so a site can
	 * recover once the cause is fixed.
	 *
	 * @param bool $force_refresh Whether to bypass cache and fetch fresh data.
	 *
	 * @return array|null License data array, or null if unavailable.
	 */
	public function get_license_data( bool $force_refresh = false ): ?array {
		// Return in-memory cache if available and not forcing refresh.
		if ( null !== $this->license_cache && ! $force_refresh ) {
			return $this->license_cache;
		}

		if ( ! $force_refresh ) {
			$cached = $this->read_cached_license();

			if ( null !== $cached ) {
				$this->license_cache = $cached;
				return $cached;
			}

			// Check failure transient to avoid hammering a failing API.
			if ( false !== get_transient( self::TRANSIENT_LICENSE_FAILED ) ) {
				return null;
			}
		}

		$key = $this->get_key();

		if ( empty( $key ) ) {
			return null;
		}

		if ( $force_refresh && $this->is_backing_off() ) {
			return $this->last_good_license();
		}

		$client = $this->platform_client();

		try {
			$request = new Request(
				'v2/credits',
				array( 'action' => 'get_credits' )
			);

			$response = $client->send( $request );
			$license  = $response->data();

			// An answer with no license document is no license: never cache it.
			if ( array() === $license ) {
				throw new Api_Exception( esc_html__( 'Invalid license response.', 'uncanny-automator' ) );
			}

			$this->license_cache = $license;
			set_transient( self::TRANSIENT_LICENSE, $license, self::CACHE_DURATION );
			delete_transient( self::TRANSIENT_LICENSE_FAILED );
			delete_transient( self::TRANSIENT_LICENSE_FAILURE_STREAK );

			return $license;
		} catch ( \Exception $e ) {
			return $this->record_failure( $e );
		}
	}

	/**
	 * The license in the shared transient, when it is one.
	 *
	 * The 'automator_api_license' transient is shared with the legacy
	 * class-api-server.php, which stores the raw API 'data' value and returns
	 * it untyped. A non-array there (e.g. an error-shaped body) would break
	 * get_license_data()'s ?array contract and fatal downstream, so only an
	 * array is served. Anything else is dropped and the next read fetches, so
	 * the cache self-heals.
	 *
	 * @return array|null
	 */
	private function read_cached_license(): ?array {
		$cached = get_transient( self::TRANSIENT_LICENSE );

		if ( false === $cached ) {
			return null;
		}

		if ( is_array( $cached ) ) {
			return $cached;
		}

		delete_transient( self::TRANSIENT_LICENSE );

		return null;
	}

	/**
	 * What a failed fetch costs.
	 *
	 * A verdict on the license drops the cached license and holds the next
	 * fetch back for an hour. Any other failure is temporary: the next fetch
	 * waits one minute, doubling per consecutive failure up to an hour, or the
	 * platform's Retry-After if that is longer, and the last good license
	 * stays.
	 *
	 * @param \Exception $e The failure the API client threw.
	 *
	 * @return array|null The last good license on a temporary failure, null on a verdict.
	 */
	private function record_failure( \Exception $e ): ?array {
		if ( $this->is_verdict( $e ) ) {
			delete_transient( self::TRANSIENT_LICENSE );
			delete_transient( self::TRANSIENT_LICENSE_FAILURE_STREAK );
			$this->license_cache = null;
			set_transient( self::TRANSIENT_LICENSE_FAILED, $e->getMessage(), self::FAILED_CACHE_DURATION );

			return null;
		}

		$attempt = min( 7, max( 0, (int) get_transient( self::TRANSIENT_LICENSE_FAILURE_STREAK ) ) + 1 );
		$delay   = min( self::FAILED_CACHE_DURATION, max( MINUTE_IN_SECONDS * ( 2 ** ( $attempt - 1 ) ), $this->retry_after( $e ) ) );

		set_transient( self::TRANSIENT_LICENSE_FAILURE_STREAK, $attempt, self::CACHE_DURATION );
		set_transient( self::TRANSIENT_LICENSE_FAILED, $e->getMessage(), $delay );

		return $this->last_good_license();
	}

	/**
	 * Whether a temporary backoff is running: the failure marker is set and so
	 * is the failure streak. A temporary failure writes both; a verdict clears
	 * the streak and writes the marker (the latch); a success clears both.
	 * So the marker alone is a latch, and a forced refresh goes through it.
	 *
	 * @return bool
	 */
	private function is_backing_off(): bool {
		return false !== get_transient( self::TRANSIENT_LICENSE_FAILED ) && false !== get_transient( self::TRANSIENT_LICENSE_FAILURE_STREAK );
	}

	/**
	 * The last license this site fetched and has not been told to drop.
	 *
	 * @return array|null
	 */
	private function last_good_license(): ?array {
		return $this->license_cache ?? $this->read_cached_license();
	}

	/**
	 * The client for a fresh fetch: the one wired by set_api_client(), else the
	 * shared platform client, which wires itself back to the shared manager.
	 *
	 * @return Client_Interface
	 */
	private function platform_client(): Client_Interface {
		return $this->api_client ?? \Uncanny_Automator\App\Infrastructure\automator_platform_client();
	}

	/**
	 * Whether a failed license fetch is a verdict on the license: it latches
	 * for an hour. Every other failure is temporary and backs off.
	 *
	 * A verdict is a 401, 402 or 403: the platform's answer about this license
	 * or site (an unknown or mismatched key, the credit check). Everything
	 * else is "not now": a transport failure (0), a 429, any 5xx,
	 * and any other 4xx, a 404 from a misrouted deploy included. The platform
	 * answers every /v2 failure that is a 5xx (a bug, a database blip, the
	 * queue-deadline refusal) as 424, whatever its error type (SERVER_ERROR,
	 * FAILED_DEPENDENCY): a collapsed 5xx (platform
	 * app/Support/Http/WireStatus.php, §9.3; V2BaseController and
	 * PlatformExceptionRenderer both go through it).
	 *
	 * One 401 is not a verdict: the /v2 throttle. LicenseAuthBase::applyRateLimit
	 * answers it with 401 "Rate limit exceeded" (V2LicenseAuth::THROTTLE_STATUS,
	 * byte-frozen for old plugins) and always sets Retry-After; a verdict 401
	 * goes through unauthenticated(), which sets none. A 401 that carries
	 * Retry-After is a throttle. It may carry 0: availableIn() is
	 * max(0, timer - now), so a real throttle says 0 in the last part of each
	 * window. The header's presence tells, not its value. (Platform_Failure
	 * decides on status and type only and maps every UNAUTHENTICATED to
	 * `license`; the header is read here, where the latch is decided.)
	 *
	 * @param \Exception $e The failure the API client threw.
	 *
	 * @return bool
	 */
	private function is_verdict( \Exception $e ): bool {
		$status = (int) $e->getCode();

		if ( 401 === $status ) {
			return ! $this->carries_retry_after( $e );
		}

		return 402 === $status || 403 === $status;
	}

	/**
	 * Whether the platform's answer carries a Retry-After header, whatever its value.
	 *
	 * @param \Exception $e The failure the API client threw.
	 *
	 * @return bool
	 */
	private function carries_retry_after( \Exception $e ): bool {
		return $e instanceof Platform_Error_Exception && '' !== $e->response()->header( 'retry-after' );
	}

	/**
	 * The seconds the platform asked this site to wait, 0 when it asked for nothing.
	 *
	 * @param \Exception $e The failure the API client threw.
	 *
	 * @return int
	 */
	private function retry_after( \Exception $e ): int {
		return $e instanceof Platform_Error_Exception ? $e->response()->retry_after() : 0;
	}

	/**
	 * The license refreshed from the platform, unless a recent failure is
	 * still backing off; then the license the site already holds.
	 *
	 * @return array|null License data array, or null if unavailable.
	 */
	public function get_fresh_license_data(): ?array {
		return $this->get_license_data( false === get_transient( self::TRANSIENT_LICENSE_FAILED ) );
	}

	/**
	 * Whether the platform could not answer the last license fetch (no
	 * response, 429 or 5xx) and has not answered one since.
	 *
	 * @return bool
	 */
	public function is_platform_unreachable(): bool {
		return false !== get_transient( self::TRANSIENT_LICENSE_FAILURE_STREAK );
	}

	/**
	 * Get only license data that is already cached locally.
	 *
	 * Unlike get_license_data(), this method never refreshes from Automator
	 * Platform and never mutates a malformed shared transient.
	 *
	 * @return array|null Cached license data, or null when unavailable.
	 */
	public function get_cached_license_data(): ?array {
		if ( null !== $this->license_cache ) {
			return $this->license_cache;
		}

		$cached = get_transient( self::TRANSIENT_LICENSE );

		if ( ! is_array( $cached ) ) {
			return null;
		}

		$this->license_cache = $cached;

		return $cached;
	}

	/**
	 * Check if the site is connected to the Automator API.
	 *
	 * @param bool $force_refresh Whether to force a fresh license check.
	 *
	 * @return bool True if connected with valid license data.
	 */
	public function is_connected( bool $force_refresh = false ): bool {
		$license = $this->get_license_data( $force_refresh );

		return null !== $license && is_array( $license );
	}

	/**
	 * Validate the license and return the license data.
	 *
	 * @return array The license data array.
	 *
	 * @throws \Exception If the license is not valid.
	 */
	public function validate(): array {
		$license = $this->get_license_data();

		if ( null === $license ) {
			throw new Api_Exception( esc_html__( 'Unable to fetch the license.', 'uncanny-automator' ) );
		}

		if ( ! isset( $license['license'] ) || 'valid' !== $license['license'] ) {
			throw new Api_Exception( esc_html__( 'License is not valid', 'uncanny-automator' ) );
		}

		return $license;
	}

	/**
	 * Reset the in-memory license cache.
	 *
	 * @return void
	 */
	public function reset_cache(): void {
		$this->license_cache = null;
	}
}
