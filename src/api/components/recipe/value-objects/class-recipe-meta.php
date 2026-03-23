<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

/**
 * Recipe Meta Value Object.
 *
 * Immutable value object that validates and encapsulates recipe metadata.
 * Provides structured storage for custom recipe properties and tracking data.
 *
 * @since 7.0.0
 */
class Recipe_Meta {

	private $value;

	/**
	 * Constructor.
	 *
	 * @param array $value Recipe metadata.
	 * @throws \InvalidArgumentException If value is not an array.
	 */
	public function __construct( $value = array() ) {
		if ( ! is_array( $value ) ) {
			throw new \InvalidArgumentException( 'Recipe meta must be an array' );
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
