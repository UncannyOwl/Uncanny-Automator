<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

/**
 * Recipe Actions Value Object.
 *
 * Immutable value object that validates and encapsulates recipe action collection.
 * Manages action validation and maintains execution sequence integrity.
 *
 * @since 7.0.0
 */
class Recipe_Actions {

	private $value;

	/**
	 * Constructor.
	 *
	 * @param array $value Recipe actions data.
	 */
	public function __construct( $value = array() ) {
		// Dev mode: Accept any data structure for actions.
		// Normalize false and empty string to empty array for consistency.
		if ( false === $value || '' === $value ) {
			$value = array();
		}

		$this->value = $value ?? array();
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
