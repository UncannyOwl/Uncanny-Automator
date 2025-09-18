<?php

namespace Uncanny_Automator;

/**
 * Handles caching for Automator Options.
 *
 * Provides multi-level caching:
 * - In-process memory cache (fastest)
 * - WordPress object cache (shared across requests)
 * - Miss caching (prevents redundant DB queries)
 *
 * @since 5.0
 */
final class Automator_Options_Cache {

	/**
	 * Sentinel used to signal a cached miss across layers.
	 *
	 * @since 5.0
	 */
	public const MISS = '__UAP_MISS__';

	/**
	 * Sentinel used to indicate absence in this cache layer (no info).
	 * This is distinct from MISS which indicates a known negative.
	 */
	public const ABSENT = '__UAP_ABSENT__';

	/**
	 * Cache key prefix to prevent collisions with other plugins.
	 */
	const CACHE_KEY_PREFIX = 'uap_opts_';

	/**
	 * Default cache TTL in seconds.
	 */
	public const CACHE_TTL = 3600; // 1 hour

	/**
	 * Default miss cache TTL in seconds.
	 */
	public const CACHE_MISS_TTL = 300; // 5 minutes

	/**
	 * WordPress object cache group.
	 */
	private $cache_group = 'uap_options';

	/**
	 * Cache key prefix for option values.
	 */
	private $option_cache_prefix = 'option_';

	/**
	 * Cache key prefix for cache misses.
	 */
	private $miss_cache_prefix = 'miss_';

	/**
	 * In-process cache for option values.
	 * Key: option_name, Value: decoded option value or null for misses.
	 */
	private $cached_values = array();

	/**
	 * In-process cache for autoloaded options.
	 * Key: option_name, Value: decoded option value.
	 */
	private $autoloaded = array();

	/**
	 * Get the cache group for WordPress object cache.
	 *
	 * @return string Cache group name.
	 */
	public function get_cache_group() {
		return $this->cache_group;
	}

	/**
	 * Get the cache key prefix for option values.
	 *
	 * @return string Cache key prefix.
	 */
	public function get_option_cache_prefix() {
		return self::CACHE_KEY_PREFIX . $this->option_cache_prefix;
	}

	/**
	 * Get the cache key prefix for cache misses.
	 *
	 * @return string Miss cache key prefix.
	 */
	public function get_miss_cache_prefix() {
		return self::CACHE_KEY_PREFIX . $this->miss_cache_prefix;
	}

	/**
	 * Get the cache TTL with filter support.
	 *
	 * @return int Cache TTL in seconds.
	 */
	public function get_cache_ttl() {
		return apply_filters( 'uap_options_cache_ttl', self::CACHE_TTL );
	}

	/**
	 * Get the miss cache TTL with filter support.
	 *
	 * @return int Miss cache TTL in seconds.
	 */
	public function get_cache_miss_ttl() {
		return apply_filters( 'uap_options_cache_miss_ttl', self::CACHE_MISS_TTL );
	}

	/**
	 * Get a value from in-process cache.
	 *
	 * @param string $key Option key.
	 * @param mixed  $default_value Default value if not cached.
	 * @return mixed Cached value or default.
	 */
	public function get_from_memory( $key, $default_value = self::ABSENT ) {
		// Check autoloaded cache first.
		if ( array_key_exists( $key, $this->autoloaded ) ) {
			return $this->autoloaded[ $key ];
		}

		// Check regular cache.
		if ( array_key_exists( $key, $this->cached_values ) ) {
			return $this->cached_values[ $key ];
		}

		return $default_value;
	}

	/**
	 * Get a value from WordPress object cache.
	 *
	 * @param string $key Option key.
	 * @param mixed  $default_value Default value if not cached.
	 * @return mixed Cached value or default.
	 */
	public function get_from_object_cache( $key, $default_value = self::ABSENT ) {
		// Check for cached miss first.
		$miss_cached = wp_cache_get( $this->get_miss_cache_prefix() . $key, $this->get_cache_group() );
		if ( false !== $miss_cached ) {
			// Remember the miss to prevent re-lookups this request.
			$this->cached_values[ $key ] = self::MISS;
			// Return a recognizable sentinel so callers can short-circuit without DB hit.
			return self::MISS;
		}

		// Check for cached value.
		$cached_value = wp_cache_get( $this->get_option_cache_prefix() . $key, $this->get_cache_group() );
		if ( false !== $cached_value ) {
			// Decode the cached value (use null default to make stored null observable) and store in memory.
			$decoded_value               = Automator_Option_Formatter::format_value( $cached_value, null );
			$this->cached_values[ $key ] = $decoded_value;
			return $decoded_value;
		}

		return $default_value;
	}

	/**
	 * Store a value in cache layers.
	 *
	 * @param string $key Option key.
	 * @param mixed  $value Decoded option value.
	 * @param string $serialized_value Serialized option value for object cache.
	 * @param bool   $autoload Whether this is an autoloaded option.
	 * @param bool   $update_object_cache Whether to update WordPress object cache.
	 */
	public function set( $key, $value, $serialized_value, $autoload = false, $update_object_cache = true ) {
		// Always store in memory cache.
		if ( $autoload ) {
			$this->autoloaded[ $key ] = $value;
		} else {
			$this->cached_values[ $key ] = $value;
		}

		// Conditionally store in object cache (serialized for persistence).
		if ( $update_object_cache ) {
			wp_cache_set( $this->get_option_cache_prefix() . $key, $serialized_value, $this->get_cache_group(), $this->get_cache_ttl() );
		}
	}

	/**
	 * Store a cache miss.
	 *
	 * @param string $key Option key that resulted in a miss.
	 */
	public function set_miss( $key ) {
		// Remember miss in memory using MISS sentinel.
		$this->cached_values[ $key ] = self::MISS;

		// Store miss in object cache with short TTL.
		wp_cache_set( $this->get_miss_cache_prefix() . $key, true, $this->get_cache_group(), $this->get_cache_miss_ttl() );
	}

	/**
	 * Delete all cache entries for a key.
	 *
	 * @param string $key Option key.
	 */
	public function delete( $key ) {
		// Remove from memory caches.
		unset( $this->cached_values[ $key ] );
		unset( $this->autoloaded[ $key ] );

		// Remove from object cache.
		wp_cache_delete( $this->get_option_cache_prefix() . $key, $this->get_cache_group() );
		wp_cache_delete( $this->get_miss_cache_prefix() . $key, $this->get_cache_group() );
	}

	/**
	 * Warm the autoloaded cache with database results.
	 *
	 * @param array $autoloaded_options Array of option_name => option_value pairs.
	 */
	public function warm_autoloaded( array $autoloaded_options ) {
		foreach ( $autoloaded_options as $option_name => $option_value ) {
			$this->autoloaded[ $option_name ] = Automator_Option_Formatter::format_value(
				$option_value,
				null
			);
		}
	}

	/**
	 * Clear all in-process caches.
	 * Useful for testing or when you need a fresh state.
	 */
	public function clear_memory() {
		$this->cached_values = array();
		$this->autoloaded    = array();
	}

	/**
	 * Get statistics about current cache state.
	 * Useful for debugging and monitoring.
	 *
	 * @return array Cache statistics.
	 */
	public function get_stats() {
		return array(
			'cached_values_count' => count( $this->cached_values ),
			'autoloaded_count'    => count( $this->autoloaded ),
			'total_memory_items'  => count( $this->cached_values ) + count( $this->autoloaded ),
		);
	}
}
