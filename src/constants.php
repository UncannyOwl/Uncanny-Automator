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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ──────────────────────────────────────────────
// Schema versions
// ──────────────────────────────────────────────

if ( ! defined( 'AUTOMATOR_DATABASE_VERSION' ) ) {
	// 8.0.0.3 — adds uap_async_jobs.connection_id: the connection a platform
	// job's write was sent with, so a failed job is handled for that connection
	// as the same answer given at once would be.
	//
	// 8.0.0.2 — drops uap_closure_log + uap_closure_log_meta (closures are
	// retired; Automator_DB::drop_closure_log_tables()).
	//
	// 8.0.0.1 — adds uap_async_jobs.origin (`platform` | `site`): a background
	// job records whether the platform or the site itself finishes it.
	//
	// 7.7.1 — adds uap_platform_inbox.claim_token + its index. The drain used
	// to establish ownership with SELECT-then-UPDATE behind a site transient,
	// which is not atomic: two drainers could select the same pending IDs and
	// the loser's follow-up SELECT still returned rows the winner had claimed,
	// so both dispatched the same event. Ownership is now stamped by the
	// UPDATE itself and read back by token.
	//
	// 7.7.0 — adds the uap_platform_inbox `retention_scan (received_at)` index.
	// The inbox stopped deleting rows on success (they are retained as
	// `completed` for audit), so the table now accumulates and the retention
	// sweep's `DELETE ... WHERE received_at < ?` needs a range scan instead of
	// a full-table scan.
	//
	// 7.6.0 — adds uap_platform_inbox (durable ingress) + its platform_received_at
	// column.
	//
	// Must differ from the prior value or the schema-upgrade gate in
	// Automator_DB::verify_base_tables() bails and the change never applies.
	define( 'AUTOMATOR_DATABASE_VERSION', '8.0.0.3' );
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
	define( 'AUTOMATOR_LLM_CREDITS_URL', 'https://automatorplugin.com/get-agent-credits/?utm_source=uncanny-automator&utm_medium=in-plugin&utm_content=automator&utm_campaign=llm-credits' );
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
	// Not registered since 8.0.0. Read only by the Redirect integration's
	// closure-retirement migration, the WP token-alias migration and uninstall.
	define( 'AUTOMATOR_POST_TYPE_CLOSURE', 'uo-closure' );
}

if ( ! defined( 'AUTOMATOR_POST_TYPE_LOOP' ) ) {
	define( 'AUTOMATOR_POST_TYPE_LOOP', 'uo-loop' );
}

if ( ! defined( 'AUTOMATOR_POST_TYPE_LOOP_FILTER' ) ) {
	define( 'AUTOMATOR_POST_TYPE_LOOP_FILTER', 'uo-loop-filter' );
}

// ──────────────────────────────────────────────
// Unified blocks architecture post types
// ──────────────────────────────────────────────

if ( ! defined( 'AUTOMATOR_POST_TYPE_BLOCK' ) ) {
	define( 'AUTOMATOR_POST_TYPE_BLOCK', 'uo-block' );
}

if ( ! defined( 'AUTOMATOR_POST_TYPE_BLOCK_MODIFIER' ) ) {
	define( 'AUTOMATOR_POST_TYPE_BLOCK_MODIFIER', 'uo-block-modifier' );
}

// ──────────────────────────────────────────────
// Item meta keys
// ──────────────────────────────────────────────

if ( ! defined( 'AUTOMATOR_META_KEY_ITEM_CUSTOM_NAME' ) ) {
	// The author's optional label for a trigger / action / block modifier,
	// stored on the item's own post. Lowercase by design — the trigger store
	// uppercases every other config key and exempts this one.
	define( 'AUTOMATOR_META_KEY_ITEM_CUSTOM_NAME', '_automator_custom_item_name_' );
}

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
