<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

/**
 * Recipe Total Times Value Object.
 *
 * @since 7.0.0
 */
class Recipe_Total_Times {

	private $value;

	/**
	 * Constructor.
	 *
	 * @param int $value Total allowed runs for the recipe.
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
	 * Validate the total times value.
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
			throw new \InvalidArgumentException( 'Total times must be a non-negative integer' );
		}
	}
}
