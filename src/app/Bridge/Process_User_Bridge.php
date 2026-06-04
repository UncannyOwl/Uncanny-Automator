<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy `Automator()->process->user` facade.
 *
 * Wraps the trigger-meta read/write methods used by the recipe runner.
 *
 * @since 7.4.0
 */
interface Process_User_Bridge {

	/**
	 * Read a trigger meta value.
	 *
	 * Wraps `Automator()->process->user->get_trigger_meta( $user_id, $trigger_id, $meta_key, $trigger_log_id )`.
	 *
	 * @param int    $user_id        User ID.
	 * @param int    $trigger_id     Trigger post ID.
	 * @param string $meta_key       Meta key.
	 * @param int    $trigger_log_id Trigger log row id.
	 * @return mixed Meta value, or null/false when missing.
	 */
	public function get_trigger_meta( int $user_id, int $trigger_id, string $meta_key, int $trigger_log_id );

	/**
	 * Write a trigger meta value.
	 *
	 * Wraps `Automator()->process->user->update_trigger_meta( $user_id, $trigger_id, $meta_key, $meta_value, $trigger_log_id )`.
	 *
	 * @param int    $user_id        User ID.
	 * @param int    $trigger_id     Trigger post ID.
	 * @param string $meta_key       Meta key.
	 * @param mixed  $meta_value     Meta value.
	 * @param int    $trigger_log_id Trigger log row id.
	 * @return bool True on success, false on failure.
	 */
	public function update_trigger_meta( int $user_id, int $trigger_id, string $meta_key, $meta_value, int $trigger_log_id ): bool;
}
