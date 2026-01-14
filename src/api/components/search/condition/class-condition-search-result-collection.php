<?php
/**
 * Condition Search Result Collection.
 *
 * A collection of condition search results with metadata about the search.
 *
 * @package Uncanny_Automator\Api\Components\Search\Condition
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Search\Condition;

/**
 * Collection of condition search results.
 */
class Condition_Search_Result_Collection {

	/**
	 * Collection of condition search results.
	 *
	 * @var Condition_Search_Result[]
	 */
	private array $items;

	/**
	 * Total count of results (before limiting).
	 *
	 * @var int
	 */
	private int $total_count;

	/**
	 * Constructor.
	 *
	 * @param Condition_Search_Result[] $items       Collection of results.
	 * @param int                       $total_count Total count before limiting.
	 */
	public function __construct( array $items, int $total_count = 0 ) {
		$this->items       = $items;
		$this->total_count = $total_count;
	}

	/**
	 * Get the items in the collection.
	 *
	 * @return Condition_Search_Result[]
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Get the count of items in this collection.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Get the total count of results (before limiting).
	 *
	 * @return int
	 */
	public function get_total_count(): int {
		return $this->total_count;
	}

	/**
	 * Check if the collection is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->items );
	}

	/**
	 * Convert all items to array representation.
	 * Each item's to_array() method controls its serialization.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array_map(
			fn( Condition_Search_Result $item ) => $item->to_array(),
			$this->items
		);
	}

	/**
	 * Create an empty collection.
	 *
	 * @return self
	 */
	public static function empty(): self {
		return new self( array() );
	}
}
