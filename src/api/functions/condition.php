<?php
/**
 * Condition Functions
 *
 * WordPress developer convenience functions for condition operations.
 * Covers registry, group management, validation, and individual conditions.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Functions
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) && ! defined( 'PHPUNIT_COMPOSER_INSTALL' ) && ! defined( 'WP_TESTS_DIR' ) ) {
	exit;
}

// Import classes
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Query_Service;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;

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

// =============================================================================
// CONDITION GROUP MANAGEMENT
// =============================================================================

/**
 * Add condition group to recipe.
 *
 * @param int    $recipe_id   Recipe ID.
 * @param array  $action_ids  Array of action IDs the conditions apply to.
 * @param string $mode       Group mode ('all', 'any'). Default 'any'.
 * @param array  $conditions  Array of condition configurations. Default empty.
 * @return array|WP_Error Group data on success, WP_Error on failure.
 */
function automator_add_condition_group( int $recipe_id, array $action_ids = array(), string $mode = 'any', array $conditions = array() ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	$condition_service = automator_get_recipe_condition_service();
	return $condition_service->add_condition_group( $recipe_id, $action_ids, $mode, $conditions );
}

/**
 * Add empty condition group to recipe.
 *
 * @param int    $recipe_id Recipe ID.
 * @param string $mode      Group mode ('all', 'any'). Default 'any'.
 * @param int    $priority  Group priority. Default 20.
 * @return array|WP_Error Group data on success, WP_Error on failure.
 */
function automator_add_empty_condition_group( int $recipe_id, string $mode = 'any', int $priority = 20 ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	$condition_service = automator_get_recipe_condition_service();
	return $condition_service->add_empty_condition_group( $recipe_id, $mode, $priority );
}

/**
 * Update condition group.
 *
 * @param int         $recipe_id Recipe ID.
 * @param string      $group_id  Group ID.
 * @param string|null $mode New evaluation mode (optional).
 * @param int|null    $priority New priority (optional).
 * @return array|WP_Error Updated group data on success, WP_Error on failure.
 */
function automator_update_condition_group( int $recipe_id, string $group_id, ?string $mode = null, ?int $priority = null ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	if ( empty( $group_id ) ) {
		return new WP_Error( 'missing_group_id', esc_html_x( 'Group ID is required', 'Condition group error', 'uncanny-automator' ) );
	}

	$condition_service = automator_get_recipe_condition_service();
	return $condition_service->update_condition_group( $recipe_id, $group_id, $mode, $priority );
}

/**
 * Remove condition group from recipe.
 *
 * @param int    $recipe_id Recipe ID.
 * @param string $group_id  Group ID.
 * @param bool   $confirmed Confirmation flag.
 * @return array|WP_Error Success confirmation on success, WP_Error on failure.
 */
function automator_remove_condition_group( int $recipe_id, string $group_id, bool $confirmed = false ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	if ( empty( $group_id ) ) {
		return new WP_Error( 'missing_group_id', esc_html_x( 'Group ID is required', 'Condition group error', 'uncanny-automator' ) );
	}

	if ( ! $confirmed ) {
		return new WP_Error( 'confirmation_required', esc_html_x( 'Group removal must be confirmed with $confirmed = true', 'Condition group error', 'uncanny-automator' ) );
	}

	$condition_service = automator_get_recipe_condition_service();
	return $condition_service->remove_condition_group( $recipe_id, $group_id );
}

// =============================================================================
// CONDITION OPERATIONS
// =============================================================================

/**
 * Add condition to group.
 *
 * @param int    $recipe_id        Recipe ID.
 * @param string $group_id         Group ID.
 * @param string $integration_code Integration code (e.g., 'WP', 'WC').
 * @param string $condition_code   Condition code.
 * @param array  $fields           Condition field values. Default empty.
 * @return array|WP_Error Condition data on success, WP_Error on failure.
 */
