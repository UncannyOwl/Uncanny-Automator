<?php
/**
 * Uncanny Automator API Functions
 *
 * WordPress developer convenience functions for Automator operations.
 * Clean, simple, and predictable API for building with Automator.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api
 */

declare(strict_types=1);

// Prevent direct access (skip in test environment)
if ( ! defined( 'ABSPATH' ) && ! defined( 'PHPUNIT_COMPOSER_INSTALL' ) && ! defined( 'WP_TESTS_DIR' ) ) {
	exit;
}

/**
 * ============================================================================
 * LOADING STRATEGY
 * ============================================================================
 *
 * Simple, predictable loading order:
 * 1. Core functions first (dependencies)
 * 2. Domain functions in logical order
 * 3. New services last
 */

// =============================================================================
// 1. CORE INFRASTRUCTURE
// =============================================================================

/**
 * Load core functions (database, services, diagnostics).
 */
require_once __DIR__ . '/functions/core.php';

// =============================================================================
// 2. DOMAIN FUNCTIONS
// =============================================================================

/**
 * Load recipe functions (CRUD, query, logs).
 */
require_once __DIR__ . '/functions/recipe.php';

/**
 * Load trigger functions (registry, management, validation).
 */
require_once __DIR__ . '/functions/trigger.php';

/**
 * Load action functions (registry, management, validation).
 */
require_once __DIR__ . '/functions/action.php';

// =============================================================================
// 3. NEW SERVICES
// =============================================================================

/**
 * Load condition functions (registry, groups, validation).
 */
require_once __DIR__ . '/functions/condition.php';

/**
 * Load plan functions (access, features, tiers).
 */
require_once __DIR__ . '/functions/plan.php';

// =============================================================================
// INITIALIZATION
// =============================================================================

/**
 * Verify all functions loaded successfully.
 */
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	// Verify functions loaded - silently continue if verification fails.
	automator_verify_core_functions();
}

/**
 * Mark functions as loaded.
 */
if ( ! defined( 'AUTOMATOR_API_FUNCTIONS_LOADED' ) ) {
	define( 'AUTOMATOR_API_FUNCTIONS_LOADED', true );
}
