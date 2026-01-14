<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Async Custom Value Object.
 *
 * Represents a custom delay/schedule value that can be a token {{}} or strtotime-compatible string.
 *
 * @since 7.0.0
 */
class Async_Custom {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Custom async value.
	 * @throws \InvalidArgumentException If invalid value.
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
	 * Check if value is a token.
	 *
	 * @return bool
	 */
	public function is_token(): bool {
		return preg_match( '/^{{.*}}$/', $this->value ) === 1;
	}

	/**
	 * Check if value is strtotime-compatible.
	 *
	 * @return bool
	 */
	public function is_strtotime_compatible(): bool {
		if ( $this->is_token() ) {
			return false;
		}

		return false !== strtotime( $this->value );
	}

	/**
	 * Validate custom value.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( $value ) ) {
			throw new \InvalidArgumentException(
				'Custom async value cannot be empty'
			);
		}

		// Allow tokens (anything between {{ and }})
		if ( $this->has_token_format( $value ) ) {
			return;
		}

		// Otherwise, must be strtotime-compatible
		if ( false === strtotime( $value ) ) {
			throw new \InvalidArgumentException(
				'Custom async value must be a token {{...}} or strtotime-compatible string, got: ' . $value
			);
		}
	}

	/**
	 * Check if value has token format.
	 *
	 * @param string $value Value to check.
	 * @return bool
	 */
	private function has_token_format( string $value ): bool {
		return preg_match( '/^{{[^{}]*[^[:space:]][^{}]*}}$/', $value ) === 1;
	}
}
