<?php
namespace Uncanny_Automator;

/**
 * Automator Options class.
 *
 * Provides a high-performance caching layer for WordPress options with support
 * for custom uap_options table and legacy value encoding/decoding.
 *
 * @package Uncanny_Automator
 *
 * @requires Database schema: UNIQUE index on uap_options(option_name) for upsert operations
 *
 * @filter uap_options_cache_ttl - Modify cache TTL for option values (default: 3600 seconds)
 * @filter uap_options_cache_miss_ttl - Modify cache TTL for cache misses (default: 300 seconds)
 *
 * Type policy:
 * - Stored values follow legacy encoding for booleans/null ('__true__', '__false__', '__null__').
 * - Other scalars/arrays are stored serialized; on read, we decode and return best-effort types.
 * - Original PHP types may not be fully reconstructed when type metadata is absent; numeric strings
 *   may remain strings. Callers that require strict typing should normalize explicitly.
 *
 * Equality policy:
 * - update_option() uses strict comparison (===). Values that are equal but not identical (e.g., "1" vs 1)
 *   are considered different and will trigger an upsert. This is intentional to avoid silent coercions.
 */
final class Automator_Options {

	/**
	 * The database query handler.
	 *
	 * @var Automator_Options_Query
	 */
	private $query;

	/**
	 * The caching handler.
	 *
	 * @var Automator_Options_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 */
	public function __construct( ?Automator_Options_Query $query = null, ?Automator_Options_Cache $cache = null ) {

		// Allow soft dependency injection.
		$this->query = $query ?? new Automator_Options_Query();
		$this->cache = $cache ?? new Automator_Options_Cache();

		$this->warm_autoloaded();
	}

	/**
	 * Get option.
	 *
	 * @param string $key The key.
	 * @param mixed $default_value The default value.
	 * @param bool $skip_cache Whether to skip cache and read fresh from database. When true:
	 *                         - Reads directly from database (ignores all cached data)
	 *                         - Saves result for this request only (won't update shared cache)
	 *                         - Use when you need the absolute latest value from database
	 * @return mixed The option value or default if not found.
	 */
	public function get_option( string $key, $default_value = null, $skip_cache = false ) {

		$key = $this->validate_key( $key );
		if ( false === $key ) {
			return $default_value;
		}

		return ( true === $skip_cache )
			// Skip cache: read fresh from database.
			? $this->get_fresh_from_database( $key, $default_value )
			// Use cache: check cached values first.
			: $this->get_from_cached( $key, $default_value );
	}

	/**
	 * Get cache group.
	 *
	 * @return string
	 */
	public function get_cache_group() {
		return $this->cache->get_cache_group();
	}

	/**
	 * Get option cache prefix.
	 *
	 * @return string
	 */
	public function get_option_cache_prefix() {
		return $this->cache->get_option_cache_prefix();
	}

	/**
	 * Get miss cache prefix.
	 *
	 * @return string
	 */
	public function get_miss_cache_prefix() {
		return $this->cache->get_miss_cache_prefix();
	}

	/**
	 * Validate and sanitize option key.
	 *
	 * @param string $key The key to validate.
	 * @return string|false Trimmed key or false if invalid.
	 */
	private function validate_key( string $key ) {
		$key = trim( $key );
		return ( '' === $key ) ? false : $key;
	}

