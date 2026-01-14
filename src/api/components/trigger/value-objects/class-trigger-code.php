<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

/**
 * Trigger Code Value Object.
 *
 * Represents the unique identifier for a trigger type (e.g., 'WP_USER_LOGIN').
 * Must be non-empty string with specific format validation.
 *
 * @since 7.0.0
 */
class Trigger_Code {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Trigger code value.
	 * @throws \InvalidArgumentException If invalid code.
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
	 * Check if codes are equal.
	 *
	 * @param Trigger_Code $other Other trigger code.
	 * @return bool
	 */
	public function equals( Trigger_Code $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate trigger code.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( trim( $value ) ) ) {
			throw new \InvalidArgumentException( 'Trigger code cannot be empty' );
		}

		// Basic format validation - alphanumeric, underscores, hyphens
		if ( ! preg_match( '/^[A-Z0-9_-]+$/i', $value ) ) {
			throw new \InvalidArgumentException(
				'Trigger code must contain only letters, numbers, underscores, and hyphens: ' . $value
			);
		}
	}
}
