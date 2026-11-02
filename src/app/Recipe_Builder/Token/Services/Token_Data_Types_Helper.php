<?php
/**
 * Token Data Types
 *
 * Defines valid token data type values and provides utility methods.
 * Uses Token_Data_Types interface as the single source of truth for constants.
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Token\Services
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Recipe_Builder\Token\Services;

use Uncanny_Automator\App\Recipe_Builder\Token\Domain\Token_Data_Types;

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

	/**
	 * Normalize a raw data_type spelling to the canonical wire vocabulary.
	 *
	 * Folds case/whitespace, maps every known legacy alias (incl. json→array),
	 * passes canonical values through, and degrades anything unrecognized to text.
	 * Superset of the frontend normalizeDataType shim so the wire always arrives
	 * canonical. is_valid() stays strict — tolerance lives here, before it.
	 *
	 * @param string $raw Raw data_type as emitted by an integration/token definition.
	 *
	 * @return string One of get_all().
	 */
	public static function normalize( string $raw ): string {
		$value = strtolower( trim( $raw ) );

		if ( '' === $value ) {
			return Token_Data_Types::TEXT;
		}

		$aliases = array(
			'int'      => Token_Data_Types::INTEGER,
			'bool'     => Token_Data_Types::BOOLEAN,
			'loopable' => Token_Data_Types::ARRAY,
			'json'     => Token_Data_Types::ARRAY,
		);

		$value = $aliases[ $value ] ?? $value;

		return self::is_valid( $value ) ? $value : Token_Data_Types::TEXT;
	}
}
