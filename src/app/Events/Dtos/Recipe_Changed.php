<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Events\Dtos;

use InvalidArgumentException;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * The `recipe_changed` event payload.
 *
 * One per service write, dispatched as `automator_event_recipe_changed` by
 * `Recipe_Change_Events` after persistence and the schema invalidation. It names the
 * node the caller addressed; a cascade (a block deleted with its content, a
 * recipe dropped) is one event for the addressed node. It does not say which
 * value changed: listeners read what they need from the stores, so they stay
 * idempotent across re-fires.
 *
 * `item_type` reuses the vocabularies the builder already has: a trigger or
 * an action is its `Integration_Item_Types` value, a block or a block modifier
 * its `Wire_Node_Type` value, and a recipe-level change is `RECIPE`.
 *
 * @since 8.0.0
 */
final class Recipe_Changed implements Event_Dto {

	/**
	 * The `item_type` of a recipe-level change (status, type, title, notes, settings, run-as, create, delete).
	 */
	const RECIPE = 'recipe';

	/**
	 * A Recipe_Change_Operation value.
	 *
	 * @var string
	 */
	private string $operation;

	/**
	 * One of `item_types()`.
	 *
	 * @var string
	 */
	private string $item_type;

	/**
	 * Post id of the addressed node (the recipe's own id for a recipe-level change).
	 *
	 * @var int
	 */
	private int $item_id;

	/**
	 * Id of the recipe the node belongs to (the recipe's own id for a recipe-level change).
	 *
	 * @var int
	 */
	private int $recipe_id;

	/**
	 * Build the event.
	 *
	 * @param string $operation A Recipe_Change_Operation value.
	 * @param string $item_type One of `item_types()`.
	 * @param int    $item_id   Post id of the addressed node.
	 * @param int    $recipe_id Id of the recipe the node belongs to.
	 *
	 * @throws InvalidArgumentException When a value is outside its vocabulary or an id is below one.
	 */
	public function __construct( string $operation, string $item_type, int $item_id, int $recipe_id ) {
		if ( ! Recipe_Change_Operation::is_valid( $operation ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown recipe change operation "%s".', esc_html( $operation ) ) );
		}

		if ( ! in_array( $item_type, self::item_types(), true ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown recipe item type "%s".', esc_html( $item_type ) ) );
		}

		if ( $item_id <= 0 ) {
			throw new InvalidArgumentException( 'A recipe change needs the id of the node it addressed.' );
		}

		if ( $recipe_id <= 0 ) {
			throw new InvalidArgumentException( 'A recipe change needs a recipe id.' );
		}

		$this->operation = $operation;
		$this->item_type = $item_type;
		$this->item_id   = $item_id;
		$this->recipe_id = $recipe_id;
	}

	/**
	 * Every `item_type` the event carries.
	 *
	 * @return list<string>
	 */
	public static function item_types(): array {
		return array(
			self::RECIPE,
			Integration_Item_Types::TRIGGER,
			Integration_Item_Types::ACTION,
			Wire_Node_Type::BLOCK,
			Wire_Node_Type::BLOCK_MODIFIER,
		);
	}

	/**
	 * What the write did: a Recipe_Change_Operation value.
	 *
	 * @return string
	 */
	public function operation(): string {
		return $this->operation;
	}

	/**
	 * The kind of node addressed: one of `item_types()`.
	 *
	 * @return string
	 */
	public function item_type(): string {
		return $this->item_type;
	}

	/**
	 * Post id of the addressed node.
	 *
	 * @return int
	 */
	public function item_id(): int {
		return $this->item_id;
	}

	/**
	 * Id of the recipe the node belongs to.
	 *
	 * @return int
	 */
	public function recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * The payload as an array.
	 *
	 * @return array{operation:string, item_type:string, item_id:int, recipe_id:int}
	 */
	public function to_array(): array {
		return array(
			'operation' => $this->operation,
			'item_type' => $this->item_type,
			'item_id'   => $this->item_id,
			'recipe_id' => $this->recipe_id,
		);
	}
}
