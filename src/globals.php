<?php

namespace Uncanny_Automator;

if ( ! defined( 'AUTOMATOR_DATABASE_VERSION' ) ) {
	/**
	 * Specify Automator version
	 */
	define( 'AUTOMATOR_DATABASE_VERSION', '6.6' );
}

if ( ! defined( 'AUTOMATOR_DATABASE_VIEWS_VERSION' ) ) {
	/**
	 * Specify Automator version
	 */
	define( 'AUTOMATOR_DATABASE_VIEWS_VERSION', '4.15' );
}

if ( ! defined( 'AUTOMATOR_DATABASE_VIEWS_ENABLED' ) ) {
	/**
	 * Specify Automator version
	 */
	define( 'AUTOMATOR_DATABASE_VIEWS_ENABLED', true );
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

if ( ! defined( 'AUTOMATOR_APP_INTEGRATIONS_PRIORITY' ) ) {
	/**
	 * Automator App integrations load priority
	 */
	define( 'AUTOMATOR_APP_INTEGRATIONS_PRIORITY', PHP_INT_MAX - 10 );
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

if ( ! defined( 'AUTOMATOR_STORE_URL' ) ) {
	/**
	 * URL of store powering the plugin
	 */
	define( 'AUTOMATOR_STORE_URL', 'https://automatorplugin.com/' );
}

if ( ! defined( 'AUTOMATOR_LICENSING_URL' ) ) {
	/**
	 * URL of store powering the plugin
	 */
	define( 'AUTOMATOR_LICENSING_URL', 'https://licensing.uncannyowl.com/' );
}

if ( ! defined( 'AUTOMATOR_API_URL' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_API_URL', apply_filters( 'automator_api_url', 'https://api.automatorplugin.com/' ) );
}

if ( ! defined( 'AUTOMATOR_FREE_ITEM_NAME' ) ) {
	/**
	 * Store download name/title
	 */
	define( 'AUTOMATOR_FREE_ITEM_NAME', 'Uncanny Automator Free Account' );
}

if ( ! defined( 'AUTOMATOR_FREE_ITEM_ID' ) ) {
	/**
	 * Store download name/title
	 */
	define( 'AUTOMATOR_FREE_ITEM_ID', 23718 );
}

if ( ! defined( 'AUTOMATOR_FREE_STORE_CONNECT_URL' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_FREE_STORE_CONNECT_URL', 'signup/' );
}

if ( ! defined( 'AUTOMATOR_INTEGRATIONS_JSON_LIST' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_INTEGRATIONS_JSON_LIST', 'https://integrations.automatorplugin.com/list.json' );
}

if ( ! defined( 'AUTOMATOR_INTEGRATIONS_JSON_LIST_WITH_ITEMS' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_INTEGRATIONS_JSON_LIST_WITH_ITEMS', 'https://integrations.automatorplugin.com/full.json' );
}

if ( ! defined( 'AUTOMATOR_LOGS_EXT' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_LOGS_EXT', apply_filters( 'automator_logs_extension', 'log' ) );
}

if ( ! defined( 'AUTOMATOR_SITE_KEY' ) ) {

	/**
	 *
	 */
	define( 'AUTOMATOR_SITE_KEY', Utilities::get_key() );

}

if ( ! defined( 'AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS', false );
}

if ( ! defined( 'AUTOMATOR_DISABLE_SENDEMAIL_ACTION' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_DISABLE_SENDEMAIL_ACTION', false );
}

if ( ! defined( 'AUTOMATOR_CLOUDFLARE_EMAIL' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_CLOUDFLARE_EMAIL', '' );
}

if ( ! defined( 'AUTOMATOR_CLOUDFLARE_API_KEY' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_CLOUDFLARE_API_KEY', '' );
}

if ( ! defined( 'AUTOMATOR_CLOUDFLARE_ZONE_ID' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_CLOUDFLARE_ZONE_ID', '' );
}

if ( ! defined( 'AUTOMATOR_FASTLY_API_KEY' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_FASTLY_API_KEY', '' );
}

if ( ! defined( 'AUTOMATOR_FASTLY_SERVICE_ID' ) ) {
	/**
	 *
	 */
	define( 'AUTOMATOR_FASTLY_SERVICE_ID', '' );
}
