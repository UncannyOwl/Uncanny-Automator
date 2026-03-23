<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Action Meta Code Value Object.
 *
 * Represents the meta code identifier for an action (e.g., 'EMAIL_TO', 'POST_ID').
 * Can be empty string for actions without meta fields.
 *
 * @since 7.0.0
 */
class Action_Meta_Code {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Action meta code value.
	 * @throws \InvalidArgumentException If invalid meta code.
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
	 * Check if meta code is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->value );
	}

	/**
	 * Check if meta codes are equal.
	 *
	 * @param Action_Meta_Code $other Other action meta code.
	 * @return bool
	 */
	public function equals( Action_Meta_Code $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate action meta code.
	 *
	 * Empty string is allowed (actions without meta fields).
	 * Non-empty values must follow code format.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		// Empty string is valid (actions without meta)
		if ( empty( $value ) ) {
			return;
		}

		// Non-empty meta must follow code format: alphanumeric, underscores, hyphens
		if ( ! preg_match( '/^[A-Z0-9_-]+$/i', $value ) ) {
			throw new \InvalidArgumentException(
				'Action meta code must contain only letters, numbers, underscores, and hyphens: ' . $value
			);
		}
	}
}
