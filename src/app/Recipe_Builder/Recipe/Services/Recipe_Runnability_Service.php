<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;

/**
 * Owns the recipe runnability invariant: a recipe is runnable only when it has
 * at least one live trigger AND at least one live action. One definition,
 * shared by both sides of the rule so they can never disagree:
 *
 *  - reactive  `demote_if_empty()` — an item was disabled or deleted; if the
 *    recipe is no longer runnable, drop it to draft.
 *  - proactive `is_runnable()`     — the publish guard; refuse to take a
 *    recipe live without the required live items.
 *
 * The tree read lives in Recipe_Violation_Service::evaluate_recipe_structural()
 * — the violation engine owns the walk (fresh tree build, block / loop nesting
 * included) and this service delegates, so the reactive and proactive sides
 * share one definition. Demotion reacts to the STRUCTURAL subset only: an item
 * losing a required field must never silently demote a live recipe.
 */
final class Recipe_Runnability_Service {

	/**
	 * Shared instance handed out by `instance()`.
	 *
	 * @var Recipe_Runnability_Service|null
	 */
	private static $instance = null;

	/**
	 * Reads the recipe and its items, which is what the runnable test counts.
	 *
	 * @var Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Private so both sides of the invariant go through `instance()` and share one definition.
	 *
	 * @param Recipe_Store|null $recipe_store Recipe storage (lazy-default).
	 */
	private function __construct( ?Recipe_Store $recipe_store = null ) {
		$this->recipe_store = $recipe_store ?? Database::get_recipe_store();
	}

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Test seam — inject a fake recipe store.
	 *
	 * @param Recipe_Store $recipe_store Recipe storage.
	 *
	 * @return self
	 */
	public static function create_with_dependencies( Recipe_Store $recipe_store ): self {
		return new self( $recipe_store );
	}

	/**
	 * Whether the recipe has at least one live trigger AND one live action.
	 *
	 * Delegates to the violation engine's structural evaluation — one
	 * definition of the invariant, shared with the publish gate, so the
	 * two sides can never disagree.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return bool
	 */
	public function is_runnable( int $recipe_id ): bool {
		return ! Recipe_Violation_Service::has_violations(
			Recipe_Violation_Service::instance()->evaluate_recipe_structural( $recipe_id )
		);
	}

	/**
	 * Demote a published recipe to draft when it is no longer runnable. The
	 * item services call this after an item's live-state changes (disable or
	 * delete) so every transport inherits the invariant.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return bool True if the recipe was demoted, false otherwise.
	 */
	public function demote_if_empty( int $recipe_id ): bool {
		$recipe = $this->recipe_store->get( $recipe_id );

		if ( ! $recipe || ! Recipe_Status::is_recipe_on( $recipe->get_recipe_status()->get_value() ) ) {
			return false;
		}

		if ( $this->is_runnable( $recipe_id ) ) {
			return false;
		}

		$this->recipe_store->update_status( $recipe_id, Recipe_Status::DRAFT );

		return true;
	}
}
