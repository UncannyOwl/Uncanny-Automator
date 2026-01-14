<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

/**
 * Recipe Status Value Object.
 *
 * Immutable value object that validates and encapsulates recipe status data.
 * Enforces valid status enumeration and prevents invalid state transitions.
 *
 * @since 7.0.0
 */
class Recipe_Status {

	const DRAFT   = 'draft';
	const PUBLISH = 'publish';

	private static $allowed_values = array(
		self::DRAFT,
		self::PUBLISH,
	);

	private $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Recipe status (draft or publish).
	 * @throws \InvalidArgumentException If status is invalid.
	 */
	public function __construct( $value ) {
		if ( ! in_array( $value, self::$allowed_values, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Recipe status must be one of: %s. Given: %s',
					implode( ', ', self::$allowed_values ),
					$value
				)
			);
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
