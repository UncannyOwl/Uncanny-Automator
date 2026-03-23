<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

use Uncanny_Automator\Api\Components\Interfaces\Parent_Id;

/**
 * Recipe ID Value Object.
 *
 * Immutable value object that validates and encapsulates recipe identifier.
 * Ensures ID integrity and manages null states for new recipe instances.
 * Implements Parent_Id interface to allow it to be used as a parent reference for actions.
 *
 * @since 7.0.0
 */
class Recipe_Id implements Parent_Id {

	private $value;

	/**
	 * Constructor.
	 *
	 * @param int|null $value Recipe ID or null for new recipes.
	 * @throws \InvalidArgumentException If ID is invalid.
	 */
	public function __construct( $value ) {
		if ( null !== $value ) {
			$value = (int) $value;
			if ( $value <= 0 ) {
				throw new \InvalidArgumentException( 'Recipe ID must be a positive integer or null' );
			}
		}
		$this->value = $value;
	}
	/**
	 * Get value.
	 *
	 * @return ?
	 */
	public function get_value(): ?int {
		return $this->value;
	}
}
