<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Events\Dtos;

use InvalidArgumentException;

/**
 * The `{item_type}_status_changed` payload.
 *
 * Dispatched by `Recipe_Change_Fanout` after `recipe_changed`, once per recipe or
 * item whose post status the write changed, the recipe included when the write
 * drafted it (for example because its last live action went). It carries the net
 * change: the status before the write and after it.
 *
 * @since 8.0.0
 */
final class Recipe_Status_Changed implements Event_Dto {

	/**
	 * One of `Recipe_Changed::item_types()`.
	 *
	 * @var string
	 */
	private string $item_type;

	/**
	 * Post id of the recipe or item.
	 *
	 * @var int
	 */
	private int $item_id;

	/**
	 * Id of the recipe (the recipe's own id for a recipe).
	 *
	 * @var int
	 */
	private int $recipe_id;

	/**
	 * Post status before the write.
	 *
	 * @var string
	 */
	private string $old_status;

	/**
	 * Post status after the write.
	 *
	 * @var string
	 */
	private string $new_status;

	/**
	 * Build the payload.
	 *
	 * @param string $item_type  One of `Recipe_Changed::item_types()`.
	 * @param int    $item_id    Post id of the recipe or item.
	 * @param int    $recipe_id  Id of the recipe.
	 * @param string $old_status Post status before the write.
	 * @param string $new_status Post status after the write.
	 * @throws InvalidArgumentException When the item type is unknown, an id is missing or the status did not change.
	 */
	public function __construct( string $item_type, int $item_id, int $recipe_id, string $old_status, string $new_status ) {
		if ( ! in_array( $item_type, Recipe_Changed::item_types(), true ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown recipe item type "%s".', esc_html( $item_type ) ) );
		}

		if ( $item_id <= 0 || $recipe_id <= 0 ) {
			throw new InvalidArgumentException( 'A status change needs the item id and its recipe id.' );
		}

		if ( $old_status === $new_status ) {
			throw new InvalidArgumentException( 'A status change needs two different statuses.' );
		}

		$this->item_type  = $item_type;
		$this->item_id    = $item_id;
		$this->recipe_id  = $recipe_id;
		$this->old_status = $old_status;
		$this->new_status = $new_status;
	}

	/**
	 * The kind of node: one of `Recipe_Changed::item_types()`.
	 *
	 * @return string
	 */
	public function item_type(): string {
		return $this->item_type;
	}

	/**
	 * Post id of the recipe or item.
	 *
	 * @return int
	 */
	public function item_id(): int {
		return $this->item_id;
	}

	/**
	 * Id of the recipe.
	 *
	 * @return int
	 */
	public function recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * Post status before the write.
	 *
	 * @return string
	 */
	public function old_status(): string {
		return $this->old_status;
	}

	/**
	 * Post status after the write.
	 *
	 * @return string
	 */
	public function new_status(): string {
		return $this->new_status;
	}

	/**
	 * The payload as an array.
	 *
	 * @return array{item_type: string, item_id: int, recipe_id: int, old_status: string, new_status: string}
	 */
	public function to_array(): array {
		return array(
			'item_type'  => $this->item_type,
			'item_id'    => $this->item_id,
			'recipe_id'  => $this->recipe_id,
			'old_status' => $this->old_status,
			'new_status' => $this->new_status,
		);
	}
}
