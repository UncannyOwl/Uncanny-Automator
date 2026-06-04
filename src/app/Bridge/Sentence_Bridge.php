<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for legacy action/trigger sentence lookups.
 *
 * Wraps `Automator()->get->action_sentence()` and `->trigger_sentence()`.
 *
 * @since 7.4.0
 */
interface Sentence_Bridge {

	/**
	 * Get the rendered sentence meta for an action post.
	 *
	 * Wraps `Automator()->get->action_sentence( $action_id )`.
	 *
	 * @param int $action_id Action post ID.
	 * @return array Sentence meta keyed by field code (empty array if none).
	 */
	public function get_action_sentence( int $action_id ): array;

	/**
	 * Get the rendered sentence value for a trigger post.
	 *
	 * Wraps `Automator()->get->trigger_sentence( $trigger_id, $type )`.
	 *
	 * @param int    $trigger_id Trigger post ID.
	 * @param string $type       Sentence type (e.g. `trigger_detail`, `sentence_human_readable`).
	 * @return mixed Sentence value (typically string), or empty string when missing.
	 */
	public function get_trigger_sentence( int $trigger_id, string $type );
}
