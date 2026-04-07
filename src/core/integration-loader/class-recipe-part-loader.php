<?php
/**
 * Recipe Part Loader
 *
 * Loads helpers, tokens, triggers, actions, closures, conditions, and loop-filters
 * for active integrations. Applies demand-driven gating at both the integration level
 * (via Recipe_Manifest::is_integration_needed) and the item level (via is_class_in_active_map).
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.2
 */

namespace Uncanny_Automator\Integration_Loader;

use Uncanny_Automator\Recipe_Manifest;
use Uncanny_Automator\Set_Up_Automator;
use Uncanny_Automator\Utilities;

/**
 * Class Recipe_Part_Loader
 *
 * Single responsibility: load integration helpers and recipe parts (triggers, actions,
 * closures, conditions, loop-filters, tokens) with manifest-driven gating.
 */
class Recipe_Part_Loader {

	/**
	 * Class name resolver.
	 *
	 * @var Class_Resolver
	 */
	private $resolver;

	/**
	 * Error handler for loading failures.
	 *
	 * @var Load_Error_Handler
	 */
	private $error_handler;

	/**
	 * Recipe_Part_Loader constructor.
	 *
	 * @param Class_Resolver     $resolver      Class name resolver.
	 * @param Load_Error_Handler $error_handler Error handler.
	 */
	public function __construct( Class_Resolver $resolver, Load_Error_Handler $error_handler ) {
		$this->resolver      = $resolver;
		$this->error_handler = $error_handler;
	}

	/**
	 * Load helpers for all active integrations.
	 *
	 * Applies integration-level gating: skips the entire integration's helpers
	 * if the manifest says no published recipe uses that integration.
	 *
	 * @param array $active_directories     Active integration instances by directory name.
	 * @param array $directories_to_include Subdirectory inclusion map.
	 *
	 * @return void
	 */
	public function load_helpers( $active_directories, $directories_to_include ) {

		if ( empty( $active_directories ) ) {
			return;
		}

		$manifest = Recipe_Manifest::get_instance();
		$load_all = $manifest->should_load_all();

		foreach ( $active_directories as $dir_name => $object ) {

			if ( ! $load_all && $this->should_skip_integration( $object, $manifest ) ) {
				continue;
			}

			$files = $this->get_files_for_type( $dir_name, 'helpers', $directories_to_include );

			if ( empty( $files ) ) {
				continue;
			}

			foreach ( $files as $file ) {
				$this->resolve_and_instantiate(
					$file,
					$dir_name,
					false,
					'automator_helpers_class_name',
					function ( $instance, $class ) use ( $dir_name ) {
						$mod = str_replace( '-', '_', $dir_name );
						Utilities::add_helper_instance( $mod, $instance );
						if ( method_exists( $instance, 'load_hooks' ) ) {
							$instance->load_hooks();
						}
					}
				);
			}
		}
	}

	/**
	 * Load triggers, actions, closures, conditions, loop-filters, and tokens
	 * for all active integrations.
	 *
	 * Tokens always load (no gating — they are shared dependencies).
	 * All other recipe parts are gated at the item level via is_class_in_active_map().
	 *
	 * @param array $active_directories     Active integration instances by directory name.
	 * @param array $directories_to_include Subdirectory inclusion map.
	 *
	 * @return void
	 */
	public function load_recipe_parts( $active_directories, $directories_to_include ) {

		if ( empty( $active_directories ) ) {
			return;
		}

		$manifest = Recipe_Manifest::get_instance();
		$load_all = $manifest->should_load_all();

		foreach ( $active_directories as $dir_name => $object ) {

			if ( ! isset( Set_Up_Automator::$all_integrations[ $dir_name ] ) ) {
				continue;
			}

			if ( ! $load_all && $this->should_skip_integration( $object, $manifest ) ) {
				continue;
			}

			$this->load_tokens( $dir_name, $directories_to_include );
			$this->load_gated_files( $dir_name, $object, $directories_to_include, $manifest, $load_all );
		}
	}

	/**
	 * Load token files for an integration.
	 *
	 * Tokens have no codes — they are shared dependencies and always load
	 * when the integration is needed.
	 *
	 * @param string $dir_name              Integration directory name.
	 * @param array  $directories_to_include Subdirectory inclusion map.
	 *
	 * @return void
	 */
	private function load_tokens( $dir_name, $directories_to_include ) {

		$files = $this->get_files_for_type( $dir_name, 'tokens', $directories_to_include );

		foreach ( $files as $file ) {
			$this->resolve_and_instantiate( $file, $dir_name, true, 'automator_recipe_parts_class_name' );
		}
	}

