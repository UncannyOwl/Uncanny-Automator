<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

/**
 * Recipe Title Value Object.
 *
 * Immutable value object that validates and encapsulates recipe title data.
 * Ensures title integrity through constructor validation and prevents invalid states.
 *
 * @since 7.0.0
 */
class Recipe_Title {

	private $value;

	const DEFAULT_VALUE = '(no title)';

	/**
	 * Constructor.
	 *
	 * @param string $value Recipe title.
	 */
	public function __construct( $value ) {
		if ( empty( $value ) || ! is_string( $value ) ) {
			$this->value = self::DEFAULT_VALUE;
			return;
		}

		$value = trim( $value );

		// Domain validation: Enforce length limits
		if ( strlen( $value ) > 250 ) {
			throw new \InvalidArgumentException( 'Recipe title must not exceed 250 characters' );
		}

		$this->value = $value;
	}
	/**
	 * Get value.
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}
}
