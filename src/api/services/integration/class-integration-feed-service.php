<?php
/**
 * Integration Feed Service
 *
 * Handles fetching, caching, and merging of integration feed data.
 *
 * Performance-critical class with multiple caching layers:
 * - Static cache: Per-request caching for processed data
 * - Transient cache: 1-hour CDN data caching
 * - Discovery cache: Version-keyed caching (in Discovery_Service)
 *
 * @package Uncanny_Automator\Api\Services\Integration
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration;

use Uncanny_Automator\Api\Services\Integration\Utilities\Store\Integration_Map_Builder;
use Uncanny_Automator\Api\Services\Integration\Utilities\Store\Integration_Feed_Registration_Merger;
use Uncanny_Automator\Traits\Singleton;

/**
 * Service for fetching and processing integration feed data.
 *
 * Responsibilities:
 * - Fetch complete.json from CDN (with transient caching)
 * - Build indexed maps for O(1) lookups
 * - Merge feed data with registered integrations
 * - Discover integrations not in feed
 * - Apply integration-specific filters
 *
 * @since 7.0.0
 */
class Integration_Feed_Service {

	use Singleton;

	/**
	 * Complete.json endpoint URL.
	 *
	 * @var string
	 */
	const COMPLETE_JSON_URL = 'https://integrations.automatorplugin.com/complete.json';

	/**
	 * Transient cache key for complete.json data.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'uap_complete_json';

	/**
	 * Transient cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Map builder utility for creating indexed lookups.
	 *
	 * @var Integration_Map_Builder|null
	 */
	private $map_builder = null;

	/**
	 * Merger utility for combining feed and registration data.
	 *
	 * @var Integration_Feed_Registration_Merger|null
	 */
	private $merger = null;

	/**
	 * Initialize dependencies lazily.
	 *
	 * Performance: Only instantiates utilities when first needed.
	 *
	 * @return void
	 */
	private function init(): void {
		if ( null === $this->map_builder ) {
			$this->map_builder = new Integration_Map_Builder();
			$this->merger      = new Integration_Feed_Registration_Merger();
		}
	}

	/**
	 * Get processed integration data with static caching.
	 *
	 * Performance: Uses static variable for per-request caching.
	 * This is the main entry point for accessing merged integration data.
	 *
	 * Processing steps:
	 * 1. Fetch raw complete.json from CDN (transient cached)
	 * 2. Build indexed map of complete.json (O(1) lookups)
	 * 3. Build indexed map of registered integrations (O(1) lookups)
	 * 4. For each registered: merge with feed OR discover
	 * 5. Add unregistered integrations from feed
	 * 6. Apply integration-specific filters
	 *
	 * @return array Processed integration data arrays
	 */
	public function get_processed(): array {
		static $processed = null;

		if ( null !== $processed ) {
			return $processed;
		}

		$this->init();

		// Step 1: Fetch raw complete.json from CDN (transient cached).
		$raw_json = $this->fetch_raw();

		// Step 2: Build indexed map of complete.json for O(1) lookups.
		$complete_map = $this->map_builder->build_complete_json_map( $raw_json );

		// Step 3: Build indexed map of registered integrations.
		$registered_map = $this->map_builder->build_registered_map();

		// Step 4: Process registered integrations.
		$processed           = array();
		$processed_from_feed = array();

		foreach ( $registered_map as $code => $registered_data ) {
			$code_lower = strtolower( $code );

			if ( isset( $complete_map[ $code_lower ] ) ) {
				// SCENARIO 1: Registered + in feed -> Merge both sources.
				$merged      = $this->merger->merge( $complete_map[ $code_lower ], $registered_data, $code );
				$processed[] = $merged;
				// Mark as processed to skip in Step 5.
				$processed_from_feed[ $code_lower ] = true;
				continue;
			}

			// SCENARIO 2: Registered + NOT in feed -> Pure discovery.
			$discovery  = Integration_Discovery_Service::get_instance();
			$discovered = $discovery->discover_integration_by_code( $code );
			if ( ! empty( $discovered ) ) {
				$processed[] = $discovered;
			}
		}

		// Step 5: Add unregistered integrations from feed.
		foreach ( $complete_map as $code_lower => $feed_data ) {
			if ( ! isset( $processed_from_feed[ $code_lower ] ) ) {
				// SCENARIO 3: NOT registered + in feed -> Use feed as-is.
				$processed[] = $feed_data['data'];
			}
		}

		// Step 6: Apply integration-specific filters.
		foreach ( $processed as $key => $integration_data ) {
			$code = $integration_data['integration_id'] ?? '';
			if ( ! empty( $code ) ) {
				$processed[ $key ] = $this->apply_integration_filter( $code, $integration_data );
			}
		}

		return $processed;
	}

	/**
	 * Fetch raw complete.json from CDN.
	 *
	 * Performance: Uses transient cache with 1-hour TTL to avoid repeated
	 * network calls. This is the only method that makes external HTTP requests.
	 *
	 * @return array Raw JSON data or empty array on failure
	 */
	public function fetch_raw(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get( self::COMPLETE_JSON_URL, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		// Cache for 1 hour.
		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Find integration in processed data by key-value match.
	 *
	 * Performance: Operates on static-cached processed data.
	 *
	 * @param string $key   Property to search (e.g., 'integration_id', 'integration_name')
	 * @param mixed  $value Value to match
	 *
	 * @return array|null Integration data or null if not found
	 */
	public function find_in_processed( string $key, $value ): ?array {
		$processed = $this->get_processed();

		if ( empty( $processed ) ) {
			return null;
		}

		foreach ( $processed as $item ) {
			if ( ( $item[ $key ] ?? null ) === $value ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Apply integration-specific filter.
	 *
	 * Allows 3rd party plugins to override or add data for a specific integration.
	 *
	 * @param string $code Integration code
	 * @param array  $data Integration data
	 *
	 * @return array Filtered data
	 *
	 * @example
	 * add_filter( 'automator_integration_data_PAYPAL', function( $data, $code ) {
	 *     $data['integration_color'] = '#00457c';
	 *     return $data;
	 * }, 10, 2 );
	 */
	private function apply_integration_filter( string $code, array $data ): array {
		return apply_filters( "automator_integration_data_{$code}", $data, $code );
	}

	/**
	 * Clear transient cache.
	 *
	 * Call this when complete.json needs to be re-fetched (e.g., after plugin update).
	 *
	 * @return bool True if cache was cleared
	 */
	public function clear_cache(): bool {
		return delete_transient( self::CACHE_KEY );
	}
}
