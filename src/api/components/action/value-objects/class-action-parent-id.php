<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

use Uncanny_Automator\Api\Components\Interfaces\Parent_Id;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Loop\Value_Objects\Loop_Id;

/**
 * Action Parent ID Value Object.
 *
 * Wraps a Parent_Id interface (Recipe_ID or Loop_ID) to represent the parent container
 * of an action. This allows actions to exist within recipes or within loops.
 *
 * @since 7.0.0
 */
class Action_Parent_Id {

	private Parent_Id $parent;

	/**
	 * Constructor.
	 *
	 * @param Parent_Id $parent Parent identifier (Recipe_ID or Loop_ID).
	 */
	public function __construct( Parent_Id $parent ) {
		$this->parent = $parent;
	}

	/**
	 * Get the parent ID value.
	 *
	 * @return int|null Parent ID or null for new instances.
	 */
	public function get_value(): ?int {
		return $this->parent->get_value();
	}

	/**
	 * Get the parent object.
	 *
	 * @return Parent_Id The parent identifier object.
	 */
	public function get_parent(): Parent_Id {
		return $this->parent;
	}

	/**
	 * Check if parent is a recipe.
	 *
	 * @return bool True if parent is Recipe_ID.
	 */
	public function is_recipe(): bool {
		return $this->parent instanceof Recipe_Id;
	}

	/**
	 * Check if parent is a loop.
	 *
	 * @return bool True if parent is Loop_ID.
	 */
	public function is_loop(): bool {
		return $this->parent instanceof Loop_Id;
	}
}
