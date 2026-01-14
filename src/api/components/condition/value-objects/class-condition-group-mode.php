<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Condition\Value_Objects;

/**
 * Condition Group Mode Value Object.
 *
 * Represents the logical evaluation mode for a condition group.
 * - "any" = OR logic (any condition must pass)
 * - "all" = AND logic (all conditions must pass)
 *
 * @since 7.0.0
 */
class Condition_Group_Mode {

	private string $value;

	private const VALID_MODES = array( 'any', 'all' );

	/**
	 * Constructor.
	 *
	 * @param string $value Mode value ("any" or "all").
	 * @throws \InvalidArgumentException If mode is invalid.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Create an "any" mode (OR logic).
	 *
	 * @return self Condition group mode set to "any".
	 */
	public static function any(): self {
		return new self( 'any' );
	}

	/**
	 * Create an "all" mode (AND logic).
	 *
	 * @return self Condition group mode set to "all".
	 */
	public static function all(): self {
		return new self( 'all' );
	}

	/**
	 * Get the mode value.
	 *
	 * @return string Mode value.
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if mode is "any" (OR logic).
	 *
	 * @return bool True if mode is "any".
	 */
	public function is_any(): bool {
		return 'any' === $this->value;
	}

	/**
	 * Check if mode is "all" (AND logic).
	 *
	 * @return bool True if mode is "all".
	 */
	public function is_all(): bool {
		return 'all' === $this->value;
	}

	/**
	 * Convert to string representation.
	 *
	 * @return string Mode value as string.
	 */
	public function __toString(): string {
		return $this->value;
	}

	/**
	 * Check equality with another Condition_Group_Mode.
	 *
	 * @param Condition_Group_Mode $other Other mode to compare.
	 * @return bool True if modes are equal.
	 */
	public function equals( Condition_Group_Mode $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate mode value.
	 *
	 * @param string $value Mode to validate.
	 * @throws \InvalidArgumentException If mode is invalid.
	 */
	private function validate( string $value ): void {
		if ( ! in_array( $value, self::VALID_MODES, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Invalid condition group mode: %s. Must be one of: %s',
					$value,
					implode( ', ', self::VALID_MODES )
				)
			);
		}
	}
}
