<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Condition\Value_Objects;

/**
 * Condition Group ID Value Object.
 *
 * Represents a unique identifier for a condition group.
 * Uses short random IDs similar to legacy format (e.g., "mfvr0ydh0g0en7rk35h").
 *
 * @since 7.0.0
 */
class Condition_Group_Id {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Condition group ID value.
	 * @throws \InvalidArgumentException If ID is invalid.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Generate a new random condition group ID.
	 *
	 * @return self New condition group ID.
	 */
	public static function generate(): self {
		$id = bin2hex( random_bytes( 10 ) );
		return new self( $id );
	}

	/**
	 * Get the condition group ID value.
	 *
	 * @return string Condition group ID.
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Convert to string representation.
	 *
	 * @return string Condition group ID as string.
	 */
	public function __toString(): string {
		return $this->value;
	}

	/**
	 * Check equality with another Condition_Group_Id.
	 *
	 * @param Condition_Group_Id $other Other condition group ID to compare.
	 * @return bool True if IDs are equal.
	 */
	public function equals( Condition_Group_Id $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate condition group ID.
	 *
	 * @param string $value ID to validate.
	 * @throws \InvalidArgumentException If ID is invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( $value ) ) {
			throw new \InvalidArgumentException( 'Condition group ID cannot be empty' );
		}

		if ( strlen( $value ) < 10 || strlen( $value ) > 50 ) {
			throw new \InvalidArgumentException( 'Condition group ID must be between 10 and 50 characters' );
		}

		if ( ! ctype_alnum( $value ) ) {
			throw new \InvalidArgumentException( 'Condition group ID must contain only alphanumeric characters' );
		}
	}
}