	/**
	 * Load gated recipe part files (triggers, actions, closures, conditions, loop-filters).
	 *
	 * Each file is checked against the item map — only files whose composite key
	 * is active in the manifest are instantiated (unless in full-load mode).
	 *
	 * @param string          $dir_name              Integration directory name.
	 * @param object          $object                Integration instance.
	 * @param array           $directories_to_include Subdirectory inclusion map.
	 * @param Recipe_Manifest $manifest              The manifest instance.
	 * @param bool            $load_all              Whether to load everything.
	 *
	 * @return void
	 */
	private function load_gated_files( $dir_name, $object, $directories_to_include, $manifest, $load_all ) {

		// Gated types exclude helpers/tokens — those always load for active integrations.
		// To add a new gated type, update automator_get_gated_directory_types() in global-functions.php.
		$gated_types = \automator_get_gated_directory_types();
		$gated_files = array();

		foreach ( $gated_types as $type ) {
			$gated_files = array_merge( $gated_files, $this->get_files_for_type( $dir_name, $type, $directories_to_include ) );
		}

		$gated_files = apply_filters( 'automator_integration_files', $gated_files, $dir_name );

		if ( empty( $gated_files ) ) {
			return;
		}

		$integration_code = self::get_integration_code( $object );

		foreach ( $gated_files as $file ) {
			$this->load_single_gated_file( $file, $dir_name, $integration_code, $manifest, $load_all );
		}
	}

	/**
	 * Load a single gated recipe part file.
	 *
	 * Resolves the class name, applies loop-filter namespace, checks the item map,
	 * and instantiates if allowed.
	 *
	 * @param string          $file             Absolute file path.
	 * @param string          $dir_name         Integration directory name.
	 * @param string          $integration_code Integration code (e.g. WC, LD).
	 * @param Recipe_Manifest $manifest         The manifest instance.
	 * @param bool            $load_all         Whether to load everything.
	 *
	 * @return void
	 */
	private function load_single_gated_file( $file, $dir_name, $integration_code, $manifest, $load_all ) {

		if ( is_array( $file ) || ! is_file( $file ) ) {
			return;
		}

		$class = apply_filters(
			'automator_recipe_parts_class_name',
			$this->resolver->resolve( $file, true, $dir_name ),
			$file
		);

		// Loop-filter classes need a Loop_Filters sub-namespace to avoid collisions.
		if ( false !== strpos( $file, 'loop-filter' ) ) {
			$class = Class_Resolver::prepend_loop_filter_namespace( $class );
		}

		// In targeted mode: skip files whose class isn't in the active item map.
		if ( ! $load_all && ! self::is_class_in_active_map( $class, $manifest, $integration_code ) ) {
			return;
		}

		// Check Utilities tracker, not class_exists.
		if ( false !== Utilities::get_class_instance( $class ) ) {
			return;
		}

		include_once $file;

		try {
			Utilities::add_class_instance( $class, new $class() );
		} catch ( \Throwable $e ) {
			$this->error_handler->handle( $class, $e );
		}
	}

	/**
	 * Resolve, include, and instantiate a class from a file path.
	 *
	 * This is the DRY core of the loading system. All four loading loops (integrations,
	 * helpers, tokens, gated recipe parts) share this pattern:
	 *   resolve class name → check tracker → include → instantiate → register → handle error
	 *
	 * @param string        $file        Absolute path to the PHP file.
	 * @param string        $dir_name    Integration directory name.
	 * @param bool          $uppercase   Whether to uppercase the class name.
	 * @param string        $filter_hook Filter hook name for class name override.
	 * @param callable|null $post_init   Optional callback after successful instantiation.
	 *                                   Receives ($instance, $class).
	 *
	 * @return object|null The instantiated class, or null on skip/error.
	 */
	private function resolve_and_instantiate( $file, $dir_name, $uppercase, $filter_hook, $post_init = null ) {

		if ( is_array( $file ) || ! is_file( $file ) ) {
			return null;
		}

		$class = apply_filters( $filter_hook, $this->resolver->resolve( $file, $uppercase, $dir_name ), $file );

		if ( false !== Utilities::get_class_instance( $class ) ) {
			return null;
		}

		include_once $file;

		try {
			$instance = new $class();
			Utilities::add_class_instance( $class, $instance );

			if ( null !== $post_init ) {
				$post_init( $instance, $class );
			}

			return $instance;
		} catch ( \Throwable $e ) {
			$this->error_handler->handle( $class, $e );
			return null;
		}
	}

