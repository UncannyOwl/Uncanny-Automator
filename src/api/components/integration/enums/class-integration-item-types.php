<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Enums;

/**
 * Integration Item Types Enum.
 *
 * Defines valid item types for integration queries.
 *
 * @since 7.0
 */
class Integration_Item_Types {

	/**
	 * Trigger type.
	 *
	 * @var string
	 */
	const TRIGGER = 'trigger';

	/**
	 * Action type.
	 *
	 * @var string
	 */
	const ACTION = 'action';

	/**
	 * Loop filter type.
	 *
	 * @var string
	 */
	const LOOP_FILTER = 'loop_filter';

	/**
	 * Filter condition type.
	 *
	 * @var string
	 */
	const FILTER_CONDITION = 'filter_condition';

	/**
	 * Closure type.
	 *
	 * @var string
	 */
	const CLOSURE = 'closure';

	/**
	 * Get all valid item types.
	 *
	 * @return array<string> Array of valid item types.
	 */
	public static function get_all(): array {
		return array(
			self::TRIGGER,
			self::ACTION,
			self::LOOP_FILTER,
			self::FILTER_CONDITION,
			self::CLOSURE,
		);
	}

	/**
	 * Check if item type is valid.
	 *
	 * @param string $item_type Item type to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $item_type ): bool {
		return in_array( $item_type, self::get_all(), true );
	}
}
