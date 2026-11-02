<?php
/**
 * Recipe Functions
 *
 * WordPress developer convenience functions for recipe operations.
 * Covers CRUD, querying, and logging operations.
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
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Query_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Log_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Registry_Service;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Duplication_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Exporter;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Importer;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Stores\Recipe_Schema_Store;
use Uncanny_Automator\App\Presentation\Recipe_View\Recipe_View_Preference_Service;

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
 *         @type string $unit     Time unit (seconds, minutes, hours, days, weeks, months, years). Default 'hours'.
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
 * Prepopulate a freshly created recipe from a normalized parameter map.
 *
 * Partner quick-create seam: sets the recipe type, adds a trigger, and
 * optionally applies one option value then publishes the trigger. Thin
 * wrapper over Recipe_Prepopulation_Service for callers outside src/app.
 *
 * @param int   $recipe_id Recipe post ID.
 * @param array $params    See Recipe_Prepopulation_Service::populate() for keys.
 * @return int|WP_Error Created trigger ID on success, WP_Error on failure.
 */
function automator_prepopulate_recipe( int $recipe_id, array $params ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	try {
		return automator_get_recipe_prepopulation_service()->populate( $recipe_id, $params );
	} catch ( \Throwable $e ) {
		return new WP_Error( 'prepopulate_failed', $e->getMessage() );
	}
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
 * Deep-duplicate an existing recipe.
 *
 * Delegates to the deep engine ({@see Recipe_Duplication_Service}) — the copy
 * carries the source's full graph (triggers, actions, blocks, conditions) with
 * fresh ids and rewritten intra-recipe references, not a shallow shell.
 *
 * Return contract (unchanged from the previous shallow seam): an array with at
 * least `success => true` and `recipe_id` on success, a WP_Error on failure.
 *
 * @param int    $source_recipe_id Source recipe ID.
 * @param string $new_title        New recipe title. Default '' (auto-generate "<title> (Copy)").
 * @param string $new_status       New recipe status. Default Recipe_Status::DRAFT.
 * @return array|WP_Error Duplicated recipe summary on success, WP_Error on failure.
 */
function automator_duplicate_recipe( int $source_recipe_id, string $new_title = '', string $new_status = Recipe_Status::DRAFT ) {
	if ( empty( $source_recipe_id ) || $source_recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_source_recipe_id',
			esc_html_x( 'Valid source recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	try {
		$result = ( new Recipe_Duplication_Service() )->duplicate_recipe( $source_recipe_id, $new_title, $new_status );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$new_recipe_id = $result->get_root_id();

		return array(
			'success'   => true,
			'recipe_id' => $new_recipe_id,
			'title'     => get_the_title( $new_recipe_id ),
			'status'    => get_post_status( $new_recipe_id ),
			'type'      => automator_get_recipe_user_type( $new_recipe_id ),
		);
	} catch ( \Throwable $e ) {
		return new WP_Error( 'duplicate_failed', $e->getMessage() );
	}
}

// =============================================================================
// RECIPE PORTABILITY (EXPORT / IMPORT)
// =============================================================================

/**
 * Export a recipe as a versioned portable document (the canonical tree plus
 * a small envelope). The admin export download JSON-encodes this document.
 *
 * @param int $recipe_id Recipe to export.
 * @return array|WP_Error Portable document on success, WP_Error on failure.
 */
function automator_export_recipe( int $recipe_id ) {
	if ( $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}
	try {
		$document = ( new Recipe_Exporter() )->export( $recipe_id );
		if ( is_wp_error( $document ) ) {
			return $document;
		}
		return Dispatcher::filter( 'automator_recipe_export_document', $document, $recipe_id );
	} catch ( \Throwable $e ) {
		return new WP_Error( 'export_failed', $e->getMessage() );
	}
}

/**
 * Import a portable recipe document as a new draft recipe. Mirrors
 * automator_duplicate_recipe's return envelope so core consumes one shape.
 *
 * @param array $document Decoded portable document (schema_version envelope).
 * @return array|WP_Error {success, recipe_id} on success, WP_Error on failure.
 */
function automator_import_recipe( array $document ) {
	try {
		$result = ( new Recipe_Importer() )->import( $document );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'success'   => true,
			'recipe_id' => $result->get_root_id(),
		);
	} catch ( \Throwable $e ) {
		return new WP_Error( 'import_failed', $e->getMessage() );
	}
}

/**
 * Import an OLD-FORMAT recipe export file (raw post+meta dump) as a new
 * draft recipe through the adapter path. Same return envelope as
 * automator_import_recipe.
 *
 * @param object $old_json Decoded legacy export JSON.
 * @return array|WP_Error {success, recipe_id} on success, WP_Error on failure.
 */
function automator_import_legacy_recipe( object $old_json ) {
	try {
		$result = ( new Recipe_Importer() )->import_legacy( $old_json );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'success'   => true,
			'recipe_id' => $result->get_root_id(),
		);
	} catch ( \Throwable $e ) {
		return new WP_Error( 'import_failed', $e->getMessage() );
	}
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
 * Search recipes by title or notes.
 *
 * Scores every recipe the filters allow and returns the best matches first.
 *
 * @param string $query    Search query.
 * @param array  $filters  Additional filters (status, type).
 * @param int    $limit    Maximum results. Default 20, max 50.
 * @return array|WP_Error Array of matching recipes on success, WP_Error on failure.
 */
function automator_search_recipes( string $query, array $filters = array(), int $limit = 20 ) {
	if ( empty( $query ) ) {
		return new WP_Error(
			'missing_query',
			esc_html_x( 'Search query is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

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
 * Integration codes of a recipe's on items, at any nesting depth.
 *
 * Counts triggers, actions, filter conditions and loop filters that would run;
 * drafted triggers and actions are not counted.
 *
 * @param int $recipe_id Recipe post id.
 * @return string[] Unique codes in tree order; empty when the recipe cannot be read.
 */
function automator_get_recipe_integration_codes( int $recipe_id ): array {
	try {
		return automator_get_recipe_integrations_service()->integration_codes( $recipe_id );
	} catch ( \Throwable $e ) {
		return array();
	}
}

/**
 * Every trigger and action in a recipe, on or off, labelled by custom name, else catalog item name, else title, else code.
 *
 * @param int $recipe_id Recipe post id.
 * @return array<int, array{entity: string, post_id: int, is_on: bool, label: string}> Tree order; empty when the recipe cannot be read.
 */
function automator_get_recipe_items( int $recipe_id ): array {
	try {
		return automator_get_recipe_integrations_service()->items( $recipe_id );
	} catch ( \Throwable $e ) {
		return array();
	}
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
 * Get a recipe's latest run state — the newest `uap_recipe_log` row.
 *
 * The public surface (single source of truth) for
 * {@see \Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store::get_latest_recipe_log}.
 * This is the raw run-state read (log id, run number, completion status) that
 * Run Now, recipe state-flags reporting, and trigger re-entrancy checks rely
 * on — distinct from the enriched logs-UI reads above (which query the
 * `uap_recipe_logs_view`).
 *
 * @param int $recipe_id Recipe ID.
 *
 * @return array{recipe_log_id:int, run_number:int, completed:int}|null Null when
 *         the recipe has never run (or on invalid input).
 */
function automator_get_recipe_latest_log( int $recipe_id ) {
	if ( $recipe_id <= 0 ) {
		return null;
	}

	try {
		$row = Database::get_execution_log_store()->get_latest_recipe_log( $recipe_id );
	} catch ( \Throwable $e ) {
		return null;
	}

	if ( null === $row ) {
		return null;
	}

	return array(
		'recipe_log_id' => (int) $row->ID,
		'run_number'    => (int) $row->run_number,
		'completed'     => (int) $row->completed,
	);
}

/**
 * Get one run's details: the run tree the recipe log shows, with each
 * trigger, action, filter, delay and loop and its status.
 *
 * @since 8.0.0 Returns the 8.0 run tree; the profiling argument is gone.
 *
 * @param int $recipe_id     Recipe ID.
 * @param int $run_number    Run number.
 * @param int $recipe_log_id Recipe log ID.
 * @return array|WP_Error The run tree on success, WP_Error on failure.
 */
function automator_get_recipe_log_details( int $recipe_id, int $run_number, int $recipe_log_id ) {
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

	try {
		return automator_get_recipe_log_query_service()->get_log_payload( $recipe_id, $run_number, $recipe_log_id );
	} catch ( \Throwable $e ) {
		return new WP_Error(
			'recipe_log_read_failed',
			esc_html_x( 'The recipe log could not be read.', 'Recipe helper error', 'uncanny-automator' )
		);
	}
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

// =============================================================================
// RECIPE SETTINGS
// =============================================================================

/**
 * Check whether a recipe is currently throttled for a user (runtime check).
 *
 * Consults the throttle config + throttle log. Returns false (not throttled)
 * on any error so a throttle-read failure never blocks execution.
 *
 * @param int $recipe_id Recipe ID.
 * @param int $user_id   User ID.
 * @return bool True if throttled (should NOT execute), false otherwise.
 */
function automator_is_recipe_throttled( int $recipe_id, int $user_id ): bool {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return false;
	}

	try {
		return automator_get_recipe_throttle_service()->is_throttled( $recipe_id, $user_id );
	} catch ( \Throwable $e ) {
		return false;
	}
}

/**
 * Get a recipe's throttle configuration.
 *
 * @param int $recipe_id Recipe ID.
 * @return array|WP_Error { enabled, duration, unit, scope } on success, WP_Error on failure.
 */
function automator_get_recipe_throttle( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	try {
		return automator_get_recipe_settings_service()->get_throttle( $recipe_id );
	} catch ( \Throwable $e ) {
		return new WP_Error(
			'throttle_read_failed',
			$e->getMessage()
		);
	}
}

/**
 * Get a recipe's total completion limit.
 *
 * @param int $recipe_id Recipe ID.
 * @return int|WP_Error Limit on success, WP_Error on failure.
 */
function automator_get_recipe_total_times( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	try {
		return automator_get_recipe_settings_service()->get_total_times( $recipe_id );
	} catch ( \Throwable $e ) {
		return new WP_Error(
			'total_times_read_failed',
			$e->getMessage()
		);
	}
}

/**
 * Get a recipe's per-user completion limit.
 *
 * @param int $recipe_id Recipe ID.
 * @return int|WP_Error Limit on success (-1 = unlimited), WP_Error on failure.
 */
function automator_get_recipe_times_per_user( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	try {
		return automator_get_recipe_settings_service()->get_times_per_user( $recipe_id );
	} catch ( \Throwable $e ) {
		return new WP_Error(
			'times_per_user_read_failed',
			$e->getMessage()
		);
	}
}

/**
 * Get a recipe's user type ('user' or 'anonymous').
 *
 * @param int $recipe_id Recipe ID.
 * @return string|WP_Error Type on success, WP_Error on failure.
 */
function automator_get_recipe_user_type( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' )
		);
	}

	try {
		return automator_get_recipe_settings_service()->get_recipe_type( $recipe_id );
	} catch ( \Throwable $e ) {
		return new WP_Error(
			'user_type_read_failed',
			$e->getMessage()
		);
	}
}

/**
 * Get a recipe's run-as (user selector) config as a normalized domain array.
 *
 * The single read seam for outside-`src/app` consumers (Pro's run-as
 * resolver). The `source` is normalized to canonical kebab (`existing-user` /
 * `new-user`) regardless of the casing stored in post meta.
 *
 * @param int $recipe_id Recipe ID.
 *
 * @return array|null Normalized selector array (`source` + structured fields),
 *                    or null when none is configured or on invalid input.
 */
function automator_get_recipe_user_selector( int $recipe_id ) {
	if ( $recipe_id <= 0 ) {
		return null;
	}

	try {
		$selector = automator_get_user_selector_service()->get_user_selector( $recipe_id );
	} catch ( \Throwable $e ) {
		return null;
	}

	if ( ! is_array( $selector ) || empty( $selector['data'] ) || ! is_array( $selector['data'] ) ) {
		return null;
	}

	return $selector['data'];
}

/**
 * Whether the recipe has a run-as (user selector) configured.
 *
 * @since 8.0.0
 *
 * @param int $recipe_id Recipe ID.
 *
 * @return bool False when none is configured, or on invalid input or failure.
 */
function automator_recipe_has_user_selector( int $recipe_id ): bool {
	if ( $recipe_id <= 0 ) {
		return false;
	}

	try {
		return automator_get_user_selector_service()->has_user_selector( $recipe_id );
	} catch ( \Throwable $e ) {
		return false;
	}
}

/**
 * Whether any published Everyone recipe's run-as logs the user in.
 *
 * Reads every stored shape of the run-as `fields` meta, so callers outside
 * `src/app` (Pro's deferred login) never match on the raw meta.
 *
 * @since 8.0.0
 *
 * @return bool False when none does, or on failure.
 */
function automator_any_published_recipe_logs_user_in(): bool {
	try {
		return automator_get_user_selector_service()->any_published_recipe_logs_user_in();
	} catch ( \Throwable $e ) {
		return false;
	}
}

if ( ! function_exists( 'automator_invalidate_recipe_schema' ) ) {
	/**
	 * Drop a recipe's cached wire schema so the next read rebuilds it from storage. For code that writes recipe
	 * children outside the stores (a migration pass); store writes invalidate on their own.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id The recipe.
	 *
	 * @return bool False for a bad id or when the cache could not be dropped.
	 */
	function automator_invalidate_recipe_schema( int $recipe_id ): bool {
		if ( $recipe_id <= 0 ) {
			return false;
		}
		try {
			Recipe_Schema_Store::instance()->invalidate( $recipe_id );
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}

if ( ! function_exists( 'automator_get_recipe_default_view' ) ) {
	/**
	 * What a recipe opens as for a person who has not picked a view: their
	 * settled default, or what it would be settled to now, `list` when they
	 * have created a recipe (published or draft) and `diagram` when they have
	 * not. Never stores anything.
	 *
	 * @since 8.0.0
	 *
	 * @param int $user_id The person.
	 *
	 * @return string|\WP_Error `diagram` or `list`; a WP_Error when it cannot be read.
	 */
	function automator_get_recipe_default_view( int $user_id ) {
		try {
			return Recipe_View_Preference_Service::instance()->default_view_without_settling( $user_id )->value();
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'recipe_default_view_unavailable', $e->getMessage() );
		}
	}
}

if ( ! function_exists( 'automator_get_recipe_type_or_null' ) ) {
	/**
	 * A recipe's chosen type as the builder sees it: null until the person
	 * picks one in the type selector, so null means a new recipe.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id The recipe.
	 *
	 * @return string|null|\WP_Error 'user', 'anonymous' or null; a WP_Error for a bad ID or when it cannot be read.
	 */
	function automator_get_recipe_type_or_null( int $recipe_id ) {
		if ( $recipe_id <= 0 ) {
			return new WP_Error( 'invalid_recipe_id', esc_html_x( 'Valid recipe ID is required.', 'Recipe helper error', 'uncanny-automator' ) );
		}

		try {
			return automator_get_recipe_settings_service()->get_recipe_type_or_null( $recipe_id );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'recipe_type_read_failed', $e->getMessage() );
		}
	}
}

if ( ! function_exists( 'automator_user_has_published_recipe' ) ) {
	/**
	 * Whether a person has published a recipe they authored. Drafts do not count.
	 *
	 * @since 8.0.0
	 *
	 * @param int $user_id The person; 0 or less has none.
	 *
	 * @return bool|\WP_Error A WP_Error when it cannot be read.
	 */
	function automator_user_has_published_recipe( int $user_id ) {
		try {
			return automator_get_recipe_query_service()->user_has_published_recipe( $user_id );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'recipe_query_failed', $e->getMessage() );
		}
	}
}
