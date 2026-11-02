<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects;

/**
 * Block tree-entry — abstract base for the canonical schema-v3
 * projection of a `uo-block` row.
 *
 * Concrete subclasses ({@see Filter_Tree_Entry}, {@see Loop_Tree_Entry},
 * {@see Delay_Schedule_Tree_Entry}) own their block-type-specific
 * computations (delay seconds, schedule timestamp, loop-fields
 * translation, filter dependency scan) and extend {@see self::to_array()}
 * with their type-specific keys.
 *
 * Pattern mirrors `Recipe_Runner/Blocks/Schema_Nodes/` — the runner-side
 * typed VOs for the same blocks. Build-time projection lives here; run-time
 * hydration lives there; both share the resulting array contract.
 */
abstract class Block_Tree_Entry {

	/**
	 * Post id of the `uo-block` row this entry projects.
	 *
	 * @var int
	 */
	protected int $id;

	/**
	 * Which kind of block this is — filter, loop or delay — as stored on the row.
	 *
	 * @var string
	 */
	protected string $block_type;

	/**
	 * Values configured on the block itself, such as a filter's match mode.
	 *
	 * @var array
	 */
	protected array $fields;

	/**
	 * Projected modifier entries attached to this block, already in tree shape.
	 *
	 * @var array
	 */
	protected array $modifiers;

	/**
	 * Child items grouped by path code, in the order the catalog declares those paths.
	 *
	 * @var array
	 */
	protected array $paths;

	/**
	 * Zero-based position among sibling blocks under the same parent.
	 *
	 * @var int
	 */
	protected int $menu_order;

	/**
	 * Legacy hash group id (e.g. `mot70kkkzm8sn42eaua`) for filter blocks
	 * migrated from `actions_conditions` JSON. Null when no legacy id
	 * existed — block-first recipes never carried one.
	 */
	protected ?string $legacy_group_id;

	/**
	 * The `block_modifier_type` discriminator this block type owns — the
	 * `Block_Modifier_Store::get_by_block($id, $discriminator)` argument
	 * used to fetch this block's modifiers. Null when the block type has
	 * no modifiers (e.g. delay_schedule).
	 *
	 * Domain knowledge: each block type owns what kind of modifier slot
	 * it has. Consumers (Recipe_Tree_Builder) read this instead of
	 * mapping block_type → modifier_type inline.
	 *
	 * @return string|null One of the `Block_Modifier_Types::*` constants, or null.
	 */
	abstract public static function modifier_type(): ?string;

	/**
	 * The default `path_code` for a child of this block whose own
	 * `path_code` row is empty. Used by `Recipe_Tree_Builder::build_block_paths`
	 * to bucket unstamped children. Each block type owns its first
	 * declared path code (matches the `set_paths(...)` declaration in
	 * `Block_Registry/Blocks/{Block}.php` under Rule 17).
	 *
	 * @return string One of the `Block_Path_Code::*` constants.
	 */
	abstract public static function default_path_code(): string;

	/**
	 * Base entry shape shared by every block type. Concrete subclasses
	 * extend with their type-specific keys via `parent::to_array() + [...]`.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'kind'            => 'block',
			'block_type'      => $this->block_type,
			'id'              => $this->id,
			'fields'          => $this->fields,
			'modifiers'       => $this->modifiers,
			'paths'           => $this->paths,
			'menu_order'      => $this->menu_order,
			'legacy_group_id' => $this->legacy_group_id,
		);
	}
}
