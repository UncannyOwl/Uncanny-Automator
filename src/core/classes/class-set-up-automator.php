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
	 * @var array
	 */
	public $directories_to_include = array();

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

		add_action( 'plugins_loaded', array( $this, 'automator_configure' ), AUTOMATOR_CONFIGURATION_PRIORITY );
		add_action(
			'automator_configuration_complete',
			array(
				$this,
				'automator_configuration_complete_func',
			),
			AUTOMATOR_CONFIGURATION_COMPLETE_PRIORITY
		);
		add_action( 'admin_notices', array( $this, 'automator_pro_configure' ), 999 );
	}

	/**
	 * @since 3.0.4
	 */
	public function automator_pro_configure() {
		if ( ! class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
			return;
		}
		$version = \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION;
		if ( version_compare( $version, '3.0', '<' ) ) {
			?>
			<div class="notice notice-error">
				<?php
				echo sprintf(
					'<p><strong>%s:</strong> %s</p>',
					esc_html__( 'Warning', 'uncanny-automator' ),
					sprintf(
						'%s (%s) %s <a href="%s" target="_blank">%s<span style="font-size:14px; margin-left:-3px" class="dashicons dashicons-external"></span></a>',
						esc_html__( 'The version of Uncanny Automator Pro', 'uncanny-automator' ),
						esc_attr( $version ),
						esc_html__( 'installed on your site is incompatible with Uncanny Automator 3.0 and higher. Uncanny Automator Pro has been temporarily disabled. Upgrade to the latest version of Uncanny Automator Pro to re-enable functionality or downgrade Uncanny Automator to version 2.11.1.', 'uncanny-automator' ),
						'https://automatorplugin.com/knowledge-base/upgrading-to-uncanny-automator-3-0/?utm_medium=admin_notice&utm_campaign=30upgradewarning',
						esc_html__( 'Learn More', 'uncanny-automator' )
					)  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<?php
		}
	}

	/**
	 * Sets all trigger, actions, and closure classes directories
	 *
	 * @throws Exception
	 */
	public function get_integrations_autoload_directories() {
		$directory = UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'integrations';
		try {
			$integrations = self::read_directory( $directory );
		} catch ( Exception $e ) {
			throw new Automator_Exception( $e->getTraceAsString() );
		}
		$integrations           = apply_filters_deprecated( 'uncanny_automator_integrations', array( $integrations ), '3.0', 'automator_integrations_setup' );
		self::$all_integrations = apply_filters( 'automator_integrations_setup', $integrations );
		Automator()->cache->set( 'automator_get_all_integrations', self::$all_integrations, 'automator', Automator()->cache->long_expires );

		return self::extract_integration_folders( self::$all_integrations, $directory );
	}

	/**
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
					if ( preg_match( '/(add-)/', $item ) ) {
						$integration_files['main'] = $directory . DIRECTORY_SEPARATOR . $item;
					} else {
						// Avoid Integromat fatal error if Pro < 3.0 and Free is >= 3.0
						if ( class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
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
	 * @param $integrations
	 * @param $directory
	 *
	 * @return array
	 */
	public static function extract_integration_folders( $integrations, $directory ) {
		$folders = array();
		if ( $integrations ) {
			foreach ( $integrations as $f => $integration ) {
				$path      = isset( $integration['main'] ) ? dirname( $integration['main'] ) : $directory . DIRECTORY_SEPARATOR . $f;
				$path      = apply_filters( 'automator_integration_folder_paths', $path, $integration, $directory, $f );
				$folders[] = $path;
			}
		}

		return apply_filters( 'automator_integration_folders', $folders, $integrations, $directory );
	}

	/**
	 * Hook Here
	 *
	 * @throws Exception
	 */
	public function automator_configure() {

		// Add all extensions --- hook here to add your own triggers and actions
		do_action( 'automator_configure' );
		// Sets all trigger, actions, and closure classes directories for spl autoloader
		self::$auto_loaded_directories = Automator()->cache->get( 'automator_integration_directories_loaded' );
		self::$all_integrations        = Automator()->cache->get( 'automator_get_all_integrations' );

		if ( empty( self::$auto_loaded_directories ) || empty( self::$all_integrations ) ) {
			self::$auto_loaded_directories = $this->get_integrations_autoload_directories();
			Automator()->cache->set( 'automator_integration_directories_loaded', self::$auto_loaded_directories, 'automator', Automator()->cache->long_expires );
		}
		// Loads all internal triggers, actions, and closures then provides hooks for external ones
		// All extensions are loaded.
		do_action( 'automator_configuration_complete' );
	}

	/**
	 *
	 * @throws Exception
	 */
	public function automator_configuration_complete_func() {

		//Let others hook in and add integrations
		do_action_deprecated( 'uncanny_automator_add_integration', array(), '3.0', 'automator_add_integration' );
		do_action( 'automator_add_integration' );

		// Loads integrations
		try {
			$this->initialize_add_integrations();
		} catch ( Exception $e ) {
			throw new Automator_Exception( $e->getMessage() );
		}

		//Let others hook in and add integrations
		do_action_deprecated( 'uncanny_automator_add_recipe_type', array(), '3.0', 'automator_add_recipe_type' );
		do_action( 'automator_add_recipe_type' );

		//Let others hook in to the directories and add their integration's actions / triggers etc
		self::$auto_loaded_directories = apply_filters_deprecated( 'uncanny_automator_integration_directory', array( self::$auto_loaded_directories ), '3.0', 'automator_integration_directory' );
		self::$auto_loaded_directories = apply_filters( 'automator_integration_directory', self::$auto_loaded_directories );
		// Loads all options and provide a hook for external options
		add_action(
			'plugins_loaded',
			function () {
				$this->initialize_integration_helpers();
				// Let others hook in and add options
				do_action_deprecated( 'uncanny_automator_add_integration_helpers', array(), '3.0', 'automator_add_integration_helpers' );
				do_action( 'automator_add_integration_helpers' );

				// Loads all internal triggers, actions, and closures then provides hooks for external ones
				$this->initialize_triggers_actions_closures();

				// Let others hook in and add triggers actions or tokens
				do_action_deprecated( 'uncanny_automator_add_integration_triggers_actions_tokens', array(), '3.0', 'automator_add_integration_recipe_parts' );
				do_action( 'automator_add_integration_recipe_parts' );
			}
		);

	}

	/**
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

				if ( ! file_exists( $file ) ) {
					continue;
				}
				require_once $file;
				$class = apply_filters( 'automator_integrations_class_name', $this->get_class_name( $file ), $file );
				try {
					$is_using_trait = ( new ReflectionClass( $class ) )->getTraits();
				} catch ( ReflectionException $e ) {
					throw new Automator_Exception( $e->getMessage() );
				}
				$i                = new $class();
				$integration_code = ! empty( $is_using_trait ) ? $i->get_integration() : $class::$integration;
				$active           = ! empty( $is_using_trait ) ? $i->plugin_active() : $i->plugin_active( 0, $integration_code );
				$active           = apply_filters( 'automator_maybe_integration_active', $active, $integration_code );
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
				$this->active_directories              = apply_filters( 'automator_active_integration_directories', $this->active_directories );
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
				Automator()->cache->set( 'automator_active_integrations', $this->active_directories );
				Utilities::add_class_instance( $class, $i );
			}
		}
	}

	/**
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
				if ( ! file_exists( $file ) ) {
					continue;
				}
				require_once $file;
				$class = apply_filters( 'automator_helpers_class_name', $this->get_class_name( $file ), $file );
				if ( class_exists( $class ) ) {
					$mod = str_replace( '-', '_', $dir_name );
					try {
						$reflection = new ReflectionClass( $class );
						if ( $reflection->hasMethod( 'setOptions' ) ) {
							// Todo: Do not initiate helpers class.
							Utilities::add_helper_instance( $mod, new $class() );
						}
					} catch ( Automator_Exception $e ) {
						// is not a helper file.. shouldn't be loaded as helper
						Utilities::add_class_instance( $class, new $class() );
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
				// bail early if the $file is not a string
				if ( is_array( $file ) ) {
					continue;
				}
				if ( ! file_exists( $file ) ) {
					continue;
				}
				require_once $file;
				$class = apply_filters( 'automator_recipe_parts_class_name', $this->get_class_name( $file ), $file );
				if ( class_exists( $class ) ) {
					Utilities::add_class_instance( $class, new $class() );
				}
			}
		}
	}

	/**
	 * @param $file
	 *
	 * @return mixed|void
	 */
	public function get_class_name( $file ) {
		// Remove file extension my-class-name.php to my-class-name
		$file_name = basename( $file, '.php' );
		// Implode array into class name - eg. array( 'My', 'Class', 'Name') to MyClassName
		$class_name = self::file_name_to_class( $file_name );
		$class      = self::validate_namespace( $class_name, $file_name, $file );

		return apply_filters( 'automator_recipes_class_name', $class, $file, $file_name );
	}

	/**
	 * @param $class_name
	 * @param $file_name
	 * @param $file
	 *
	 * @return mixed|string
	 */
	public static function validate_namespace( $class_name, $file_name, $file ) {
		$class_name = strtoupper( $class_name );
		try {
			$is_free = new ReflectionClass( 'Uncanny_Automator\\' . $class_name );
			if ( $is_free->inNamespace() ) {
				return 'Uncanny_Automator\\' . $class_name;
			}
		} catch ( ReflectionException $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		try {
			$is_pro = new ReflectionClass( 'Uncanny_Automator_Pro\\' . $class_name );
			if ( $is_pro->inNamespace() ) {
				return 'Uncanny_Automator_Pro\\' . $class_name;
			}
		} catch ( ReflectionException $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		try {
			$custom_namespace = apply_filters( 'automator_class_namespace', __NAMESPACE__, $class_name, $file_name, $file );
			$is_custom        = new ReflectionClass( $custom_namespace . '\\' . $class_name );
			if ( $is_custom->inNamespace() ) {
				return $custom_namespace . '\\' . $class_name;
			}
		} catch ( ReflectionException $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		return $class_name;
	}
}
