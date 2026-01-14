<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Condition\Value_Objects;

/**
 * Individual Condition ID Value Object.
 *
 * Represents a unique identifier for an individual condition within a condition group.
 * Uses short random IDs similar to legacy format (e.g., "mfvr11omqoicb5vcnea").
 *
 * @since 7.0.0
 */
class Condition_Id {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Condition ID value.
	 * @throws \InvalidArgumentException If ID is invalid.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Generate a new random condition ID.
	 *
	 * @return self New condition ID.
	 */
	public static function generate(): self {
		$id = bin2hex( random_bytes( 10 ) );
		return new self( $id );
	}

	/**
	 * Get the condition ID value.
	 *
	 * @return string Condition ID.
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Convert to string representation.
	 *
	 * @return string Condition ID as string.
	 */
	public function __toString(): string {
		return $this->value;
	}

	/**
	 * Check equality with another Condition_Id.
	 *
	 * @param Condition_Id $other Other condition ID to compare.
	 * @return bool True if IDs are equal.
	 */
	public function equals( Condition_Id $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate condition ID.
	 *
	 * @param string $value ID to validate.
	 * @throws \InvalidArgumentException If ID is invalid.
	 */
	private function validate( string $value ): void {
		if ( empty( $value ) ) {
			throw new \InvalidArgumentException( 'Condition ID cannot be empty' );
		}

		if ( strlen( $value ) < 10 || strlen( $value ) > 50 ) {
			throw new \InvalidArgumentException( 'Condition ID must be between 10 and 50 characters' );
		}

		if ( ! ctype_alnum( $value ) ) {
			throw new \InvalidArgumentException( 'Condition ID must contain only alphanumeric characters' );
		}
	}
}
