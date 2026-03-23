<?php
/**
 * Trigger Search Result Collection.
 *
 * A collection of trigger search results with metadata about the search.
 *
 * @package Uncanny_Automator\Api\Components\Search\Trigger
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Search\Trigger;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;

/**
 * Collection of trigger search results.
 */
class Trigger_Search_Result_Collection {

	/**
	 * Collection of trigger search results.
	 *
	 * @var Trigger_Search_Result[]
	 */
	private array $items;

	/**
	 * Total count of results (before limiting).
	 *
	 * @var int
	 */
	private int $total_count;

	/**
	 * Information about alternative triggers (different recipe type).
	 *
	 * @var Alternative_Triggers_Info|null
	 */
	private ?Alternative_Triggers_Info $alternative_triggers;

	/**
	 * Constructor.
	 *
	 * @param Trigger_Search_Result[]        $items                Collection of results.
	 * @param int                            $total_count          Total count before limiting.
	 * @param Alternative_Triggers_Info|null $alternative_triggers Info about alternative triggers.
	 */
	public function __construct(
		array $items,
		int $total_count = 0,
		?Alternative_Triggers_Info $alternative_triggers = null
	) {
		$this->items                = $items;
		$this->total_count          = $total_count;
		$this->alternative_triggers = $alternative_triggers;
	}

	/**
	 * Get the items in the collection.
	 *
	 * @return Trigger_Search_Result[]
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
	 * Check if there are alternative triggers available.
	 *
	 * @return bool
	 */
	public function has_alternative_triggers(): bool {
		return null !== $this->alternative_triggers;
	}

	/**
	 * Get information about alternative triggers.
	 *
	 * @return Alternative_Triggers_Info|null
	 */
	public function get_alternative_triggers(): ?Alternative_Triggers_Info {
		return $this->alternative_triggers;
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
			fn( Trigger_Search_Result $item ) => $item->to_array(),
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

	/**
	 * Create from RAG response data with availability info.
	 *
	 * @param array $rag_results       Results from RAG search.
	 * @param array $availability_map  Map of code => Component_Availability.
	 * @param array $alternative_info  Alternative triggers info array.
	 * @return self
	 */
	public static function from_rag_results(
		array $rag_results,
		array $availability_map = array(),
		array $alternative_info = array()
	): self {
		$items = array();

		foreach ( $rag_results as $result ) {
			$code         = $result['code'] ?? '';
			$availability = $availability_map[ $code ] ?? Component_Availability::available();

			$items[] = Trigger_Search_Result::from_rag_result( $result, $availability );
		}

		$alternative_triggers = null;
		if ( ! empty( $alternative_info ) ) {
			$alternative_triggers = Alternative_Triggers_Info::from_array( $alternative_info );
		}

		return new self( $items, count( $rag_results ), $alternative_triggers );
	}
}
