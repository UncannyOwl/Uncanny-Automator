<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Code Value Object.
 *
 * Represents the unique, uppercase string identifier for the integration.
 * Examples: "WOO", "LEARNDASH", "MAILCHIMP"
 *
 * @since 7.0.0
 */
class Integration_Code {

	/**
	 * The integration code value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration code value.
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
	 * Validate integration code.
	 *
	 * @param string $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( trim( $value ) ) ) {
			throw new InvalidArgumentException( 'Integration code cannot be empty' );
		}
	}
}
