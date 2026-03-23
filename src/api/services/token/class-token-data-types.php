<?php
/**
 * Token Data Types
 *
 * Defines valid token data type values and provides utility methods.
 * Uses Token_Data_Types interface as the single source of truth for constants.
 *
 * @package Uncanny_Automator\Api\Services\Token
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Token;

use Uncanny_Automator\Api\Components\Token\Domain\Token_Data_Types;

/**
 * Token data type constants and utilities.
 *
 * @since 7.0.0
 */
class Token_Data_Types_Helper {

	/**
	 * Get all valid token data types.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		return array(
			Token_Data_Types::TEXT,
			Token_Data_Types::EMAIL,
			Token_Data_Types::URL,
			Token_Data_Types::INTEGER,
			Token_Data_Types::FLOAT,
			Token_Data_Types::DATE,
			Token_Data_Types::TIME,
			Token_Data_Types::DATETIME,
			Token_Data_Types::BOOLEAN,
			Token_Data_Types::ARRAY,
		);
	}

	/**
	 * Check if data type is valid.
	 *
	 * @param string $data_type Data type to validate
	 *
	 * @return bool
	 */
	public static function is_valid( string $data_type ): bool {
		return in_array( $data_type, self::get_all(), true );
	}
}
