<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects;

/**
 * Item tree-entry — abstract base for the canonical schema-v3 projection
 * of a trigger / action row.
 *
 * Concrete subclasses ({@see Trigger_Tree_Entry}, {@see Action_Tree_Entry})
 * own their item-kind-specific computations
 * (depends_on and is_async_app_action for actions) and extend
 * {@see self::to_array()} with their kind-specific keys.
 */
abstract class Item_Tree_Entry {

	/**
	 * Post id of the trigger or action this entry projects.
	 *
	 * @var int
	 */
	protected int $id;

	/**
	 * Which item kind this is, so the wire can route the entry without inspecting its class.
	 *
	 * @var string
	 */
	protected string $kind;

	/**
	 * Identifies which item of its integration this is, such as `WPFORMS_SUBMIT`.
	 *
	 * @var string
	 */
	protected string $code;

	/**
	 * Integration the item came from, such as `WPF` or `GEN`.
	 *
	 * @var string
	 */
	protected string $integration_code;

	/**
	 * Whether the author has this item enabled; a disabled item still projects, it just does not run.
	 *
	 * @var bool
	 */
	protected bool $is_item_on;

	/**
	 * Author-supplied label shown instead of the generated sentence, or null when unset.
	 *
	 * @var string|null
	 */
	protected ?string $custom_name;

	/**
	 * Values the author configured on the item, in the shape the wire serializer projects from.
	 *
	 * @var array
	 */
	protected array $fields;

	/**
	 * Zero-based position among sibling items under the same parent.
	 *
	 * @var int
	 */
	protected int $menu_order;

	/**
	 * Presentation meta the registry owns, which `fields` deliberately excludes.
	 *
	 * The aggregates strip sentence/hook keys out of the user-facing field bag
	 * because they are registry-supplied, not author-supplied. Every surface
	 * that has a registry to consult — builder, admin list, logs — re-derives
	 * them and never misses them.
	 *
	 * Replay is the exception: it rebuilds an item from the tree alone, by
	 * design, so anything absent here it has to invent. Carrying the values
	 * means a duplicated or imported item keeps the sentence it renders from
	 * and the hook it listens on, instead of falling back to its own code.
	 *
	 * @var array<string, string>
	 */
	protected array $presentation = array();

	/**
	 * Base entry shape shared by every item kind. Concrete subclasses
	 * extend with their kind-specific keys via `parent::to_array() + [...]`.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'kind'             => $this->kind,
			'item_type'        => $this->kind,
			'id'               => $this->id,
			'integration_code' => $this->integration_code,
			'code'             => $this->code,
			'is_item_on'       => $this->is_item_on,
			'custom_name'      => $this->custom_name,
			'fields'           => $this->fields,
			'tokens'           => array(),
			'menu_order'       => $this->menu_order,
			'presentation'     => $this->presentation,
		);
	}
}
