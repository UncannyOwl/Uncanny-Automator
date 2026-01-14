<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Async Schedule Date Value Object.
 *
 * Represents a schedule date in Y-m-d format.
 *
 * @since 7.0.0
 */
class Async_Schedule_Date {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Schedule date value.
	 * @throws \InvalidArgumentException If invalid date.
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
	 * Get as DateTime object.
	 *
	 * @return \DateTime
	 */
	public function to_datetime(): \DateTime {
		return \DateTime::createFromFormat( 'Y-m-d', $this->value );
	}

	/**
	 * Validate schedule date.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		$date = \DateTime::createFromFormat( 'Y-m-d', $value );

		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			throw new \InvalidArgumentException(
				'Schedule date must be in Y-m-d format, got: ' . $value
			);
		}
	}
}
