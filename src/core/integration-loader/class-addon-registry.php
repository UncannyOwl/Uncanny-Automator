<?php
/**
 * Addon Registry
 *
 * Generic entry point for Pro, addons, and third-party plugins to inject their
 * integration data into Free's loading pipeline. Handles file map merging,
 * namespace registration, framework integration loading, and item map hooking.
 *
 * Call register() during `automator_add_integration` hook (priority 11+).
 * After register() runs, Free's existing pipeline handles registration (Phase 3),
 * helpers (Phase 4), and recipe parts (Phase 5) automatically — the addon needs
 * no separate loading code.
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.2
 */

namespace Uncanny_Automator\Integration_Loader;

use Uncanny_Automator\Recipe_Manifest;
use Uncanny_Automator\Set_Up_Automator;

/**
 * Class Addon_Registry
 *
 * Single responsibility: accept addon configuration and merge it into Free's
 * loading statics so the unified pipeline processes everything.
 */
class Addon_Registry {

	/**
	 * Register an external addon's integration files.
	 *
	 * Merges the addon's file maps, namespace, framework integrations, and item map
	 * into Free's loading pipeline. Call during `automator_add_integration` (priority 11+),
	 * BEFORE Free's `initialize_add_integrations()` runs.
	 *
	 * After this method runs:
	 *   - Free's Integration_Registrar picks up the addon's legacy integrations automatically
	 *   - Free's Recipe_Part_Loader picks up helpers, tokens, triggers, actions, etc.
	 *   - Free's manifest-driven gating applies to the addon's items
	 *
	 * Example usage (Pro):
	 * ```php
	 * add_action( 'automator_add_integration', function() {
	 *     $loader = Initialize_Automator::get_instance()->get_loader();
	 *     $loader->register_addon( array(
	 *         'integrations_path' => dirname( AUTOMATOR_PRO_FILE ) . '/src/integrations',
	 *         'namespace'         => 'Uncanny_Automator_Pro',
	 *         'integrations'      => include UAPro_ABSPATH . 'vendor/composer/autoload_integrations_map.php',
	 *         'item_map_file'     => UAPro_ABSPATH . 'vendor/composer/autoload_item_map.php',
	 *         'filemap_file'      => UAPro_ABSPATH . 'vendor/composer/autoload_filemap.php',
	 *     ) );
	 * }, 11 );
	 * ```
	 *
	 * @since 7.2
	 *
	 * @param array $config {
	 *     Addon configuration.
	 *
	 *     @type string $integrations_path Absolute path to the addon's integrations directory.
	 *     @type string $namespace         Root namespace (e.g. 'Uncanny_Automator_Pro').
	 *     @type array  $integrations      Integration file map (from autoload_integrations_map.php).
	 *                                     Keys are directory names, values are arrays of file types.
	 *     @type string $item_map_file     Path to autoload_item_map.php for demand-driven gating. Optional.
	 *     @type string $filemap_file      Path to autoload_filemap.php for framework integrations. Optional.
	 * }
	 *
	 * @return bool True if registration succeeded, false if validation failed.
	 */
	public function register( array $config ) {

		$config = wp_parse_args(
			$config,
			array(
				'integrations_path' => '',
				'namespace'         => '',
				'integrations'      => array(),
				'item_map_file'     => '',
				'filemap_file'      => '',
			)
		);

		if ( empty( $config['integrations'] ) || empty( $config['namespace'] ) ) {
			return false;
		}

		$this->merge_integrations( $config['integrations'], $config['integrations_path'], $config['namespace'] );

		// Hook the item map BEFORE loading framework integrations so that
		// addon constructors calling get_item_map() see the merged map.
		$this->hook_item_map( $config['item_map_file'] );
		Recipe_Manifest::get_instance()->invalidate_item_map();

		$this->load_framework_integrations( $config['filemap_file'] );

		/**
		 * Fires after an addon has been registered with the loading pipeline.
		 *
		 * @since 7.2
		 *
		 * @param array $config The addon configuration.
		 */
		do_action( 'automator_addon_registered', $config );

		return true;
	}

