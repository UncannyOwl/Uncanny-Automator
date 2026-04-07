<?php
/**
 * Integration Registrar
 *
 * Instantiates legacy add-*-integration.php files, checks plugin_active() on each,
 * and builds the active_directories map.
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.2
 */

namespace Uncanny_Automator\Integration_Loader;

use Uncanny_Automator\Set_Up_Automator;
use Uncanny_Automator\Utilities;

/**
 * Class Integration_Registrar
 *
 * Single responsibility: iterate add-*-integration.php files, instantiate their classes,
 * run plugin_active() checks, and register active integrations.
 */
class Integration_Registrar {

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
	 * Successfully loaded integration instances, indexed by directory name.
	 *
	 * @var array<string, object>
	 */
	private $active_directories = array();

	/**
	 * Subdirectories to include per integration (e.g. 'actions', 'helpers').
	 *
	 * @var array<string, string[]>
	 */
	private $directories_to_include = array();

	/**
	 * Integration_Registrar constructor.
	 *
	 * @param Class_Resolver     $resolver      Class name resolver.
	 * @param Load_Error_Handler $error_handler Error handler.
	 */
	public function __construct( Class_Resolver $resolver, Load_Error_Handler $error_handler ) {
		$this->resolver      = $resolver;
		$this->error_handler = $error_handler;
	}

	/**
	 * Process all legacy add-*-integration.php files and register active integrations.
	 *
	 * @return void
	 */
	public function register_integrations() {

		if ( ! Set_Up_Automator::$auto_loaded_directories ) {
			return;
		}

		foreach ( Set_Up_Automator::$auto_loaded_directories as $directory ) {

			$dir_name = basename( $directory );
			$file     = $this->get_main_file( $dir_name );

			if ( null === $file ) {
				continue;
			}

			$class = apply_filters(
				'automator_integrations_class_name',
				$this->resolver->resolve( $file, false, $dir_name ),
				$file
			);

			if ( false !== Utilities::get_class_instance( $class ) ) {
				continue;
			}

			include_once $file;

			try {
				$instance = new $class();
			} catch ( \Throwable $e ) {
				$this->error_handler->handle( $class, $e );
				continue;
			}

			$this->store_integration_metadata( $instance, $class );

			if ( ! $this->is_integration_active( $instance, $class ) ) {
				unset( $instance );
				continue;
			}

			$this->activate_integration( $instance, $class, $dir_name, $file );
		}

		$this->active_directories = apply_filters( 'automator_active_integration_directories', $this->active_directories );
		Automator()->cache->set( 'automator_active_integrations', $this->active_directories );
	}

	/**
	 * Get the main add-*-integration.php file for a directory.
	 *
	 * @param string $dir_name Directory name.
	 *
	 * @return string|null File path or null if not available.
	 */
	private function get_main_file( $dir_name ) {

		if ( ! isset( Set_Up_Automator::$all_integrations[ $dir_name ]['main'] ) ) {
			return null;
		}

		$file = Set_Up_Automator::$all_integrations[ $dir_name ]['main'];

		if ( is_array( $file ) || ! file_exists( $file ) ) {
			return null;
		}

		return $file;
	}

	/**
	 * Store integration name and icon for the catalog (active or not).
	 *
	 * @param object $instance The integration instance.
	 * @param string $class    The FQCN.
	 *
	 * @return void
	 */
	private function store_integration_metadata( $instance, $class ) {

		$integration_code = $this->get_integration_code( $instance, $class );
		$integration_name = method_exists( $instance, 'get_name' ) ? $instance->get_name() : '';
		$integration_icon = method_exists( $instance, 'get_integration_icon' ) ? $instance->get_integration_icon() : '';

		if ( empty( $integration_icon ) || empty( $integration_name ) ) {
			return;
		}

		Automator()->set_all_integrations(
			$integration_code,
			array(
				'name'     => $integration_name,
				'icon_svg' => $integration_icon,
			)
		);
	}

	/**
	 * Check if the integration's underlying plugin is active.
	 *
	 * @param object $instance The integration instance.
	 * @param string $class    The FQCN.
	 *
	 * @return bool
	 */
	private function is_integration_active( $instance, $class ) {

		$integration_code = $this->get_integration_code( $instance, $class );

		$active = method_exists( $instance, 'get_integration' )
			? $instance->plugin_active()
			: $instance->plugin_active( 0, $integration_code );

		return true === apply_filters( 'automator_maybe_integration_active', $active, $integration_code );
	}

	/**
	 * Register an active integration — legacy callbacks, directory mapping, system registration.
	 *
	 * @param object $instance The integration instance.
	 * @param string $class    The FQCN.
	 * @param string $dir_name The directory name.
	 * @param string $file     The main file path.
	 *
	 * @return void
	 */
	private function activate_integration( $instance, $class, $dir_name, $file ) {

		$integration_code = $this->get_integration_code( $instance, $class );

		if ( method_exists( $instance, 'add_integration_func' ) ) {
			$instance->add_integration_func();
		}

		Set_Up_Automator::set_active_integration_code( $integration_code );

		$this->active_directories[ $dir_name ] = $instance;
		$this->collect_directories_to_include( $instance, $dir_name, $file );

		if ( method_exists( $instance, 'add_integration' ) ) {
			$instance->add_integration( $instance->get_integration(), array( $instance->get_name(), $instance->get_icon() ) );
		}

		Utilities::add_class_instance( $class, $instance );
	}

	/**
	 * Collect subdirectories to include for an integration.
	 *
	 * @param object $instance The integration instance.
	 * @param string $dir_name The directory name.
	 * @param string $file     The main file path.
	 *
	 * @return void
	 */
	private function collect_directories_to_include( $instance, $dir_name, $file ) {

		if ( ! method_exists( $instance, 'add_integration_directory_func' ) ) {
			return;
		}

		$directories = $instance->add_integration_directory_func( array(), $file );

		if ( empty( $directories ) ) {
			return;
		}

		foreach ( $directories as $dir ) {
			$this->directories_to_include[ $dir_name ][] = basename( $dir );
		}
	}

	/**
	 * Extract the integration code from an instance.
	 *
	 * @param object $instance The integration instance.
	 * @param string $class    The FQCN.
	 *
	 * @return string
	 */
	private function get_integration_code( $instance, $class ) {
		if ( method_exists( $instance, 'get_integration' ) ) {
			return (string) $instance->get_integration();
		}

		return property_exists( $class, 'integration' ) ? (string) $class::$integration : '';
	}

	/**
	 * Get the active directories map.
	 *
	 * @return array<string, object>
	 */
	public function get_active_directories() {
		return $this->active_directories;
	}

	/**
	 * Set the active directories map.
	 *
	 * Used by Pro's finalize_directory_merge() to update active directories
	 * after merging Pro-only types.
	 *
	 * @param array<string, object> $directories Directory name → integration instance.
	 *
	 * @return void
	 */
	public function set_active_directories( $directories ) {
		$this->active_directories = $directories;
	}

	/**
	 * Get the directories_to_include map.
	 *
	 * @return array<string, string[]>
	 */
	public function get_directories_to_include() {
		return $this->directories_to_include;
	}

	/**
	 * Set the directories_to_include map.
	 *
	 * Used by Pro's finalize_directory_merge() to add Pro-contributed types.
	 *
	 * @param array<string, string[]> $directories Directory name → subdirectory names.
	 *
	 * @return void
	 */
	public function set_directories_to_include( $directories ) {
		$this->directories_to_include = $directories;
	}
}
