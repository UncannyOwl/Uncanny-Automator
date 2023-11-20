<?php

namespace Uncanny_Automator;

use Error;
use Exception;

/**
 *
 */
class Initialize_Automator extends Set_Up_Automator {
	/**
	 * Set_Up_Automator constructor.
	 *
	 * @throws Exception
	 */
	public function __construct() {
		$this->integrations_directory_path = UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'integrations';

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

		add_action(
			'plugins_loaded',
			array( $this, 'automator_configure' ),
			AUTOMATOR_CONFIGURATION_PRIORITY
		);
	}

	/**
	 * Configure Automator
	 *
	 * @throws Exception
	 */
	public function automator_configure() {

		// Add all extensions --- hook here to add your own triggers and actions
		do_action_deprecated( 'automator_configure', array(), '4.2', 'automator_before_configure' );
		do_action( 'automator_before_configure', $this );

		$this->load_integrations();

		// Loads all internal triggers, actions, and closures then provides hooks for external ones
		// All extensions are loaded.
		do_action( 'automator_configuration_complete', $this );

		$this->automator_configuration_complete_func();
	}

	/**
	 * @return void
	 * @throws Automator_Exception
	 */
	public function automator_configuration_complete_func() {

		//Let others hook in and add integrations
		do_action_deprecated( 'uncanny_automator_add_integration', array(), '3.0', 'automator_add_integration' );
		do_action( 'automator_add_integration' );

		// Loads integrations
		try {
			$this->initialize_add_integrations();
		} catch ( Error $e ) {
			throw new Automator_Error( $e->getMessage() );
		} catch ( Exception $e ) {
			throw new Automator_Exception( $e->getMessage() );
		}

		//Let others hook in to the directories and add their integration's actions / triggers etc
		self::$auto_loaded_directories = apply_filters_deprecated( 'uncanny_automator_integration_directory', array( self::$auto_loaded_directories ), '3.0', 'automator_integration_directory' );
		self::$auto_loaded_directories = apply_filters( 'automator_integration_directory', self::$auto_loaded_directories );

		//Let others hook in and add integrations
		do_action_deprecated( 'uncanny_automator_add_recipe_type', array(), '3.0', 'automator_add_recipe_type' );
		do_action( 'automator_add_recipe_type' );
		// Loads all options and provide a hook for external options
		// Load Helpers
		$this->load_helpers();

		// Load Recipe parts
		$this->load_recipe_parts();

	}

	/**
	 * Fetch integrations/* directories and initiate integrations
	 *
	 * @return void
	 * @throws Automator_Exception
	 * @throws Exception
	 */
	public function load_integrations() {
		// Sets all trigger, actions, and closure classes directories for spl autoloader
		self::$auto_loaded_directories = Automator()->cache->get( 'automator_integration_directories_loaded' );
		self::$all_integrations        = Automator()->cache->get( 'automator_get_all_integrations' );

		if ( empty( self::$auto_loaded_directories ) || empty( self::$all_integrations ) ) {
			self::$auto_loaded_directories = $this->get_integrations_autoload_directories();
			Automator()->cache->set( 'automator_integration_directories_loaded', self::$auto_loaded_directories, 'automator', Automator()->cache->long_expires );
		}

		$this->load_framework_integrations();
	}

	/**
	 * @return void
	 * @throws Automator_Exception
	 */
	public function load_helpers() {
		// Loads all options and provide a hook for external options
		try {
			$this->initialize_integration_helpers();
		} catch ( Error $e ) {
			throw new Automator_Error( $e->getMessage() );
		} catch ( Exception $e ) {
			throw new Automator_Exception( $e->getMessage() );
		}

		// Let others hook in and add options
		do_action_deprecated( 'uncanny_automator_add_integration_helpers', array(), '3.0', 'automator_add_integration_helpers' );
		do_action( 'automator_add_integration_helpers' );
	}

	/**
	 * @return void
	 * @throws Automator_Exception
	 */
	public function load_recipe_parts() {
		// Loads all internal triggers, actions, and closures then provides hooks for external ones
		try {
			$this->initialize_triggers_actions_closures();
		} catch ( Error $e ) {
			throw new Automator_Error( $e->getMessage() );
		} catch ( Exception $e ) {
			throw new Automator_Exception( $e->getMessage() );
		}

		// Let others hook in and add triggers actions or tokens
		do_action_deprecated( 'uncanny_automator_add_integration_triggers_actions_tokens', array(), '3.0', 'automator_add_integration_recipe_parts' );
		do_action( 'automator_add_integration_recipe_parts' );
	}

	/**
	 * load_framework_integrations
	 *
	 * Will scan the integrations folder and if one has the load.php file, it will include it.
	 *
	 * @return void
	 */
	public function load_framework_integrations() {

		$vendor_dir = dirname( AUTOMATOR_BASE_FILE ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR;

		$automator_file_map = include $vendor_dir . 'autoload_filemap.php';

		if ( empty( $automator_file_map ) ) {
			return;
		}

		foreach ( $automator_file_map as $file ) {
			include_once $file;
		}

		if ( empty( $automator_file_map ) ) {
			return;
		}

		foreach ( $automator_file_map as $file ) {
			include_once $file;
		}
	}
}
