<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Integration\Enums;

/**
 * Integration Item Types Enum.
 *
 * Discriminator literals for catalog rows under
 * `integration.items[item_type][item_code]`. Modifier types
 * (filter_condition, loop_filter) live on the sibling
 * `Block_Modifier_Types` enum.
 */
class Integration_Item_Types {

	/**
	 * Something that happens on the site and starts a recipe running.
	 *
	 * @var string
	 */
	const TRIGGER = 'trigger';

	/**
	 * Something a recipe does once its triggers are satisfied.
	 *
	 * @var string
	 */
	const ACTION = 'action';

	/**
	 * Every item type a catalog row may be keyed under.
	 *
	 * Declaration order is the order these run in a recipe, which is also the
	 * order the builder presents them.
	 *
	 * @return array<string> Item type literals in declaration order.
	 */
	public static function get_all(): array {
		return array(
			self::TRIGGER,
			self::ACTION,
		);
	}

	/**
	 * Whether a string names an item type this catalog recognises.
	 *
	 * Modifier literals return false here — they are validated against
	 * `Block_Modifier_Types` instead.
	 *
	 * @param string $item_type Candidate item type literal.
	 *
	 * @return bool True when the literal is a known item type.
	 */
	public static function is_valid( string $item_type ): bool {
		return in_array( $item_type, self::get_all(), true );
	}
}
