<?php

namespace Uncanny_Automator\Api\Components\Block;

/**
 * Block Colors
 *
 * Defines valid primary color values for blocks.
 *
 * @package Uncanny_Automator\Api\Components\Block
 * @since 7.0.0
 */
class Block_Colors {

	/**
	 * Get all valid primary colors.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		return array(
			'red',
			'pink',
			'purple',
			'deep-purple',
			'indigo',
			'blue',
			'light-blue',
			'cyan',
			'teal',
			'green',
			'light-green',
			'lime',
			'yellow',
			'amber',
			'orange',
			'deep-orange',
		);
	}

	/**
	 * Check if color is valid.
	 *
	 * @param string $color Color to validate
	 *
	 * @return bool
	 */
	public static function is_valid( string $color ): bool {
		return in_array( $color, self::get_all(), true );
	}
}
