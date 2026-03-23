<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Application\Value_Objects;

/**
 * URL Value Object.
 *
 * Represents a valid URL string.
 * Tokens ({{...}}) bypass validation as they're resolved at runtime.
 *
 * @since 7.0.0
 */
class Url {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value URL string to validate and store.
	 * @throws \InvalidArgumentException If invalid URL.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get URL value.
	 *
	 * @return string The URL.
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Convert to string.
	 *
	 * @return string The URL value.
	 */
	public function __toString(): string {
		return $this->value;
	}

	/**
	 * Check if URL contains tokens.
	 *
	 * @param string $value Value to check.
	 * @return bool True if contains tokens.
	 */
	private function contains_tokens( string $value ): bool {
		return false !== strpos( $value, '{{' ) && false !== strpos( $value, '}}' );
	}

	/**
	 * Validate URL.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid URL.
	 */
	private function validate( string $value ): void {

		$value = trim( $value );

		if ( empty( $value ) ) {
			throw new \InvalidArgumentException( esc_html_x( 'URL cannot be empty', 'URL validation error', 'uncanny-automator' ) );
		}

		// Skip validation if URL contains tokens - they'll be resolved at runtime.
		if ( $this->contains_tokens( $value ) ) {
			return;
		}

		// Validate URL format.
		if ( false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %s URL value */
					esc_html_x( 'Invalid URL format: %s', 'URL validation error', 'uncanny-automator' ),
					esc_html( $value )
				)
			);
		}
	}
}
