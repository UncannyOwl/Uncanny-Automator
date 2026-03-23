<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

/**
 * Trigger Tokens Value Object.
 *
 * Represents the collection of available tokens for a trigger.
 * Manages token definitions and validation.
 *
 * @since 7.0.0
 */
class Trigger_Tokens {

	private array $tokens;

	/**
	 * Constructor.
	 *
	 * @param array $tokens Array of token definitions.
	 */
	public function __construct( array $tokens = array() ) {
		$this->validate( $tokens );
		$this->tokens = $tokens;
	}

	/**
	 * Get all tokens.
	 *
	 * @return array
	 */
	public function get_tokens(): array {
		return $this->tokens;
	}

	/**
	 * Check if tokens are defined.
	 *
	 * @return bool
	 */
	public function has_tokens(): bool {
		return ! empty( $this->tokens );
	}

	/**
	 * Get token by key.
	 *
	 * @param string $key Token key.
	 * @return array|null Token definition or null.
	 */
	public function get_token( string $key ): ?array {
		return $this->tokens[ $key ] ?? null;
	}

	/**
	 * Count tokens.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->tokens );
	}

	/**
	 * To array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return $this->tokens;
	}

	/**
	 * Create from token definitions array.
	 *
	 * @param array $token_definitions Token definitions.
	 * @return self
	 */
	public static function from_definitions( array $token_definitions ): self {
		return new self( $token_definitions );
	}

	/**
	 * Validate tokens array.
	 *
	 * @param array $tokens Tokens to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( array $tokens ): void {
		// Basic validation - tokens should be associative array
		foreach ( $tokens as $key => $value ) {
			if ( ! is_string( $key ) ) {
				throw new \InvalidArgumentException( 'Token keys must be strings' );
			}

			if ( ! is_array( $value ) && ! is_string( $value ) ) {
				throw new \InvalidArgumentException( 'Token values must be arrays or strings' );
			}
		}
	}
}
