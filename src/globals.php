<?php

namespace Uncanny_Automator;

if ( ! defined( 'AUTOMATOR_DATABASE_VERSION' ) ) {
	/**
	 * Specify Automator version
	 */
	define( 'AUTOMATOR_DATABASE_VERSION', '4.15' );
}

if ( ! defined( 'AUTOMATOR_DATABASE_VIEWS_VERSION' ) ) {
	/**
	 * Specify Automator version
	 */
	define( 'AUTOMATOR_DATABASE_VIEWS_VERSION', '4.15' );
}

if ( ! defined( 'AUTOMATOR_REST_API_END_POINT' ) ) {
	/**
	 * Specify Automator Rest API base
	 */
	define( 'AUTOMATOR_REST_API_END_POINT', 'uap/v2' );
}

if ( ! defined( 'AUTOMATOR_CONFIGURATION_PRIORITY' ) ) {
	/**
	 * Automator Configuration priority
	 */
	define( 'AUTOMATOR_CONFIGURATION_PRIORITY', 10 );
}

if ( ! defined( 'AUTOMATOR_LOAD_INTEGRATIONS_PRIORITY' ) ) {
	/**
	 * Automator trigger load priority
	 */
	define( 'AUTOMATOR_LOAD_INTEGRATIONS_PRIORITY', 15 );
}

if ( ! defined( 'AUTOMATOR_ACTIONIFY_TRIGGERS_PRIORITY' ) ) {
	/**
	 * Automator trigger load priority
	 */
	define( 'AUTOMATOR_ACTIONIFY_TRIGGERS_PRIORITY', 20 );
}

if ( ! defined( 'AUTOMATOR_CONFIGURATION_COMPLETE_PRIORITY' ) ) {
	/**
	 * Automator action load priority
	 */
	define( 'AUTOMATOR_CONFIGURATION_COMPLETE_PRIORITY', 10 );
}

if ( ! defined( 'AUTOMATOR_DEBUG_MODE' ) ) {
	/**
	 * Automator debug mode on/off
	 */
	define( 'AUTOMATOR_DEBUG_MODE', ! empty( automator_get_option( 'automator_settings_debug_notices_enabled', false ) ) ? true : false );
}


if ( ! defined( 'LOAD_AUTOMATOR' ) ) {
	/**
	 * Load Automator on/off
	 */
	define( 'LOAD_AUTOMATOR', filter_var( automator_get_option( 'load_automator', true ), FILTER_VALIDATE_BOOLEAN ) );
}

if ( ! defined( 'UA_ABSPATH' ) ) {
	/**
	 * Automator ABSPATH for file includes
	 */
	define( 'UA_ABSPATH', dirname( AUTOMATOR_BASE_FILE ) . DIRECTORY_SEPARATOR );
}

if ( ! defined( 'UA_DEBUG_LOGS_DIR' ) ) {
	/**
	 * Automator ABSPATH for automator logs directory
	 */
	define( 'UA_DEBUG_LOGS_DIR', trailingslashit( UA_ABSPATH ) . 'logs' . DIRECTORY_SEPARATOR );
}

if ( ! defined( 'AUTOMATOR_FREE_STORE_URL' ) ) {
	// URL of store powering the plugin
	define( 'AUTOMATOR_FREE_STORE_URL', 'https://automatorplugin.com/' );
}

if ( ! defined( 'AUTOMATOR_API_URL' ) ) {
	define( 'AUTOMATOR_API_URL', apply_filters( 'automator_api_url', 'https://api.automatorplugin.com/' ) );
}

if ( ! defined( 'AUTOMATOR_FREE_ITEM_NAME' ) ) {
	// Store download name/title
	define( 'AUTOMATOR_FREE_ITEM_NAME', 'Uncanny Automator Free Account' );
}

if ( ! defined( 'AUTOMATOR_FREE_STORE_CONNECT_URL' ) ) {
	define( 'AUTOMATOR_FREE_STORE_CONNECT_URL', 'signup/' );
}

if ( ! defined( 'AUTOMATOR_INTEGRATIONS_JSON_LIST' ) ) {
	define( 'AUTOMATOR_INTEGRATIONS_JSON_LIST', 'https://integrations.automatorplugin.com/list.json' );
}

if ( ! defined( 'AUTOMATOR_INTEGRATIONS_JSON_LIST_WITH_ITEMS' ) ) {
	define( 'AUTOMATOR_INTEGRATIONS_JSON_LIST_WITH_ITEMS', 'https://integrations.automatorplugin.com/full.json' );
}

if ( ! defined( 'AUTOMATOR_LOGS_EXT' ) ) {
	define( 'AUTOMATOR_LOGS_EXT', apply_filters( 'automator_logs_extension', 'log' ) );
}

if ( ! defined( 'AUTOMATOR_SITE_KEY' ) ) {

	define( 'AUTOMATOR_SITE_KEY', Utilities::get_key() );

}
