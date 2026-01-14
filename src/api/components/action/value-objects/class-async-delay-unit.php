<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Async Delay Unit Value Object.
 *
 * Represents the time unit for delay - seconds, minutes, hours, days, years.
 *
 * @since 7.0.0
 */
class Async_Delay_Unit {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Delay unit value.
	 * @throws \InvalidArgumentException If invalid unit.
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
	 * Convert delay to seconds based on unit.
	 *
	 * @param int $number The delay number.
	 * @return int Total seconds.
	 */
	public function to_seconds( int $number ): int {
		$multipliers = array(
			'seconds' => 1,
			'minutes' => 60,
			'hours'   => 3600,
			'days'    => 86400,
			'years'   => 31536000, // 365 days
		);

		return $number * $multipliers[ $this->value ];
	}

	/**
	 * Validate delay unit.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		$valid_units = array( 'seconds', 'minutes', 'hours', 'days', 'years' );

		if ( ! in_array( $value, $valid_units, true ) ) {
			throw new \InvalidArgumentException(
				'Delay unit must be one of: seconds, minutes, hours, days, years. Got: ' . $value
			);
		}
	}
}
