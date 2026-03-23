<?php
/**
 * Plan Levels
 *
 * Defines valid plan level types and provides utility methods.
 * Uses Plan_Levels interface as the single source of truth for constants.
 *
 * @package Uncanny_Automator\Api\Services\Plan
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Plan;

use Uncanny_Automator\Api\Components\Plan\Domain\Plan_Levels;

/**
 * Plan level constants and utilities.
 *
 * @since 7.0.0
 */
class Plan_Levels_Helper {

	/**
	 * Get all valid plan levels.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		return array(
			Plan_Levels::LITE,
			Plan_Levels::PRO_BASIC,
			Plan_Levels::PRO_PLUS,
			Plan_Levels::PRO_ELITE,
		);
	}

	/**
	 * Check if plan level is valid.
	 *
	 * @param string $level Plan level to validate
	 *
	 * @return bool
	 */
	public static function is_valid( string $level ): bool {
		return in_array( $level, self::get_all(), true );
	}
}
