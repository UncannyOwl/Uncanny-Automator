<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Trigger_Definition_Bridge}.
 *
 * The only place in `src/app/` permitted to call `Automator()->get_triggers()`
 * and `Automator()->get_trigger()`. Consumers depend on the interface.
 *
 * @since 7.4.0
 */
final class Automator_Trigger_Definition_Bridge implements Trigger_Definition_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_all_trigger_definitions(): array {
		$triggers = \Automator()->get_triggers();

		if ( ! is_array( $triggers ) ) {
			return array();
		}

		return $triggers;
	}

	/**
	 * @inheritDoc
	 */
	public function get_trigger_definition( string $code ): ?array {
		$trigger = \Automator()->get_trigger( $code );

		if ( ! is_array( $trigger ) ) {
			return null;
		}

		return $trigger;
	}
}
