<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Action Code Value Object.
 *
 * Represents the specific action identifier (e.g., 'SENDEMAIL', 'ENRLCOURSE-A').
 * Validates format: uppercase letters, numbers, underscores, and hyphens.
 *
 * @since 7.0.0
 */
class Action_Code {

	/**
	 * Maximum allowed length for action codes.
	 *
	 * Set to 100 characters to accommodate composite action codes like
	 * 'WOOCOMMERCE_ORDER_STATUS_CHANGED_TO_COMPLETED' while preventing
	 * excessively long codes that could cause database or display issues.
	 *
	 * @var int
	 */
	const MAX_LENGTH = 100;

	/**
	 * Valid action code pattern (uppercase letters, numbers, underscores, hyphens).
	 *
	 * Enforces the Automator convention for action codes:
	 * - Uppercase letters (A-Z) for readability
	 * - Numbers (0-9) for versioning or IDs
	 * - Underscores (_) as word separators
	 * - Hyphens (-) for action variants (e.g., ENRLCOURSE-A)
	 *
	 * Examples: 'SENDEMAIL', 'WP_CREATE_POST', 'ENRLCOURSE-A'
	 *
	 * @var string
	 */
	const PATTERN = '/^[A-Z0-9_-]+$/';

	/**
	 * The validated action code value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Action code value.
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
	 * Check equality with another Action_Code.
	 *
	 * @param Action_Code $other Other value object to compare.
	 * @return bool
	 */
	public function equals( Action_Code $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate action code format and length.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {

		if ( empty( trim( $value ) ) ) {
			throw new \InvalidArgumentException( 'Action code cannot be empty' );
		}

		if ( strlen( $value ) > self::MAX_LENGTH ) {
			throw new \InvalidArgumentException(
				sprintf( 'Action code exceeds maximum length of %d characters', self::MAX_LENGTH )
			);
		}

		if ( ! preg_match( self::PATTERN, $value ) ) {
			throw new \InvalidArgumentException(
				'Action code must contain only uppercase letters, numbers, underscores, and hyphens'
			);
		}
	}
}
