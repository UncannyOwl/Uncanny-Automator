<?php
/**
 * Class Resolver
 *
 * Converts file paths to fully qualified class names (FQCN) and handles namespace
 * validation. Centralizes the class name resolution logic previously scattered across
 * Set_Up_Automator.
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.2
 */

namespace Uncanny_Automator\Integration_Loader;

use Uncanny_Automator\Set_Up_Automator;
use ReflectionClass;
use ReflectionException;

/**
 * Class Class_Resolver
 *
 * Single responsibility: resolve PHP file paths into fully qualified class names,
 * validate custom namespaces via reflection, and apply namespace transformations
 * (e.g. Loop_Filters sub-namespace).
 */
class Class_Resolver {

	/**
	 * Path to the Free integrations directory.
	 *
	 * Used to determine whether a file is internal (Uncanny_Automator namespace)
	 * or external (custom namespace).
	 *
	 * @var string
	 */
	private $integrations_directory_path = '';

	/**
	 * Class_Resolver constructor.
	 *
	 * @param string $integrations_directory_path Absolute path to src/integrations/.
	 */
	public function __construct( $integrations_directory_path = '' ) {
		$this->integrations_directory_path = $integrations_directory_path;
	}

	/**
	 * Resolve a file path to a fully qualified class name.
	 *
	 * Applies the Uncanny_Automator namespace for internal integrations, or looks up
	 * the external namespace from Set_Up_Automator::$external_integrations_namespace
	 * and validates it via ReflectionClass.
	 *
	 * @param string $file             Absolute path to the PHP file.
	 * @param bool   $uppercase        Whether to uppercase the class name (for triggers/actions).
	 * @param string $integration_name Integration directory name (e.g. 'woocommerce').
	 *
	 * @return string The fully qualified class name.
	 */
	public function resolve( $file, $uppercase = false, $integration_name = '' ) {

		$file_name  = basename( $file, '.php' );
		$class_name = self::file_name_to_class( $file_name );

		if ( $uppercase ) {
			$class_name = strtoupper( $class_name );
		}

		// Check if it's an internal Automator file.
		// Normalize path separators — Windows uses backslashes which break the regex. See: UncannyOwl/Automator#7194
		$normalized_file = wp_normalize_path( $file );
		$normalized_dir  = wp_normalize_path( $this->integrations_directory_path );
		$esc_characters  = apply_filters( 'automator_esc_with_slash_characters', '/-:\\()_,.' );
		$pattern         = '/(' . addcslashes( $normalized_dir, $esc_characters ) . ')/';

		if ( preg_match( $pattern, $normalized_file ) ) {
			$class_name = 'Uncanny_Automator\\' . $class_name;
		} else {
			$custom_namespace = isset( Set_Up_Automator::$external_integrations_namespace[ $integration_name ] )
				? Set_Up_Automator::$external_integrations_namespace[ $integration_name ]
				: '';
			$custom_namespace = apply_filters( 'automator_external_class_namespace', $custom_namespace, $class_name, $file_name, $file );
			$class_name       = apply_filters( 'automator_external_class_with_namespace', $custom_namespace . '\\' . $class_name, $class_name, $file_name, $file );

			if ( self::validate_namespace( $class_name, $file_name, $file, $integration_name ) ) {
				return apply_filters( 'automator_recipes_class_name', $class_name, $file, $file_name );
			}
		}

		return apply_filters( 'automator_recipes_class_name', $class_name, $file, $file_name );
	}

	/**
	 * Convert a hyphenated file name to a PascalCase (underscore-joined) class name.
	 *
	 * Examples:
	 *   'wc-purchprod'        → 'Wc_Purchprod'
	 *   'add-wc-integration'  → 'Add_Wc_Integration'
	 *   'class-my-helper'     → 'My_Helper' (strips 'class-' prefix)
	 *
	 * @param string $file The file name (with or without .php extension).
	 *
	 * @return string The class name.
	 */
	public static function file_name_to_class( $file ) {

		$name = array_map(
			'ucfirst',
			explode(
				'-',
				str_replace(
					array( 'class-', '.php' ),
					'',
					basename( $file )
				)
			)
		);

		return join( '_', $name );
	}

	/**
	 * Validate that a class name exists in a custom namespace.
	 *
	 * Uses ReflectionClass to check if the class is discoverable by the autoloader.
	 * Note: this triggers the autoloader, which may define the class without
	 * instantiating it. Callers should use Utilities::get_class_instance() (not
	 * class_exists) to check if a class has been instantiated.
	 *
	 * @param string $class_name       The fully qualified class name to validate.
	 * @param string $file_name        The base file name (for filter context).
	 * @param string $file             The full file path (for filter context).
	 * @param string $integration_name The integration directory name (for filter context).
	 *
	 * @return bool True if the class exists in the namespace, false otherwise.
	 */
	public static function validate_namespace( $class_name, $file_name, $file, $integration_name = '' ) {

		try {
			$is_custom = new ReflectionClass( $class_name );
			if ( $is_custom->inNamespace() ) {
				return true;
			}
		} catch ( ReflectionException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		return false;
	}

	/**
	 * Prepend Loop_Filters sub-namespace for loop-filter classes.
	 *
	 * Loop-filter files share a naming pattern with conditions and other types,
	 * so they use a sub-namespace to avoid class name collisions.
	 *
	 * Example:
	 *   'Uncanny_Automator_Pro\BDB_IS_USER_IN_GROUP'
	 *   → 'Uncanny_Automator_Pro\Loop_Filters\BDB_IS_USER_IN_GROUP'
	 *
	 * @param string $class_name The fully qualified class name.
	 *
	 * @return string The class name with Loop_Filters sub-namespace inserted.
	 */
	public static function prepend_loop_filter_namespace( $class_name ) {

		$last_slash = strrpos( $class_name, '\\' );

		if ( false === $last_slash ) {
			return 'Loop_Filters\\' . $class_name;
		}

		$namespace  = substr( $class_name, 0, $last_slash );
		$class_name = substr( $class_name, $last_slash + 1 );

		return $namespace . '\\Loop_Filters\\' . $class_name;
	}
}
