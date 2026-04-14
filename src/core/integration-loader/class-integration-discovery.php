<?php
/**
 * Integration Discovery
 *
 * Discovers integration directories and file maps from pre-built Composer artifacts
 * or via runtime directory scanning. Provides the raw data that the Registrar and
 * Recipe_Part_Loader consume.
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.2
 */

namespace Uncanny_Automator\Integration_Loader;

use Uncanny_Automator\Automator_Exception;
use Uncanny_Automator\Legacy_Integrations;
use Uncanny_Automator\Set_Up_Automator;

/**
 * Class Integration_Discovery
 *
 * Single responsibility: locate integration files and build the integration map
 * (directory → file arrays). Does NOT instantiate any classes.
 */
class Integration_Discovery {

	/**
	 * Path to the Free integrations directory.
	 *
	 * @var string
	 */
	private $integrations_directory_path;

	/**
	 * Integration_Discovery constructor.
	 *
	 * @param string $integrations_directory_path Absolute path to src/integrations/.
	 */
	public function __construct( $integrations_directory_path ) {
		$this->integrations_directory_path = $integrations_directory_path;
	}

	/**
	 * Load the integration autoload directories.
	 *
	 * Prefers the pre-built vendor/composer/autoload_integrations_map.php (generated at
	 * zip time). Falls back to a runtime classmap scan via Legacy_Integrations when the
	 * pre-built file is unavailable (dev environments).
	 *
	 * Side effect: populates Set_Up_Automator::$all_integrations and caches it.
	 *
	 * @return array Array of directory paths for auto-loading.
	 *
	 * @throws Automator_Exception On scan failure.
	 */
	public function get_autoload_directories() {

		$map_file = dirname( AUTOMATOR_BASE_FILE )
			. DIRECTORY_SEPARATOR . 'vendor'
			. DIRECTORY_SEPARATOR . 'composer'
			. DIRECTORY_SEPARATOR . 'autoload_integrations_map.php';

		if ( file_exists( $map_file ) ) {
			$integrations = include $map_file;
		} else {
			// Fallback to runtime classmap scan when pre-built map is unavailable.
			try {
				$legacy_integrations = new Legacy_Integrations();
				$integrations        = $legacy_integrations->generate_integrations_file_map();
			} catch ( \Throwable $e ) {
				throw new Automator_Exception( esc_html( $e->getTraceAsString() ) );
			}
		}

		$integrations                       = apply_filters_deprecated( 'uncanny_automator_integrations', array( $integrations ), '3.0', 'automator_integrations_setup' );
		Set_Up_Automator::$all_integrations = apply_filters( 'automator_integrations_setup', $integrations );

		Automator()->cache->set( 'automator_get_all_integrations', Set_Up_Automator::$all_integrations, 'automator', Automator()->cache->long_expires );

		return self::extract_integration_folders( Set_Up_Automator::$all_integrations, $this->integrations_directory_path );
	}

	/**
	 * Recursively read all files in an integration directory.
	 *
	 * Identifies the main add-*-integration.php file and component subdirectories
	 * (actions, helpers, tokens, triggers, closures, conditions, loop-filters).
	 *
	 * @param string $directory The directory to scan.
	 * @param bool   $recursive Whether to recurse into subdirectories.
	 *
	 * @return array|false The file map, or false if directory doesn't exist.
	 *
	 * @throws Automator_Exception On scan failure.
	 */
	public static function read_directory( $directory, $recursive = true ) {

		if ( false === is_dir( $directory ) ) {
			return false;
		}

		$resource = opendir( $directory );

		if ( false === $resource ) {
			return false;
		}

		try {
			$integration_files = array();

			while ( false !== ( $item = readdir( $resource ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition

				if ( self::should_skip_item( $item, $directory ) ) {
					continue;
				}

				$full_path = $directory . DIRECTORY_SEPARATOR . $item;

				if ( true === $recursive && is_dir( $full_path ) ) {
					$integration_files[ basename( $full_path ) ] = self::read_directory( $full_path );
					continue;
				}

				self::process_php_file( $item, $full_path, $integration_files );
			}
		} catch ( \Throwable $e ) {
			throw new Automator_Exception( esc_html( $e->getTraceAsString() ) );
		} finally {
			closedir( $resource );
		}

		return $integration_files;
	}

	/**
	 * Determine if a directory item should be skipped during scanning.
	 *
	 * @param string $item      The directory entry name.
	 * @param string $directory The parent directory path.
	 *
	 * @return bool true to skip.
	 */
	private static function should_skip_item( $item, $directory ) {

		$skip_names = array( '.', '..', 'index.php' );

		if ( in_array( (string) $item, $skip_names, true ) ) {
			return true;
		}

		// Ignore vendor folder in integrations directory.
		return 'vendor' === (string) $item && is_dir( $directory . DIRECTORY_SEPARATOR . $item );
	}

	/**
	 * Process a single PHP file and add it to the integration files array.
	 *
	 * Identifies main integration files (add-*-integration.php) and regular
	 * component files (triggers, actions, helpers, etc.).
	 *
	 * @param string $item              The file name.
	 * @param string $full_path         The full file path.
	 * @param array  $integration_files Reference to the integration files array.
	 *
	 * @return void
	 */
	private static function process_php_file( $item, $full_path, &$integration_files ) {

		if ( 'php' !== (string) pathinfo( $item, PATHINFO_EXTENSION ) ) {
			return;
		}

		if ( preg_match( '/(add\-(.+)\-integration)/', $item ) ) {
			$integration_files['main'] = $full_path;
			return;
		}

		// Avoid Integromat fatal error if Pro < 3.0 and Free is >= 3.0.
		if ( self::is_legacy_pro_incompatible() ) {
			return;
		}

		$integration_files[] = $full_path;
	}

	/**
	 * Check if Pro plugin is too old to be compatible.
	 *
	 * @return bool true if Pro < 3.0 is active.
	 */
	private static function is_legacy_pro_incompatible() {

		if ( ! class_exists( '\Uncanny_Automator_Pro\InitializePlugin', false ) ) {
			return false;
		}

		return version_compare( \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION, '3.0', '<' );
	}

	/**
	 * Extract directory paths from the integration map.
	 *
	 * Converts the integration map (directory_name → file_array) into a flat array
	 * of directory paths that initialize_add_integrations() can iterate.
	 *
	 * @param array  $integrations The integration file map.
	 * @param string $directory    The base integrations directory path.
	 *
	 * @return array Array of directory paths.
	 */
	public static function extract_integration_folders( $integrations, $directory ) {

		$folders = array();

		if ( empty( $integrations ) ) {
			return $folders;
		}

		foreach ( $integrations as $f => $integration ) {
			$path      = isset( $integration['main'] ) ? dirname( $integration['main'] ) : $directory . DIRECTORY_SEPARATOR . $f;
			$path      = apply_filters( 'automator_integration_folder_paths', $path, $integration, $directory, $f );
			$folders[] = $path;
		}

		return apply_filters( 'automator_integration_folders', $folders, $integrations, $directory );
	}
}
