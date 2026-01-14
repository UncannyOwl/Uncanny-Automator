<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Token\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Token Name Value Object.
 *
 * Represents the human-readable display name for the integration token.
 * Examples: "User Email", "User Name", "User ID"
 *
 * This is for integration-level tokens (tokens provided by integrations).
 *
 * @since 7.0.0
 */
class Integration_Token_Name {

	/**
	 * The integration token name value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration token name value.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid name.
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
	 * Validate integration token name.
	 *
	 * @param string $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( trim( $value ) ) ) {
			throw new InvalidArgumentException( 'Integration token name cannot be empty' );
		}
	}
}
