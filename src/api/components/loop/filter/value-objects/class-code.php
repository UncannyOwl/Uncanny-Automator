<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects;

/**
 * Code Value Object.
 *
 * Represents the specific filter identifier (e.g., 'USER_ROLE', 'POST_STATUS').
 * Validates format: uppercase letters, numbers, underscores, and hyphens.
 *
 * @since 7.0.0
 */
class Code {

	/**
	 * Maximum allowed length for filter codes.
	 *
	 * @var int
	 */
	const MAX_LENGTH = 100;

	/**
	 * Valid filter code pattern (uppercase letters, numbers, underscores, hyphens).
	 *
	 * @var string
	 */
	const PATTERN = '/^[A-Z0-9_-]+$/';

	/**
	 * The validated filter code value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Filter code value (must be uppercase).
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
	 * String representation.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->value;
	}

	/**
	 * Check equality with another Code.
	 *
	 * @param Code $other Other value object to compare.
	 * @return bool
	 */
	public function equals( Code $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate filter code format and length.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( trim( $value ) ) ) {
			throw new \InvalidArgumentException( 'Code cannot be empty' );
		}

		if ( strlen( $value ) > self::MAX_LENGTH ) {
			throw new \InvalidArgumentException(
				sprintf( 'Code exceeds maximum length of %d characters', self::MAX_LENGTH )
			);
		}

		if ( ! preg_match( self::PATTERN, $value ) ) {
			throw new \InvalidArgumentException(
				'Code must contain only uppercase letters, numbers, underscores, and hyphens'
			);
		}
	}
}
