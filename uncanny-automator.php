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
 * Version:             7.2.1
 * Requires at least:   5.8
 * Requires PHP:        7.4
 */

use Uncanny_Automator\Actionify_Triggers;
use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Load;
use Uncanny_Automator\DB_Tables;
use Uncanny_Automator\Api\Application\Application_Bootstrap;

if ( ! defined( 'AUTOMATOR_PLUGIN_VERSION' ) ) {
	/*
	 * Specify Automator version.
	 */
	define( 'AUTOMATOR_PLUGIN_VERSION', '7.2.1' );
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

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
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
				printf( esc_html__( 'Notice: Uncanny Automator v%s requires PHP 7.4 or higher to run properly. Your current PHP version is below this requirement, so the plugin has been deactivated and all automations have stopped. Please upgrade your PHP version to ensure that your automations and other plugin features work correctly.', 'uncanny-automator' ), esc_html( AUTOMATOR_PLUGIN_VERSION ) );
			?>
				</p>
		</div>
		<?php
	}

	// Stop loading the plugin
	return;
}


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

// Strict load order: tier 1 constants → tier 2 functions → tier 3 runtime constants.
// See src/constants.php, src/global-functions.php, src/globals.php for the rules
// each tier must obey. Don't reorder these without understanding the dependency chain.
// constants.php is intentionally NOT in composer's files autoload — that would beat
// mu-plugins to the punch and break legitimate constant overrides like
// AUTOMATOR_STORE_URL / AUTOMATOR_LICENSING_URL / AUTOMATOR_API_URL.
require_once UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'constants.php';
require_once UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'global-functions.php';
require_once UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'globals.php';

/*
 * Eager schema install — runs once on first request.
 *
 * On a brand-new install WordPress's plugin_sandbox_scrape() includes this file
 * BEFORE the activation hook executes, so anything downstream that touches
 * uap_options would error out. We install the full schema up-front from the
 * single source of truth (Automator_DB::get_schema). dbDelta is idempotent, so
 * the activation hook's later create_tables() call is a safe no-op.
 *
 * Hot path: a single autoloaded wp_option lookup, then early return.
 * The marker is prefixed with `automator_` so uninstall.php sweeps it up
 * automatically alongside the rest of the plugin's options.
 */
( static function () {
	if ( get_option( 'automator_schema_installed' ) ) {
		return;
	}
	\Uncanny_Automator\Automator_DB::create_tables();
	update_option( 'automator_schema_installed', AUTOMATOR_DATABASE_VERSION, true );
} )();

/**
 * Case-insensitive fallback autoloader.
 *
 * Appended after Composer's autoloader so it only fires when Composer's
 * case-sensitive classmap lookup misses. Builds a lowercase → file map from
 * the classmap once (static property) giving O(1) lookup on every subsequent
 * call. Handles legacy class-name capitalisation inconsistencies across
 * 200+ integrations without requiring individual per-class fixes.
 *
 * @param string $class Fully-qualified class name requested by PHP.
 * @return void
 */
function automator_autoloader( $class ) {
	static $ci_map = null;
	if ( null === $ci_map ) {
		$ci_map        = array();
		$classmap_file = UA_ABSPATH . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'autoload_classmap.php';
		if ( file_exists( $classmap_file ) ) {
			foreach ( require $classmap_file as $fqcn => $file ) {
				$ci_map[ strtolower( $fqcn ) ] = $file;
			}
		}
	}
	$file = $ci_map[ strtolower( $class ) ] ?? null;
	if ( null !== $file ) {
		require_once $file;
	}
}
spl_autoload_register( 'automator_autoloader' );

// Add API functions.
require UA_ABSPATH . 'src' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'functions.php';
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

/**
 * @deprecated 7.2 — Backward compatibility for third-party plugins that reference `global $uncanny_automator`.
 *             Will be removed in a future major version. Use `Automator()` function instead.
 * @global Automator_Functions $uncanny_automator
 */
global $uncanny_automator;
$uncanny_automator = Automator();

if ( AUTOMATOR_PLUGIN_VERSION !== automator_get_option( 'AUTOMATOR_PLUGIN_VERSION', 0 ) ) {
	automator_update_option( 'AUTOMATOR_PLUGIN_VERSION', AUTOMATOR_PLUGIN_VERSION );

	Automator()->cache->reset_integrations_directory( null, null );

	// Pre-build the recipe manifest during plugin update so gating logic has data on first request.
	\Uncanny_Automator\Recipe_Manifest::reset();
}

// Include the Automator_Load class and kickstart Automator.
if ( ! class_exists( 'Automator_Load', false ) ) {
	include_once UA_ABSPATH . 'src/class-automator-load.php';
}

// Initialize API applications (MCP, RESTful).
if ( ! class_exists( 'Application_Bootstrap', false ) ) {
	include_once UA_ABSPATH . 'src/api/application/class-application-bootstrap.php';
}
$application_bootstrap = new Application_Bootstrap();
$application_bootstrap->init();

Automator_Load::get_instance();

$actionify = new Actionify_Triggers\Trigger_Engine();
$actionify->init();
