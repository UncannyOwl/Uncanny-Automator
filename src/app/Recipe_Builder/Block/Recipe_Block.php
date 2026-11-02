<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Recipe_Block_Modifiers;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Recipe_Block_Paths;

/**
 * Recipe-instance block aggregate.
 *
 * Distinct from the catalog `Block.php` (which describes block TYPES with
 * their declared paths, modifier_types, sentence templates). `Recipe_Block`
 * is the INSTANCE — a specific filter / loop / delay block under a recipe
 * with its own id, fields, attached modifiers, and per-path child items.
 *
 * Frozen after construction (Rule 8); validates `block_type` via the
 * existing `Block_Type` enum at the boundary.
 *
 * `id` is `int|string`: filter blocks emit legacy string-hash group ids,
 * loop / delay blocks emit numeric post ids. The wire contract accepts both.
 */
final class Recipe_Block {

	/**
	 * Wire id. Filter blocks carry a legacy string hash, loop and delay blocks carry a numeric post id.
	 *
	 * @var int|string
	 */
	private $id;

	/**
	 * Which kind of block this is — filter, loop or delay — validated against the Block_Type enum.
	 *
	 * @var string
	 */
	private $block_type;

	/**
	 * What encloses this block: the recipe id when it sits at the top level, otherwise the enclosing block's id.
	 *
	 * @var int
	 */
	private $parent_id;

	/**
	 * Id of the recipe this block belongs to, however deeply it nests.
	 *
	 * @var int
	 */
	private $recipe_id;

	/**
	 * Values configured on the block itself, such as a filter's match mode or a loop's type.
	 *
	 * @var Fields_Bag
	 */
	private $fields;

	/**
	 * Filter conditions or loop filters attached to this block.
	 *
	 * @var Recipe_Block_Modifiers
	 */
	private $modifiers;

	/**
	 * Child items grouped by path, in the order the catalog declares those paths.
	 *
	 * @var Recipe_Block_Paths
	 */
	private $paths;

	/**
	 * Zero-based position among the sibling blocks under the same parent.
	 *
	 * @var int
	 */
	private $menu_order;

	/**
	 * Group id carried by filter blocks migrated from the legacy `actions_conditions` shape; null for every other block type.
	 *
	 * @var string|null
	 */
	private $legacy_group_id;

	/**
	 * Build a block, rejecting an id or block type the tree could not project.
	 *
	 * @param int|string             $id              Wire id (string for filter legacy hashes, int otherwise).
	 * @param string                 $block_type      Validated against Block_Type enum.
	 * @param int                    $parent_id       Recipe id (for top-level blocks) or enclosing block id.
	 * @param int                    $recipe_id       Owning recipe id.
	 * @param Fields_Bag             $fields          Block-level fields (e.g. CONDITION_MATCH_MODE for filter, LOOP_TYPE for loop).
	 * @param Recipe_Block_Modifiers $modifiers       Attached modifiers (filter conditions / loop filters).
	 * @param Recipe_Block_Paths     $paths           Per-path child items, in catalog-declared order.
	 * @param int                    $menu_order      Position within parent, zero-based.
	 * @param string|null            $legacy_group_id Legacy filter `actions_conditions[i].id`; null for non-filter blocks.
	 *
	 * @throws \InvalidArgumentException When id is not int|string, block_type is unknown, or legacy_group_id is not string|null.
	 */
	public function __construct(
		$id,
		string $block_type,
		int $parent_id,
		int $recipe_id,
		Fields_Bag $fields,
		Recipe_Block_Modifiers $modifiers,
		Recipe_Block_Paths $paths,
		int $menu_order,
		$legacy_group_id
	) {
		if ( ! is_int( $id ) && ! is_string( $id ) ) {
			throw new \InvalidArgumentException( 'Recipe_Block: id must be int or string' );
		}
		if ( ! Block_Type::is_valid( $block_type ) ) {
			throw new \InvalidArgumentException( 'Recipe_Block: unknown block_type: ' . esc_html( $block_type ) );
		}
		if ( null !== $legacy_group_id && ! is_string( $legacy_group_id ) ) {
			throw new \InvalidArgumentException( 'Recipe_Block: legacy_group_id must be string or null' );
		}

		$this->id              = $id;
		$this->block_type      = $block_type;
		$this->parent_id       = $parent_id;
		$this->recipe_id       = $recipe_id;
		$this->fields          = $fields;
		$this->modifiers       = $modifiers;
		$this->paths           = $paths;
		$this->menu_order      = $menu_order;
		$this->legacy_group_id = $legacy_group_id;
	}

	/**
	 * The block's wire id, in whichever form storage holds it.
	 *
	 * @return int|string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Which kind of container this is — loop, filter or delay_schedule.
	 *
	 * @return string
	 */
	public function get_block_type(): string {
		return $this->block_type;
	}

	/**
	 * The post this block hangs off: the recipe itself, or an enclosing block
	 * when nested.
	 *
	 * @return int
	 */
	public function get_parent_id(): int {
		return $this->parent_id;
	}

	/**
	 * The recipe this block ultimately belongs to, however deeply nested.
	 *
	 * @return int
	 */
	public function get_recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * The block's own configuration, such as a loop's iterable expression.
	 *
	 * @return Fields_Bag
	 */
	public function get_fields(): Fields_Bag {
		return $this->fields;
	}

	/**
	 * Modifiers attached to this block — filter conditions or loop filters.
	 *
	 * @return Recipe_Block_Modifiers
	 */
	public function get_modifiers(): Recipe_Block_Modifiers {
		return $this->modifiers;
	}

	/**
	 * The branches items are routed down, keyed by catalog path code.
	 *
	 * @return Recipe_Block_Paths
	 */
	public function get_paths(): Recipe_Block_Paths {
		return $this->paths;
	}

	/**
	 * Position among sibling blocks under the same parent.
	 *
	 * @return int
	 */
	public function get_menu_order(): int {
		return $this->menu_order;
	}

	/**
	 * The legacy filter group id, or null when this block never had one.
	 *
	 * @return string|null
	 */
	public function get_legacy_group_id() {
		return $this->legacy_group_id;
	}
}
