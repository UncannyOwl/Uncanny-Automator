<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Async Schedule Time Value Object.
 *
 * Represents a schedule time in h:i A format (12-hour with AM/PM).
 *
 * @since 7.0.0
 */
class Async_Schedule_Time {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Schedule time value.
	 * @throws \InvalidArgumentException If invalid time.
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
	 * Convert to 24-hour format.
	 *
	 * @return string Time in H:i format.
	 */
	public function to_24_hour(): string {
		$time = \DateTime::createFromFormat( 'h:i A', $this->value );
		return $time->format( 'H:i' );
	}

	/**
	 * Validate schedule time.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		$time = \DateTime::createFromFormat( 'h:i A', $value );

		if ( ! $time || $time->format( 'h:i A' ) !== $value ) {
			throw new \InvalidArgumentException(
				'Schedule time must be in h:i A format (12-hour with AM/PM), got: ' . $value
			);
		}
	}
}
