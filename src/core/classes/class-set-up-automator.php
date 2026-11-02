<?php
/**
 * Set Up Automator
 *
 * Base class for integration loading. Maintains static properties and utility methods
 * for backward compatibility. Heavy-lifting logic has been extracted to focused classes
 * in src/core/integration-loader/:
 *
 *   - Class_Resolver         — file path → FQCN resolution
 *   - Integration_Discovery  — directory scanning and file map building
 *   - Integration_Registrar  — legacy integration registration
 *   - Recipe_Part_Loader     — helpers, tokens, triggers, actions loading
 *   - Load_Error_Handler     — error collection and admin notice display
 *   - Integration_Loader     — orchestrator wiring all sub-systems
 *
 * @package Uncanny_Automator
 * @since   3.0
 */

namespace Uncanny_Automator;

use Exception;
use Uncanny_Automator\Integration_Loader\Class_Resolver;
use Uncanny_Automator\Integration_Loader\Integration_Discovery;
use Uncanny_Automator\Integration_Loader\Load_Error_Handler;
use Uncanny_Automator\Integration_Loader\Recipe_Part_Loader;

/**
 * Class Set_Up_Automator
 *
 * Holds static state (active codes, integration maps, external namespaces) and
 * public instance properties (active_directories, directories_to_include) that
 * Pro and third-party addons depend on. Instance methods delegate to the
 * integration-loader sub-system.
 */
class Set_Up_Automator {

	/**
	 * Active integration codes (e.g. 'WC', 'LD', 'SLACK').
	 *
	 * Populated by both legacy (add-*-integration.php) and modern (abstract Integration)
	 * integrations. Used for filtering available triggers/actions in the recipe editor.
	 *
	 * @var string[]
	 */
	public static $active_integrations_code = array();

	/**
	 * The directories that are auto loaded and initialized.
	 *
	 * @since 1.0.0
	 *
	 * @var array|null
	 */
	public static $auto_loaded_directories = null;

	/**
	 * Successfully loaded integration instances, indexed by directory name.
	 *
	 * Public because Pro's finalize_directory_merge() accesses it via the
	 * `automator_after_add_integrations` hook.
	 *
	 * @var array<string, object>
	 */
	public $active_directories = array();

	/**
	 * Default subdirectory names to scan within each integration directory.
	 *
	 * @var string[]
	 */
	public $default_directories = array();

	/**
	 * All discovered integrations file map (directory_name → file arrays).
	 *
	 * @var array
	 */
	public static $all_integrations = array();

	/**
	 * Namespaces registered by external integrations (directory_name → namespace).
	 *
	 * @var array<string, string>
	 */
	public static $external_integrations_namespace = array();

	/**
	 * Integration directories add-ons added through automator_add_integration_directory().
	 *
	 * Initialize_Automator::load_integrations() replaces $auto_loaded_directories and
	 * $all_integrations with the cached or discovered lists on `init`, then puts these
	 * back, so a directory added before `init` registers on the same pass.
	 *
	 * @since 8.0.0
	 *
	 * @var array<string, array> Folder name => file map from read_directory().
	 */
	private static $addon_directories = array();

	/**
	 * Subdirectories to include per integration (e.g. 'actions', 'helpers').
	 *
	 * Public because Pro's finalize_directory_merge() modifies it via the
	 * `automator_after_add_integrations` hook.
	 *
	 * @var array<string, string[]>
	 */
	public $directories_to_include = array();

	/**
	 * Path to the Free integrations directory (src/integrations/).
	 *
	 * Set by Initialize_Automator in its constructor.
	 *
	 * @var string
	 */
	public $integrations_directory_path = '';

