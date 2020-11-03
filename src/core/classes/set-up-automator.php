<?php

namespace Uncanny_Automator;

/**
 * Class Set_Automator_Triggers
 * @package Uncanny_Automator
 */
class Set_Up_Automator {

	/**
	 * The directories that are auto loaded and initialized
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      array
	 */
	public $auto_loaded_directories = null;
	/**
	 * @var array
	 */
	public $active_directories = array();
	/**
	 * @var array|string[]
	 */
	public $default_directories = [];

	/**
	 * @var array
	 */
	public $all_integrations = array();

	/**
	 * @var array
	 */
	public $directories_to_include = array();

	/**
	 * @var array
	 */
	public static $active_integrations_code = array();

	/**
	 * Set_Up_Automator constructor.
	 */
	public function __construct() {
		if ( isset( $_SERVER['REQUEST_URI'] ) && 'favicon.ico' === basename( $_SERVER['REQUEST_URI'] ) ) {
			// bail out if it's favicon.ico
			return;
		}

		if ( isset( $_GET['doing_wp_cron'] ) ) {

			$automator_in_cron = false;

			$next_crons_jobs = wp_get_ready_cron_jobs();

			foreach ( $next_crons_jobs as $cron_job ) {
				if ( isset( $cron_job['uo_ceu_scheduled_learndash_course_completed'] ) ) {
					$automator_in_cron = true;
					break;
				}
			}

			$automator_in_cron = apply_filters( 'uap_run_automator_actions', $automator_in_cron );

			if ( ! $automator_in_cron ) {
				return;
			}
		}

		$this->default_directories = [ 'actions', 'helpers', 'tokens', 'triggers', 'closures' ];
		add_action( 'plugins_loaded', array( $this, 'automator_configure' ), AUTOMATOR_CONFIGURATION_PRIORITY );
		add_action( 'automator_configuration_complete', array(
			$this,
			'automator_configuration_complete_func'
		), AUTOMATOR_CONFIGURATION_COMPLETE_PRIORITY );

		// Sets all trigger, actions, and closure classes directories for spl autoloader
		$this->auto_loaded_directories = $this->get_integrations_autoload_directories();

		// Loads all internal triggers, actions, and closures then provides hooks for external ones
	}

	/**
	 * Hook Here
	 */
	public function automator_configure() {

		// Add all extensions --- hook here to add your own triggers and actions
		do_action( 'automator_configure' );

		$this->get_integrations_autoload_directories();

		// All extensions are loaded.
		do_action( 'automator_configuration_complete' );
	}

	/**
	 * Sets all trigger, actions, and closure classes directories
	 */
	public function get_integrations_autoload_directories() {
		$directory              = dirname( AUTOMATOR_BASE_FILE ) . '/src/integrations';
		$integrations           = self::read_directory( $directory );
		$this->all_integrations = $integrations;

		return self::extract_integration_folders( $integrations, $directory );
	}

	/**
	 * @param $integrations
	 * @param $directory
	 *
	 * @return array
	 */
	public static function extract_integration_folders( $integrations, $directory ) {
		$folders = [];
		if ( $integrations ) {
			foreach ( $integrations as $f => $integration ) {
				$folders[] = "{$directory}/{$f}";
			}
		}

		return $folders;
	}

	/**
	 *
	 */
	public function automator_configuration_complete_func() {

		// Loads integrations
		$this->initialize_add_integrations();

		//Let others hook in and add integrations
		do_action( 'uncanny_automator_add_recipe_type' );

		//Let others hook in and add integrations
		do_action( 'uncanny_automator_add_integration' );

		//Let others hook in to the directories and add their integration's actions / triggers etc
		$this->auto_loaded_directories = apply_filters( 'uncanny_automator_integration_directory', $this->auto_loaded_directories );

		//spl_autoload_register( array( $this, 'require_triggers_actions' ) );

		// Loads all options and provide a hook for external options
		$this->initialize_integration_helpers();

		// Let others hook in and add options
		do_action( 'uncanny_automator_add_integration_helpers' );

		// Loads all internal triggers, actions, and closures then provides hooks for external ones
		$this->initialize_triggers_actions_closures();

		// Let others hook in and add triggers actions or tokens
		do_action( 'uncanny_automator_add_integration_triggers_actions_tokens' );
	}