	/**
	 * Extract the integration code from a legacy integration object.
	 *
	 * @param object $object The integration instance.
	 *
	 * @return string The integration code, or empty string if not found.
	 */
	public static function get_integration_code( $object ) {

		if ( method_exists( $object, 'get_integration' ) ) {
			return (string) $object->get_integration();
		}

		$class = get_class( $object );

		if ( property_exists( $class, 'integration' ) ) {
			return (string) $class::$integration;
		}

		return '';
	}

	/**
	 * Check if a class is in the item map and its composite key is active.
	 *
	 * Decision outcomes:
	 *   1. Integration not in map → true (third-party or item map gap, safe fallback)
	 *   2. Class found, type is 'conditions' → true (conditions always load if integration is needed)
	 *   3. Class found, composite key active → true (the normal case)
	 *   4. Class found, composite key inactive → false (the optimization)
	 *   5. Class NOT in map → true (token, third-party, or gap — allow it)
	 *
	 * @param string          $class            FQCN of the class.
	 * @param Recipe_Manifest $manifest         The manifest instance.
	 * @param string          $integration_code The integration code (e.g. WC, LD).
	 *
	 * @return bool Whether the class should be loaded.
	 */
	public static function is_class_in_active_map( $class, $manifest, $integration_code ) {

		$item_map = $manifest->get_item_map();

		// Integration not in map — third-party or item map gap, allow it.
		if ( empty( $item_map[ $integration_code ] ) ) {
			return true;
		}

		$result = self::find_class_in_integration( $item_map[ $integration_code ], $class, $manifest );

		// null = not found in map, true = active, false = inactive.
		if ( null === $result ) {
			self::log_unmapped_class( $class );
			return true;
		}

		return $result;
	}

	/**
	 * Search for a class within an integration's item map entries.
	 *
	 * @param array           $integration_items Items for a single integration.
	 * @param string          $class             FQCN to find.
	 * @param Recipe_Manifest $manifest          The manifest instance.
	 *
	 * @return bool|null true = active, false = found but inactive, null = not found.
	 */
	private static function find_class_in_integration( $integration_items, $class, $manifest ) {

		$class_upper = strtoupper( $class );
		$found       = false;

		// Item-map keys use underscores (loop_filters), not hyphens (loop-filters).
		// When adding a new gated type, also update automator_get_gated_directory_types() in global-functions.php.
		foreach ( array( 'triggers', 'actions', 'closures', 'conditions', 'loop_filters' ) as $type ) {

			if ( empty( $integration_items[ $type ] ) ) {
				continue;
			}

			foreach ( $integration_items[ $type ] as $composite_key => $entry ) {

				if ( strtoupper( $entry['class'] ) !== $class_upper ) {
					continue;
				}

				$found = true;

				if ( $manifest->is_code_active( $composite_key ) ) {
					return true;
				}
			}
		}

		return $found ? false : null;
	}

	/**
	 * Log a class that wasn't found in the item map (debug only).
	 *
	 * @param string $class The FQCN.
	 *
	 * @return void
	 */
	private static function log_unmapped_class( $class ) {

		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && AUTOMATOR_DEBUG_MODE ) {
			automator_log( 'Class not in item map (loading anyway): ' . $class, 'Recipe_Manifest' );
		}
	}

	/**
	 * Check if an integration should be skipped (not needed by any published recipe).
	 *
	 * @param object          $object   The integration instance.
	 * @param Recipe_Manifest $manifest The manifest instance.
	 *
	 * @return bool true to skip, false to continue loading.
	 */
	private function should_skip_integration( $object, $manifest ) {

		$integration_code = self::get_integration_code( $object );

		return ! empty( $integration_code ) && ! $manifest->is_integration_needed( $integration_code );
	}

	/**
	 * Get the file list for a specific component type in an integration.
	 *
	 * @param string $dir_name              Integration directory name.
	 * @param string $type                  Component type (helpers, triggers, actions, etc.).
	 * @param array  $directories_to_include Subdirectory inclusion map.
	 *
	 * @return array File paths, or empty array if not available.
	 */
	private function get_files_for_type( $dir_name, $type, $directories_to_include ) {

		$has_type = isset( Set_Up_Automator::$all_integrations[ $dir_name ][ $type ] )
			&& isset( $directories_to_include[ $dir_name ] )
			&& in_array( $type, $directories_to_include[ $dir_name ], true );

		return $has_type ? Set_Up_Automator::$all_integrations[ $dir_name ][ $type ] : array();
	}
}