	/**
	 * Set_Up_Automator constructor.
	 *
	 * Sets default directories and registers the admin notice hook for load errors.
	 *
	 * @throws Exception On unexpected initialization errors.
	 */
	public function __construct() {
		if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'favicon' ) ) {
			return;
		}

		$this->default_directories = \automator_get_default_directories();
	}

	/**
	 * Discover integration directories and build the file map.
	 *
	 * Delegates to Integration_Discovery. Populates self::$all_integrations.
	 *
	 * @return array Array of directory paths for auto-loading.
	 *
	 * @throws Exception On scan failure.
	 */
	public function get_integrations_autoload_directories() {
		$discovery = new Integration_Discovery( $this->integrations_directory_path );

		return $discovery->get_autoload_directories();
	}

	/**
	 * Recursively read all files in an integration directory.
	 *
	 * Delegates to Integration_Discovery::read_directory().
	 *
	 * @param string $directory The directory to scan.
	 * @param bool   $recursive Whether to recurse into subdirectories.
	 *
	 * @return array|false The file map, or false if directory doesn't exist.
	 *
	 * @throws Automator_Exception On scan failure.
	 */
	public static function read_directory( $directory, $recursive = true ) {
		return Integration_Discovery::read_directory( $directory, $recursive );
	}

	/**
	 * Extract directory paths from the integration map.
	 *
	 * Delegates to Integration_Discovery::extract_integration_folders().
	 *
	 * @param array  $integrations The integration file map.
	 * @param string $directory    The base integrations directory path.
	 *
	 * @return array Array of directory paths.
	 */
	public static function extract_integration_folders( $integrations, $directory ) {
		return Integration_Discovery::extract_integration_folders( $integrations, $directory );
	}

	/**
	 * Register legacy integrations from add-*-integration.php files.
	 *
	 * Populates $this->active_directories and $this->directories_to_include.
	 * Initialize_Automator overrides this to use Integration_Registrar.
	 *
	 * @return void
	 *
	 * @throws Exception On instantiation failure.
	 */
	public function initialize_add_integrations() {

		if ( ! self::$auto_loaded_directories ) {
			return;
		}

		foreach ( self::$auto_loaded_directories as $directory ) {

			$dir_name = basename( $directory );

			if ( ! isset( self::$all_integrations[ $dir_name ] ) ) {
				continue;
			}

			if ( ! isset( self::$all_integrations[ $dir_name ]['main'] ) ) {
				continue;
			}

			$file = self::$all_integrations[ $dir_name ]['main'];

			if ( is_array( $file ) || ! file_exists( $file ) ) {
				continue;
			}

			$class = apply_filters( 'automator_integrations_class_name', $this->get_class_name( $file, false, $dir_name ), $file );

			// Use Utilities tracker, not class_exists — validate_namespace() may have
			// triggered the autoloader (via ReflectionClass), defining the class
			// without instantiating it.
			if ( false !== Utilities::get_class_instance( $class ) ) {
				continue;
			}

			include_once $file;

			try {
				$instance = new $class();
			} catch ( \Throwable $e ) {
				( new Load_Error_Handler() )->handle( $class, $e );
				continue;
			}

			Utilities::add_class_instance( $class, $instance );
			$integration_code = method_exists( $instance, 'get_integration' ) ? $instance->get_integration() : $class::$integration;
			$active           = method_exists( $instance, 'get_integration' ) ? $instance->plugin_active() : $instance->plugin_active( 0, $integration_code );
			$active           = apply_filters( 'automator_maybe_integration_active', $active, $integration_code );

			// Store all integrations (active or not) for name/icon display.
			$integration_name = method_exists( $instance, 'get_name' ) ? $instance->get_name() : '';
			$integration_icon = method_exists( $instance, 'get_integration_icon' ) ? $instance->get_integration_icon() : '';

			if ( ! empty( $integration_icon ) && ! empty( $integration_name ) ) {
				Automator()->set_all_integrations(
					$integration_code,
					array(
						'name'     => $integration_name,
						'icon_svg' => $integration_icon,
					)
				);
			}

			if ( true !== $active ) {
				unset( $instance );
				continue;
			}

			if ( method_exists( $instance, 'add_integration_func' ) ) {
				$instance->add_integration_func();
			}

			self::set_active_integration_code( $integration_code );

			$this->active_directories[ $dir_name ] = $instance;

			if ( method_exists( $instance, 'add_integration_directory_func' ) ) {
				$directories_to_include = $instance->add_integration_directory_func( array(), $file );
				if ( $directories_to_include ) {
					foreach ( $directories_to_include as $dir ) {
						$this->directories_to_include[ $dir_name ][] = basename( $dir );
					}
				}
			}

			if ( method_exists( $instance, 'add_integration' ) ) {
				$instance->add_integration( $instance->get_integration(), array( $instance->get_name(), $instance->get_icon() ) );
			}

			Utilities::add_class_instance( $class, $instance );
		}

		$this->active_directories = apply_filters( 'automator_active_integration_directories', $this->active_directories );
		Automator()->cache->set( 'automator_active_integrations', $this->active_directories );
	}

	/**
	 * Add an add-on's legacy integration directory to the loading pipeline.
	 *
	 * Keyed by the directory's folder name, the key Integration_Registrar and
	 * Class_Resolver look the directory up by.
	 *
	 * @since 8.0.0
	 *
	 * @param array  $files            The directory's file map from read_directory(), with its 'main' file.
	 * @param string $plugin_namespace The PHP namespace of the add-on's integration classes.
	 *
	 * @return void
	 */
	public static function register_addon_directory( $files, $plugin_namespace = '' ) {

		$dir_name = basename( dirname( $files['main'] ) );

		self::$addon_directories[ $dir_name ]               = $files;
		self::$external_integrations_namespace[ $dir_name ] = $plugin_namespace;

		self::merge_addon_directory( $dir_name, $files );
	}

	/**
	 * Put back the add-on directories after the integration lists were restored or discovered.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public static function merge_addon_directories() {
		foreach ( self::$addon_directories as $dir_name => $files ) {
			self::merge_addon_directory( $dir_name, $files );
		}
	}

	/**
	 * Add one add-on directory to the auto-loaded directories and the file map.
	 *
	 * @param string $dir_name The directory's folder name.
	 * @param array  $files    The directory's file map.
	 *
	 * @return void
	 */
	private static function merge_addon_directory( $dir_name, $files ) {

		$directory = dirname( $files['main'] );

		self::$all_integrations[ $dir_name ] = $files;

		if ( ! is_array( self::$auto_loaded_directories ) ) {
			self::$auto_loaded_directories = array();
		}

		if ( ! in_array( $directory, self::$auto_loaded_directories, true ) ) {
			self::$auto_loaded_directories[] = $directory;
		}
	}

	/**
	 * Convert a hyphenated file name to a PascalCase (underscore-joined) class name.
	 *
	 * Delegates to Class_Resolver::file_name_to_class().
	 *
	 * @param string $file The file name (with or without .php extension).
	 *
	 * @return string The class name.
	 */
	public static function file_name_to_class( $file ) {
		return Class_Resolver::file_name_to_class( $file );
	}

	/**
	 * Load helpers for all active integrations.
	 *
	 * Delegates to Recipe_Part_Loader::load_helpers().
	 *
	 * @return void
	 */
	public function initialize_integration_helpers() {
		$resolver      = new Class_Resolver( $this->integrations_directory_path );
		$error_handler = new Load_Error_Handler();
		$part_loader   = new Recipe_Part_Loader( $resolver, $error_handler );

		$part_loader->load_helpers( $this->active_directories, $this->directories_to_include );
	}

	/**
	 * Set an integration code as active.
	 *
	 * Centralized method for adding integration codes to the active integrations list.
	 * Used by both legacy (add-*-integration.php) and modern (abstract Integration class)
	 * integrations.
	 *
	 * @since 7.0.0
	 *
	 * @param string $integration_code The integration code to set as active (e.g., 'WC', 'GITHUB').
	 *
	 * @return bool True if added, false if already exists.
	 */
	public static function set_active_integration_code( $integration_code ) {
		if ( in_array( $integration_code, self::$active_integrations_code, true ) ) {
			return false;
		}

		self::$active_integrations_code[] = $integration_code;

		return true;
	}

	/**
	 * Resolve a file path to a fully qualified class name.
	 *
	 * Delegates to Class_Resolver::resolve().
	 *
	 * @param string $file             Absolute path to the PHP file.
	 * @param bool   $uppercase        Whether to uppercase the class name.
	 * @param string $integration_name Integration directory name.
	 *
	 * @return string The fully qualified class name.
	 */
	public function get_class_name( $file, $uppercase = false, $integration_name = '' ) {
		$resolver = new Class_Resolver( $this->integrations_directory_path );

		return $resolver->resolve( $file, $uppercase, $integration_name );
	}

	/**
	 * Validate that a class name exists in a custom namespace.
	 *
	 * Delegates to Class_Resolver::validate_namespace().
	 *
	 * @param string $class_name       The fully qualified class name to validate.
	 * @param string $file_name        The base file name.
	 * @param string $file             The full file path.
	 * @param string $integration_name The integration directory name.
	 *
	 * @return bool True if the class exists in the namespace.
	 */
	public static function validate_namespace( $class_name, $file_name, $file, $integration_name = '' ) {
		return Class_Resolver::validate_namespace( $class_name, $file_name, $file, $integration_name );
	}
}
