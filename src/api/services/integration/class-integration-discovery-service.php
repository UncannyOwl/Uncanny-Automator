<?php
/**
 * Integration Discovery Service
 *
 * Auto-discovers 3rd party integrations and caches by plugin version.
 *
 * @package Uncanny_Automator\Api\Services\Integration
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration;

use Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Integration_Manifest_Resolver;
use Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Integration_Plugin_Resolver;
use Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Integration_Data_Resolver;
use Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items\Trigger_Discoverer;
use Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items\Action_Discoverer;
use Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items\Filter_Condition_Discoverer;
use Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items\Loop_Filter_Discoverer;
use Uncanny_Automator\Api\Services\Plugin\Plugin_Service;
use Uncanny_Automator\Traits\Singleton;

/**
 * Discovers and completes integration data from local registrations.
 *
 * Performance: Uses uap_option cache keyed by plugin version.
 *
 * @since 7.0.0
 */
class Integration_Discovery_Service {

	use Singleton;

	/**
	 * Option name for discovery cache.
	 *
	 * @var string
	 */
	const CACHE_OPTION = 'uap_integration_discovery_cache';

	/**
	 * Integration registry service.
	 *
	 * @var Integration_Registry_Service
	 */
	private $registry;

	/**
	 * Manifest resolver.
	 *
	 * @var Integration_Manifest_Resolver
	 */
	private $manifest_resolver;

	/**
	 * Plugin resolver.
	 *
	 * @var Integration_Plugin_Resolver
	 */
	private $plugin_resolver;

	/**
	 * Data resolver.
	 *
	 * @var Integration_Data_Resolver
	 */
	private $data_resolver;

	/**
	 * Item discoverers.
	 *
	 * @var array
	 */
	private $item_discoverers;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->registry          = Integration_Registry_Service::get_instance();
		$this->manifest_resolver = new Integration_Manifest_Resolver();
		$this->plugin_resolver   = new Integration_Plugin_Resolver();
		$this->item_discoverers  = array(
			'trigger'          => new Trigger_Discoverer(),
			'action'           => new Action_Discoverer(),
			'filter_condition' => new Filter_Condition_Discoverer(),
			'loop_filter'      => new Loop_Filter_Discoverer(),
		);

