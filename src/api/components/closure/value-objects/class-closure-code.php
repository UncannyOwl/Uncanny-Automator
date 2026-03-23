<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Closure\Value_Objects;

/**
 * Closure Code Value Object.
 *
 * Immutable value object that validates and encapsulates closure code.
 * Ensures closure codes are safe and properly formatted.
 *
 * @since 7.0.0
 */
class Closure_Code {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Closure code value.
	 * @throws \InvalidArgumentException If invalid code.
	 */
	public function __construct( string $value ) {
		$this->validate_and_set( $value );
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
	 * Check if codes are equal.
	 *
	 * @param Closure_Code $other Other closure code.
	 * @return bool
	 */
	public function equals( Closure_Code $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * To string.
	 *
	 * @return string Closure code.
	 */
	public function __toString(): string {
		return $this->value;
	}

	/**
	 * Validate and set closure code.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate_and_set( string $value ): void {
		// Trim and uppercase
		$value = strtoupper( trim( $value ) );

		// Check if empty
		if ( empty( $value ) ) {
			throw new \InvalidArgumentException( 'Closure code cannot be empty' );
		}

		// Check length
		if ( strlen( $value ) > 50 ) {
			throw new \InvalidArgumentException( 'Closure code cannot exceed 50 characters' );
		}

		// Check format: alphanumeric with underscores and hyphens.
		if ( ! preg_match( '/^[A-Z0-9_-]+$/', $value ) ) {
			throw new \InvalidArgumentException(
				'Closure code must contain only uppercase letters, numbers, underscores, and hyphens. Got: ' . $value
			);
		}

		$this->value = $value;
	}

	/**
	 * Create from string (factory method).
	 *
	 * @param string $code Closure code.
	 * @return self New instance.
	 */
	public static function from_string( string $code ): self {
		return new self( $code );
	}
}
