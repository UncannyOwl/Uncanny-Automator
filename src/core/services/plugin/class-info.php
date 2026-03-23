<?php

namespace Uncanny_Automator\Services\Plugin;

/**
 * Info - Helper class to get info about a plugin.
 *
 * @package Uncanny_Automator\Services\Plugin
 */
class Info {

	/**
	 * The constant name for the Pro plugin file.
	 *
	 * @var string
	 */
	private const PRO_PLUGIN_FILE_CONSTANT = 'AUTOMATOR_PRO_FILE';

	/**
	 * Get the Pro plugin file.
	 *
	 * @return string|false The Pro plugin file path if found, false otherwise.
	 */
	public static function get_pro_plugin_file() {
		return defined( self::PRO_PLUGIN_FILE_CONSTANT )
			? self::get_relative_path_to_plugins_directory( constant( self::PRO_PLUGIN_FILE_CONSTANT ) )
			: false;
	}

	/**
	 * Convert an absolute plugin path to a relative path.
	 *
	 * @param string $absolute_path The absolute path to convert.
	 *
	 * @return string The relative path from the plugins directory.
	 */
	private static function get_relative_path_to_plugins_directory( $absolute_path ) {
		return str_replace(
			trailingslashit( WP_PLUGIN_DIR ),
			'',
			$absolute_path
		);
	}

	/**
	 * Get the addon plugin file.
	 *
	 * @param array $addon - The addon details from Uncanny_Automator\Services\Addons\Data\External_Feed.
	 *
	 * @return string|false The addon plugin file path if found, false otherwise.
	 */
	public static function get_addon_plugin_file( $addon ) {

		if ( empty( $addon['key'] ) || empty( $addon['name'] ) ) {
			return false;
		}

		// Try to get the plugin file from its constant first
		$constant_name = $addon['key'] . '_PLUGIN_FILE';
		if ( self::does_plugin_constant_exist( $constant_name ) ) {
			return self::get_relative_path_to_plugins_directory( constant( $constant_name ) );
		}

		// Fallback to generating the path manually.
		$slug           = str_replace( ' ', '-', strtolower( $addon['name'] ) );
		$elite_suffix   = 'UAEI' === $addon['key'] ? '-addon' : '';
		$generated_path = sprintf(
			'uncanny-automator-%1$s%2$s/uncanny-automator-%1$s%2$s.php',
			$slug,
			$elite_suffix
		);

		return $generated_path;
	}

	/**
	 * Check if the Pro plugin is installed.
	 *
	 * @return bool
	 */
	public static function is_pro_plugin_installed() {
		return self::get_pro_plugin_file()
			? self::is_plugin_installed( self::get_pro_plugin_file() )
			: false;
	}

	/**
	 * Check if the Pro plugin is active.
	 *
	 * @return bool
	 */
	public static function is_pro_plugin_active() {
		return self::get_pro_plugin_file()
			? self::is_plugin_active( self::get_pro_plugin_file() )
			: false;
	}

	/**
	 * Check if a plugin is installed.
	 *
	 * @param string $plugin_file The plugin file path relative to the plugins directory.
	 *
	 * @return bool True if the plugin is installed, false otherwise.
	 */
	public static function is_plugin_installed( $plugin_file ) {
		return file_exists( trailingslashit( WP_PLUGIN_DIR ) . $plugin_file );
	}

	/**
	 * Check if a plugin is installed but not active.
	 *
	 * @param string $plugin_file The plugin file path relative to the plugins directory.
	 *
	 * @return bool True if the plugin is installed but not active, false otherwise.
	 */
	public static function is_plugin_installed_but_inactive( $plugin_file ) {
		return self::is_plugin_installed( $plugin_file ) && ! self::is_plugin_active( $plugin_file );
	}

	/**
	 * Check if a plugin is installed and active.
	 *
	 * @param string $plugin_file The plugin file path relative to the plugins directory.
	 *
	 * @return bool True if the plugin is installed and active, false otherwise.
	 */
	public static function is_plugin_active( $plugin_file ) {
		// Load dependencies.
		self::load_dependencies();

		// Check for multisite network activation.
		if ( is_multisite() ) {
			return is_plugin_active_for_network( $plugin_file ) || is_plugin_active( $plugin_file );
		}

		return is_plugin_active( $plugin_file );
	}

