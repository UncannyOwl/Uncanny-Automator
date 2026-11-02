<?php
namespace Uncanny_Automator\Services\Recipe\Process;

/**
 * Per-user run-limit comparator. Reads the limit from the recipe-settings SSoT
 * (automator_get_recipe_times_per_user) and compares it to a supplied completed
 * count. UNLIMITED (-1) never blocks.
 */
class User_Run_Number_Threshold {

	/** Sentinel returned when the recipe has no per-user run limit. */
	const UNLIMITED = -1;

	protected $recipe_id;
	protected $completed_times;

	/**
	 * Set the recipe ID to evaluate.
	 *
	 * @param int $recipe_id
	 * @return void
	 */
	public function set_recipe_id( int $recipe_id ) {
		$this->recipe_id = $recipe_id;
	}

	/**
	 * Get the recipe ID.
	 *
	 * @return mixed
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Set the completed-times count to compare against the limit.
	 *
	 * @param int $completed_times
	 * @return void
	 */
	public function set_completed_times( int $completed_times ) {
		$this->completed_times = $completed_times;
	}

	/**
	 * Get the completed-times count.
	 *
	 * @return mixed
	 */
	public function get_completed_times() {
		return $this->completed_times;
	}

	/**
	 * Resolve the per-user run limit from the recipe-settings SSoT.
	 *
	 * A read error (invalid recipe id / settings-service failure) coerces to
	 * UNLIMITED — fail-open, so an enforcement gate never wrongly blocks a
	 * legitimate run. Matches the legacy contract (empty/absent meta → -1).
	 *
	 * @return int Positive limit, or self::UNLIMITED.
	 */
	protected function get_run_limit(): int {
		$limit = automator_get_recipe_times_per_user( (int) $this->get_recipe_id() );
		return is_wp_error( $limit ) ? self::UNLIMITED : (int) $limit;
	}

	/**
	 * Check whether the configured run limit has been reached.
	 *
	 * @return bool True when the recipe's run limit has been reached.
	 */
	public function has_run_times_reached_limit(): bool {
		$run_limit = $this->get_run_limit();
		if ( self::UNLIMITED === $run_limit ) {
			return false;
		}
		return $this->get_completed_times() >= $run_limit;
	}
}
