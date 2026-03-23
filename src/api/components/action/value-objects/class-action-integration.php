<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Action Integration Value Object.
 *
 * Represents the integration code for an action (e.g., 'WP', 'WC', 'LEARNDASH').
 *
 * @since 7.0.0
 */
class Action_Integration {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Integration code.
	 * @throws \InvalidArgumentException If integration code is invalid.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get the integration code value.
	 *
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if integration is WordPress native.
	 *
	 * @return bool
	 */
	public function is_wordpress_native(): bool {
		return 'WP' === $this->value;
	}

	/**
	 * Validate integration code.
	 *
	 * @param string $value Integration code to validate.
	 * @throws \InvalidArgumentException If integration code is invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( trim( $value ) ) ) {
			throw new \InvalidArgumentException( 'Action integration code cannot be empty' );
		}

		if ( strlen( $value ) > 50 ) {
			throw new \InvalidArgumentException( 'Action integration code cannot exceed 50 characters' );
		}

		// Integration codes should be uppercase alphanumeric with underscores and hyphens.
		if ( ! preg_match( '/^[A-Z0-9_-]+$/', $value ) ) {
			throw new \InvalidArgumentException( 'Action integration code must contain only uppercase letters, numbers, underscores, and hyphens' );
		}
	}
}
