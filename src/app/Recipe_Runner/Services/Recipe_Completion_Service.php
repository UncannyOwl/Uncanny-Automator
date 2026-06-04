<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\Services\Recipe\Process\Universal_Run_Number_Threshold;
use Uncanny_Automator\Services\Recipe\Process\User_Run_Number_Threshold;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Manager;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Repository\Settings_Repository;

/**
 * Recipe completion checks for the recipe runner.
 *
 * Replaces \Automator()->is_recipe_completed() and the batch completion
 * methods previously on Trigger_Entry_Stage.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.2
 */
class Recipe_Completion_Service {

	/**
	 * @var Field_Manager|null Cached instance — stateless, safe to reuse.
	 */
	private $field_manager;

	/**
	 * @var Execution_Log_Store
	 */
	private $log_store;

	/**
	 * @param Execution_Log_Store|null $log_store Optional log store.
	 */
	public function __construct( ?Execution_Log_Store $log_store = null ) {
		$this->log_store = $log_store ?? Database::get_execution_log_store();
	}

	/**
	 * @return Field_Manager
	 */
	private function field_manager(): Field_Manager {
		$this->field_manager = $this->field_manager ?? new Field_Manager( new Settings_Repository() );
		return $this->field_manager;
	}

	/**
	 * Check if a recipe is completed for a user (global max + per-user).
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return bool
	 */
	public function is_completed( int $recipe_id, int $user_id ): bool {

		if ( $this->is_completed_max_times( $recipe_id ) ) {
			return true;
		}

		// user_id=0 means anonymous — no per-user limit applies.
		// Don't gate on is_user_logged_in() — async/cron paths pass valid
		// user_id without a session.
		if ( 0 === $user_id ) {
			return false;
		}

		$completed_count = $this->user_completed_count( $recipe_id, $user_id );

		return $this->user_threshold_reached( $recipe_id, $completed_count );
	}

	/**
	 * Check if a recipe has been completed the maximum global times.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return bool
	 */
	public function is_completed_max_times( int $recipe_id ): bool {

		$count = $this->log_store->get_global_completion_count( $recipe_id );

		if ( 0 === $count ) {
			return false;
		}

		return $this->global_threshold_reached( $recipe_id, $count );
	}

	/**
	 * Get the number of times a user has completed a recipe.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int
	 */
	public function user_completed_count( int $recipe_id, int $user_id ): int {
		return $this->log_store->get_user_completed_recipe_count( $recipe_id, $user_id );
	}

	/**
	 * Batch-check whether recipes are completed for a user.
	 *
	 * Combines global max-times and per-user completion into 1-2 queries
	 * for all recipe IDs instead of N*2 per-recipe queries.
	 *
	 * @param int[] $recipe_ids Array of recipe IDs.
	 * @param int   $user_id   The user ID.
	 *
	 * @return array Map of recipe_id => true for completed recipes.
	 */
	public function batch_check_completed( array $recipe_ids, int $user_id ): array {

		if ( empty( $recipe_ids ) ) {
			return array();
		}

		$completed = $this->batch_check_global_completion( $recipe_ids );

		if ( 0 === absint( $user_id ) ) {
			return $completed;
		}

		return $this->batch_check_user_completion( $recipe_ids, $user_id, $completed );
	}

	/**
	 * Check global (max-times) completion for all recipe IDs.
	 *
	 * @param int[] $recipe_ids Array of recipe IDs.
	 *
	 * @return array Map of recipe_id => true for completed recipes.
	 */
	public function batch_check_global_completion( array $recipe_ids ): array {

		$completed     = array();
		$global_counts = $this->log_store->batch_get_global_completion_counts( $recipe_ids );

		foreach ( $global_counts as $row ) {
			$rid = absint( $row->recipe_id );
			if ( $this->global_threshold_reached( $rid, (int) $row->num_times ) ) {
				$completed[ $rid ] = true;
			}
		}

		return $completed;
	}

	/**
	 * Check per-user completion for remaining (non-globally-completed) recipe IDs.
	 *
	 * @param int[] $recipe_ids Array of all recipe IDs.
	 * @param int   $user_id   The user ID.
	 * @param array $completed Already-completed map from global check.
	 *
	 * @return array Updated completed map.
	 */
	public function batch_check_user_completion( array $recipe_ids, int $user_id, array $completed ): array {

		$remaining = array_values( array_diff( $recipe_ids, array_keys( $completed ) ) );

		if ( empty( $remaining ) ) {
			return $completed;
		}

		$user_counts = $this->log_store->batch_get_user_completion_counts( $user_id, $remaining );

		foreach ( $user_counts as $row ) {
			$rid = absint( $row->recipe_id );
			if ( $this->user_threshold_reached( $rid, (int) $row->num_times ) ) {
				$completed[ $rid ] = true;
			}
		}

		return $completed;
	}

	/**
	 * Check if the global (universal) threshold has been reached.
	 *
	 * @param int $recipe_id       The recipe ID.
	 * @param int $completed_times The number of completed times.
	 *
	 * @return bool
	 */
	protected function global_threshold_reached( int $recipe_id, int $completed_times ): bool {

		$threshold = new Universal_Run_Number_Threshold( $this->field_manager() );

		$threshold->set_recipe_id( $recipe_id );
		$threshold->set_completed_times( $completed_times );

		return $threshold->has_run_times_reached_limit();
	}

	/**
	 * Check if the per-user threshold has been reached.
	 *
	 * @param int $recipe_id       The recipe ID.
	 * @param int $completed_times The number of completed times.
	 *
	 * @return bool
	 */
	protected function user_threshold_reached( int $recipe_id, int $completed_times ): bool {

		$threshold = new User_Run_Number_Threshold( $this->field_manager() );

		$threshold->set_recipe_id( $recipe_id );
		$threshold->set_completed_times( $completed_times );

		return $threshold->has_run_times_reached_limit();
	}
}
