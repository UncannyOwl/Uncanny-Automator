<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Action_Definition_Bridge}.
 *
 * The only place in `src/app/` permitted to call `Automator()->get_actions()`
 * and `Automator()->get_action()`. Consumers depend on the interface.
 *
 * @since 7.4.0
 */
final class Automator_Action_Definition_Bridge implements Action_Definition_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_all_action_definitions(): array {
		$actions = \Automator()->get_actions();

		if ( ! is_array( $actions ) ) {
			return array();
		}

		return $actions;
	}

	/**
	 * @inheritDoc
	 */
	public function get_action_definition( string $code ): ?array {
		$action = \Automator()->get_action( $code );

		if ( ! is_array( $action ) ) {
			return null;
		}

		return $action;
	}
}