	/**
	 * Get a search url to the plugin page.
	 *
	 * @param string $name - The name of the plugin to search for.
	 *
	 * @return string
	 */
	public static function get_plugin_search_url( $name ) {
		return self::build_admin_url(
			'plugins.php',
			array(
				'plugin_status' => 'all',
				's'             => $name,
			)
		);
	}

	/**
	 * Get a search URL to the Add Plugins (plugin install) page.
	 *
	 * @param string $name - The name of the plugin to search for.
	 *
	 * @return string
	 */
	public static function get_plugin_install_search_url( $name ) {
		return self::build_admin_url(
			'plugin-install.php',
			array(
				'tab' => 'search',
				's'   => $name,
			)
		);
	}

	/**
	 * Build admin URL with query args using RFC3986 encoding.
	 *
	 * Uses RFC3986 encoding (%20 for spaces) instead of application/x-www-form-urlencoded
	 * (+ for spaces) to ensure compatibility with strict filter_var() URL validation.
	 *
	 * @param string $path  Admin path (e.g., 'plugins.php', 'plugin-install.php')
	 * @param array  $args  Query arguments
	 *
	 * @return string Complete admin URL with encoded query string
	 */
	private static function build_admin_url( $path, array $args ) {
		$base = admin_url( $path );

		if ( empty( $args ) ) {
			return $base;
		}

		$query = http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );

		return $base . '?' . $query;
	}

	/**
	 * Get a plugin's path by matching any plugin data field.
	 *
	 * @param string $key   The plugin data key to match against ('Name', 'PluginURI', 'Version', etc., or 'BaseFile' for filename)
	 * @param string $value The value to match
	 * @param bool   $case_sensitive Whether the comparison should be case-sensitive
	 *
	 * @return string|false The plugin path if found, false otherwise
	 */
	public static function get_plugin_path_by( $key, $value, $case_sensitive = false ) {
		// Load dependencies
		self::load_dependencies();

		// Get all plugins
		$all_plugins = get_plugins();

		// Special handling for base filename search
		if ( 'BaseFile' === $key ) {
			foreach ( array_keys( $all_plugins ) as $plugin_path ) {
				if ( basename( $plugin_path ) === $value ) {
					return $plugin_path;
				}
			}
			return false;
		}

		// Prepare the search value
		$search_value = $case_sensitive ? trim( $value ) : strtolower( trim( $value ) );

		// Search through all plugins
		foreach ( $all_plugins as $path => $plugin_data ) {
			if ( ! isset( $plugin_data[ $key ] ) ) {
				continue;
			}

			$current_value = $case_sensitive
				? trim( $plugin_data[ $key ] )
				: strtolower( trim( $plugin_data[ $key ] ) );

			if ( $current_value === $search_value ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * Get a plugin's file path by its exact display name.
	 *
	 * @param string $plugin_name - The exact display name of the plugin.
	 * @param bool   $case_sensitive - Whether the comparison should be case-sensitive.
	 *
	 * @return string|false The plugin path if found, false otherwise.
	 */
	public static function get_plugin_path_by_name( $plugin_name, $case_sensitive = false ) {
		return self::get_plugin_path_by( 'Name', $plugin_name, $case_sensitive );
	}

	/**
	 * Attempt to determine the actual relative path of a plugin by its base file.
	 *
	 * @param string $base_file - The base filename to search for (e.g., 'woocommerce.php')
	 *
	 * @return string|false The actual relative plugin path if found, false otherwise.
	 */
	public static function get_plugin_path_by_base_file( $base_file ) {
		return self::get_plugin_path_by( 'BaseFile', $base_file );
	}

	/**
	 * Check if a plugin constant exists and is defined.
	 *
	 * @param string $constant_name - The constant name to check.
	 *
	 * @return bool True if the constant exists and is defined, false otherwise.
	 */
	public static function does_plugin_constant_exist( $constant_name ) {
		return defined( $constant_name );
	}

	/**
	 * Check if a plugin class exists.
	 *
	 * @param string $class_name - The fully qualified class name to check.
	 *
	 * @return bool True if the class exists, false otherwise.
	 */
	public static function does_plugin_class_exist( $class_name ) {
		return class_exists( $class_name, false );
	}

	/**
	 * Discover plugin file by integration code or name.
	 *
	 * Searches installed plugins to find a match, prioritizing integration plugins
	 * (those with "automator" or "integration" in their slug).
	 *
	 * @param string $integration_code Integration code (e.g., 'acymailing', 'PAYPAL')
	 * @param string $integration_name Integration name (e.g., 'AcyMailing', 'PayPal')
	 * @return string Plugin file path (e.g., 'plugin-dir/plugin.php') or empty string if not found
	 */
	public static function discover_integration_plugin_file( $integration_code, $integration_name = '' ) {
		static $all_plugins = null;

		// Cache get_plugins() call
		if ( null === $all_plugins ) {
			self::load_dependencies();
			$all_plugins = get_plugins();
		}

		$code_lower = strtolower( $integration_code );
		$name_lower = strtolower( $integration_name );

		// Remove common suffixes for better matching
		$name_search = str_replace( array( ' (not connected)', ' (test mode)', ' integration' ), '', $name_lower );
		$name_search = trim( $name_search );

		// Prioritize integration plugins (with "automator" or "integration" in slug)
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$plugin_name_lower = strtolower( $plugin_data['Name'] ?? '' );
			$plugin_slug       = strtolower( dirname( $plugin_file ) );

			// Only check plugins that look like Automator integrations
			$is_integration = ( strpos( $plugin_slug, 'automator' ) !== false || strpos( $plugin_slug, 'integration' ) !== false );

			if ( ! $is_integration ) {
				continue;
			}

			// Match by integration code in plugin slug
			if ( false !== strpos( $plugin_slug, $code_lower ) ) {
				return $plugin_file;
			}

			// Match by integration name
			if ( ! empty( $name_search ) && ( strpos( $plugin_name_lower, $name_search ) !== false || strpos( $plugin_slug, $name_search ) !== false ) ) {
				return $plugin_file;
			}
		}

		return '';
	}

	/**
	 * Get plugin data (metadata) from plugin file.
	 *
	 * @param string $plugin_file Plugin file path relative to plugins directory
	 * @return array Plugin data array or empty array if not found
	 */
	public static function get_plugin_data( $plugin_file ) {
		self::load_dependencies();

		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

		if ( ! file_exists( $plugin_path ) ) {
			return array();
		}

		return get_plugin_data( $plugin_path, false, false );
	}

	/**
	 * Get plugin version from plugin file.
	 *
	 * @param string $plugin_file Plugin file path relative to plugins directory
	 * @return string Plugin version or '1.0.0' as fallback
	 */
	public static function get_plugin_version( $plugin_file ) {
		$plugin_data = self::get_plugin_data( $plugin_file );
		return $plugin_data['Version'] ?? '1.0.0';
	}

	/**
	 * Normalize plugin path to relative format.
	 *
	 * Converts absolute paths to relative and normalizes directory separators.
	 *
	 * @param string $plugin_file_path Plugin file path (absolute or relative)
	 * @return string Normalized relative plugin path
	 */
	public static function normalize_plugin_path( $plugin_file_path ) {
		if ( empty( $plugin_file_path ) ) {
			return '';
		}

		$plugin_file = $plugin_file_path;

		// Convert absolute path to relative
		if ( strpos( $plugin_file_path, WP_PLUGIN_DIR ) === 0 ) {
			$plugin_file = str_replace( trailingslashit( WP_PLUGIN_DIR ), '', $plugin_file_path );
		}

		// Normalize directory separators to forward slashes
		return str_replace( DIRECTORY_SEPARATOR, '/', $plugin_file );
	}

	/**
	 * Load dependencies.
	 *
	 * @return void
	 */
	private static function load_dependencies() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}
