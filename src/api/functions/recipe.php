<?php
/**
 * Recipe Functions
 *
 * WordPress developer convenience functions for recipe operations.
 * Covers CRUD, querying, and logging operations.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Functions
 */

declare(strict_types=1);

// Prevent direct access (skip in test environment)
if ( ! defined( 'ABSPATH' ) && ! defined( 'PHPUNIT_COMPOSER_INSTALL' ) && ! defined( 'WP_TESTS_DIR' ) ) {
	exit;
}

// Import classes
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_Query_Service;
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_Log_Service;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Registry_Service;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;

// =============================================================================
// RECIPE CRUD OPERATIONS
// =============================================================================

/**
 * Create a new recipe.
 *
 * @param array $data {
 *     Recipe configuration data.
 *     @type string $title         Recipe title (required).
 *     @type string $status        Recipe status ('draft', 'published'). Default 'draft'.
 *     @type string $type          Recipe type ('user', 'anonymous'). Default 'user'.
 *     @type string $trigger_logic Recipe trigger logic ('all', 'any'). Default 'all'.
 *     @type string $notes         Recipe notes. Default ''.
 *     @type array  $throttle {
 *         Throttling configuration.
 *         @type bool   $enabled  Whether throttling is enabled. Default false.
 *         @type int    $duration Duration value. Default 1.
 *         @type string $unit     Time unit ('minutes', 'hours', 'days'). Default 'hours'.
 *         @type string $scope    Throttle scope ('recipe', 'user'). Default 'recipe'.
 *     }
 * }
 * @return array|WP_Error Recipe data on success, WP_Error on failure.
 */
/**
 * Automator create recipe.
 *
 * @param array $data The data.
 * @return mixed
 */