	// ──────────────────────────────────────────────
	// File map merging
	// ──────────────────────────────────────────────

	/**
	 * Merge an addon's integration file arrays into Free's statics.
	 *
	 * For shared integrations (e.g. WooCommerce in both Free and Pro), merges
	 * file arrays (helpers, triggers, actions, etc.) into the existing entry.
	 * For addon-only integrations, adds new entries to both $all_integrations
	 * and $auto_loaded_directories.
	 *
	 * @param array  $integrations      Integration file map.
	 * @param string $integrations_path Absolute path to integrations directory.
	 * @param string $namespace         Root namespace.
	 *
	 * @return void
	 */
	public function merge_integrations( $integrations, $integrations_path, $namespace ) {

		foreach ( $integrations as $dir_name => $files ) {

			// Register namespace so Class_Resolver can resolve this addon's classes.
			Set_Up_Automator::$external_integrations_namespace[ $dir_name ] = $namespace;

			if ( isset( Set_Up_Automator::$all_integrations[ $dir_name ] ) ) {
				$this->merge_shared_integration( $dir_name, $files );
			} else {
				$this->add_addon_only_integration( $dir_name, $files, $integrations_path );
			}
		}
	}

	/**
	 * Merge file arrays for a shared integration (exists in both Free and addon).
	 *
	 * @param string $dir_name Integration directory name.
	 * @param array  $files    Addon's file arrays for this integration.
	 *
	 * @return void
	 */
	private function merge_shared_integration( $dir_name, $files ) {

		foreach ( \automator_get_default_directories() as $type ) {

			if ( empty( $files[ $type ] ) ) {
				continue;
			}

			if ( ! isset( Set_Up_Automator::$all_integrations[ $dir_name ][ $type ] ) ) {
				Set_Up_Automator::$all_integrations[ $dir_name ][ $type ] = array();
			}

			Set_Up_Automator::$all_integrations[ $dir_name ][ $type ] = array_merge(
				Set_Up_Automator::$all_integrations[ $dir_name ][ $type ],
				(array) $files[ $type ]
			);
		}
	}

	/**
	 * Add an addon-only integration to the loading pipeline.
	 *
	 * @param string $dir_name          Integration directory name.
	 * @param array  $files             File arrays for this integration.
	 * @param string $integrations_path Absolute path to integrations directory.
	 *
	 * @return void
	 */
	private function add_addon_only_integration( $dir_name, $files, $integrations_path ) {

		Set_Up_Automator::$all_integrations[ $dir_name ] = $files;

		// Build directory path from the main file or the integrations_path.
		$dir_path = ! empty( $files['main'] )
			? dirname( $files['main'] )
			: $integrations_path . DIRECTORY_SEPARATOR . $dir_name;

		if ( null === Set_Up_Automator::$auto_loaded_directories ) {
			Set_Up_Automator::$auto_loaded_directories = array();
		}

		Set_Up_Automator::$auto_loaded_directories[] = $dir_path;
	}

	// ──────────────────────────────────────────────
	// Framework integrations (load.php files)
	// ──────────────────────────────────────────────

	/**
	 * Load an addon's framework integrations (modern load.php pattern).
	 *
	 * Includes each load.php from the addon's filemap, applying the same
	 * manifest-driven gating as Free's framework loader.
	 *
	 * @param string $filemap_file Path to autoload_filemap.php. Empty to skip.
	 *
	 * @return void
	 */
	public function load_framework_integrations( $filemap_file ) {

		if ( empty( $filemap_file ) || ! file_exists( $filemap_file ) ) {
			return;
		}

		$filemap = include $filemap_file;

		if ( empty( $filemap ) || ! is_array( $filemap ) ) {
			return;
		}

		self::include_gated_files( $filemap );
	}

