<?php
/*
 * Plugin Name: Uncanny Automator
 * Description: Connect WordPress plugins together to create powerful recipes that save time and improve the user experience. With no coding required, Uncanny Automator can replace dozens of plugins with millions of recipe combinations!
 * Version: 2.1.1
 * Author: Uncanny Owl
 * Author URI: https://www.uncannyowl.com/
 * Plugin URI: https://automatorplugin.com/
 * Text Domain: uncanny-automator
 * Domain Path: /languages
 */

namespace Uncanny_Automator;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'AUTOMATOR_BASE_FILE' ) ) {
	define( 'AUTOMATOR_BASE_FILE', __FILE__ );
	//fallback for older pro version. TODO remove below line after couple releases
}

//fallback for older pro version. TODO remove below line after couple releases
if ( ! defined( 'UAP_AUTOMATOR_FILE_' ) ) {
	define( 'UAP_AUTOMATOR_FILE_', __FILE__ );
}

if ( ! defined( 'AUTOMATOR_REST_API_END_POINT' ) ) {
	define( 'AUTOMATOR_REST_API_END_POINT', 'uap/v2/' );
}

//fallback for older pro version. TODO remove below line after couple releases
if ( ! defined( 'REST_API_END_POINT' ) ) {
	define( 'REST_API_END_POINT', 'uap/v2/' );
}

if ( ! defined( 'AUTOMATOR_CONFIGURATION_PRIORITY' ) ) {
	define( 'AUTOMATOR_CONFIGURATION_PRIORITY', 10 );
}

if ( ! defined( 'AUTOMATOR_ACTIONIFY_TRIGGERS_PRIORITY' ) ) {
	define( 'AUTOMATOR_ACTIONIFY_TRIGGERS_PRIORITY', 20 );
}

if ( ! defined( 'AUTOMATOR_CONFIGURATION_COMPLETE_PRIORITY' ) ) {
	define( 'AUTOMATOR_CONFIGURATION_COMPLETE_PRIORITY', 10 );
}

// this global variable stores many functions that can be used for integrations, triggers, actions, and closures
global $uncanny_automator;
require_once( dirname( AUTOMATOR_BASE_FILE ) . '/src/core/mu-classes/mu-functions.php' );
$uncanny_automator = new Automator_Functions();


require_once( plugin_dir_path( __FILE__ ) . '/src/libraries/action-scheduler/action-scheduler.php' );


/**
 * This class initiates the plugin load sequence and sets general plugin variables
 *
 * @package Uncanny_Automator
 */
class InitializePlugin {

	/**
	 * The plugin name
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const PLUGIN_NAME = 'Uncanny Automator';

	/**
	 * The plugin name acronym
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const PLUGIN_PREFIX = 'uap';

	/**
	 * Min PHP Version
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const MIN_PHP_VERSION = '7.0';

	/**
	 * The plugin version number
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const PLUGIN_VERSION = '2.1.1';

	/**
	 * The database version number
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const DATABASE_VERSION = '1.0';

	/**
	 * The full path and filename
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const MAIN_FILE = __FILE__;

	/**
	 * Allows the debugging scripts to initialize and log them in a file
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $log_debug_messages = false;

	/**
	 * The instance of the class
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Object
	 */
	private static $instance = null;

	/**
	 * Creates singleton instance of class
	 *
	 * @return InitializePlugin $instance The InitializePlugin Class
	 * @since 1.0.0
	 *
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * class constructor
	 */
	private function __construct() {

		// Load text domain
		add_action( 'plugins_loaded', array( $this, 'automator_load_textdomain' ) );

		// Load Utilities
		$this->initialize_utilities();

		// Load Configuration
		$this->initialize_config();

		// Load the plugin files
		$this->boot_plugin();
	}


	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	function automator_load_textdomain() {
		load_plugin_textdomain( 'uncanny-automator', false, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Initialize static singleton class that has shared functions and variables
	 *
	 * @since 1.0.0
	 */
	private function initialize_utilities() {

		include_once( dirname( __FILE__ ) . '/src/utilities.php' );
		Utilities::set_date_time_format();

	}

	/**
	 * Initialize static singleton class that configures all constants, utilities variables and handles activation/deactivation
	 *
	 * @since 1.0.0
	 */
	private function initialize_config() {

		include_once( dirname( __FILE__ ) . '/src/config.php' );

		$config_instance = Config::get_instance();
		$config_instance->configure_plugin_before_boot( self::PLUGIN_NAME, self::PLUGIN_PREFIX, self::PLUGIN_VERSION, self::MAIN_FILE, $this->log_debug_messages );
	}

	/**
	 * Initialize Static singleton class auto loads all the files needed for the plugin to work
	 *
	 * @since 1.0.0
	 */
	private function boot_plugin() {


		// Only include Module_interface, do not initialize is ... interfaces cannot be initialized
		add_filter( 'Skip_class_initialization', array( $this, 'add_skipped_classes' ), 10, 1 );

		include_once( dirname( __FILE__ ) . '/src/boot.php' );
		Boot::get_instance();

		do_action( Utilities::get_prefix() . '_plugin_loaded' );

	}

	/**
	 * Add Classes that need to be included automatically but not initialized
	 *
	 * @param array $skipped_classes Collection of classes that are being skipped over for initialization (new Class)
	 *
	 * @return array
	 */
	public function add_skipped_classes( $skipped_classes ) {
		$skipped_classes[] = 'Module_Interface';

		return $skipped_classes;
	}
}

// Let's run it
InitializePlugin::get_instance();

add_filter( 'gettext', 'Uncanny_Automator\uap_temp_warning_change', 20, 3 );

/**
 * Change comment form default field names.
 *
 * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/gettext
 */
function uap_temp_warning_change( $translated_text, $text, $domain ) {

	switch ( $translated_text ) {

		case 'Please activate Uncanny Automator before activating Uncanny Automator Pro.':
			$translated_text = __( 'Uncanny Automator Pro needs to be updated before it can be used with the new updates and enhancements of Uncanny Automator.', 'theme_text_domain' );
			break;
	}

	return $translated_text;
}