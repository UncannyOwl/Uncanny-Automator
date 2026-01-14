<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

/**
 * Recipe Times Per User Value Object.
 *
 * @since 7.0.0
 */
class Recipe_Times_Per_User {

	private $value;

	/**
	 * Constructor.
	 *
	 * @param int|null $value Number of allowed runs per user.
	 * @throws \InvalidArgumentException If value is invalid.
	 */
	public function __construct( $value = null ) {

		if ( is_null( $value ) ) {
			$this->value = null;
			return;
		}

		$this->validate( $value );
		$this->value = (int) $value;
	}

	/**
	 * Get the value.
	 *
	 * @return int
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Validate the times per user value.
	 *
	 * @param mixed $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( $value ) {

		// Null value is good.
		if ( is_null( $value ) ) {
			return;
		}

		if ( ! is_numeric( $value ) || $value < 0 ) {
			throw new \InvalidArgumentException( 'Times per user must be a non-negative integer' );
		}
	}
}
