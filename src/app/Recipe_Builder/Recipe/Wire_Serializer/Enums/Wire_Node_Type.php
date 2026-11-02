<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums;

/**
 * Wire_Node_Type Enum.
 *
 * Values of the wire `type` discriminator (`Wire_Key::TYPE`) the serializers
 * stamp on every node of a ModelRecipe flow.
 */
final class Wire_Node_Type {

	/**
	 * A trigger or action node; `item_type` says which.
	 */
	const ITEM = 'item';

	/**
	 * A filter, delay_schedule or loop container node; `block_type` says which.
	 */
	const BLOCK = 'block';

	/**
	 * A filter condition or loop filter on a block; `block_modifier_type` says which.
	 */
	const BLOCK_MODIFIER = 'block_modifier';

	/**
	 * Every node type, in declaration order.
	 *
	 * @return list<string>
	 */
	public static function get_all(): array {
		return array(
			self::ITEM,
			self::BLOCK,
			self::BLOCK_MODIFIER,
		);
	}

	/**
	 * Whether `$type` is a known node type.
	 *
	 * @param string $type Candidate node type.
	 * @return bool True when the value is a node type.
	 */
	public static function is_valid( string $type ): bool {
		return in_array( $type, self::get_all(), true );
	}
}
