<?php
/*
 * Plugin Name:         Uncanny Automator
 * Description:         Connect WordPress plugins together to create powerful recipes that save time and improve the user experience. With no coding required, Uncanny Automator can replace dozens of plugins with millions of recipe combinations!
 * Author:              Uncanny Automator, Uncanny Owl
 * Author URI:          https://automatorplugin.com/
 * Plugin URI:          https://automatorplugin.com/
 * Text Domain:         uncanny-automator
 * Domain Path:         /languages
 * License:             GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 * Version:             4.1.1
 * Requires at least:   5.3
 * Requires PHP:        5.6
 */

use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Load;

if ( ! defined( 'AUTOMATOR_PLUGIN_VERSION' ) ) {
	/*
	 * Specify Automator version.
	 */
	define( 'AUTOMATOR_PLUGIN_VERSION', '4.1.1' );
}

if ( ! defined( 'AUTOMATOR_BASE_FILE' ) ) {
	/*
	 * Specify Automator base file.
	 */
	define( 'AUTOMATOR_BASE_FILE', __FILE__ );
}


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
