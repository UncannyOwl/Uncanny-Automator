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
 * @package Uncanny_Automator\App\Integration_Catalog\Services
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Services;

use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store\Catalog_Build_Report;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store\Integration_Map_Builder;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store\Integration_Feed_Registration_Merger;
use Uncanny_Automator\Traits\Singleton;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Components\Translation\Locale_Resolver;

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
	 * Transient value meaning "no file for this language yet; use English".
	 *
	 * @var string
	 */
	const FALLBACK = 'fallback';

	/**
	 * Transient value meaning "the CDN did not answer"; kept briefly so a build
	 * during an outage does not repeat a fifteen-second request.
	 *
	 * @var string
	 */
	const UNAVAILABLE = 'unavailable';

	/**
	 * How long an unavailable English feed is remembered (five minutes).
	 *
	 * @var int
	 */
	const UNAVAILABLE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Option listing every language suffix a transient has been written for.
	 *
	 * @var string
	 */
	const LANGUAGES_OPTION = 'uap_complete_json_languages';

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
	 * Which file the last fetch_raw() served, for the build report.
	 *
	 * @var string
	 */
	private string $active_feed = '';

	/**
	 * Processed integration data, cached for the request.
	 *
	 * @var array|null
	 */
	private ?array $processed = null;

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
	 * Performance: Cached on the (singleton) instance for the request.
	 * This is the main entry point for accessing merged integration data.
	 *
	 * Processing steps:
	 * 1. Fetch raw complete.json from CDN (transient cached)
	 * 2. Build indexed map of complete.json (O(1) lookups)
	 * 3. Build indexed map of registered integrations (O(1) lookups)
	 * 4. For each registered: merge with feed OR discover
	 * 5. Add unregistered integrations from feed, and report the scenario split
	 * 6. Apply integration-specific filters
	 *
	 * @return array Processed integration data arrays
	 */
	public function get_processed(): array {
		if ( null !== $this->processed ) {
			return $this->processed;
		}

		$this->init();
		Catalog_Build_Report::start_discovery();

		// Step 1: Fetch raw complete.json from CDN (transient cached).
		$raw_json = $this->fetch_raw();

		// Step 2: Build indexed map of complete.json for O(1) lookups.
		$complete_map = $this->map_builder->build_complete_json_map( $raw_json );

		// Step 3: Build indexed map of registered integrations.
		$registered_map = $this->map_builder->build_registered_map();

		// Step 4: Process registered integrations.
		$processed           = array();
		$processed_from_feed = array();
		$discovered_count    = 0;

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
				++$discovered_count;
			}
		}

		// Step 5: Add unregistered integrations from feed.
		foreach ( $complete_map as $code_lower => $feed_data ) {
			if ( ! isset( $processed_from_feed[ $code_lower ] ) ) {
				// SCENARIO 3: NOT registered + in feed -> Use feed as-is.
				$processed[] = $feed_data['data'];
			}
		}

		$merged_count = count( $processed_from_feed );
		Catalog_Build_Report::scenarios( $merged_count, $discovered_count, count( $processed ) - $merged_count - $discovered_count );

		// Step 6: Apply integration-specific filters.
		foreach ( $processed as $key => $integration_data ) {
			$code = $integration_data['integration_id'] ?? '';
			if ( ! empty( $code ) ) {
				$processed[ $key ] = $this->apply_integration_filter( $code, $integration_data );
			}
		}

		$this->processed = $processed;

		return $processed;
	}

	/**
	 * Fetch the catalog feed for the current user's language.
	 *
	 * A non-English user gets complete-{lang}.json when it exists, else English.
	 * The only method that performs HTTP; every copy is transient-cached.
	 *
	 * @return array Raw feed records, or an empty array when nothing could be fetched.
	 */
	public function fetch_raw(): array {
		$language = $this->resolve_language();

		return '' === $language ? $this->fetch_english() : $this->fetch_language( $language );
	}

	/**
	 * The file the last fetch_raw() served: `complete.json`, `complete-fr.json`,
	 * or `complete.json (fr: fallback)` when the language file was missing.
	 *
	 * @return string
	 */
	public function get_active_feed(): string {
		return $this->active_feed;
	}

	/**
	 * The current user's language as a feed suffix, '' for English.
	 *
	 * Filterable so a site can opt out (return '') or exercise a language
	 * before marketing publishes it (return 'fr'). Anything that is not a
	 * plain lowercase code is treated as English rather than built into a URL.
	 *
	 * @return string
	 */
	private function resolve_language(): string {
		$language = ( new Locale_Resolver() )->get_language_code();
		$language = (string) Dispatcher::filter( 'automator_integration_feed_language', $language );

		if ( 'en' === $language || 1 !== preg_match( '/^[a-z][a-z0-9-]{1,9}$/', $language ) ) {
			return '';
		}

		return $language;
	}

	/**
	 * Serve a language copy, falling back to English when there is none.
	 *
	 * @param string $language Language suffix, e.g. 'fr'.
	 *
	 * @return array
	 */
	private function fetch_language( string $language ): array {
		$key    = self::CACHE_KEY . '_' . $language;
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			$this->active_feed = "complete-{$language}.json";
			return $cached;
		}

		if ( self::FALLBACK !== $cached ) {
			$data = $this->request( str_replace( 'complete.json', "complete-{$language}.json", self::COMPLETE_JSON_URL ) );
			$this->remember_language( $language );
			if ( null !== $data ) {
				set_transient( $key, $data, self::CACHE_TTL );
				$this->active_feed = "complete-{$language}.json";
				return $data;
			}
			// No file for this language yet: remember that for an hour so the
			// miss costs one request, not one per catalog build.
			set_transient( $key, self::FALLBACK, self::CACHE_TTL );
		}

		$english            = $this->fetch_english();
		$this->active_feed .= " ({$language}: fallback)";

		return $english;
	}

	/**
	 * Serve the English feed.
	 *
	 * @return array
	 */
	private function fetch_english(): array {
		$this->active_feed = 'complete.json';

		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( self::UNAVAILABLE === $cached ) {
			$this->active_feed .= ' (unavailable)';
			return array();
		}

		$data = $this->request( self::COMPLETE_JSON_URL );
		if ( null === $data ) {
			set_transient( self::CACHE_KEY, self::UNAVAILABLE, self::UNAVAILABLE_TTL );
			$this->active_feed .= ' (unavailable)';
			return array();
		}

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * GET a feed file. Anything but a 200 carrying a JSON array is a miss.
	 *
	 * @param string $url Feed URL.
	 *
	 * @return array|null Decoded records, or null on any failure.
	 */
	private function request( string $url ): ?array {
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Index a language so clear_cache() can reach its transient later.
	 *
	 * @param string $language Language suffix.
	 *
	 * @return void
	 */
	private function remember_language( string $language ): void {
		$languages = (array) automator_get_option( self::LANGUAGES_OPTION, array() );
		if ( in_array( $language, $languages, true ) ) {
			return;
		}
		$languages[] = $language;
		automator_update_option( self::LANGUAGES_OPTION, $languages, false );
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
		return Dispatcher::filter( "automator_integration_data_{$code}", $data, $code );
	}

	/**
	 * Drop every cached copy of the feed - English and each language - and the index.
	 *
	 * @return bool Whether the English transient existed and was deleted.
	 */
	public function clear_cache(): bool {
		foreach ( (array) automator_get_option( self::LANGUAGES_OPTION, array() ) as $language ) {
			delete_transient( self::CACHE_KEY . '_' . $language );
		}
		automator_delete_option( self::LANGUAGES_OPTION );

		return delete_transient( self::CACHE_KEY );
	}
}
