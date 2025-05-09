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
		return add_query_arg(
			array(
				'plugin_status' => 'all',
				's'             => $name,
			),
			admin_url( 'plugins.php' )
		);
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
