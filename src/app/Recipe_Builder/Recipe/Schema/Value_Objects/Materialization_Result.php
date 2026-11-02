<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects;

/**
 * Result of materializing a canonical (sub)tree into a target recipe.
 *
 * Frozen value object (Rule 8): `root_id` is the freshly-allocated id of the
 * subtree root (the new action id for a single-node materialize, the new
 * recipe id for a whole-tree materialize), and `id_map` is the complete
 * old_id => new_id translation table built during Pass 1 — the same map the
 * Pass-2 reference rewrite consumed.
 */
final class Materialization_Result {

	/**
	 * Freshly-allocated id of the subtree root: the new action id for a single node, the new recipe id for a whole tree.
	 *
	 * @var int
	 */
	private $root_id;

	/**
	 * Complete old id => new id translation table built during Pass 1.
	 *
	 * @var array<int,int>
	 */
	private $id_map;

	/**
	 * Freeze the outcome of a materialize pass.
	 *
	 * @param array<int,int> $id_map
	 */
	public function __construct( int $root_id, array $id_map ) {
		$this->root_id = $root_id;
		$this->id_map  = $id_map;
	}

	/**
	 * Id of the root the materialize pass created.
	 *
	 * @return int
	 */
	public function get_root_id(): int {
		return $this->root_id;
	}

	/**
	 * The old id => new id table, for callers rewriting references into the new tree.
	 *
	 * @return array<int,int>
	 */
	public function get_id_map(): array {
		return $this->id_map;
	}
}
