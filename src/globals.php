<?php

namespace Uncanny_Automator;

if ( ! defined( 'AUTOMATOR_DATABASE_VERSION' ) ) {
	/**
	 * Specify Automator version
	 */
	define( 'AUTOMATOR_DATABASE_VERSION', '3.0' );
}

if ( ! defined( 'AUTOMATOR_DATABASE_VIEWS_VERSION' ) ) {
	/**
	 * Specify Automator version
	 */
	define( 'AUTOMATOR_DATABASE_VIEWS_VERSION', '3.0' );
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
	define( 'AUTOMATOR_DEBUG_MODE', false );
}

if ( ! defined( 'LOAD_AUTOMATOR' ) ) {
	/**
	 * Load Automator on/off
	 */
	define( 'LOAD_AUTOMATOR', true );
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
	define( 'UA_DEBUG_LOGS_DIR', trailingslashit( WP_CONTENT_DIR ) . 'uploads' . DIRECTORY_SEPARATOR . 'automator-logs' . DIRECTORY_SEPARATOR );
}