		// Pass dependencies to data resolver.
		$this->data_resolver = new Integration_Data_Resolver(
			$this->plugin_resolver,
			$this->item_discoverers
		);
	}

	/**
	 * Discover integration by code directly.
	 *
	 * Used for integrations not in complete.json.
	 * Uses caching keyed by code + version.
	 *
	 * @param string $code Integration code
	 * @return array|null Discovered integration data
	 */
	public function discover_integration_by_code( $code ) {
		// Try cache first.
		$cached = $this->get_cached_integration_data( $code );
		if ( null !== $cached ) {
			return $cached;
		}

		// Not cached, discover it.
		$discovered = $this->build_integration_data( $code );

		if ( ! empty( $discovered ) ) {
			$this->cache_integration_data( $code, $discovered );
		}

		return $discovered;
	}

	/**
	 * Get cached integration data by code.
	 *
	 * Checks if cached version matches current version.
	 * If outdated, returns null to trigger cache refresh.
	 *
	 * @param string $code Integration code
	 *
	 * @return array|null Cached data or null if not cached or outdated
	 */
	private function get_cached_integration_data( $code ) {
		$cache = $this->get_cache();

		if ( ! isset( $cache[ $code ] ) ) {
			return null;
		}

		$cached_data    = $cache[ $code ];
		$cached_version = $cached_data['_cache_version'] ?? '';

		// Get current version to compare with cached version.
		$current_version = $this->get_integration_version( $code );
		if ( empty( $current_version ) ) {
			return null;
		}

		// If versions don't match, cache is outdated.
		if ( $cached_version !== $current_version ) {
			// Clear outdated cache.
			unset( $cache[ $code ] );
			$this->save_cache( $cache );
			return null;
		}

		return $cached_data;
	}

	/**
	 * Get integration version.
	 *
	 * Resolves version from manifest, plugin data, or plugin file.
	 * Used for cache validation to determine if cached data is still valid.
	 *
	 * @param string $code Integration code
	 *
	 * @return string Version string or empty string if not found
	 */
	private function get_integration_version( $code ) {
		$integration = $this->registry->get_integration_full( $code );
		if ( null === $integration ) {
			return '';
		}

		$manifest = $this->manifest_resolver->extract_manifest( $integration );

		// Check manifest first - if it has version, return it.
		$version = $manifest['integration_version'] ?? '';
		if ( ! empty( $version ) ) {
			return $version;
		}

		// Manifest doesn't have version, resolve from plugin file and data.
		$plugin_file_path = $integration['plugin_file_path'] ?? '';
		$manifest_file    = $this->manifest_resolver->get_plugin_file_path( $manifest );
		$plugin_file      = ! empty( $manifest_file ) ? $manifest_file : $plugin_file_path;

		$plugin_data = array();
		if ( ! empty( $plugin_file ) ) {
			$plugin_service = new Plugin_Service();
			$plugin_data    = $plugin_service->get_plugin_data( $plugin_file );
		}

		return Integration_Data_Resolver::resolve_version(
			$manifest,
			$plugin_data,
			$plugin_file
		);
	}

	/**
	 * Save integration data to cache.
	 *
	 * Stores data keyed by integration code and includes version for validation.
	 *
	 * @param string $code Integration code
	 * @param array $data Integration data
	 *
	 * @return void
	 */
	private function cache_integration_data( $code, $data ) {
		$version = $data['_plugin_version'] ?? '';
		if ( empty( $version ) ) {
			return;
		}

		// Store version with data for validation.
		$data['_cache_version'] = $version;

		$cache          = $this->get_cache();
		$cache[ $code ] = $data;
		$this->save_cache( $cache );
	}

	/**
	 * Build integration data from registration.
	 *
	 * Discovers integration data from local registration.
	 * Does not handle caching - caller should check cache first.
	 *
	 * @param string $code Integration code
	 *
	 * @return array|null Discovered integration data
	 */
	private function build_integration_data( $code ) {
		$integration = $this->registry->get_integration_full( $code );
		if ( null === $integration ) {
			return null;
		}

		$plugin_file_path = $integration['plugin_file_path'] ?? '';
		$integration_name = $integration['name'] ?? '';

		// Extract manifest data.
		$manifest = $this->manifest_resolver->extract_manifest( $integration );

		// Resolve plugin file (priority: manifest > registration > discovery).
		$plugin_file = $this->plugin_resolver->resolve_plugin_file(
			$manifest,
			$plugin_file_path,
			$code,
			$integration_name
		);

		// Check if we have all crucial fields from manifest.
		$has_crucial_fields = $this->manifest_resolver->has_all_crucial_fields( $manifest );

		// Get plugin data if needed (only if crucial fields are missing).
		$plugin_data = $this->plugin_resolver->get_plugin_data_if_needed(
			$plugin_file,
			$has_crucial_fields
		);

		// Resolve integration data (includes _plugin_version).
		return $this->data_resolver->resolve(
			$code,
			$integration,
			$manifest,
			$plugin_file,
			$plugin_data
		);
	}

	/**
	 * Discover triggers for integration.
	 *
	 * @param string $code Integration code
	 * @param string $name Integration name
	 *
	 * @return array Trigger data
	 */
	public function discover_triggers( $code, $name ) {
		return $this->item_discoverers['trigger']->discover( $code, $name );
	}

	/**
	 * Discover actions for integration.
	 *
	 * @param string $code Integration code
	 * @param string $name Integration name
	 *
	 * @return array Action data
	 */
	public function discover_actions( $code, $name ) {
		return $this->item_discoverers['action']->discover( $code, $name );
	}

	/**
	 * Discover loop filters for integration.
	 *
	 * @param string $code Integration code
	 * @param string $name Integration name
	 *
	 * @return array Loop filter data
	 */
	public function discover_loop_filters( $code, $name ) {
		return $this->item_discoverers['loop_filter']->discover( $code, $name );
	}

	/**
	 * Discover conditions for integration.
	 *
	 * @param string $code Integration code
	 * @param string $name Integration name
	 *
	 * @return array Condition data
	 */
	public function discover_conditions( $code, $name ) {
		return $this->item_discoverers['filter_condition']->discover( $code, $name );
	}

	/**
	 * Clear discovery cache for integration.
	 *
	 * Call when integration changes or plugin updates.
	 *
	 * @param string $code Integration code
	 *
	 * @return void
	 */
	public function clear_cache( $code ) {
		$cache = $this->get_cache();

		if ( isset( $cache[ $code ] ) ) {
			unset( $cache[ $code ] );
			$this->save_cache( $cache );
		}
	}

	/**
	 * Get cache.
	 *
	 * @return array Cache
	 */
	private function get_cache() {
		return automator_get_option( self::CACHE_OPTION, array() );
	}

	/**
	 * Save cache.
	 *
	 * @param array $cache Cache
	 *
	 * @return bool Result
	 */
	private function save_cache( $cache ) {
		return automator_update_option( self::CACHE_OPTION, $cache, true );
	}

	/**
	 * Clear all discovery caches.
	 *
	 * @return void
	 */
	public function clear_all_caches() {
		automator_delete_option( self::CACHE_OPTION );
	}
}
