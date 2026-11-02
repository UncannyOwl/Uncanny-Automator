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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
	define( 'LOAD_AUTOMATOR', apply_filters( 'automator_should_load_automator', true ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Existing load-control constant that sites may define before the plugin loads.
}

if ( ! defined( 'UA_DEBUG_LOGS_DIR' ) ) {
	/**
	 * Automator ABSPATH for automator logs directory
	 */
	define( 'UA_DEBUG_LOGS_DIR', trailingslashit( UA_ABSPATH ) . 'logs' . DIRECTORY_SEPARATOR ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Existing log-directory constant that sites may override before the plugin loads.
}

if ( ! defined( 'AUTOMATOR_API_URL' ) ) {
	define( 'AUTOMATOR_API_URL', apply_filters( 'automator_api_url', 'https://api.automatorplugin.com/' ) );
}

if ( ! defined( 'AUTOMATOR_PLATFORM_API_PATH' ) ) {
	/**
	 * URL path prefix for the Automator Platform's plugin↔platform plumbing
	 * surface — site registration, jobs polling, connection management,
	 * OAuth flows. Decoupled from `/v3/{integration}` (vendor-call surface)
	 * per the unified rollout plan's "Decoupling rule".
	 *
	 * Endpoints under this prefix:
	 *  - {AUTOMATOR_PLATFORM_API_PATH}/sites/register
	 *  - {AUTOMATOR_PLATFORM_API_PATH}/sites/alive
	 *  - {AUTOMATOR_PLATFORM_API_PATH}/jobs/{id}
	 *  - {AUTOMATOR_PLATFORM_API_PATH}/connections/{id}
	 *  - {AUTOMATOR_PLATFORM_API_PATH}/oauth/{integration}/start
	 *
	 * @since 8.0.0
	 */
	define( 'AUTOMATOR_PLATFORM_API_PATH', apply_filters( 'automator_platform_api_path', '/app/v1' ) );
}

if ( ! defined( 'AUTOMATOR_PLATFORM_WEBHOOK_RECEIVER_ROUTE' ) ) {
	/**
	 * REST API route on the plugin side that receives platform fanouts —
	 * async-completion callbacks AND vendor-event webhook deliveries
	 * (discriminated by `payload_type` per Contract I).
	 *
	 * Used by:
	 *  - `Site.callback_url` registered at platform via /sites/register
	 *  - `webhook_url` body field on /v3/{integration}/register-site-for-webhooks
	 *
	 * Resolve to a full URL via `rest_url( AUTOMATOR_PLATFORM_WEBHOOK_RECEIVER_ROUTE )`.
	 *
	 * @since 8.0.0
	 */
	define( 'AUTOMATOR_PLATFORM_WEBHOOK_RECEIVER_ROUTE', apply_filters( 'automator_platform_webhook_receiver_route', 'automator/platform/v1/webhooks/events' ) );
}

if ( ! defined( 'AUTOMATOR_USAGE_REPORTS_URL' ) ) {
	/**
	 * Usage Reports Service — where usage reports are sent.
	 *
	 * Reports post here directly rather than through AUTOMATOR_API_URL. The
	 * service answers with an envelope carrying its own `statusCode`, and
	 * Usage_Reports::send_report() requires that to be 201 before it clears the
	 * locally accumulated stats it has just sent.
	 */
	define( 'AUTOMATOR_USAGE_REPORTS_URL', apply_filters( 'automator_usage_reports_url', 'https://usage.uncannyowl.com/' ) );
}

if ( ! defined( 'AUTOMATOR_LOGS_EXT' ) ) {
	define( 'AUTOMATOR_LOGS_EXT', apply_filters( 'automator_logs_extension', 'log' ) );
}

if ( ! defined( 'AUTOMATOR_SITE_KEY' ) ) {
	define( 'AUTOMATOR_SITE_KEY', Utilities::get_key() );
}
