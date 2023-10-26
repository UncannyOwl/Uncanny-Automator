<?php

namespace Uncanny_Automator;

use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * Class Set_Automator_Triggers
 *
 * @package Uncanny_Automator
 */
class Set_Up_Automator {

	/**
	 * @var array
	 */
	public static $active_integrations_code = array();
	/**
	 * The directories that are auto loaded and initialized
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      array
	 */
	public static $auto_loaded_directories = null;
	/**
	 * @var array
	 */
	public $active_directories = array();
	/**
	 * @var array|string
	 */
	public $default_directories = array();
	/**
	 * @var array
	 */
	public static $all_integrations = array();

	/**
	 * Store namespaces of external integrations
	 * @var array
	 */
	public static $external_integrations_namespace = array();
	/**
	 * @var array
	 */
	public $directories_to_include = array();

	/**
	 * @var string
	 */
	public $integrations_directory_path = '';

	/**
	 * Set_Up_Automator constructor.
	 *
	 * @throws Exception
	 */
	public function __construct() {
		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'favicon' ) ) {
			// bail out if it's favicon.ico
			return;
		}

		$this->default_directories = apply_filters(
			'automator_integration_default_directories',
			array(
				'actions',
				'helpers',
				'tokens',
				'triggers',
				'closures',
			)
		);
	}

	/**
	 * Sets all trigger, actions, and closure classes directories
	 *
	 * @throws Exception
	 */
	public function get_integrations_autoload_directories() {
		try {
			$legacy_integrations = new Legacy_Integrations();
			$integrations        = $legacy_integrations->generate_integrations_file_map();
		} catch ( Exception $e ) {
			throw new Automator_Exception( $e->getTraceAsString() );
		}
		$integrations           = apply_filters_deprecated( 'uncanny_automator_integrations', array( $integrations ), '3.0', 'automator_integrations_setup' );
		self::$all_integrations = apply_filters( 'automator_integrations_setup', $integrations );
		Automator()->cache->set( 'automator_get_all_integrations', self::$all_integrations, 'automator', Automator()->cache->long_expires );

		return self::extract_integration_folders( self::$all_integrations, $this->integrations_directory_path );
	}

	/**
	 * Recursively read all integration directories
	 *
	 * @param      $directory
	 * @param bool $recursive
	 *
	 * @return array|false
	 * @throws Automator_Exception
	 */
	public static function read_directory( $directory, $recursive = true ) {
		if ( is_dir( $directory ) === false ) {
			return false;
		}

		try {
			$resource          = opendir( $directory );
			$integration_files = array();
			while ( false !== ( $item = readdir( $resource ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				if ( '.' === (string) $item || '..' === (string) $item || 'index.php' === (string) $item ) {
					continue;
				}

				/**
				 * Ignore vendor folder in Integrations directory
				 */
				if ( 'vendor' === (string) $item && is_dir( $directory . DIRECTORY_SEPARATOR . $item ) ) {
					continue;
				}

				if ( true === $recursive && is_dir( $directory . DIRECTORY_SEPARATOR . $item ) ) {
					$dir                       = basename( $directory . DIRECTORY_SEPARATOR . $item );
					$integration_files[ $dir ] = self::read_directory( $directory . DIRECTORY_SEPARATOR . $item );
				} else {
					// only include files that have .php extension
					$ext = pathinfo( $item, PATHINFO_EXTENSION );
					if ( 'php' !== (string) $ext ) {
						continue;
					}
					if ( preg_match( '/(add\-(.+)\-integration)/', $item ) ) {
						$integration_files['main'] = $directory . DIRECTORY_SEPARATOR . $item;
					} else {
						// Avoid Integromat fatal error if Pro < 3.0 and Free is >= 3.0
						if ( class_exists( '\Uncanny_Automator_Pro\InitializePlugin', false ) ) {
							$version = \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION;
							if ( version_compare( $version, '3.0', '<' ) ) {
								/**
								 * Added to avoid fatal errors from Pro specially for LearnDash and BuddyBoss
								 */
								return array();
							}
						}
						$integration_files[] = $directory . DIRECTORY_SEPARATOR . $item;
					}
				}
			}
		} catch ( Exception $e ) {
			throw new Automator_Exception( $e->getTraceAsString() );
		}

		return $integration_files;
	}

	/**
	 * Get all integration folders after read directory
	 *
	 * @param $integrations
	 * @param $directory
	 *
	 * @return array
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

	/**
	 * Initialize integrations
	 *
	 * @throws Exception
	 */
	public function initialize_add_integrations() {
		// Check each directory
		if ( ! self::$auto_loaded_directories ) {
			return;
		}
		foreach ( self::$auto_loaded_directories as $directory ) {
			$files    = array();
			$dir_name = basename( $directory );
			if ( ! isset( self::$all_integrations[ $dir_name ] ) ) {
				continue;
			}
			// Check if integration has main add-integration file
			if ( ! isset( self::$all_integrations[ $dir_name ]['main'] ) ) {
				continue;
			}

			$files[] = self::$all_integrations[ $dir_name ]['main'];
			if ( ! $files ) {
				continue;
			}
			foreach ( $files as $file ) {
				// bail early if the $file is not a string
				if ( is_array( $file ) ) {
					continue;
				}
				$class = apply_filters( 'automator_integrations_class_name', $this->get_class_name( $file, false, $dir_name ), $file );
				if ( class_exists( $class, false ) ) {
					continue;
				}
				if ( ! is_file( $file ) ) {
					continue;
				}
				include_once $file;
				$i                = new $class();
				$integration_code = method_exists( $i, 'get_integration' ) ? $i->get_integration() : $class::$integration;
				$active           = method_exists( $i, 'get_integration' ) ? $i->plugin_active() : $i->plugin_active( 0, $integration_code );
				$active           = apply_filters( 'automator_maybe_integration_active', $active, $integration_code );
				/**
				 * Store all the integrations, regardless of the status,
				 * to get integration name and the icon
				 * @since v4.6
				 */
				$integration_name = method_exists( $i, 'get_name' ) ? $i->get_name() : '';
				$integration_icon = method_exists( $i, 'get_integration_icon' ) ? $i->get_integration_icon() : '';
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
					unset( $i );
					continue;
				}
				/**
				 * Include only active integrations. Legacy method.
				 *
				 * @since 3.0, trait-integrations.php does not contains this function. Not required to define
				 * for each integration now.
				 * @see \Uncanny_Automator\Recipe\Integrations::add_integration()
				 */
				if ( method_exists( $i, 'add_integration_func' ) ) {
					$i->add_integration_func();
				}

				if ( ! in_array( $integration_code, self::$active_integrations_code, true ) ) {
					self::$active_integrations_code[] = $integration_code;
				}

				$this->active_directories[ $dir_name ] = $i;
				if ( method_exists( $i, 'add_integration_directory_func' ) ) {
					$directories_to_include = $i->add_integration_directory_func( array(), $file );
					if ( $directories_to_include ) {
						foreach ( $directories_to_include as $dir ) {
							$this->directories_to_include[ $dir_name ][] = basename( $dir );
						}
					}
				}

				//Now everything is checked, add integration to the system.
				if ( method_exists( $i, 'add_integration' ) ) {
					$i->add_integration( $i->get_integration(), array( $i->get_name(), $i->get_icon() ) );
				}
				Utilities::add_class_instance( $class, $i );
			}
			$this->active_directories = apply_filters( 'automator_active_integration_directories', $this->active_directories );
			Automator()->cache->set( 'automator_active_integrations', $this->active_directories );
		}
	}

	/**
	 * Filename to class name
	 *
	 * @param $file
	 *
	 * @return string
	 */
	public static function file_name_to_class( $file ) {
		$name = array_map(
			'ucfirst',
			explode(
				'-',
				str_replace(
					array(
						'class-',
						'.php',
					),
					'',
					basename( $file )
				)
			)
		);

		return join( '_', $name );
	}


	/**
	 * Initialize all trigger,action, and closure classes
	 */
	public function initialize_integration_helpers() {

		if ( empty( $this->active_directories ) ) {
			return;
		}

		foreach ( $this->active_directories as $dir_name => $object ) {
			$files = isset( self::$all_integrations[ $dir_name ]['helpers'] ) && in_array( 'helpers', $this->directories_to_include[ $dir_name ], true ) ? self::$all_integrations[ $dir_name ]['helpers'] : array();

			if ( empty( $files ) ) {
				continue;
			}
			// Loop through all files in directory to create class names from file name
			foreach ( $files as $file ) {
				// bail early if the $file is not a string
				if ( is_array( $file ) ) {
					continue;
				}

				$class = apply_filters( 'automator_helpers_class_name', $this->get_class_name( $file, false, $dir_name ), $file );
				if ( ! class_exists( $class, false ) ) {
					if ( ! is_file( $file ) ) {
						continue;
					}
					include_once $file;
					$mod            = str_replace( '-', '_', $dir_name );
					$class_instance = new $class();
					// Todo: Do not initiate helpers class.
					Utilities::add_helper_instance( $mod, $class_instance );
					if ( method_exists( $class_instance, 'load_hooks' ) ) {
						$class_instance->load_hooks();
					}
				}
			}
		}
	}

	/**
	 * Initialize all trigger,action, and closure classes
	 */
	public function initialize_triggers_actions_closures() {

		if ( empty( $this->active_directories ) ) {
			return;
		}

		// Adding textdomain fix trigger/action
		// sentences not getting translated
		Automator()->automator_load_textdomain();

		foreach ( $this->active_directories as $dir_name => $object ) {
			$mod = $dir_name;
			if ( ! isset( self::$all_integrations[ $mod ] ) ) {
				continue;
			}
			//Todo: Include directories in loop
			$tokens   = isset( self::$all_integrations[ $mod ]['tokens'] ) && in_array( 'tokens', $this->directories_to_include[ $dir_name ], true ) ? self::$all_integrations[ $mod ]['tokens'] : array();
			$triggers = isset( self::$all_integrations[ $mod ]['triggers'] ) && in_array( 'triggers', $this->directories_to_include[ $dir_name ], true ) ? self::$all_integrations[ $mod ]['triggers'] : array();
			$actions  = isset( self::$all_integrations[ $mod ]['actions'] ) && in_array( 'actions', $this->directories_to_include[ $dir_name ], true ) ? self::$all_integrations[ $mod ]['actions'] : array();
			$closures = isset( self::$all_integrations[ $mod ]['closures'] ) && in_array( 'closures', $this->directories_to_include[ $dir_name ], true ) ? self::$all_integrations[ $mod ]['closures'] : array();

			$files = array_merge( $tokens, $triggers, $actions, $closures );
			$files = apply_filters( 'automator_integration_files', $files, $dir_name );
			if ( empty( $files ) ) {
				continue;
			}
			// Loop through all files in directory to create class names from file name
			foreach ( $files as $file ) {
				if ( ! is_file( $file ) ) {
					continue;
				}
				// bail early if the $file is not a string
				if ( is_array( $file ) ) {
					continue;
				}
				$class = apply_filters( 'automator_recipe_parts_class_name', $this->get_class_name( $file, true, $mod ), $file );
				if ( ! class_exists( $class, false ) ) {
					include_once $file;
					Utilities::add_class_instance( $class, new $class() );
				}
			}
		}
	}

	/**
	 * Get a class name based on file name
	 *
	 * @param $file
	 * @param bool $uppercase
	 * @param string $integration_name
	 *
	 * @return mixed|void
	 */
	public function get_class_name( $file, $uppercase = false, $integration_name = '' ) {
		// Remove file extension my-class-name.php to my-class-name
		$file_name = basename( $file, '.php' );
		// Implode array into class name - eg. array( 'My', 'Class', 'Name') to MyClassName
		$class_name = self::file_name_to_class( $file_name );
		if ( $uppercase ) {
			$class_name = strtoupper( $class_name );
		}

		// Check if it's an internal Automator file
		$esc_characters = apply_filters( 'automator_esc_with_slash_characters', '/-:\\()_,.' );
		$pattern        = '/(' . addcslashes( $this->integrations_directory_path, $esc_characters ) . ')/';
		if ( preg_match( $pattern, $file ) ) {
			$class_name = __NAMESPACE__ . '\\' . $class_name;
		} else {
			$custom_namespace = isset( self::$external_integrations_namespace[ $integration_name ] ) ? self::$external_integrations_namespace[ $integration_name ] : '';
			$custom_namespace = apply_filters( 'automator_external_class_namespace', $custom_namespace, $class_name, $file_name, $file );
			$class_name       = apply_filters( 'automator_external_class_with_namespace', $custom_namespace . '\\' . $class_name, $class_name, $file_name, $file );
			if ( self::validate_namespace( $class_name, $file_name, $file, $integration_name ) ) {
				return $class_name;
			}
		}

		return apply_filters( 'automator_recipes_class_name', $class_name, $file, $file_name );
	}

	/**
	 * Validate namespace
	 *
	 * @param $class_name
	 * @param $file_name
	 * @param $file
	 * @param string $integration_name
	 *
	 * @return mixed|string
	 */
	public static function validate_namespace( $class_name, $file_name, $file, $integration_name = '' ) {
		$class_name = strtoupper( $class_name );
		//		try {
		//          $is_free = new ReflectionClass( 'Uncanny_Automator\\' . $class_name );
		//          if ( $is_free->inNamespace() ) {
		//              return 'Uncanny_Automator\\' . $class_name;
		//          }
		//      } catch ( ReflectionException $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		//      }
		//
		//      try {
		//          $is_pro = new ReflectionClass( 'Uncanny_Automator_Pro\\' . $class_name );
		//          if ( $is_pro->inNamespace() ) {
		//              return 'Uncanny_Automator_Pro\\' . $class_name;
		//          }
		//      } catch ( ReflectionException $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		//      }

		try {
			$is_custom = new ReflectionClass( $class_name );
			if ( $is_custom->inNamespace() ) {
				return true;
			}
		} catch ( ReflectionException $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		return false;
	}
}
