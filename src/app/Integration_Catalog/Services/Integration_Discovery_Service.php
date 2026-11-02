<?php
/**
 * Integration Discovery Service
 *
 * Auto-discovers 3rd party integrations and caches by fingerprint.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Services
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Services;

use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Discovery\Integration_Manifest_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Discovery\Integration_Plugin_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Discovery\Integration_Data_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Discovery\Items\Trigger_Discoverer;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Discovery\Items\Action_Discoverer;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Discovery\Items\Filter_Condition_Discoverer;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Discovery\Items\Loop_Filter_Discoverer;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store\Catalog_Build_Report;
use Uncanny_Automator\App\Plan\Services\Plugin\Plugin_Service;
use Uncanny_Automator\App\Recipe_Builder\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\App\Recipe_Builder\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Registry\WP_Trigger_Registry;
use Uncanny_Automator\Traits\Singleton;

/**
 * Discovers and completes integration data from local registrations.
 *
 * Performance: Uses uap_option cache keyed by a fingerprint of the
 * integration's version, manifest and item codes, so an entry lives until
 * any of them changes.
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
	 * Trigger registry the fingerprint reads, loaded once per request.
	 *
	 * @var WP_Trigger_Registry|null
	 */
	private ?WP_Trigger_Registry $trigger_registry = null;

	/**
	 * Action registry the fingerprint reads, loaded once per request.
	 *
	 * @var WP_Action_Registry|null
	 */
	private ?WP_Action_Registry $action_registry = null;

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
	 * Return an integration's discovered data only if it is already cached.
	 *
	 * Unlike discover_integration_by_code(), this never builds. Callers that
	 * run outside a load-all request use it so a partially loaded action set
	 * is never written into the cache.
	 *
	 * @param string $code Integration code.
	 * @return array|null Cached data, or null when absent or outdated.
	 */
	public function get_cached_integration( $code ) {
		return $this->get_cached_integration_data( $code, $this->fingerprint( $code ) );
	}

	/**
	 * Discover integration by code directly.
	 *
	 * Used for integrations not in complete.json.
	 * Uses caching keyed by code + fingerprint. With the catalog debug switch
	 * on (Catalog_Build_Report::enabled()) the cache is never read, only
	 * rewritten, so every build is fresh and turning the switch off leaves a
	 * correct entry behind.
	 *
	 * @param string $code Integration code
	 * @return array|null Discovered integration data
	 */
	public function discover_integration_by_code( $code ) {
		$fingerprint = $this->fingerprint( $code );
		$bypass      = Catalog_Build_Report::enabled();

		// Try cache first, unless the developer switch asks for a fresh build.
		if ( ! $bypass ) {
			$cached = $this->get_cached_integration_data( $code, $fingerprint );
			if ( null !== $cached ) {
				$this->record_discovery( $code, 'cache', $fingerprint, $cached );
				return $cached;
			}
		}

		// Not cached, discover it.
		$discovered = $this->build_integration_data( $code );

		if ( ! empty( $discovered ) ) {
			$this->cache_integration_data( $code, $discovered, $fingerprint );
			$this->record_discovery( $code, $bypass ? 'bypass' : 'rebuilt', $fingerprint, $discovered );
		}

		return $discovered;
	}

	/**
	 * Tell the build report how a discovered entry was resolved.
	 *
	 * @param string $code        Integration code
	 * @param string $source      `cache`, `rebuilt` or `bypass`.
	 * @param string $fingerprint Fingerprint the entry carries.
	 * @param array  $data        The entry returned to the caller.
	 *
	 * @return void
	 */
	private function record_discovery( $code, string $source, string $fingerprint, array $data ): void {
		Catalog_Build_Report::integration_discovered(
			(string) $code,
			$source,
			$fingerprint,
			array(
				'triggers'     => count( (array) ( $data['integration_triggers'] ?? array() ) ),
				'actions'      => count( (array) ( $data['integration_actions'] ?? array() ) ),
				'conditions'   => count( (array) ( $data['integration_conditions'] ?? array() ) ),
				'loop_filters' => count( (array) ( $data['integration_loop_filters'] ?? array() ) ),
			),
			(string) ( $data['_plugin_version'] ?? '' )
		);
	}

	/**
	 * Get cached integration data by code.
	 *
	 * Checks if the cached fingerprint matches the current one. An entry
	 * without a fingerprint was written before fingerprints existed and is
	 * treated as outdated.
	 *
	 * @param string $code        Integration code
	 * @param string $fingerprint Current fingerprint, from fingerprint().
	 *
	 * @return array|null Cached data or null if not cached or outdated
	 */
	private function get_cached_integration_data( $code, string $fingerprint ) {
		$cache = $this->get_cache();

		if ( ! isset( $cache[ $code ] ) ) {
			return null;
		}

		if ( '' === $fingerprint ) {
			return null;
		}

		$cached_data = $cache[ $code ];

		if ( ( $cached_data['_cache_fingerprint'] ?? '' ) !== $fingerprint ) {
			unset( $cache[ $code ] );
			$this->save_cache( $cache );
			return null;
		}

		$cached_data['integration_icon'] = self::in_request_scheme( (string) ( $cached_data['integration_icon'] ?? '' ) );

		return $cached_data;
	}

	/**
	 * An icon URL on this site, in the scheme of the current request.
	 *
	 * WordPress builds a registered integration's icon with plugins_url(), in the scheme of the request that builds
	 * it, and the cache keeps the icon with the entry. An entry built under WP-CLI, a system cron or a loopback request
	 * on a site served over https holds an http icon, which a browser blocks as mixed content on an https page. This
	 * gives the icon the scheme plugins_url() gives the current request. An icon on another host keeps its scheme.
	 *
	 * @since 8.0.0
	 *
	 * @param string $url Icon URL as cached.
	 *
	 * @return string The icon URL for this request.
	 */
	private static function in_request_scheme( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || wp_parse_url( plugins_url(), PHP_URL_HOST ) !== $host ) {
			return $url;
		}

		return set_url_scheme( $url );
	}

	/**
	 * Fingerprint of everything a discovered entry is built from.
	 *
	 * Covers the version (a release still invalidates), the registration
	 * manifest (type, tier, name, links) and the sorted codes of the
	 * integration's triggers, actions, conditions and loop filters, read the
	 * way Integration_Map_Builder::build_registered_map() reads them.
	 *
	 * @param string $code Integration code
	 *
	 * @return string md5 hash, or an empty string when the integration is not registered.
	 */
	private function fingerprint( $code ): string {
		$integration = $this->registry->get_integration_full( $code );
		if ( null === $integration ) {
			return '';
		}

		// One load per request: on a cold feed every registered integration is fingerprinted.
		$this->trigger_registry = $this->trigger_registry ?? new WP_Trigger_Registry();
		$this->action_registry  = $this->action_registry ?? new WP_Action_Registry();

		$triggers = array_keys( $this->trigger_registry->get_triggers_by_integration( $code ) );
		$actions  = array_keys( $this->action_registry->get_actions_by_integration( $code ) );

		$conditions = array();
		$result     = Condition_Registry_Service::instance()->list_conditions( array( 'integration' => $code ) );
		if ( ! is_wp_error( $result ) && ! empty( $result['conditions'] ) ) {
			$conditions = array_column( $result['conditions'], 'condition_code' );
		}

		$loop_filters = array_keys( Filter_Registry_Service::instance()->get_filters_by_integration( $code ) );

		sort( $triggers );
		sort( $actions );
		sort( $conditions );
		sort( $loop_filters );

		return md5(
			(string) wp_json_encode(
				array(
					'version'      => $this->get_integration_version( $code ),
					'manifest'     => $this->manifest_resolver->extract_manifest( $integration ),
					'triggers'     => $triggers,
					'actions'      => $actions,
					'conditions'   => $conditions,
					'loop_filters' => $loop_filters,
				)
			)
		);
	}

	/**
	 * Get integration version.
	 *
	 * Resolves version from manifest, plugin data, or plugin file.
	 * Part of the cache fingerprint, so a release invalidates the entry.
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
	 * Stores data keyed by integration code with the fingerprint it is
	 * validated against. The version is stamped too, for readability only.
	 *
	 * @param string $code        Integration code
	 * @param array  $data        Integration data
	 * @param string $fingerprint Fingerprint the entry was built under.
	 *
	 * @return void
	 */
	private function cache_integration_data( $code, $data, string $fingerprint ) {
		$version = $data['_plugin_version'] ?? '';
		if ( empty( $version ) || '' === $fingerprint ) {
			return;
		}

		$data['_cache_version']     = $version;
		$data['_cache_fingerprint'] = $fingerprint;

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