	/**
	 * Determine if a framework integration file should be included.
	 *
	 * @param string          $file      The load.php file path.
	 * @param array           $dir_codes Directory name → integration code map.
	 * @param Recipe_Manifest $manifest  The manifest instance.
	 *
	 * @return bool True to include.
	 */
	public static function should_include_framework_file( $file, $dir_codes, $manifest ) {

		$dir = basename( dirname( $file ) );

		// Not in item map — third-party or unmapped, safe fallback: load it.
		if ( ! isset( $dir_codes[ $dir ] ) ) {
			return true;
		}

		return $manifest->is_integration_needed( $dir_codes[ $dir ] );
	}

	/**
	 * Include framework load.php files with manifest-driven gating.
	 *
	 * Centralizes the manifest-gating pattern used by Free's Addon_Registry,
	 * Pro's Internal_Triggers_Actions, and Pro's Pro_Addon_Loader fallback.
	 * Callers provide an array of load.php paths; this method handles the
	 * Recipe_Manifest check, should_load_all(), and directory_code_map loop.
	 *
	 * If Recipe_Manifest is unavailable (Free < 7.2), all files are included.
	 *
	 * @since 7.2
	 *
	 * @param string[] $files Array of absolute paths to load.php files.
	 *
	 * @return void
	 */
	public static function include_gated_files( array $files ) {

		if ( empty( $files ) ) {
			return;
		}

		// Pre-7.2 Free — no manifest, load everything.
		if ( ! class_exists( '\Uncanny_Automator\Recipe_Manifest' ) ) {
			foreach ( $files as $file ) {
				include_once $file;
			}
			return;
		}

		$manifest = Recipe_Manifest::get_instance();

		// Full load: recipe editor, escape hatches, first deploy.
		if ( $manifest->should_load_all() ) {
			foreach ( $files as $file ) {
				include_once $file;
			}
			return;
		}

		// Targeted mode — only include integrations with active codes.
		$dir_codes = $manifest->get_directory_code_map();

		foreach ( $files as $file ) {
			if ( self::should_include_framework_file( $file, $dir_codes, $manifest ) ) {
				include_once $file;
			}
		}
	}

	// ──────────────────────────────────────────────
	// Item map merging
	// ──────────────────────────────────────────────

	/**
	 * Hook an addon's item map into the manifest's item map merge.
	 *
	 * @param string $item_map_file Path to autoload_item_map.php. Empty to skip.
	 *
	 * @return void
	 */
	public function hook_item_map( $item_map_file ) {

		if ( empty( $item_map_file ) || ! file_exists( $item_map_file ) ) {
			return;
		}

		add_filter(
			'automator_item_map',
			function ( $map ) use ( $item_map_file ) {
				return $this->merge_item_map( $map, $item_map_file );
			}
		);
	}

	/**
	 * Merge an addon's item map into the combined map.
	 *
	 * Deep merges integration code → type → items without overwriting
	 * existing entries.
	 *
	 * @param array  $map           Current combined item map.
	 * @param string $item_map_file Path to the addon's autoload_item_map.php.
	 *
	 * @return array Merged item map.
	 */
	public function merge_item_map( $map, $item_map_file ) {

		if ( ! file_exists( $item_map_file ) ) {
			return $map;
		}

		$addon_map = include $item_map_file;

		if ( empty( $addon_map ) || ! is_array( $addon_map ) ) {
			return $map;
		}

		foreach ( $addon_map as $integration_code => $types ) {

			if ( ! isset( $map[ $integration_code ] ) ) {
				$map[ $integration_code ] = $types;
				continue;
			}

			foreach ( $types as $type => $items ) {

				if ( ! isset( $map[ $integration_code ][ $type ] ) ) {
					$map[ $integration_code ][ $type ] = $items;
					continue;
				}

				$map[ $integration_code ][ $type ] = array_merge(
					$map[ $integration_code ][ $type ],
					$items
				);
			}
		}

		return $map;
	}
}
