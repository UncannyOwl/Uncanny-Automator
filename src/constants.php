<?php
/**
 * Tier 1: Pure scalar constants.
 *
 * This file defines all Automator constants that have NO dependency on
 * WordPress, the Utilities class, or anything else. It is the first plugin
 * file loaded — even before global-functions.php — so any other code can
 * safely reference these constants without worrying about load order.
 *
 * Rules for this file:
 *   - Only `define( 'NAME', <value> );` calls, where <value> is a scalar literal
 *     or a compile-time constant expression (e.g. PHP_INT_MAX - 10). No runtime
 *     evaluation, no I/O, no DB.
 *   - No apply_filters(), no trailingslashit(), no Utilities::*, no get_option().
 *   - No function calls of any kind beyond `defined()` guards.
 *   - If you need a runtime/filterable value, put it in globals.php (tier 3).
 *
 * Tier 1 → 2 → 3 load order is enforced in uncanny-automator.php.
 *
 * No namespace declaration on purpose: define() always creates global
 * constants regardless, and a bare file makes it explicit that nothing here
 * should ever be a class or namespaced symbol.
 *
 * NOTE: This file is intentionally NOT registered in composer's `files`
 * autoload. Doing so would force these constants to be defined at
 * `vendor/autoload.php` require time, which beats mu-plugins to the punch
 * and causes "constant already defined" warnings on sites whose mu-plugins
 * legitimately override AUTOMATOR_STORE_URL / AUTOMATOR_LICENSING_URL /
 * AUTOMATOR_API_URL etc. The manual require in uncanny-automator.php runs
 * after mu-plugins, so the `if ( ! defined( ... ) )` guards correctly skip
 * any value the mu-plugin set first.
 *
 * @package Uncanny_Automator
 */

// ──────────────────────────────────────────────
// Schema versions
// ──────────────────────────────────────────────

if ( ! defined( 'AUTOMATOR_DATABASE_VERSION' ) ) {
	define( 'AUTOMATOR_DATABASE_VERSION', '7.2.0' );
}

if ( ! defined( 'AUTOMATOR_MANIFEST_SCHEMA_VERSION' ) ) {
	/**
	 * Schema version of the persisted Recipe_Manifest envelope.
	 *
	 * BUMP THIS whenever the manifest's stored shape, SQL build logic, or
	 * composite key format changes in a way that makes existing cached data
	 * wrong. On the next request, every site will see the version mismatch,
	 * treat its cached manifest as a miss, and trigger a fresh full_rebuild().
	 * No DB migration required.
	 *
	 * Same discipline as $wp_db_version in WordPress core — the constant IS
	 * the deploy marker. Forget to bump and stale data sticks around; bump it
	 * in the same PR that changes the build logic.
	 */
	define( 'AUTOMATOR_MANIFEST_SCHEMA_VERSION', 1 );
}

if ( ! defined( 'AUTOMATOR_DATABASE_VIEWS_VERSION' ) ) {
	define( 'AUTOMATOR_DATABASE_VIEWS_VERSION', '7.2' );
}

if ( ! defined( 'AUTOMATOR_DATABASE_VIEWS_ENABLED' ) ) {
	define( 'AUTOMATOR_DATABASE_VIEWS_ENABLED', true );
}

// ──────────────────────────────────────────────
// REST API
// ──────────────────────────────────────────────

if ( ! defined( 'AUTOMATOR_REST_API_END_POINT' ) ) {
	define( 'AUTOMATOR_REST_API_END_POINT', 'uap/v2' );
}

// ──────────────────────────────────────────────
// Hook priorities
// ──────────────────────────────────────────────

if ( ! defined( 'AUTOMATOR_CONFIGURATION_PRIORITY' ) ) {
	define( 'AUTOMATOR_CONFIGURATION_PRIORITY', 10 );
}

if ( ! defined( 'AUTOMATOR_CONFIGURATION_PRIORITY_TRIGGER_ENGINE' ) ) {
	define( 'AUTOMATOR_CONFIGURATION_PRIORITY_TRIGGER_ENGINE', 1 );
}

if ( ! defined( 'AUTOMATOR_RECIPE_PARTS_PRIORITY_TRIGGER_ENGINE' ) ) {
	define( 'AUTOMATOR_RECIPE_PARTS_PRIORITY_TRIGGER_ENGINE', 30 );
}

if ( ! defined( 'AUTOMATOR_LOAD_INTEGRATIONS_PRIORITY' ) ) {
	define( 'AUTOMATOR_LOAD_INTEGRATIONS_PRIORITY', 15 );
}

if ( ! defined( 'AUTOMATOR_ACTIONIFY_TRIGGERS_PRIORITY' ) ) {
	define( 'AUTOMATOR_ACTIONIFY_TRIGGERS_PRIORITY', 20 );
}

if ( ! defined( 'AUTOMATOR_APP_INTEGRATIONS_PRIORITY' ) ) {
	define( 'AUTOMATOR_APP_INTEGRATIONS_PRIORITY', PHP_INT_MAX - 10 );
}

