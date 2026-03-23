<?php
/**
 * Integration Plugin Resolver
 *
 * Resolves plugin file path and plugin data from multiple sources.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Discovery
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Discovery;

use Uncanny_Automator\Api\Services\Plugin\Plugin_Service;

/**
 * Resolves plugin file path and plugin data.
 *
 * Handles priority: manifest > registration > discovery
 *
 * @since 7.0.0
 */
class Integration_Plugin_Resolver {

	/**
	 * Plugin service.
	 *
	 * @var Plugin_Service
	 */
	private $plugin_service;

	/**
	 * Constructor.
	 *
	 * @param Plugin_Service|null $plugin_service Optional plugin service for DI/testing.
	 */
	public function __construct( ?Plugin_Service $plugin_service = null ) {
		$this->plugin_service = $plugin_service ?? new Plugin_Service();
	}

	/**
	 * Resolve plugin file path.
	 *
	 * Priority: manifest > registration > discovery
	 *
	 * @param array $manifest Manifest data
	 * @param string $plugin_file_path Plugin file path from registration
	 * @param string $code Integration code
	 * @param string $name Integration name
	 *
	 * @return string Plugin file path
	 */
	public function resolve_plugin_file( array $manifest, string $plugin_file_path, string $code, string $name ): string {
		// Priority: manifest > registration > discovery.
		$manifest_file = $manifest['plugin_file_path'] ?? '';
		if ( ! empty( $manifest_file ) ) {
			return $this->plugin_service->normalize_plugin_path( $manifest_file );
		}

		if ( ! empty( $plugin_file_path ) ) {
			return $this->plugin_service->normalize_plugin_path( $plugin_file_path );
		}

		// Only discover if we need plugin file for missing crucial fields.
		return $this->plugin_service->discover_integration_plugin_file( $code, $name );
	}

	/**
	 * Get plugin data if needed.
	 *
	 * Only fetches plugin data if crucial fields are missing from manifest.
	 *
	 * @param string $plugin_file Plugin file path
	 * @param bool $has_crucial_fields Whether manifest has all crucial fields
	 *
	 * @return array Plugin data (empty if not needed or not available)
	 */
	public function get_plugin_data_if_needed( string $plugin_file, bool $has_crucial_fields ): array {
		// Only run plugin discovery if crucial fields are missing.
		if ( $has_crucial_fields || empty( $plugin_file ) ) {
			return array();
		}

		// Run plugin discovery to fill missing crucial fields.
		return $this->plugin_service->get_plugin_data( $plugin_file );
	}

	/**
	 * Resolve external URL from multiple sources.
	 *
	 * Priority: manifest > plugin data > fallback
	 *
	 * @param array $manifest Manifest data
	 * @param array $plugin_data Plugin data
	 * @param string $integration_name Integration name
	 *
	 * @return string External URL
	 */
	public function resolve_external_url( array $manifest, array $plugin_data, string $integration_name ): string {
		// Priority: manifest > plugin data > fallback.
		$external_url = $manifest['developer_site'] ?? '';

		if ( empty( $external_url ) && ! empty( $plugin_data ) ) {
			$external_url = $plugin_data['PluginURI'] ?? '';
			if ( empty( $external_url ) ) {
				$external_url = $plugin_data['AuthorURI'] ?? '';
			}
		}

		// Final fallback if still empty.
		if ( empty( $external_url ) ) {
			$search_name = ! empty( $plugin_data ) ? ( $plugin_data['Name'] ?? $integration_name ) : $integration_name;
			if ( ! empty( $search_name ) ) {
				$external_url = $this->plugin_service->get_plugin_install_search_url( $search_name );
			} else {
				$external_url = 'https://automatorplugin.com/integrations/';
			}
		}

		return $external_url;
	}

	/**
	 * Get description from plugin data.
	 *
	 * @param array $plugin_data Plugin data
	 *
	 * @return string Description
	 */
	public function get_plugin_description( array $plugin_data ): string {
		return ! empty( $plugin_data ) ? ( $plugin_data['Description'] ?? '' ) : '';
	}

	/**
	 * Process required plugins from plugin data.
	 *
	 * @param array $plugin_data Plugin data
	 *
	 * @return array Required plugins (empty if not available)
	 */
	public function get_required_plugins( array $plugin_data ): array {
		if ( empty( $plugin_data ) ) {
			return array();
		}

		$required_plugins_string = $this->plugin_service->process_required_plugins( $plugin_data['RequiresPlugins'] ?? '' );

		// process_required_plugins returns ?string, convert to array.
		if ( empty( $required_plugins_string ) ) {
			return array();
		}

		// Convert comma-separated string to array.
		return array_map( 'trim', explode( ',', $required_plugins_string ) );
	}
}
