<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Enums;

/**
 * Block Type Enum.
 *
 * Represents the type of block in a recipe.
 *
 * @since 7.0
 */
class Block_Type {

	/**
	 * Delay/Schedule block type.
	 *
	 * @var string
	 */
	const DELAY_SCHEDULE = 'delay_schedule';

	/**
	 * Filter/Conditional block type.
	 *
	 * @var string
	 */
	const FILTER = 'filter';

	/**
	 * Loop block type.
	 *
	 * @var string
	 */
	const LOOP = 'loop';

	/**
	 * Validate block type value.
	 *
	 * @param string $value Block type value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::get_all(), true );
	}

	/**
	 * Get all valid block type values.
	 *
	 * @return array<string> Array of valid block type values.
	 */
	public static function get_all(): array {
		return array(
			self::DELAY_SCHEDULE,
			self::FILTER,
			self::LOOP,
		);
	}
}