if ( ! defined( 'AUTOMATOR_CONFIGURATION_COMPLETE_PRIORITY' ) ) {
	define( 'AUTOMATOR_CONFIGURATION_COMPLETE_PRIORITY', 10 );
}

// ──────────────────────────────────────────────
// Store / licensing / API URLs
// ──────────────────────────────────────────────

if ( ! defined( 'AUTOMATOR_STORE_URL' ) ) {
	define( 'AUTOMATOR_STORE_URL', 'https://automatorplugin.com/' );
}

if ( ! defined( 'AUTOMATOR_LICENSING_URL' ) ) {
	define( 'AUTOMATOR_LICENSING_URL', 'https://licensing.uncannyowl.com/' );
}

if ( ! defined( 'AUTOMATOR_FREE_ITEM_NAME' ) ) {
	define( 'AUTOMATOR_FREE_ITEM_NAME', 'Uncanny Automator Free Account' );
}

if ( ! defined( 'AUTOMATOR_FREE_ITEM_ID' ) ) {
	define( 'AUTOMATOR_FREE_ITEM_ID', 23718 );
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

if ( ! defined( 'AUTOMATOR_LLM_CREDITS_URL' ) ) {
	/**
	 * URL to purchase LLM/Agent credits.
	 *
	 * @since 7.0.0
	 */
	define( 'AUTOMATOR_LLM_CREDITS_URL', 'https://automatorplugin.com/get-agent-credits/?utm_source=plugin&utm_medium=automator&utm_campaign=llm_credits' );
}

// ──────────────────────────────────────────────
// Recipe Post Types
// ──────────────────────────────────────────────
// Single source of truth for all Automator custom post types.
// Use these constants instead of hardcoding strings. When new
// post types are added (e.g. uo-delay, uo-condition, uo-block),
// add them here and all dependent code picks them up automatically.

if ( ! defined( 'AUTOMATOR_POST_TYPE_RECIPE' ) ) {
	define( 'AUTOMATOR_POST_TYPE_RECIPE', 'uo-recipe' );
}

if ( ! defined( 'AUTOMATOR_POST_TYPE_TRIGGER' ) ) {
	define( 'AUTOMATOR_POST_TYPE_TRIGGER', 'uo-trigger' );
}

if ( ! defined( 'AUTOMATOR_POST_TYPE_ACTION' ) ) {
	define( 'AUTOMATOR_POST_TYPE_ACTION', 'uo-action' );
}

if ( ! defined( 'AUTOMATOR_POST_TYPE_CLOSURE' ) ) {
	define( 'AUTOMATOR_POST_TYPE_CLOSURE', 'uo-closure' );
}

if ( ! defined( 'AUTOMATOR_POST_TYPE_LOOP' ) ) {
	define( 'AUTOMATOR_POST_TYPE_LOOP', 'uo-loop' );
}

if ( ! defined( 'AUTOMATOR_POST_TYPE_LOOP_FILTER' ) ) {
	define( 'AUTOMATOR_POST_TYPE_LOOP_FILTER', 'uo-loop-filter' );
}

// ──────────────────────────────────────────────
// Future post types (unified blocks architecture)
//
// Uncomment when the block/condition post types ship.
// Then append to the relevant helper functions in global-functions.php.
// ──────────────────────────────────────────────

// if ( ! defined( 'AUTOMATOR_POST_TYPE_BLOCK' ) ) {
//  define( 'AUTOMATOR_POST_TYPE_BLOCK', 'uo-block' );
// }

// if ( ! defined( 'AUTOMATOR_POST_TYPE_FILTER_CONDITION' ) ) {
//  define( 'AUTOMATOR_POST_TYPE_FILTER_CONDITION', 'uo-filter-condition' );
// }

// ──────────────────────────────────────────────
// Feature toggles (compile-time defaults)
// ──────────────────────────────────────────────

if ( ! defined( 'AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS' ) ) {
	define( 'AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS', false );
}

if ( ! defined( 'AUTOMATOR_DISABLE_SENDEMAIL_ACTION' ) ) {
	define( 'AUTOMATOR_DISABLE_SENDEMAIL_ACTION', false );
}

// ──────────────────────────────────────────────
// CDN / cache integration credentials (empty defaults)
// ──────────────────────────────────────────────

if ( ! defined( 'AUTOMATOR_CLOUDFLARE_EMAIL' ) ) {
	define( 'AUTOMATOR_CLOUDFLARE_EMAIL', '' );
}

if ( ! defined( 'AUTOMATOR_CLOUDFLARE_API_KEY' ) ) {
	define( 'AUTOMATOR_CLOUDFLARE_API_KEY', '' );
}

if ( ! defined( 'AUTOMATOR_CLOUDFLARE_ZONE_ID' ) ) {
	define( 'AUTOMATOR_CLOUDFLARE_ZONE_ID', '' );
}

if ( ! defined( 'AUTOMATOR_FASTLY_API_KEY' ) ) {
	define( 'AUTOMATOR_FASTLY_API_KEY', '' );
}

if ( ! defined( 'AUTOMATOR_FASTLY_SERVICE_ID' ) ) {
	define( 'AUTOMATOR_FASTLY_SERVICE_ID', '' );
}
