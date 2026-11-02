<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_Registry_Service;

/**
 * Whether the builder switches an item on for the user after an add or a save.
 *
 * Two kinds of item:
 * - A trigger without an on / off switch (`has_status_toggle` false, such as
 *   Run now and the loopable import triggers). The user can never switch it
 *   on, so it goes live whatever the recipe's status: on add when it has
 *   nothing to configure, otherwise on its first valid save.
 * - Every other item: the same moments, but only while the recipe is a
 *   draft. In a live recipe the user's switch is the only way on, so an edit
 *   never puts a step live behind their back.
 *
 * An add that carries configuration (MCP and the automator_* helpers add and
 * configure in one call) is an add followed by a save, so it follows the save
 * rule.
 *
 * Answers "should we try" only. The CRUD services ask it after a successful
 * add or save that named no status of its own; their set_status( true ) still
 * runs the item-on check and may refuse.
 */
final class Item_Publish_On_Save_Policy {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Recipe status source.
	 *
	 * @var Recipe_Store
	 */
	private Recipe_Store $recipe_store;

	/**
	 * Switch-less declaration reader.
	 *
	 * @var Trigger_Registry_Service
	 */
	private Trigger_Registry_Service $trigger_registry;

	/**
	 * Configurable-fields reader.
	 *
	 * @var Field_Registry_Service
	 */
	private Field_Registry_Service $field_registry;

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @param Recipe_Store|null             $recipe_store     Recipe status source.
	 * @param Trigger_Registry_Service|null $trigger_registry Switch-less declaration reader.
	 * @param Field_Registry_Service|null   $field_registry   Configurable-fields reader.
	 */
	private function __construct(
		?Recipe_Store $recipe_store = null,
		?Trigger_Registry_Service $trigger_registry = null,
		?Field_Registry_Service $field_registry = null
	) {
		$this->recipe_store     = $recipe_store ?? Database::get_recipe_store();
		$this->trigger_registry = $trigger_registry ?? Trigger_Registry_Service::instance();
		$this->field_registry   = $field_registry ?? Field_Registry_Service::instance();
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
	 * Test seam: a fresh instance with injected collaborators.
	 *
	 * @param Recipe_Store|null             $recipe_store     Recipe status source.
	 * @param Trigger_Registry_Service|null $trigger_registry Switch-less declaration reader.
	 * @param Field_Registry_Service|null   $field_registry   Configurable-fields reader.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Recipe_Store $recipe_store = null,
		?Trigger_Registry_Service $trigger_registry = null,
		?Field_Registry_Service $field_registry = null
	): self {
		return new self( $recipe_store, $trigger_registry, $field_registry );
	}

	/**
	 * Whether to switch an item on right after it is added. A bare add
	 * qualifies only when the item has nothing to configure; an add that
	 * carries configuration follows the save rule.
	 *
	 * @param int    $recipe_id        Recipe post ID.
	 * @param string $item_type        Item type (trigger, action).
	 * @param string $integration_code Integration code.
	 * @param string $item_code        Item code.
	 * @param bool   $configured       Whether the add carried field values.
	 *
	 * @return bool
	 */
	public function publishes_on_add( int $recipe_id, string $item_type, string $integration_code, string $item_code, bool $configured = false ): bool {
		if ( ! $configured && $this->field_registry->declares_author_fields( $integration_code, $item_type, $item_code ) ) {
			return false;
		}

		return $this->recipe_allows( $recipe_id, $item_type, $item_code );
	}

	/**
	 * Whether to switch an item on after a successful save.
	 *
	 * @param int    $recipe_id Recipe post ID.
	 * @param string $item_type Item type (trigger, action).
	 * @param string $item_code Item code.
	 *
	 * @return bool
	 */
	public function publishes_on_update( int $recipe_id, string $item_type, string $item_code ): bool {
		return $this->recipe_allows( $recipe_id, $item_type, $item_code );
	}

	/**
	 * A switch-less trigger, in any recipe; any other item, in a draft recipe.
	 *
	 * @param int    $recipe_id Recipe post ID.
	 * @param string $item_type Item type.
	 * @param string $item_code Item code.
	 *
	 * @return bool
	 */
	private function recipe_allows( int $recipe_id, string $item_type, string $item_code ): bool {
		if ( 'trigger' === $item_type && ! $this->trigger_registry->has_status_toggle( $item_code ) ) {
			return true;
		}

		$recipe = $this->recipe_store->get( $recipe_id );

		return null !== $recipe && Recipe_Status::DRAFT === $recipe->get_recipe_status()->get_value();
	}
}