	/**
	 * Get the value from the cached.
	 *
	 * @param string $key The key.
	 * @param mixed $default_value The default value.
	 * @return mixed
	 */
	private function get_from_cached( $key, $default_value ) {

		// Check in-process memory cache first.
		$memory_value = $this->cache->get_from_memory( $key, Automator_Options_Cache::ABSENT );

		// If the value from the memory is not ABSENT, return it.
		if ( Automator_Options_Cache::ABSENT !== $memory_value ) {

			// Do check if the value is MISS.
			if ( Automator_Options_Cache::MISS === $memory_value ) {
				return $default_value; // cached negative in memory
			}

			// If the value is an empty string with null as default, return the default value which is null.
			if ( is_scalar( $memory_value ) && is_string( $memory_value ) && '' === $memory_value && is_null( $default_value ) ) {
				return $default_value;
			}

			// Policy switch ON: make stored null observable; only missing maps to default.
			return $memory_value;

		}

		// Check object cache.
		$object_cache_value = $this->cache->get_from_object_cache( $key, Automator_Options_Cache::ABSENT );
		// Short-circuit on explicit miss sentinel to avoid DB hit this request.
		if ( Automator_Options_Cache::MISS === $object_cache_value ) {
			return $default_value;
		}
		// If object cache returned a real value (not ABSENT), use it.
		if ( Automator_Options_Cache::ABSENT !== $object_cache_value ) {
			return $object_cache_value;
		}

		// Fallback to database with caching.
		return $this->fetch_and_cache_from_database( $key, $default_value );
	}

	/**
	 * Get option directly from database, bypassing all caches.
	 *
	 * @param string $key The option key.
	 * @param mixed $default_value The default value.
	 *
	 * @return mixed The option value.
	 */
	private function get_fresh_from_database( string $key, $default_value ) {
		return $this->fetch_from_database_with_caching( $key, $default_value, false );
	}

	/**
	 * Fetch option from database with full caching.
	 *
	 * @param string $key The option key.
	 * @param mixed $default_value The default value.
	 *
	 * @return mixed The option value.
	 */
	private function fetch_and_cache_from_database( string $key, $default_value ) {
		return $this->fetch_from_database_with_caching( $key, $default_value, true );
	}

	/**
	 * Fetch option from database with conditional caching.
	 *
	 * @param string $key The option key.
	 * @param mixed $default_value The default value.
	 * @param bool $update_object_cache Whether to update object cache.
	 *
	 * @return mixed The option value.
	 */
	private function fetch_from_database_with_caching( string $key, $default_value, bool $update_object_cache ) {

		$raw_value = $this->fetch_raw_value_from_database( $key );

		// Handle database errors.
		if ( null === $raw_value && $this->query->get_db()->last_error ) {

			$error = $this->query->get_db()->last_error;

			// Send an action hook when Automator_Options encounters a database error fetching an option.
			do_action( 'uap_options_db_error', $error, $key );

			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// Log only when debug logging is enabled.
				error_log( 'Automator_Options DB error: ' . $error ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			}

			return $default_value;
		}

		// Handle missing option.
		if ( null === $raw_value ) {
			if ( $update_object_cache ) {
				$this->cache->set_miss( $key );
			}
			return $default_value;
		}

		// Decode and cache the value.
		// Use null as default to keep stored '__null__' observable and consistent with cache paths.
		$decoded_value = Automator_Option_Formatter::format_value( $raw_value, null );
		$this->cache->set( $key, $decoded_value, $raw_value, false, $update_object_cache );

		return $decoded_value;
	}

	/**
	 * Fetch raw option value from database.
	 *
	 * @param string $key The option key.
	 * @return string|null Raw option value or null if not found.
	 */
	private function fetch_raw_value_from_database( string $key ) {
		return $this->query->get_option_value( $key );
	}

	/**
	 * Fetch the options from the uap_options table.
	 *
	 * @return array
	 */
	public function fetch_options_from_uap() {
		return $this->query->get_autoloaded_uap_options();
	}

	/**
	 * Fetch the options from the wp_options table.
	 *
	 * @return array
	 */
	public function fetch_options_from_wp() {

		$options_keys = include trailingslashit( __DIR__ ) . 'automator-options/array-option-keys.php';

		if ( empty( $options_keys ) ) {
			return array();
		}

		return $this->query->get_autoloaded_wp_options( $options_keys );
	}

