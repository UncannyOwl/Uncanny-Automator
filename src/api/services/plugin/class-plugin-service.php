<?php
/**
 * Plugin Service
 *
 * A thin facade providing a simple API for plugin status operations.
 *
 * @package Uncanny_Automator\Api\Services\Plugin
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Plugin;

use Uncanny_Automator\Services\Plugin\Info;

/**
 * Plugin Service.
 *
 * Provides a clean interface to plugin status operations.
 *
 * @since 7.0.0
 */
class Plugin_Service {

	/**
	 * Check if Pro plugin is installed.
	 *
	 * @return bool
	 */
	public function is_pro_installed(): bool {
		return Info::is_pro_plugin_installed();
	}

	/**
	 * Check if Pro plugin is active.
	 *
	 * @return bool
	 */
	public function is_pro_active(): bool {
		return Info::is_pro_plugin_active();
	}

	/**
	 * Discover integration plugin file by integration code or name.
	 *
	 * Searches installed plugins to find a match, prioritizing integration plugins.
	 *
	 * @param string $integration_code Integration code (e.g., 'acymailing', 'PAYPAL')
	 * @param string $integration_name Integration name (e.g., 'AcyMailing', 'PayPal')
	 * @return string Plugin file path (e.g., 'plugin-dir/plugin.php') or empty string if not found
	 */
	public function discover_integration_plugin_file( string $integration_code, string $integration_name = '' ): string {
		return Info::discover_integration_plugin_file( $integration_code, $integration_name );
	}

	/**
	 * Get plugin data (metadata) from plugin file.
	 *
	 * @param string $plugin_file Plugin file path relative to plugins directory
	 * @return array Plugin data array or empty array if not found
	 */
	public function get_plugin_data( string $plugin_file ): array {
		return Info::get_plugin_data( $plugin_file );
	}

	/**
	 * Get plugin version from plugin file.
	 *
	 * @param string $plugin_file Plugin file path relative to plugins directory
	 * @return string Plugin version or '1.0.0' as fallback
	 */
	public function get_plugin_version( string $plugin_file ): string {
		return Info::get_plugin_version( $plugin_file );
	}

	/**
	 * Normalize plugin path to relative format.
	 *
	 * Converts absolute paths to relative and normalizes directory separators.
	 *
	 * @param string $plugin_file_path Plugin file path (absolute or relative)
	 * @return string Normalized relative plugin path
	 */
	public function normalize_plugin_path( string $plugin_file_path ): string {
		return Info::normalize_plugin_path( $plugin_file_path );
	}

	/**
	 * Get plugin install search URL.
	 *
	 * @param string $name Plugin name to search
	 * @return string WordPress.org plugin search URL
	 */
	public function get_plugin_install_search_url( string $name ): string {
		return Info::get_plugin_install_search_url( $name );
	}

	/**
	 * Get plugin search URL.
	 *
	 * @param string $name Plugin name to search
	 * @return string WordPress.org plugin search URL
	 */
	public function get_plugin_search_url( string $name ): string {
		return Info::get_plugin_search_url( $name );
	}

	/**
	 * Process RequiresPlugins field and strip uncanny-automator.
	 *
	 * @param string $requires_plugins Comma-separated list of required plugins
	 * @return string|null Filtered list or null if empty
	 */
	public function process_required_plugins( string $requires_plugins ): ?string {
		if ( empty( $requires_plugins ) ) {
			return null;
		}

		$plugins = array_map( 'trim', explode( ',', $requires_plugins ) );
		$plugins = array_filter(
			$plugins,
			function ( $plugin ) {
				return false === strpos( $plugin, 'uncanny-automator' );
			}
		);

		return ! empty( $plugins ) ? implode( ', ', $plugins ) : null;
	}
}
