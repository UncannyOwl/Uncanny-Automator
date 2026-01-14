<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

/**
 * Recipe Notes Value Object.
 *
 * @since 7.0.0
 */
class Recipe_Notes {

	private $value;

	/**
	 * Constructor.
	 *
	 * @param string $value The notes value.
	 * @throws \InvalidArgumentException If value is invalid.
	 */
	public function __construct( $value = '' ) {
		$this->validate( $value );
		$this->value = (string) $value;
	}

	/**
	 * Get the value.
	 *
	 * @return string
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Validate the notes value.
	 *
	 * @param mixed $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( $value ) {
		if ( ! is_string( $value ) && ! is_null( $value ) ) {
			throw new \InvalidArgumentException( 'Notes must be a string' );
		}

		if ( is_string( $value ) ) {
			// Domain validation: Enforce length limits
			if ( strlen( $value ) > 10000 ) {
				throw new \InvalidArgumentException( 'Recipe notes must not exceed 10,000 characters' );
			}
		}
	}
}
