<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the items whose requirements are not met on this site.
 *
 * Wraps `Automator()->get_unavailable_item()` and `Automator()->get_unavailable_items()`, by item
 * type, so one reader answers for every type without a method per type.
 *
 * @since 8.1.0
 */
interface Item_Availability_Bridge {

	/**
	 * One item whose requirements are not met on this site.
	 *
	 * Wraps `Automator()->get_unavailable_item()`.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type, an `Integration_Item_Types` or `Block_Modifier_Types` value.
	 * @param string $integration_code The integration code, any case.
	 * @param string $code             The item code, any case.
	 *
	 * @return array|null The definition it would register, with `requirements`, or null when it is not stored as unavailable.
	 */
	public function get_unavailable_item( string $type, string $integration_code, string $code ): ?array;

	/**
	 * An integration's items of one type whose requirements are not met on this site.
	 *
	 * Wraps `Automator()->get_unavailable_items()`.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type, an `Integration_Item_Types` or `Block_Modifier_Types` value.
	 * @param string $integration_code The integration code, any case.
	 *
	 * @return array Map of item code => definition it would register, with `requirements`.
	 */
	public function get_unavailable_items( string $type, string $integration_code ): array;
}
