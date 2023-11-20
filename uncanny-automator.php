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
 * Version:             5.3
 * Requires at least:   5.4
 * Requires PHP:        7.0
 */

use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Load;

if ( ! defined( 'AUTOMATOR_PLUGIN_VERSION' ) ) {
	/*
	 * Specify Automator version.
	 */
	define( 'AUTOMATOR_PLUGIN_VERSION', '5.3' );
}

if ( ! defined( 'AUTOMATOR_BASE_FILE' ) ) {
	/*
	 * Specify Automator base file.
	 */
	define( 'AUTOMATOR_BASE_FILE', __FILE__ );
}

if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
	add_action( 'admin_notices', 'automator_version_check_admin_notice', - 99999 );

	// Function to display admin notice
	function automator_version_check_admin_notice() {
		?>
		<div class="notice notice-error"
			 style="border-left: 4px solid #dc3232; font-weight: bold; background-color: #fff4e5; color: #000;">
			<p>
			<?php
				//Translators: %s: The version number of Uncanny Automator.
				echo sprintf( esc_html__( 'Notice: Uncanny Automator v%s requires PHP 7.0 or higher to run properly. Your current PHP version is below this requirement, so the plugin has been deactivated and all automations have stopped. Please upgrade your PHP version to ensure that your automations and other plugin features work correctly.', 'uncanny-automator' ), esc_html( AUTOMATOR_PLUGIN_VERSION ) );
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
function automator_autoloader( $class ) {

	$class = strtolower( $class );

	global $automator_class_map;

	if ( ! $automator_class_map ) {
		$automator_class_map = include __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_classmap.php';
		$automator_class_map = array_change_key_case( $automator_class_map, CASE_LOWER );
	}

	if ( isset( $automator_class_map[ $class ] ) ) {
		include_once $automator_class_map[ $class ];
	}
}

spl_autoload_register( 'automator_autoloader' );

// Autoload files
require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
// Add global functions.
require __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'global-functions.php';
// Add other global variables for plugin.
require __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'globals.php';
// Add InitializePlugin class for other plugins checking for version.
require __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'legacy.php';

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

if ( AUTOMATOR_PLUGIN_VERSION !== get_option( 'AUTOMATOR_PLUGIN_VERSION', 0 ) ) {
	update_option( 'AUTOMATOR_PLUGIN_VERSION', AUTOMATOR_PLUGIN_VERSION );
	Automator()->cache->reset_integrations_directory( null, null );
}

// Include the Automator_Load class and kickstart Automator.
if ( ! class_exists( 'Automator_Load', false ) ) {
	include_once UA_ABSPATH . 'src/class-automator-load.php';
}

Automator_Load::get_instance();
