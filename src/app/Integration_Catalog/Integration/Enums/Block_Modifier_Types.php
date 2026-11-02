<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Integration\Enums;

/**
 * Block Modifier Types Enum.
 *
 * Discriminator literals for catalog rows that live under
 * `integration.block_modifiers[block_modifier_type][modifier_code]` —
 * sibling to `Integration_Item_Types` which discriminates rows under
 * `integration.items[item_type][item_code]`.
 */
class Block_Modifier_Types {

	/**
	 * A test a recipe applies before letting its actions run.
	 *
	 * @var string
	 */
	const FILTER_CONDITION = 'filter_condition';

	/**
	 * A test that narrows which items a loop iterates over.
	 *
	 * @var string
	 */
	const LOOP_FILTER = 'loop_filter';

	/**
	 * Every modifier type a catalog row may be keyed under, in declaration order.
	 *
	 * @return array<string> Modifier type literals in declaration order.
	 */
	public static function get_all(): array {
		return array(
			self::FILTER_CONDITION,
			self::LOOP_FILTER,
		);
	}

	/**
	 * Whether a string names a modifier type this catalog recognises.
	 *
	 * Item literals return false here — they are validated against
	 * `Integration_Item_Types` instead.
	 *
	 * @param string $type Candidate modifier type literal.
	 *
	 * @return bool True when the literal is a known modifier type.
	 */
	public static function is_valid( string $type ): bool {
		return in_array( $type, self::get_all(), true );
	}
}
