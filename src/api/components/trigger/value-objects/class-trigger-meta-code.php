<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

/**
 * Trigger Meta Code Value Object.
 *
 * Represents the meta code identifier for a trigger (e.g., 'POST_TYPE', 'USER_ID').
 * Can be empty string for triggers without meta fields.
 *
 * @since 7.0.0
 */
class Trigger_Meta_Code {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Trigger meta value.
	 * @throws \InvalidArgumentException If invalid meta.
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
	 * Check if meta is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->value );
	}

	/**
	 * Check if meta codes are equal.
	 *
	 * @param Trigger_Meta_Code $other Other trigger meta code.
	 * @return bool
	 */
	public function equals( Trigger_Meta_Code $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate trigger meta code.
	 *
	 * Empty string is allowed (triggers without meta fields).
	 * Non-empty values must follow the same format as trigger codes.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		// Empty string is valid (triggers without meta)
		if ( empty( $value ) ) {
			return;
		}

		// Non-empty meta must follow code format: alphanumeric, underscores, hyphens
		if ( ! preg_match( '/^[A-Z0-9_-]+$/i', $value ) ) {
			throw new \InvalidArgumentException(
				'Trigger meta must contain only letters, numbers, underscores, and hyphens: ' . $value
			);
		}
	}
}
