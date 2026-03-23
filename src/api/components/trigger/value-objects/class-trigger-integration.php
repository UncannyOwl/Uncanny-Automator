<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

/**
 * Trigger Integration Value Object.
 *
 * Ensures integration codes are properly validated and safe from AI drift.
 * Only allows alphanumeric characters with underscores and dashes.
 *
 * @since 7.0.0
 */
class Trigger_Integration {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $integration Integration code (e.g., 'WP', 'WC', 'CONTACT_FORM_7').
	 * @throws \InvalidArgumentException If integration format is invalid.
	 */
	public function __construct( string $integration ) {
		$this->validate_and_set( $integration );
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
	 * @param string $integration Integration code to validate.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private function validate_and_set( string $integration ): void {
		// Trim whitespace
		$integration = trim( $integration );

		// Check if empty
		if ( empty( $integration ) ) {
			throw new \InvalidArgumentException( 'Integration code cannot be empty' );
		}

		// Check length (reasonable limits)
		if ( strlen( $integration ) > 50 ) {
			throw new \InvalidArgumentException( 'Integration code cannot exceed 50 characters' );
		}

		// Check format: alphanumeric with underscores and dashes only
		if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $integration ) ) {
			throw new \InvalidArgumentException(
				'Integration code must contain only alphanumeric characters, underscores, and dashes. Got: ' . $integration
			);
		}

		$this->value = trim( $integration );
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
