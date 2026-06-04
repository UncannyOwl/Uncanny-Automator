<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Action_Tokens_Bridge}.
 *
 * Calls through to the legacy hydrator via `Automator()->action_tokens()->hydrator()`.
 *
 * @since 7.4.0
 */
final class Automator_Action_Tokens_Bridge implements Action_Tokens_Bridge {

	/**
	 * @inheritDoc
	 */
	public function hydrate_action_tokens( int $user_id, int $action_id, int $action_log_id, array $process_args, $value, bool $encode = true ) {

		$hydrator = \Automator()->action_tokens()->hydrator();

		$hydrator->set_user_id( $user_id );
		$hydrator->set_action_id( $action_id );
		$hydrator->set_action_log_id( $action_log_id );
		$hydrator->set_process_args( $process_args );

		return $hydrator->hydrate( $value, $encode );
	}
}
