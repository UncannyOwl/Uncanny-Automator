<?php
/**
 * Loop Functions
 *
 * WordPress developer convenience functions for loop-block operations.
 *
 * @package Uncanny_Automator\App\Functions
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Uncanny_Automator\App\Recipe_Builder\Loop\Enums\Loop_Status;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Loop_CRUD_Service;

if ( ! function_exists( 'automator_add_loop_to_recipe' ) ) {
	/**
	 * Create a unified loop block on a recipe.
	 *
	 * Thin convenience wrapper over Loop_CRUD_Service::add_to_recipe() — the
	 * sanctioned outside-`src/app` seam for callers (e.g. Pro's loopable
	 * auto-loop) that must create loops without reaching into `src/app`
	 * services directly. Produces a unified `uo-block(block_type='loop')` row;
	 * when $filters is empty the service seeds the iteration type's default
	 * loop filter (token → ITEM_NOT_EMPTY, users → WP_USER_HAS_ROLE,
	 * posts → WP_POST_EQUALS_POST_TYPE).
	 *
	 * @param int    $recipe_id           Recipe post id.
	 * @param array  $iterable_expression Iteration config: ['type' => 'users'|'posts'|'token', 'fields' => json, 'backup' => json].
	 * @param string $status              Loop post status. Defaults to Loop_Status::PUBLISH (the service default is DRAFT; helper callers want an active loop).
	 * @param array  $filters             Optional initial filters; empty seeds the type's default filter.
	 *
	 * @return array|\WP_Error Service success payload (loop_id, loop, ...) or WP_Error.
	 */
	function automator_add_loop_to_recipe( int $recipe_id, array $iterable_expression, string $status = Loop_Status::PUBLISH, array $filters = array() ) {
		if ( $recipe_id <= 0 ) {
			return new \WP_Error(
				'invalid_recipe_id',
				esc_html_x( 'Valid recipe ID is required.', 'Loop helper error', 'uncanny-automator' )
			);
		}

		if ( empty( $iterable_expression['type'] ) ) {
			return new \WP_Error(
				'missing_loop_type',
				esc_html_x( 'Loop iteration type is required.', 'Loop helper error', 'uncanny-automator' )
			);
		}

		try {
			return Loop_CRUD_Service::instance()->add_to_recipe( $recipe_id, $iterable_expression, $status, $filters );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'loop_creation_failed',
				esc_html_x( 'Failed to create loop.', 'Loop helper error', 'uncanny-automator' )
			);
		}
	}
}

if ( ! function_exists( 'automator_get_loop_filters' ) ) {
	/**
	 * Retrieve a loop's filters as unified modifier rows.
	 *
	 * Thin wrapper over Filter_CRUD_Service::get_loop_filters() — the
	 * sanctioned outside-`src/app` read seam for a loop's `uo-block-modifier`
	 * (block_modifier_type='loop_filter') rows. Each row carries id, code,
	 * integration_code, decoded fields/backup, and menu_order, so callers
	 * (e.g. Pro's loop entity resolution) extract everything in one shot.
	 *
	 * @param int $loop_id Unified loop block post id.
	 *
	 * @return array|\WP_Error Modifier rows ordered by menu_order, or WP_Error.
	 */
	function automator_get_loop_filters( int $loop_id ) {
		if ( $loop_id <= 0 ) {
			return new \WP_Error(
				'invalid_loop_id',
				esc_html_x( 'Valid loop ID is required.', 'Loop helper error', 'uncanny-automator' )
			);
		}

		try {
			return Filter_CRUD_Service::instance()->get_loop_filters( $loop_id );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'loop_filters_read_failed',
				esc_html_x( 'Failed to retrieve loop filters.', 'Loop helper error', 'uncanny-automator' )
			);
		}
	}
}
