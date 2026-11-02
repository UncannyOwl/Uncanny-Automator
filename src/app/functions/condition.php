<?php
/**
 * Condition Functions
 *
 * WordPress developer convenience functions for condition operations.
 * Covers registry, group management, validation, and individual conditions.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Functions
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import classes
use Uncanny_Automator\App\Recipe_Builder\Condition\Services\Condition_Registry_Service;

// =============================================================================
// CONDITION REGISTRY
// =============================================================================

/**
 * Get all available conditions.
 *
 * @param array $filters {
 *     Optional filters for condition listing.
 *     @type string $integration Filter by integration code.
 * }
 * @return array|WP_Error Array of conditions on success, WP_Error on failure.
 */
function automator_get_available_conditions( array $filters = array() ) {
	$registry_service = automator_get_condition_registry_service();
	return $registry_service->list_conditions( $filters );
}

/**
 * Find conditions using search.
 *
 * @param string $query      Search query.
 * @param array  $filters    Additional filters.
 * @param int    $limit      Maximum results. Default 10.
 * @return array|WP_Error Array of matching conditions on success, WP_Error on failure.
 */
function automator_find_conditions( string $query, array $filters = array(), int $limit = 10 ) {
	if ( empty( $query ) ) {
		return new WP_Error( 'missing_query', esc_html_x( 'Search query is required', 'Condition search error', 'uncanny-automator' ) );
	}

	$registry_service = automator_get_condition_registry_service();
	return $registry_service->find_conditions( $query, $filters, $limit );
}

/**
 * Get condition definition by integration and code.
 *
 * @param string $integration_code Integration code (e.g., 'WP', 'WC').
 * @param string $condition_code   Condition code.
 * @return array|WP_Error Condition definition on success, WP_Error on failure.
 */
function automator_get_condition_definition( string $integration_code, string $condition_code ) {
	if ( empty( $integration_code ) ) {
		return new WP_Error( 'missing_integration_code', esc_html_x( 'Integration code is required', 'Condition definition error', 'uncanny-automator' ) );
	}

	if ( empty( $condition_code ) ) {
		return new WP_Error( 'missing_condition_code', esc_html_x( 'Condition code is required', 'Condition definition error', 'uncanny-automator' ) );
	}

	$registry_service = automator_get_condition_registry_service();
	return $registry_service->get_condition_definition( $integration_code, $condition_code );
}

/**
 * Check if condition exists.
 *
 * @param string $condition_code Condition code.
 * @return bool True if condition exists.
 */
function automator_condition_exists( string $condition_code ) {
	$result = automator_get_condition_registry_service()->condition_exists_by_code( $condition_code );
	return ! is_wp_error( $result ) && ! empty( $result['exists'] );
}

/**
 * Check condition integration availability.
 *
 * @param array $condition_data {
 *     Condition data to check availability for.
 *
 *     @type string $integration_id Integration code.
 *     @type string $code           Condition code.
 *     @type string $required_tier  Required tier for this condition (default 'pro-basic' for conditions).
 * }
 */
function automator_check_condition_integration_availability( array $condition_data ) {
	$registry_service = automator_get_condition_registry_service();
	return $registry_service->check_condition_integration_availability( $condition_data );
}

/**
 * Validate condition exists in registry.
 *
 * @param string $integration_code Integration code.
 * @param string $condition_code   Condition code.
 * @return array|WP_Error Validation result on success, WP_Error on failure.
 */
function automator_validate_condition_exists( string $integration_code, string $condition_code ) {
	if ( empty( $integration_code ) ) {
		return new WP_Error( 'missing_integration_code', esc_html_x( 'Integration code is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	if ( empty( $condition_code ) ) {
		return new WP_Error( 'missing_condition_code', esc_html_x( 'Condition code is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	$registry_service = automator_get_condition_registry_service();
	return $registry_service->condition_exists( $integration_code, $condition_code );
}
