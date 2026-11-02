<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Events\Dtos;

use InvalidArgumentException;

/**
 * The `{item_type}_fields_changed` payload.
 *
 * Dispatched by `Recipe_Change_Fanout` after `recipe_changed`, once per item whose
 * field values a write changed. For a trigger or an action the fields are its
 * declared field codes (a `{code}_custom` change reports `{code}`); for a block or a
 * block modifier they are the keys of its field bag. A field's first write counts.
 *
 * @since 8.0.0
 */
final class Recipe_Fields_Changed implements Event_Dto {

	/**
	 * One of `Recipe_Changed::item_types()`, never the recipe itself.
	 *
	 * @var string
	 */
	private string $item_type;

	/**
	 * Post id of the item.
	 *
	 * @var int
	 */
	private int $item_id;

	/**
	 * Id of the recipe the item belongs to.
	 *
	 * @var int
	 */
	private int $recipe_id;

	/**
	 * The field codes whose stored value changed.
	 *
	 * @var list<string>
	 */
	private array $fields;

	/**
	 * Build the payload.
	 *
	 * @param string       $item_type One of `Recipe_Changed::item_types()` other than the recipe.
	 * @param int          $item_id   Post id of the item.
	 * @param int          $recipe_id Id of the recipe the item belongs to.
	 * @param list<string> $fields    The changed field codes.
	 * @throws InvalidArgumentException When a value is outside its vocabulary, an id is below one or no field changed.
	 */
	public function __construct( string $item_type, int $item_id, int $recipe_id, array $fields ) {
		if ( Recipe_Changed::RECIPE === $item_type || ! in_array( $item_type, Recipe_Changed::item_types(), true ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown recipe item type "%s".', esc_html( $item_type ) ) );
		}

		if ( $item_id <= 0 || $recipe_id <= 0 ) {
			throw new InvalidArgumentException( 'A field change needs the item id and its recipe id.' );
		}

		if ( array() === $fields ) {
			throw new InvalidArgumentException( 'A field change needs at least one field.' );
		}

		$this->item_type = $item_type;
		$this->item_id   = $item_id;
		$this->recipe_id = $recipe_id;
		$this->fields    = array_values( array_map( 'strval', $fields ) );
	}

	/**
	 * The kind of item: one of `Recipe_Changed::item_types()`.
	 *
	 * @return string
	 */
	public function item_type(): string {
		return $this->item_type;
	}

	/**
	 * Post id of the item.
	 *
	 * @return int
	 */
	public function item_id(): int {
		return $this->item_id;
	}

	/**
	 * Id of the recipe the item belongs to.
	 *
	 * @return int
	 */
	public function recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * The field codes whose stored value changed.
	 *
	 * @return list<string>
	 */
	public function fields(): array {
		return $this->fields;
	}

	/**
	 * The payload as an array.
	 *
	 * @return array{item_type: string, item_id: int, recipe_id: int, fields: list<string>}
	 */
	public function to_array(): array {
		return array(
			'item_type' => $this->item_type,
			'item_id'   => $this->item_id,
			'recipe_id' => $this->recipe_id,
			'fields'    => $this->fields,
		);
	}
}
