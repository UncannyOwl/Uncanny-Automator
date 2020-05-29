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
	public $default_directories = [];

	/**
	 * SetAutomatorTriggers constructor.
	 */
	public function __construct() {
		$this->default_directories = [ 'actions', 'helpers', 'tokens', 'triggers', 'closures' ];
		add_action( 'plugins_loaded', array( $this, 'automator_configure' ), AUTOMATOR_CONFIGURATION_PRIORITY );
		add_action( 'automator_configuration_complete', array(
			$this,
			'automator_configuration_complete_func'
		), AUTOMATOR_CONFIGURATION_COMPLETE_PRIORITY );

		// Sets all trigger, actions, and closure classes directories for spl autoloader
		$this->auto_loaded_directories = $this->get_integrations_autoload_directories();

		// Loads all internal triggers, actions, and closures then provides hooks for external ones
		spl_autoload_register( array( $this, 'require_triggers_actions' ) );
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
		$directory    = dirname( AUTOMATOR_BASE_FILE ) . '/src/integrations';
		$integrations = glob( $directory . '/*', GLOB_ONLYDIR );

		return $integrations;
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
	 * @throws \ReflectionException
	 */
	public function initialize_add_integrations() {
		// Check each directory
		if ( $this->auto_loaded_directories ) {

			foreach ( $this->auto_loaded_directories as $directory ) {
				$files = array_diff( scandir( $directory ), array(
					'..',
					'.',
					'index.php',
					'helpers',
					'actions',
					'triggers',
					'tokens',
					'closures',
				) );

				if ( $files ) {
					foreach ( $files as $file ) {
						$file = "$directory/$file";
						if ( file_exists( $file ) ) {
							$file_name = basename( $file, '.php' );

							// Split file name on - eg: my-class-name to array( 'my', 'class', 'name')
							$class_to_filename = explode( '-', $file_name );

							// Make the first letter of each word in array upper case - eg array( 'my', 'class', 'name') to array( 'My', 'Class', 'Name')
							$class_to_filename = array_map( function ( $word ) {
								return ucfirst( $word );
							}, $class_to_filename );

							// Implode array into class name - eg. array( 'My', 'Class', 'Name') to MyClassName
							$class_name = implode( '_', $class_to_filename );

							$class = __NAMESPACE__ . '\\' . strtoupper( $class_name );
							require_once $file;

							$status = false;

							$reflection_class = new \ReflectionClass( $class );

							$static_properties = $reflection_class->getStaticProperties();
							if ( key_exists( 'integration', $static_properties ) ) {
								$integration = $reflection_class->getStaticPropertyValue( 'integration' );
								$instance    = $reflection_class->newInstanceWithoutConstructor();
								$status      = $instance->plugin_active( 0, $integration );
							}

							if ( true === $status ) {
								Utilities::add_class_instance( $class, new $class() );
							}
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

		global $uncanny_automator;
		if ( $this->auto_loaded_directories ) {
			// Check each directory
			foreach ( $this->auto_loaded_directories as $directory ) {
				if ( in_array( basename( $directory ), [ 'helpers' ] ) ) {

					if ( file_exists( $directory ) ) {
						// Get all files in directory
						// remove parent directory, sub directory, and silence is golden index.php
						$files = array_diff( scandir( $directory ), array( '..', '.', 'index.php' ) );
						if ( $files ) {
							// Loop through all files in directory to create class names from file name
							foreach ( $files as $file ) {
								if ( strpos( $file, '.php' ) ) {
									// Remove file extension my-class-name.php to my-class-name
									$file_name = basename( $file, '.php' );

									// Split file name on - eg: my-class-name to array( 'my', 'class', 'name')
									$class_to_filename = explode( '-', $file_name );

									// Make the first letter of each word in array upper case - eg array( 'my', 'class', 'name') to array( 'My', 'Class', 'Name')
									$class_to_filename = array_map( function ( $word ) {
										return ucfirst( $word );
									}, $class_to_filename );

									// Implode array into class name - eg. array( 'My', 'Class', 'Name') to MyClassName
									$class_name = implode( '_', $class_to_filename );

									$class = __NAMESPACE__ . '\\' . $class_name;
									$key   = str_replace( '-', '_', basename( dirname( $directory ) ) );
									if ( class_exists( $class ) ) {
										Utilities::add_helper_instance( $key, new $class() );
									}
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Initialize all trigger,action, and closure classes
	 */
	public function initialize_triggers_actions_closures() {

		global $uncanny_automator;
		if ( $this->auto_loaded_directories ) {
			// Check each directory
			foreach ( $this->auto_loaded_directories as $directory ) {
				if ( in_array( basename( $directory ), $this->default_directories ) ) {

					if ( file_exists( $directory ) ) {
						// Get all files in directory
						// remove parent directory, sub directory, and silence is golden index.php
						$files = array_diff( scandir( $directory ), array( '..', '.', 'index.php' ) );

						if ( $files ) {
							// Loop through all files in directory to create class names from file name
							foreach ( $files as $file ) {
								if ( strpos( $file, '.php' ) ) {
									// Remove file extension my-class-name.php to my-class-name
									$file_name = basename( $file, '.php' );

									// Split file name on - eg: my-class-name to array( 'my', 'class', 'name')
									$class_to_filename = explode( '-', $file_name );

									// Make the first letter of each word in array upper case - eg array( 'my', 'class', 'name') to array( 'My', 'Class', 'Name')
									$class_to_filename = array_map( function ( $word ) {
										return ucfirst( $word );
									}, $class_to_filename );

									// Implode array into class name - eg. array( 'My', 'Class', 'Name') to MyClassName
									$class_name = implode( '_', $class_to_filename );

									$class = __NAMESPACE__ . '\\' . strtoupper( $class_name );

									// We way want to include some class with the autoloader but not initialize them ex. interface class
									$skip_classes = apply_filters( 'skip_class_initialization', array(), $directory, $files, $class, $class_name );
									if ( in_array( $class_name, $skip_classes ) ) {
										continue;
									}

									if ( class_exists( $class ) ) {
										$reflection_class  = new \ReflectionClass( $class );
										$static_properties = $reflection_class->getStaticProperties();

										$status = 0;

										if ( key_exists( 'integration', $static_properties ) ) {
											$integration = $reflection_class->getStaticPropertyValue( 'integration' );
											$status      = $uncanny_automator->plugin_status->get( $integration );
										}

										if ( 1 === (int) $status && class_exists( $class ) ) {
											Utilities::add_class_instance( $class, new $class() );
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Require all trigger,action, and closure classes
	 *
	 * @param $class
	 */
	public function require_triggers_actions( $class ) {

		// Remove Class's namespace eg: my_namespace/MyClassName to MyClassName
		$class = str_replace( __NAMESPACE__, '', $class );
		$class = str_replace( '\\', '', $class );

		// Replace _ with - eg. eg: My_Class_Name to My-Class-Name
		$class_to_filename = str_replace( '_', '-', $class );

		// Create file name that will be loaded from the classes directory eg: My-Class-Name to my-class-name.php
		$file_name = strtolower( $class_to_filename ) . '.php';

		// Check each directory
		foreach ( $this->auto_loaded_directories as $directory ) {

			if ( in_array( basename( $directory ), $this->default_directories ) ) {
				//$directory = str_replace( dirname( AUTOMATOR_BASE_FILE ) . '/', '', $directory );
				if ( 'index.php' !== $file_name ) {
					$file_path = $directory . DIRECTORY_SEPARATOR . $file_name;


					// Does the file exist
					if ( file_exists( $file_path ) ) {
						// File found, require it
						require_once( $file_path );

						// You can cannot have duplicate files names. Once the first file is found, the loop ends.
						return;
					}
				}
			}
		}
	}
}
