<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Action Recipe ID Value Object.
 *
 * Represents the ID of the recipe this action belongs to.
 * Can be null for actions not yet assigned to a recipe.
 *
 * @since 7.0.0
 */
class Action_Recipe_Id {

	private $value;

	/**
	 * Constructor.
	 *
	 * @param int|null $value Recipe ID value.
	 * @throws \InvalidArgumentException If invalid ID.
	 */
	public function __construct( $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get the recipe ID value.
	 *
	 * @return int|null
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Check if recipe ID is null (action not assigned to recipe).
	 *
	 * @return bool
	 */
	public function is_null(): bool {
		return null === $this->value;
	}

	/**
	 * Check if action is assigned to a recipe.
	 *
	 * @return bool
	 */
	public function is_assigned(): bool {
		return null !== $this->value;
	}

	/**
	 * Validate recipe ID.
	 *
	 * @param mixed $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( $value ): void {
		if ( null !== $value && ( ! is_int( $value ) || $value <= 0 ) ) {
			throw new \InvalidArgumentException( 'Action recipe ID must be a positive integer or null' );
		}
	}
}
