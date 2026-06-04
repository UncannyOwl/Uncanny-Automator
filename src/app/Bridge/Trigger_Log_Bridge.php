<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for trigger-log lookups.
 *
 * Wraps the `Automator()->get->trigger_log_id()`,
 * `->get->trigger_run_number()`, and
 * `->get->maybe_get_meta_id_from_trigger_log()` calls used by the recipe
 * runner pipeline.
 *
 * @since 7.4.0
 */
interface Trigger_Log_Bridge {

	/**
	 * Look up an existing trigger log row id for a (user, trigger, recipe[, recipe_log]) tuple.
	 *
	 * Wraps `Automator()->get->trigger_log_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id )`.
	 *
	 * @param int      $user_id        User ID.
	 * @param int      $trigger_id     Trigger post ID.
	 * @param int      $recipe_id      Recipe post ID.
	 * @param int|null $recipe_log_id  Optional recipe log row id.
	 * @return int|null Trigger log id, or null when none exists.
	 */
	public function get_trigger_log_id( int $user_id, int $trigger_id, int $recipe_id, $recipe_log_id ): ?int;

	/**
	 * Get the run number for a trigger log row.
	 *
	 * Wraps `Automator()->get->trigger_run_number( $trigger_id, $trigger_log_id, $user_id )`.
	 *
	 * @param int $trigger_id     Trigger post ID.
	 * @param int $trigger_log_id Trigger log row id.
	 * @param int $user_id        User ID.
	 * @return int Run number.
	 */
	public function get_trigger_run_number( int $trigger_id, int $trigger_log_id, int $user_id ): int;

	/**
	 * Resolve a trigger log meta row id, if one already exists.
	 *
	 * Wraps `Automator()->get->maybe_get_meta_id_from_trigger_log( $run_number, $trigger_id, $trigger_log_id, $meta_key, $user_id )`.
	 *
	 * @param int    $run_number     Run number.
	 * @param int    $trigger_id     Trigger post ID.
	 * @param int    $trigger_log_id Trigger log row id.
	 * @param string $meta_key       Meta key.
	 * @param int    $user_id        User ID.
	 * @return int|null Meta row id, or null when none exists.
	 */
	public function find_trigger_log_meta_id( int $run_number, int $trigger_id, int $trigger_log_id, string $meta_key, int $user_id ): ?int;
}
