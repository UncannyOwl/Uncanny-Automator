<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Trigger_Log_Bridge}.
 *
 * @since 7.4.0
 */
final class Automator_Trigger_Log_Bridge implements Trigger_Log_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_trigger_log_id( int $user_id, int $trigger_id, int $recipe_id, $recipe_log_id ): ?int {
		$result = \Automator()->get->trigger_log_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );

		return is_numeric( $result ) ? (int) $result : null;
	}

	/**
	 * @inheritDoc
	 */
	public function get_trigger_run_number( int $trigger_id, int $trigger_log_id, int $user_id ): int {
		return (int) \Automator()->get->trigger_run_number( $trigger_id, $trigger_log_id, $user_id );
	}

	/**
	 * @inheritDoc
	 */
	public function find_trigger_log_meta_id( int $run_number, int $trigger_id, int $trigger_log_id, string $meta_key, int $user_id ): ?int {
		$result = \Automator()->get->maybe_get_meta_id_from_trigger_log( $run_number, $trigger_id, $trigger_log_id, $meta_key, $user_id );

		return is_numeric( $result ) ? (int) $result : null;
	}
}
