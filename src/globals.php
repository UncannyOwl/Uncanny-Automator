<?php
/**
 * Tier 3: Runtime/filterable constants.
 *
 * This file defines constants whose values depend on WordPress being loaded
 * (apply_filters, trailingslashit) or on Automator's own classes (Utilities).
 * It MUST be loaded last in the plugin bootstrap, after:
 *   - WordPress is fully loaded (wp-settings.php has run)
 *   - src/constants.php (tier 1: pure scalar constants)
 *   - src/global-functions.php (tier 2: helper functions like automator_get_option)
 *
 * Rules for this file:
 *   - Anything that needs apply_filters(), a WP function, or an Automator class
 *     belongs HERE — never in src/constants.php.
 *   - Pure scalar constants belong in src/constants.php so they can be loaded
 *     before WP, before classes, before anything.
 *
 * Tier 1 → 2 → 3 load order is enforced in uncanny-automator.php.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

if ( ! defined( 'AUTOMATOR_DEBUG_MODE' ) ) {
	/**
	 * Automator debug mode on/off
	 */
	define( 'AUTOMATOR_DEBUG_MODE', apply_filters( 'automator_should_enable_debug_mode', false ) );
}

if ( ! defined( 'LOAD_AUTOMATOR' ) ) {
	/**
	 * Load Automator on/off
	 */
	define( 'LOAD_AUTOMATOR', apply_filters( 'automator_should_load_automator', true ) );
}

if ( ! defined( 'UA_DEBUG_LOGS_DIR' ) ) {
	/**
	 * Automator ABSPATH for automator logs directory
	 */
	define( 'UA_DEBUG_LOGS_DIR', trailingslashit( UA_ABSPATH ) . 'logs' . DIRECTORY_SEPARATOR );
}

if ( ! defined( 'AUTOMATOR_API_URL' ) ) {
	define( 'AUTOMATOR_API_URL', apply_filters( 'automator_api_url', 'https://api.automatorplugin.com/' ) );
}

if ( ! defined( 'AUTOMATOR_LOGS_EXT' ) ) {
	define( 'AUTOMATOR_LOGS_EXT', apply_filters( 'automator_logs_extension', 'log' ) );
}

if ( ! defined( 'AUTOMATOR_SITE_KEY' ) ) {
	define( 'AUTOMATOR_SITE_KEY', Utilities::get_key() );
}
