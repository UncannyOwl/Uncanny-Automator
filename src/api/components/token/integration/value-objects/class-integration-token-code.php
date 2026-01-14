<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Token\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Token Code Value Object.
 *
 * Represents the unique, uppercase string identifier for the integration token.
 * Examples: "USER_EMAIL", "USER_NAME", "USER_ID"
 *
 * This is for integration-level tokens (tokens provided by integrations).
 *
 * @since 7.0.0
 */
class Integration_Token_Code {

	/**
	 * The integration token code value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration token code value.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid code.
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
	 * Validate integration token code.
	 *
	 * @param string $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( trim( $value ) ) ) {
			throw new InvalidArgumentException( 'Integration token code cannot be empty' );
		}
	}
}
