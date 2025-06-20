<?php
/*
 * Plugin Name:         Uncanny Automator
 * Description:         Connect your WordPress plugins, sites and apps together with powerful automations. Save time and money with the #1 automation plugin for WordPress!
 * Author:              Uncanny Automator, Uncanny Owl
 * Author URI:          https://automatorplugin.com/
 * Plugin URI:          https://automatorplugin.com/
 * Text Domain:         uncanny-automator
 * Domain Path:         /languages
 * License:             GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 * Version:             6.6.0.1
 * Requires at least:   5.6
 * Requires PHP:        7.3
 */

use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Load;
use Uncanny_Automator\DB_Tables;

if ( ! defined( 'AUTOMATOR_PLUGIN_VERSION' ) ) {
	/*
	 * Specify Automator version.
	 */
	define( 'AUTOMATOR_PLUGIN_VERSION', '6.6.0.1' );
}

if ( ! defined( 'AUTOMATOR_BASE_FILE' ) ) {
	/*
	 * Specify Automator base file.
	 */
	define( 'AUTOMATOR_BASE_FILE', __FILE__ );
}

if ( ! defined( 'UNCANNY_AUTOMATOR_ASSETS_DIR' ) ) {
	/*
	 * Specify Automator assets directory.
	 */
	define( 'UNCANNY_AUTOMATOR_ASSETS_DIR', plugin_dir_path( __FILE__ ) . 'src/assets/' );
}

if ( ! defined( 'UNCANNY_AUTOMATOR_ASSETS_URL' ) ) {
	/*
	 * Specify Automator assets URL.
	 */
	define( 'UNCANNY_AUTOMATOR_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'src/assets/' );
}

if ( version_compare( PHP_VERSION, '7.3', '<' ) ) {
	add_action( 'admin_notices', 'automator_version_check_admin_notice', - 99999 );
	// Function to display admin notice
	/**
	 * Automator version check admin notice.
	 */
	function automator_version_check_admin_notice() {
		?>
		<div class="notice notice-error"
			style="border-left: 4px solid #dc3232; font-weight: bold; background-color: #fff4e5; color: #000;">
			<p>
			<?php
				// translators: %s: The version number of Uncanny Automator.
				printf( esc_html__( 'Notice: Uncanny Automator v%s requires PHP 7.3 or higher to run properly. Your current PHP version is below this requirement, so the plugin has been deactivated and all automations have stopped. Please upgrade your PHP version to ensure that your automations and other plugin features work correctly.', 'uncanny-automator' ), esc_html( AUTOMATOR_PLUGIN_VERSION ) );
			?>
				</p>
		</div>
		<?php
	}

	// Stop loading the plugin
	return;
}

/**
 * @param string $class
 *
 * @return void
 */
function automator_autoloader( $class_name ) {

	$class_name = strtolower( $class_name );

	global $automator_class_map;

	if ( ! $automator_class_map ) {
		$automator_class_map = include __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_classmap.php';
		$automator_class_map = array_change_key_case( $automator_class_map, CASE_LOWER );
	}

	if ( isset( $automator_class_map[ $class_name ] ) ) {
		include_once $automator_class_map[ $class_name ];
	}
}

spl_autoload_register( 'automator_autoloader' );

if ( ! defined( 'UA_ABSPATH' ) ) {
	/**
	 * Automator ABSPATH for file includes
	 */
	define( 'UA_ABSPATH', dirname( AUTOMATOR_BASE_FILE ) . DIRECTORY_SEPARATOR );
}


// Load database tables early
require UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'class-db-tables.php';

// Load database tables.
DB_Tables::register_tables();

// Autoload files
require UA_ABSPATH . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
// Add global functions.
require UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'global-functions.php';
// Add other global variables for plugin.
require UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'globals.php';
// Add InitializePlugin class for other plugins checking for version.
require UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'legacy.php';

/**
 * @return Automator_Functions
 */
function Automator() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	// this global variable stores many functions that can be used for integrations, triggers, actions, and closures.
	include_once UA_ABSPATH . 'src/core/lib/class-automator-functions.php';

	return Automator_Functions::get_instance();
}

// fallback for < 3.0 Automator plugin (Pro).
global $uncanny_automator;
$uncanny_automator = Automator();

if ( AUTOMATOR_PLUGIN_VERSION !== automator_get_option( 'AUTOMATOR_PLUGIN_VERSION', 0 ) ) {
	automator_update_option( 'AUTOMATOR_PLUGIN_VERSION', AUTOMATOR_PLUGIN_VERSION );

	Automator()->cache->reset_integrations_directory( null, null );
}

// Include the Automator_Load class and kickstart Automator.
if ( ! class_exists( 'Automator_Load', false ) ) {
	include_once UA_ABSPATH . 'src/class-automator-load.php';
}

Automator_Load::get_instance();
