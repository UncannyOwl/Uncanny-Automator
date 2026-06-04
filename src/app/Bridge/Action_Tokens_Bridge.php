<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy action-tokens hydrator.
 *
 * Exposes one intent-named method (`hydrate_action_tokens`) that
 * encapsulates the full configure-then-hydrate sequence. The legacy
 * hydrator object (`Automator()->action_tokens()->hydrator()`) never
 * leaks through this interface.
 *
 * @since 7.4.0
 */
interface Action_Tokens_Bridge {

	/**
	 * Hydrate action tokens for a completed action.
	 *
	 * Configures the legacy hydrator with the action context and stores
	 * the token value. Replaces the leaked `get_hydrator()` pattern.
	 *
	 * @param int   $user_id       The user ID.
	 * @param int   $action_id     The action post ID.
	 * @param int   $action_log_id The action log ID.
	 * @param array $process_args  The process arguments.
	 * @param mixed $value         The token value to hydrate.
	 * @param bool  $encode        Whether to JSON-encode the value before storing.
	 *
	 * @return bool|int Result of the store operation, or false on empty value.
	 */
	public function hydrate_action_tokens( int $user_id, int $action_id, int $action_log_id, array $process_args, $value, bool $encode = true );
}
