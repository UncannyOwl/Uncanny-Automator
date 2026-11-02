<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects;

use Uncanny_Automator\App\Recipe_Builder\Action\Action;
use Uncanny_Automator\App\Recipe_Builder\Block\Recipe_Block;

/**
 * Heterogeneous, ordered list of recipe content entries.
 *
 * Each entry is one of: `Action` (action item) or `Recipe_Block`
 * (filter / loop / delay block instance). Triggers do NOT
 * live here — entry triggers are kept on the existing `Recipe_Triggers`
 * collection (composes into `flow.entry`, not `flow.content`).
 *
 * Frozen after construction. Constructor throws on any unsupported member
 * type rather than silently filtering — caller misuse surfaces loud.
 */
final class Recipe_Content {

	/**
	 * Ordered list of content entries.
	 *
	 * @var array<int, Action|Recipe_Block>
	 */
	private $entries;

	/**
	 * Take a recipe's ordered content, rejecting anything that is not an action or block.
	 *
	 * @param array<int, Action|Recipe_Block> $entries Entries; reindexed to zero-based.
	 *
	 * @throws \InvalidArgumentException When any member is not Action / Recipe_Block.
	 */
	public function __construct( array $entries ) {
		foreach ( $entries as $entry ) {
			if ( ! $entry instanceof Action && ! $entry instanceof Recipe_Block ) {
				throw new \InvalidArgumentException(
					'Recipe_Content entries must be Action or Recipe_Block'
				);
			}
		}
		$this->entries = array_values( $entries );
	}

	/**
	 * Whether the recipe has no content entries at all.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->entries );
	}

	/**
	 * How many content entries the recipe holds.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->entries );
	}

	/**
	 * Every content entry, in the order the recipe runs them.
	 *
	 * @return array<int, Action|Recipe_Block>
	 */
	public function all(): array {
		return $this->entries;
	}
}