	/**
	 * Warm the autoloaded options.
	 */
	public function warm_autoloaded() {

		$result_wp_options  = $this->fetch_options_from_wp();
		$result_uap_options = $this->fetch_options_from_uap();

		// The array $uap_options takes precedence over $wp_options.
		$results = array_merge( (array) $result_wp_options, (array) $result_uap_options );

		// Convert results to option_name => option_value format for cache.
		$autoloaded_options = array();
		foreach ( $results as $result ) {
			$autoloaded_options[ $result['option_name'] ] = $result['option_value'];
		}

		// Let cache handle the warming.
		$this->cache->warm_autoloaded( $autoloaded_options );
	}



	/**
	 * Add an option to the database.
	 *
	 * RACE CONDITION: option_exists() → insert_option() has a classic race.
	 * May return false if another process created the option concurrently.
	 * This is acceptable behavior due to UNIQUE constraint protection.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param bool $autoload
	 * @param bool $run_action_hooks
	 *
	 * @return bool True on success, false if option exists or on failure.
	 */
	public function add_option( string $key, $value, bool $autoload = true, bool $run_action_hooks = true ) {

		$key = $this->validate_key( $key );
		if ( false === $key ) {
			return false;
		}

		// Check if option already exists in cache or database.
		$existing = $this->cache->get_from_memory( $key, Automator_Options_Cache::ABSENT );
		if ( Automator_Options_Cache::ABSENT !== $existing && Automator_Options_Cache::MISS !== $existing ) {
			return false; // exists in memory/autoloaded
		}

		// Check database.
		if ( $this->query->option_exists( $key ) ) {
			return false;
		}

		// Fire actions before adding or updating the option.
		if ( $run_action_hooks ) {
			do_action( 'automator_before_add_option', $key, $value, $autoload );
		}

		// Add to database.
		$encoded_value    = Automator_Option_Formatter::encode_value( $value );
		$serialized_value = maybe_serialize( $encoded_value );

		if ( false === $this->query->insert_option( $key, $serialized_value, $autoload ) ) {
			return false;
		}

		// Delete any existing cache entries.
		$this->cache->delete( $key );

		// Update cache with new value.
		$this->cache->set( $key, $value, $serialized_value, $autoload );

		// Fire post-add/update actions.
		if ( $run_action_hooks ) {
			do_action( "automator_add_option_{$key}", $key, $value );
			do_action( 'automator_option_added', $key, $value );
		}

		return true;
	}

	/**
	 * Update an option in the database.
	 *
	 * @param string $key The option key.
	 * @param mixed $value The option value.
	 * @param boolean $autoload Whether to autoload the option.
	 *
	 * @return bool True on success (including no-op when value unchanged), false on failure.
	 */
	public function update_option( string $key, $value, bool $autoload = true ) {

		$key = $this->validate_key( $key );
		if ( false === $key ) {
			return false;
		}

		// Check if option exists and value is unchanged.
		$exists = $this->query->option_exists( $key );
		if ( true === $exists ) {
			$current = $this->get_option( $key );
			if ( $current === $value ) {
				// Value unchanged - this is a successful no-op
				return true;
			}
		}

		$encoded_value    = Automator_Option_Formatter::encode_value( $value );
		$serialized_value = maybe_serialize( $encoded_value );

		// Upsert to database.
		if ( false === $this->query->upsert_option( $key, $serialized_value, $autoload ) ) {
			return false;
		}

		// Delete existing cache entries.
		$this->cache->delete( $key );

		// Update cache with new value.
		$this->cache->set( $key, $value, $serialized_value, $autoload );

		do_action( "automator_update_option_{$key}", $key, $value );
		do_action( 'automator_updated_option', $key, $value );

		return true;
	}

	/**
	 * Delete an option from the database.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function delete_option( string $key ) {

		$key = $this->validate_key( $key );
		if ( false === $key ) {
			return false;
		}

		// Delete from database.
		$result = $this->query->delete_option( $key );

		// Always clean cache regardless of database result.
		$this->cache->delete( $key );

		return $result;
	}
}
