<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Async Delay Number Value Object.
 *
 * Represents the numeric value for delay duration.
 *
 * @since 7.0.0
 */
class Async_Delay_Number {

	private int $value;

	/**
	 * Constructor.
	 *
	 * @param int $value Delay number value.
	 * @throws \InvalidArgumentException If invalid number.
	 */
	public function __construct( int $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return int
	 */
	public function get_value(): int {
		return $this->value;
	}

	/**
	 * Validate delay number.
	 *
	 * @param int $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( int $value ): void {
		if ( $value < 1 ) {
			throw new \InvalidArgumentException(
				'Delay number must be at least 1, got: ' . $value
			);
		}
	}
}
