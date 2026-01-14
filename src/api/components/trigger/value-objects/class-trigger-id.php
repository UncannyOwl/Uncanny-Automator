<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

/**
 * Trigger ID Value Object.
 *
 * Represents a trigger identifier - must be positive integer or null.
 * Follows the same pattern as Recipe_Id.
 *
 * @since 7.0.0
 */
class Trigger_Id {

	private $value;

	/**
	 * Constructor.
	 *
	 * @param mixed $value Trigger ID value.
	 * @throws \InvalidArgumentException If invalid ID.
	 */
	public function __construct( $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return int|null
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Check if ID is null (new trigger).
	 *
	 * @return bool
	 */
	public function is_null(): bool {
		return null === $this->value;
	}

	/**
	 * Validate trigger ID.
	 *
	 * @param mixed $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( $value ): void {
		if ( null !== $value && ( ! is_int( $value ) || $value <= 0 ) ) {
			throw new \InvalidArgumentException( 'Trigger ID must be a positive integer or null' );
		}
	}
}
