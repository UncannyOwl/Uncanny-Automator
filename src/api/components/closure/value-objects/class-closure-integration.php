<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Closure\Value_Objects;

/**
 * Closure Integration Value Object.
 *
 * Immutable value object that validates and encapsulates integration code.
 * Ensures integration codes are safe and properly formatted.
 *
 * @since 7.0.0
 */
class Closure_Integration {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration code (e.g., 'WP', 'WC').
	 * @throws \InvalidArgumentException If integration format is invalid.
	 */
	public function __construct( string $value ) {
		$this->validate_and_set( $value );
	}

	/**
	 * Get integration value.
	 *
	 * @return string Validated integration code.
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if integrations are equal.
	 *
	 * @param Closure_Integration $other Other integration.
	 * @return bool
	 */
	public function equals( Closure_Integration $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * To string.
	 *
	 * @return string Integration code.
	 */
	public function __toString(): string {
		return $this->value;
	}

	/**
	 * Validate and set integration value.
	 *
	 * @param string $value Integration code to validate.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private function validate_and_set( string $value ): void {
		// Trim whitespace
		$value = trim( $value );

		// Check if empty
		if ( empty( $value ) ) {
			throw new \InvalidArgumentException( 'Integration code cannot be empty' );
		}

		// Check length
		if ( strlen( $value ) > 50 ) {
			throw new \InvalidArgumentException( 'Integration code cannot exceed 50 characters' );
		}

		// Check format: alphanumeric with underscores and hyphens.
		if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
			throw new \InvalidArgumentException(
				'Integration code must contain only alphanumeric characters, underscores, and hyphens. Got: ' . $value
			);
		}

		$this->value = trim( $value );
	}

	/**
	 * Create from string (factory method).
	 *
	 * @param string $integration Integration code.
	 * @return self New instance.
	 */
	public static function from_string( string $integration ): self {
		return new self( $integration );
	}
}
