<?php
namespace Uncanny_Automator\Services\Recipe\Process;

/**
 * Recipe-wide (all-users) run-limit comparator. Same comparison as the parent,
 * but the limit is the recipe's total-times setting.
 */
class Universal_Run_Number_Threshold extends User_Run_Number_Threshold {

	/**
	 * Read the recipe-wide total-times limit from the SSoT. A read error
	 * coerces to UNLIMITED (fail-open), matching the parent's contract.
	 *
	 * @return int Positive limit, or self::UNLIMITED.
	 */
	protected function get_run_limit(): int {
		$limit = automator_get_recipe_total_times( (int) $this->get_recipe_id() );
		return is_wp_error( $limit ) ? self::UNLIMITED : (int) $limit;
	}
}
