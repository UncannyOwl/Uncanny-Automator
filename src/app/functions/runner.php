<?php
/**
 * Public helpers for the recipe runner.
 *
 * The seam integrations use to influence the run they execute in; nothing here throws.
 *
 * @package Uncanny_Automator\App
 */

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Schema_Reader;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Tree_Walk;
use Uncanny_Automator\App\Recipe_Runner\Services\Run_Detach_Request;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'automator_request_run_detach' ) ) {
	/**
	 * Ask the walker to hand everything after the current action to the caller's background job: the remainder is recorded waiting on the calling action and released when its job completes.
	 *
	 * Honoured only on a synchronous, non-loop walk; the walker drops it anywhere else. The caller's
	 * own row is unaffected: complete it as usual.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The run the calling action executes in (`$action_data['recipe_log_id']`).
	 *
	 * @return void
	 */
	function automator_request_run_detach( int $recipe_log_id ): void {
		if ( $recipe_log_id <= 0 ) {
			return;
		}
		Run_Detach_Request::request( $recipe_log_id );
	}
}

if ( ! function_exists( 'automator_run_has_snapshot' ) ) {
	/**
	 * Whether a run kept its snapshot. Background completion rebuilds the run's args from it, so an action that
	 * hands the rest of the run to a background job must know the snapshot exists before it does.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return bool
	 */
	function automator_run_has_snapshot( int $recipe_log_id ): bool {
		if ( $recipe_log_id <= 0 ) {
			return false;
		}
		try {
			return null !== Database::get_run_snapshot_store()->get( $recipe_log_id );
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}

if ( ! function_exists( 'automator_run_has_work_after' ) ) {
	/**
	 * Whether anything runnable follows an action in its recipe's walk order, at its own level or at the level of
	 * any block enclosing it; a delay or schedule block does not count, it only books its later run. An action that is
	 * last everywhere can finish the run synchronously instead of handing it to a background job. False when the
	 * recipe tree cannot be read or does not hold the action: the synchronous path is always safe.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id The recipe.
	 * @param int $action_id The action.
	 *
	 * @return bool
	 */
	function automator_run_has_work_after( int $recipe_id, int $action_id ): bool {
		if ( $recipe_id <= 0 || $action_id <= 0 ) {
			return false;
		}
		try {
			$tree = Recipe_Schema_Reader::instance()->get_or_build( $recipe_id );
		} catch ( \Throwable $e ) {
			return false;
		}
		$items = is_array( $tree ) ? (array) ( $tree['content'] ?? array() ) : array();

		return true === Tree_Walk::has_work_after( $items, $action_id );
	}
}
