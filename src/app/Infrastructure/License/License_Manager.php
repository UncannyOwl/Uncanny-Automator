<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\License;

use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Request;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Client_Interface;
use Uncanny_Automator\App\Infrastructure\Exceptions\Api_Exception;
use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;

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
class License_Manager implements License_Provider_Interface {

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

	/**
	 * Cache duration in seconds (12 hours).
	 *
	 * @var int
	 */
	const CACHE_DURATION = HOUR_IN_SECONDS * 12;

	/**
	 * Failed license check cache duration (1 hour — shorter to auto-recover).
	 *
	 * @var int
	 */
	const FAILED_CACHE_DURATION = HOUR_IN_SECONDS;

	/**
	 * Lazy-loaded API client for license data fetches.
	 * Set via set_api_client() after construction to break circular dependency.
	 *
	 * @var Api_Client_Interface|null
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
	 * @param Api_Client_Interface $api_client The API client instance.
	 *
	 * @return void
	 */
	public function set_api_client( Api_Client_Interface $api_client ): void {
		$this->api_client = $api_client;
	}

	/**
	 * Get the license type based on wp_options.
	 *
	 * Checks Pro first (requires AUTOMATOR_PRO_FILE constant), then Free.
	 *
	 * @return string 'pro', 'free', or empty string.
	 */
	public function get_type(): string {
		if ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === automator_get_option( 'uap_automator_pro_license_status' ) ) {
			return 'pro';
		}

		if ( 'valid' === automator_get_option( 'uap_automator_free_license_status' ) ) {
			return 'free';
		}

		return '';
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
	 * @param bool $force_refresh Whether to bypass cache and fetch fresh data.
	 *
	 * @return array|null License data array, or null if unavailable.
	 */
	public function get_license_data( bool $force_refresh = false ): ?array {
		// Return in-memory cache if available and not forcing refresh.
		if ( null !== $this->license_cache && ! $force_refresh ) {
			return $this->license_cache;
		}

		// Check failure transient to avoid hammering a failing API.
		$has_failed = false !== get_transient( self::TRANSIENT_LICENSE_FAILED );

		if ( true === $has_failed && ! $force_refresh ) {
			return null;
		}

		// Check transient cache.
		$cached = get_transient( self::TRANSIENT_LICENSE );

		if ( false !== $cached && ! $force_refresh ) {
			// The 'automator_api_license' transient is shared with the legacy
			// class-api-server.php, which stores the raw API 'data' value and
			// returns it untyped. A non-array there (e.g. an error-shaped body)
			// would break this method's ?array contract and fatal downstream, so
			// enforce the shape: serve only arrays. Otherwise drop the poisoned
			// value and fall through to a fresh fetch so the cache self-heals.
			if ( is_array( $cached ) ) {
				$this->license_cache = $cached;
				return $cached;
			}

			delete_transient( self::TRANSIENT_LICENSE );
		}

		// Need API client for fresh fetch.
		if ( null === $this->api_client ) {
			return null;
		}

		$key = $this->get_key();

		if ( empty( $key ) ) {
			return null;
		}

		if ( $force_refresh ) {
			delete_transient( self::TRANSIENT_LICENSE );
			delete_transient( self::TRANSIENT_LICENSE_FAILED );
		}

		try {
			$request = new Api_Request(
				'v2/credits',
				array( 'action' => 'get_credits' )
			);

			$response = $this->api_client->send( $request );
			$license  = $response->data();

			$this->license_cache = $license;
			set_transient( self::TRANSIENT_LICENSE, $license, self::CACHE_DURATION );
			delete_transient( self::TRANSIENT_LICENSE_FAILED );

			return $license;
		} catch ( \Exception $e ) {
			set_transient( self::TRANSIENT_LICENSE_FAILED, $e->getMessage(), self::FAILED_CACHE_DURATION );
			return null;
		}
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