function automator_create_recipe( array $data ) {
	if ( empty( $data['title'] ) ) {
		return new WP_Error(
			'missing_title',
			esc_html_x( 'Recipe title is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_recipe_crud_service();
	return $crud_service->create_recipe( $data );
}

/**
 * Get a recipe by ID.
 *
 * @param int $recipe_id Recipe ID.
 * @return array|WP_Error Recipe data on success, WP_Error on failure.
 */
function automator_get_recipe( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$query_service = automator_get_recipe_query_service();
	return $query_service->get_recipe( $recipe_id );
}

/**
 * Update an existing recipe.
 *
 * @param int   $recipe_id Recipe ID.
 * @param array $data      Updated recipe data.
 * @return array|WP_Error Updated recipe data on success, WP_Error on failure.
 */
function automator_update_recipe( int $recipe_id, array $data ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_recipe_crud_service();
	$result       = $crud_service->update_recipe( $recipe_id, $data );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return automator_normalize_recipe_output( $result );
}

/**
 * Delete a recipe.
 *
 * @param int  $recipe_id Recipe ID.
 * @param bool $confirmed Confirmation flag (safety measure).
 * @return array|WP_Error Success confirmation on success, WP_Error on failure.
 */
function automator_delete_recipe( int $recipe_id, bool $confirmed = false ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	if ( ! $confirmed ) {
		return new WP_Error(
			'confirmation_required',
			esc_html_x( 'Recipe deletion must be confirmed with $confirmed = true.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_recipe_crud_service();
	return $crud_service->delete_recipe( $recipe_id, true );
}

/**
 * Duplicate an existing recipe.
 *
 * @param int    $source_recipe_id Source recipe ID.
 * @param string $new_title        New recipe title. Default '' (auto-generate).
 * @param string $new_status       New recipe status. Default Recipe_Status::DRAFT.
 * @return array|WP_Error Duplicated recipe data on success, WP_Error on failure.
 */
function automator_duplicate_recipe( int $source_recipe_id, string $new_title = '', string $new_status = Recipe_Status::DRAFT ) {
	if ( empty( $source_recipe_id ) || $source_recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_source_recipe_id',
			esc_html_x( 'Valid source recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_recipe_crud_service();
	return $crud_service->duplicate_recipe( $source_recipe_id, $new_title, $new_status );
}

// =============================================================================
// RECIPE QUERYING
// =============================================================================

/**
 * List recipes with optional filters.
 *
 * @param array $filters {
 *     Optional filters for recipe listing.
 *     @type string $status   Recipe status ('draft', 'published').
 *     @type string $type     Recipe type ('user', 'anonymous').
 *     @type string $title    Title search term.
 *     @type int    $limit    Maximum results. Default 50, max 500.
 *     @type int    $offset   Results offset. Default 0.
 * }
 * @return array|WP_Error Array of recipes on success, WP_Error on failure.
 */
function automator_list_recipes( array $filters = array() ) {
	$defaults = array(
		'limit'  => 50,
		'offset' => 0,
	);

	$filters = array_merge( $defaults, $filters );

	// Enforce reasonable limits
	$filters['limit'] = min( (int) $filters['limit'], 500 );

	$query_service = automator_get_recipe_query_service();
	return $query_service->list_recipes( $filters );
}

/**
 * Search recipes by title or content.
 *
 * @param string $query    Search query.
 * @param array  $filters  Additional filters.
 * @param int    $limit    Maximum results. Default 20, max 100.
 * @return array|WP_Error Array of matching recipes on success, WP_Error on failure.
 */
function automator_search_recipes( string $query, array $filters = array(), int $limit = 20 ) {
	if ( empty( $query ) ) {
		return new WP_Error(
			'missing_query',
			esc_html_x( 'Search query is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$filters['limit'] = min( $limit, 100 );

	$registry_service = new Recipe_Registry_Service();
	return $registry_service->find_recipes( $query, $filters, $limit );
}

/**
 * Get recipes by integration.
 *
 * @param string $integration Integration code (e.g., 'WP', 'WC').
 * @param array  $filters     Additional filters.
 * @return array|WP_Error Array of recipes on success, WP_Error on failure.
 */
function automator_get_recipes_by_integration( string $integration, array $filters = array() ) {
	if ( empty( $integration ) ) {
		return new WP_Error(
			'missing_integration',
			esc_html_x( 'Integration code is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$query_service = automator_get_recipe_query_service();
	return $query_service->get_recipes_by_integration( $integration, $filters );
}

/**
 * Get recipes by meta value.
 *
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value.
 * @param string $compare    Comparison operator. Default '='.
 * @param array  $filters    Additional filters.
 * @return array|WP_Error Array of recipes on success, WP_Error on failure.
 */
function automator_get_recipes_by_meta( string $meta_key, $meta_value, string $compare = '=', array $filters = array() ) {
	if ( empty( $meta_key ) ) {
		return new WP_Error(
			'missing_meta_key',
			esc_html_x( 'Meta key is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$query_service = automator_get_recipe_query_service();
	return $query_service->get_recipes_by_meta( $meta_key, $meta_value, $compare, $filters );
}

/**
 * Get recipes by field value.
 *
 * @param mixed $field_value Field value to search.
 * @param array $filters     Additional filters.
 * @return array|WP_Error Array of recipes on success, WP_Error on failure.
 */
function automator_get_recipes_by_field_value( $field_value, array $filters = array() ) {
	if ( null === $field_value ) {
		return new WP_Error(
			'missing_field_value',
			esc_html_x( 'Field value is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$query_service = automator_get_recipe_query_service();
	return $query_service->get_recipes_from_field_value( $field_value, $filters );
}

/**
 * Get recipe count with optional filters.
 *
 * @param array $filters Optional filters.
 * @return int|WP_Error Recipe count on success, WP_Error on failure.
 */
function automator_get_recipe_count( array $filters = array() ) {
	$query_service = automator_get_recipe_query_service();
	$result        = $query_service->get_recipe_count( $filters );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return $result['count'];
}

// =============================================================================
// RECIPE LOGGING
// =============================================================================

/**
 * Get recipe logs with optional filters.
 *
 * @param array $filters {
 *     Optional filters for log retrieval.
 *     @type int    $recipe_id    Recipe ID to filter logs.
 *     @type string $run_number   Run number to filter.
 *     @type int    $limit        Maximum results. Default 20.
 *     @type int    $offset       Results offset. Default 0.
 *     @type bool   $include_meta Include metadata. Default false.
 * }
 * @return array|WP_Error Array of logs on success, WP_Error on failure.
 */
function automator_get_recipe_logs( array $filters = array() ) {
	$defaults = array(
		'limit'        => 20,
		'offset'       => 0,
		'include_meta' => false,
	);

	$filters = array_merge( $defaults, $filters );

	// Enforce reasonable limits
	$filters['limit'] = min( (int) $filters['limit'], 100 );

	$log_service = automator_get_recipe_log_service();
	return $log_service->get_recipe_logs( $filters );
}

/**
 * Get logs for a specific recipe.
 *
 * @param int   $recipe_id Recipe ID.
 * @param array $filters   Additional filters.
 * @return array|WP_Error Array of logs on success, WP_Error on failure.
 */
function automator_get_recipe_logs_by_recipe_id( int $recipe_id, array $filters = array() ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$filters['recipe_id'] = $recipe_id;

	$log_service = automator_get_recipe_log_service();
	return $log_service->get_recipe_logs_by_recipe_id( $recipe_id, $filters );
}

/**
 * Get the most recent recipe log.
 *
 * @param array $filters Optional filters.
 * @return array|WP_Error Most recent log on success, WP_Error on failure.
 */
function automator_get_most_recent_recipe_log( array $filters = array() ) {
	$log_service = automator_get_recipe_log_service();
	return $log_service->get_most_recent_recipe_log( $filters );
}

/**
 * Get detailed log information.
 *
 * @param int  $recipe_id   Recipe ID.
 * @param int  $run_number  Run number.
 * @param int  $recipe_log_id Recipe log ID.
 * @param bool $enable_profiling Include profiling data.
 * @return array|WP_Error Detailed log data on success, WP_Error on failure.
 */
function automator_get_recipe_log_details( int $recipe_id, int $run_number, int $recipe_log_id, bool $enable_profiling = false ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	if ( empty( $run_number ) || $run_number <= 0 ) {
		return new WP_Error(
			'invalid_run_number',
			esc_html_x( 'Valid run number is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	if ( empty( $recipe_log_id ) || $recipe_log_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_log_id',
			esc_html_x( 'Valid recipe log ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	$log_service = automator_get_recipe_log_service();
	return $log_service->get_log( $recipe_id, $run_number, $recipe_log_id, $enable_profiling );
}

// =============================================================================
// RECIPE UTILITIES
// =============================================================================

/**
 * Normalize recipe output for WordPress developers.
 *
 * Converts domain keys to simple WP-friendly keys.
 *
 * @param array $recipe_data Raw recipe data.
 * @return array Normalized data with simple keys.
 */
function automator_normalize_recipe_output( array $recipe_data ): array {
	return array(
		'id'       => $recipe_data['recipe_id'] ?? $recipe_data['id'] ?? null,
		'title'    => $recipe_data['recipe_title'] ?? $recipe_data['title'] ?? '',
		'status'   => $recipe_data['recipe_status'] ?? $recipe_data['status'] ?? Recipe_Status::DRAFT,
		'type'     => $recipe_data['recipe_type'] ?? $recipe_data['type'] ?? 'user',
		'triggers' => $recipe_data['triggers'] ?? array(),
		'actions'  => $recipe_data['actions'] ?? array(),
		'meta'     => $recipe_data['meta'] ?? array(),
	);
}
