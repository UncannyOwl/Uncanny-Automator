<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Token\Integration\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\Api\Services\Token\Token_Data_Types_Helper;

/**
 * Integration Token Data Type Value Object.
 *
 * Represents the data type of the integration token.
 * Valid values: "text", "email", "url", "integer", "float", "date", "time", "datetime", "boolean", "array"
 *
 * This is for integration-level tokens (tokens provided by integrations).
 *
 * @since 7.0.0
 */
class Integration_Token_Data_Type {

	/**
	 * The integration token data type value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration token data type value.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data type.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Validate integration token data type.
	 *
	 * @param string $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data type.
	 */
	private function validate( string $value ): void {
		if ( ! Token_Data_Types_Helper::is_valid( $value ) ) {
			throw new InvalidArgumentException(
				'Integration token data type must be one of: ' . implode( ', ', Token_Data_Types_Helper::get_all() ) . ', got: ' . $value
			);
		}
	}
}
