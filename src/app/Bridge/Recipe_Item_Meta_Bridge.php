<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Bridge;

/**
 * Reads an item's flat post-meta blob (single values), matching the shape the
 * legacy recipe-data builder feeds to the token generators.
 */
interface Recipe_Item_Meta_Bridge {

	/**
	 * Flat single-value meta for a recipe item post (trigger/action).
	 *
	 * @param int $item_id Item post id.
	 * @return array<string,mixed> Meta key => single raw value (first meta row; structured-JSON strings repaired).
	 */
	public function get_item_meta( int $item_id ): array;
}
