<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Item_Availability_Bridge}.
 *
 * The only place in `src/app/` permitted to call `Automator()->get_unavailable_item()` and
 * `Automator()->get_unavailable_items()`. Consumers depend on the interface.
 *
 * @since 8.1.0
 */
final class Automator_Item_Availability_Bridge implements Item_Availability_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_unavailable_item( string $type, string $integration_code, string $code ): ?array {
		$definition = \Automator()->get_unavailable_item( $type, $integration_code, $code );

		if ( ! is_array( $definition ) ) {
			return null;
		}

		return $definition;
	}

	/**
	 * @inheritDoc
	 */
	public function get_unavailable_items( string $type, string $integration_code ): array {
		return \Automator()->get_unavailable_items( $type, $integration_code );
	}
}
