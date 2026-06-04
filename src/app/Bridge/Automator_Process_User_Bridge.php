<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Process_User_Bridge}.
 *
 * @since 7.4.0
 */
final class Automator_Process_User_Bridge implements Process_User_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_trigger_meta( int $user_id, int $trigger_id, string $meta_key, int $trigger_log_id ) {
		return \Automator()->process->user->get_trigger_meta( $user_id, $trigger_id, $meta_key, $trigger_log_id );
	}

	/**
	 * @inheritDoc
	 */
	public function update_trigger_meta( int $user_id, int $trigger_id, string $meta_key, $meta_value, int $trigger_log_id ): bool {
		return (bool) \Automator()->process->user->update_trigger_meta( $user_id, $trigger_id, $meta_key, $meta_value, $trigger_log_id );
	}
}
