<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Sentence_Bridge}.
 *
 * @since 7.4.0
 */
final class Automator_Sentence_Bridge implements Sentence_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_action_sentence( int $action_id ): array {
		$result = \Automator()->get->action_sentence( $action_id );

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_trigger_sentence( int $trigger_id, string $type ) {
		return \Automator()->get->trigger_sentence( $trigger_id, $type );
	}
}