function automator_add_condition_to_group( int $recipe_id, string $group_id, string $integration_code, string $condition_code, array $fields = array() ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	if ( empty( $group_id ) ) {
		return new WP_Error( 'missing_group_id', esc_html_x( 'Group ID is required', 'Condition group error', 'uncanny-automator' ) );
	}

	if ( empty( $integration_code ) ) {
		return new WP_Error( 'missing_integration_code', esc_html_x( 'Integration code is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	if ( empty( $condition_code ) ) {
		return new WP_Error( 'missing_condition_code', esc_html_x( 'Condition code is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	$condition_service = automator_get_recipe_condition_service();
	return $condition_service->add_condition_to_group( $recipe_id, $group_id, $integration_code, $condition_code, $fields );
}

/**
 * Update condition in group.
 *
 * @param string $condition_id   Condition ID.
 * @param string $group_id       Group ID.
 * @param int    $recipe_id      Recipe ID.
 * @param array  $fields         Updated field values.
 * @return array|WP_Error Updated condition data on success, WP_Error on failure.
 */
function automator_update_condition( string $condition_id, string $group_id, int $recipe_id, array $fields ) {
	if ( empty( $condition_id ) ) {
		return new WP_Error( 'missing_condition_id', esc_html_x( 'Condition ID is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	if ( empty( $group_id ) ) {
		return new WP_Error( 'missing_group_id', esc_html_x( 'Group ID is required', 'Condition group error', 'uncanny-automator' ) );
	}

	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	$condition_service = automator_get_recipe_condition_service();
	return $condition_service->update_condition( $condition_id, $group_id, $recipe_id, $fields );
}

/**
 * Remove condition from group.
 *
 * @param string $condition_id Condition ID.
 * @param string $group_id     Group ID.
 * @param int    $recipe_id    Recipe ID.
 * @return array|WP_Error Success confirmation on success, WP_Error on failure.
 */
function automator_remove_condition_from_group( string $condition_id, string $group_id, int $recipe_id ) {
	if ( empty( $condition_id ) ) {
		return new WP_Error( 'missing_condition_id', esc_html_x( 'Condition ID is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	if ( empty( $group_id ) ) {
		return new WP_Error( 'missing_group_id', esc_html_x( 'Group ID is required', 'Condition group error', 'uncanny-automator' ) );
	}

	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	$condition_service = automator_get_recipe_condition_service();
	return $condition_service->remove_condition_from_group( $condition_id, $group_id, $recipe_id );
}

// =============================================================================
// CONDITION QUERYING
// =============================================================================

/**
 * Get recipe conditions.
 *
 * @param int $recipe_id Recipe ID.
 * @return array|WP_Error Array of condition groups on success, WP_Error on failure.
 */
function automator_get_recipe_conditions( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	$query_service = automator_get_condition_query_service();
	return $query_service->get_recipe_conditions( $recipe_id );
}

/**
 * Get specific condition group.
 *
 * @param string $group_id  Condition group ID.
 * @param int    $recipe_id Recipe ID.
 * @return array|WP_Error Group data on success, WP_Error on failure.
 */
function automator_get_condition_group( string $group_id, int $recipe_id ) {
	if ( empty( $group_id ) ) {
		return new WP_Error( 'missing_group_id', esc_html_x( 'Group ID is required', 'Condition group error', 'uncanny-automator' ) );
	}

	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	// Get all recipe conditions and find the specific group
	$query_service = automator_get_condition_query_service();
	$result        = $query_service->get_recipe_conditions( $recipe_id );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$groups = $result['condition_groups'] ?? array();

	foreach ( $groups as $group ) {
		if ( isset( $group['group_id'] ) && $group['group_id'] === $group_id ) {
			return array(
				'success'   => true,
				'recipe_id' => $recipe_id,
				'group'     => $group,
			);
		}
	}

	return new WP_Error( 'group_not_found', esc_html_x( 'Condition group not found in recipe', 'Condition group error', 'uncanny-automator' ) );
}

/**
 * Get conditions in specific group.
 *
 * @param string $group_id  Group ID.
 * @param int    $recipe_id Recipe ID.
 * @return array|WP_Error Array of conditions on success, WP_Error on failure.
 */
function automator_get_group_conditions( string $group_id, int $recipe_id ) {
	$group = automator_get_condition_group( $group_id, $recipe_id );

	if ( is_wp_error( $group ) ) {
		return $group;
	}

	return array(
		'group_id'         => $group_id,
		'recipe_id'        => $recipe_id,
		'conditions'       => $group['conditions'] ?? array(),
		'total_conditions' => count( $group['conditions'] ?? array() ),
	);
}

// =============================================================================
// CONDITION UTILITIES
// =============================================================================

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

// =============================================================================
// ADDITIONAL CONDITION UTILITIES
// =============================================================================

/**
 * Create a standalone condition.
 *
 * @param array $data {
 *     Condition data.
 *     @type string $integration_code Integration code (e.g., 'WP', 'WC').
 *     @type string $condition_code   Condition code.
 *     @type array  $fields           Field values. Default empty.
 * }
 * @return array|WP_Error Condition data on success, WP_Error on failure.
 */
function automator_create_condition( array $data ) {
	if ( empty( $data['condition_code'] ) ) {
		return new WP_Error( 'missing_condition_code', esc_html_x( 'Condition code is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	if ( empty( $data['integration_code'] ) ) {
		return new WP_Error( 'missing_integration_code', esc_html_x( 'Integration code is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	// This is a utility function that validates condition data
	// For actual creation within a group, use automator_add_condition_to_group()
	return array(
		'success'          => true,
		'integration_code' => $data['integration_code'],
		'condition_code'   => $data['condition_code'],
		'fields'           => $data['fields'] ?? array(),
	);
}

/**
 * Create a condition group.
 *
 * This is an alias for automator_add_condition_group.
 *
 * @param int    $recipe_id   Recipe ID.
 * @param array  $action_ids  Array of action IDs.
 * @param string $mode        Group mode ('all', 'any'). Default 'any'.
 * @param array  $conditions  Array of condition configurations.
 * @return array|WP_Error Group data on success, WP_Error on failure.
 */
function automator_create_condition_group( int $recipe_id, array $action_ids = array(), string $mode = 'any', array $conditions = array() ) {
	return automator_add_condition_group( $recipe_id, $action_ids, $mode, $conditions );
}

/**
 * Refresh a condition with updated fields.
 *
 * @param string $condition_id Condition ID.
 * @param array  $fields       Updated field values.
 * @return array|WP_Error Updated condition data on success, WP_Error on failure.
 */
function automator_refresh_condition( string $condition_id, array $fields = array() ) {
	if ( empty( $condition_id ) ) {
		return new WP_Error( 'missing_condition_id', esc_html_x( 'Condition ID is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	// This is a utility function for validating refresh parameters
	// For actual condition updates, use automator_update_condition()
	return array(
		'success'      => true,
		'condition_id' => $condition_id,
		'fields'       => $fields,
	);
}

/**
 * Validate a condition configuration.
 *
 * @param array $data {
 *     Condition data to validate.
 *     @type string $integration_code Integration code.
 *     @type string $condition_code   Condition code.
 * }
 * @return array|WP_Error Validation result on success, WP_Error on failure.
 */
function automator_validate_condition( array $data ) {
	if ( empty( $data['integration_code'] ) ) {
		return new WP_Error( 'missing_integration_code', esc_html_x( 'Integration code is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	if ( empty( $data['condition_code'] ) ) {
		return new WP_Error( 'missing_condition_code', esc_html_x( 'Condition code is required', 'Condition validation error', 'uncanny-automator' ) );
	}

	return automator_validate_condition_exists( $data['integration_code'], $data['condition_code'] );
}

/**
 * Create backup info for a condition.
 *
 * @param string $integration_code Integration code.
 * @param string $condition_code   Condition code.
 * @param array  $fields           Field values.
 * @return array Backup information.
 */
function automator_create_condition_backup_info( string $integration_code, string $condition_code, array $fields = array() ) {
	return array(
		'integration_code' => $integration_code,
		'condition_code'   => $condition_code,
		'fields'           => $fields,
		'backup_timestamp' => time(),
	);
}

/**
 * Find condition groups in recipe.
 *
 * @param int   $recipe_id Recipe ID.
 * @param array $filters   Optional filters.
 * @return array|WP_Error Array of matching groups on success, WP_Error on failure.
 */
function automator_find_condition_groups( int $recipe_id, array $filters = array() ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	$result = automator_get_recipe_conditions( $recipe_id );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$groups = $result['condition_groups'] ?? array();

	// Apply filters if provided
	if ( ! empty( $filters ) ) {
		// TODO: Implement filter logic when needed
		$groups = $groups; // Placeholder for filter logic
	}

	return array(
		'success'   => true,
		'groups'    => $groups,
		'count'     => count( $groups ),
		'recipe_id' => $recipe_id,
	);
}

/**
 * Require a condition group (fails if not found).
 *
 * @param int    $recipe_id Recipe ID.
 * @param string $group_id  Group ID.
 * @return array|WP_Error Group data on success, WP_Error on failure.
 */
function automator_require_condition_group( int $recipe_id, string $group_id ) {
	$group = automator_get_condition_group( $group_id, $recipe_id );

	if ( is_wp_error( $group ) ) {
		return $group;
	}

	return $group;
}

/**
 * Replace a condition group in recipe.
 *
 * @param int    $recipe_id Recipe ID.
 * @param string $group_id  Group ID to replace.
 * @param array  $new_group_data New group configuration.
 * @return array|WP_Error Updated group data on success, WP_Error on failure.
 */
function automator_replace_condition_group( int $recipe_id, string $group_id, array $new_group_data ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required', 'Recipe validation error', 'uncanny-automator' ) );
	}

	if ( empty( $group_id ) ) {
		return new WP_Error( 'missing_group_id', esc_html_x( 'Group ID is required', 'Condition group error', 'uncanny-automator' ) );
	}

	// First verify the group exists
	$existing = automator_get_condition_group( $group_id, $recipe_id );

	if ( is_wp_error( $existing ) ) {
		return $existing;
	}

	// Use update functionality to replace
	$mode     = $new_group_data['mode'] ?? null;
	$priority = $new_group_data['priority'] ?? null;

	return automator_update_condition_group( $recipe_id, $group_id, $mode, $priority );
}

/**
 * Remove a condition group by ID.
 *
 * @param int    $recipe_id Recipe ID.
 * @param string $group_id  Group ID.
 * @return array|WP_Error Success confirmation on success, WP_Error on failure.
 */
function automator_remove_condition_group_by_id( int $recipe_id, string $group_id ) {
	return automator_remove_condition_group( $recipe_id, $group_id, true );
}