	/**
	 *
	 */
	public function initialize_add_integrations() {
		// Check each directory
		if ( $this->auto_loaded_directories ) {

			foreach ( $this->auto_loaded_directories as $directory ) {
				$files    = array();
				$dir_name = basename( $directory );
				if ( ! isset( $this->all_integrations[ $dir_name ] ) ) {
					continue;
				}

				$files[] = $this->all_integrations[ $dir_name ]['main'];

				if ( $files ) {
					foreach ( $files as $file ) {
						if ( file_exists( $file ) ) {
							$file_name = basename( $file, '.php' );

							$class_name = self::file_name_to_class( $file_name );

							$class = __NAMESPACE__ . '\\' . strtoupper( $class_name );
							require_once $file;

							$i = new $class();

							if ( ! method_exists( $i, 'plugin_active' ) ) {
								continue;
							}
							$integration_code = $class::$integration;
							$active           = $i->plugin_active( 0, $integration_code );

							if ( true !== $active ) {
								unset( $i );
								continue;
							}

							// Include only active integrations
							if ( method_exists( $i, 'add_integration_func' ) ) {
								$i->add_integration_func();
							}

							if ( ! in_array( $integration_code, self::$active_integrations_code, true ) ) {
								self::$active_integrations_code[] = $integration_code;
							}

							$this->active_directories[ $dir_name ] = $i;

							if ( method_exists( $i, 'add_integration_directory_func' ) ) {
								$directories_to_include = $i->add_integration_directory_func( array() );
								if ( $directories_to_include ) {
									foreach ( $directories_to_include as $dir ) {
										$this->directories_to_include[ $dir_name ][] = basename( $dir );
									}
								}
							}
							Utilities::add_class_instance( $class, $i );
						}
					}
				}
			}
		}
	}

	/**
	 * Initialize all trigger,action, and closure classes
	 */
	public function initialize_integration_helpers() {

		if ( empty( $this->active_directories ) ) {
			return;
		}

		foreach ( $this->active_directories as $dir_name => $object ) {
			$files = isset( $this->all_integrations[ $dir_name ]['helpers'] ) && in_array( 'helpers', $this->directories_to_include[ $dir_name ], true ) ? $this->all_integrations[ $dir_name ]['helpers'] : array();

			if ( empty( $files ) ) {
				continue;
			}
			// Loop through all files in directory to create class names from file name
			foreach ( $files as $file ) {
				require_once $file;
				// Remove file extension my-class-name.php to my-class-name
				$file_name = basename( $file, '.php' );

				// Implode array into class name - eg. array( 'My', 'Class', 'Name') to MyClassName
				$class_name = self::file_name_to_class( $file_name );

				$class = __NAMESPACE__ . '\\' . $class_name;
				if ( class_exists( $class ) ) {
					$mod = str_replace( '-', '_', $dir_name );
					Utilities::add_helper_instance( $mod, new $class() );
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
			if ( ! isset( $this->all_integrations[ $mod ] ) ) {
				continue;
			}
			$tokens   = isset( $this->all_integrations[ $mod ]['tokens'] ) && in_array( 'tokens', $this->directories_to_include[ $dir_name ], true ) ? $this->all_integrations[ $mod ]['tokens'] : array();
			$triggers = isset( $this->all_integrations[ $mod ]['triggers'] ) && in_array( 'triggers', $this->directories_to_include[ $dir_name ], true ) ? $this->all_integrations[ $mod ]['triggers'] : array();
			$actions  = isset( $this->all_integrations[ $mod ]['actions'] ) && in_array( 'actions', $this->directories_to_include[ $dir_name ], true ) ? $this->all_integrations[ $mod ]['actions'] : array();
			$closures = isset( $this->all_integrations[ $mod ]['closures'] ) && in_array( 'closures', $this->directories_to_include[ $dir_name ], true ) ? $this->all_integrations[ $mod ]['closures'] : array();

			$files = array_merge( $tokens, $triggers, $actions, $closures );

			if ( empty( $files ) ) {
				continue;
			}
			// Loop through all files in directory to create class names from file name
			foreach ( $files as $file ) {
				require_once $file;
				// Remove file extension my-class-name.php to my-class-name
				$file_name = basename( $file, '.php' );

				// Implode array into class name - eg. array( 'My', 'Class', 'Name') to MyClassName
				$class_name = self::file_name_to_class( $file_name );

				if ( preg_match( '/tokens/', $file ) ) {
					$class = __NAMESPACE__ . '\\' . $class_name;
				} else {
					$class = __NAMESPACE__ . '\\' . strtoupper( $class_name );
				}

				if ( class_exists( $class ) ) {
					Utilities::add_class_instance( $class, new $class() );
				}
			}
		}

	}

	/**
	 * @param      $directory
	 * @param bool $recursive
	 *
	 * @return array|false
	 */
	public static function read_directory( $directory, $recursive = true ) {
		if ( is_dir( $directory ) === false ) {
			return false;
		}

		try {
			$resource          = opendir( $directory );
			$integration_files = array();
			while ( false !== ( $item = readdir( $resource ) ) ) {
				if ( (string) '.' === (string) $item || (string) '..' === (string) $item || (string) 'index.php' === (string) $item ) {
					continue;
				}

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
					if ( preg_match( '/(add\-)/', $item ) ) {
						$integration_files['main'] = $directory . DIRECTORY_SEPARATOR . $item;
					} else {
						$integration_files[] = $directory . DIRECTORY_SEPARATOR . $item;
					}
				}
			}
		}
		catch ( \Exception $e ) {
			return false;
		}

		return $integration_files;
	}

	/**
	 * @param $file
	 *
	 * @return string
	 */
	public static function file_name_to_class( $file ) {
		$name = array_map( 'ucfirst', explode( '-', str_replace( array(
			'class-',
			'.php',
		), '', basename( $file ) ) ) );

		return join( '_', $name );
	}

}
