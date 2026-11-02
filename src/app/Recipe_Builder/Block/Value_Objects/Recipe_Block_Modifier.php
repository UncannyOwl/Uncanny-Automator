<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;

/**
 * Recipe-instance block-modifier — a leaf row attached to a parent
 * Recipe_Block, discriminated by `block_modifier_type` ('filter_condition'
 * | 'loop_filter' | future opaque).
 *
 * Composes Fields_Bag + Recipe_Backup so the wire shape is reachable by
 * getter chaining without per-call shape massage. Frozen after construction
 * (Rule 8); validates discriminator + required strings at the boundary.
 *
 * Discriminator validation delegates to
 * `Integration_Catalog\Integration\Enums\Block_Modifier_Types::is_valid` so
 * the allow-list stays in one place — the catalog enum, not duplicated here.
 *
 * `id` is `int|string`: filter-condition modifiers carry legacy string-hash
 * ids from the pre-Hybrid-E shape; loop-filter modifiers carry numeric post
 * ids preserved through the post-type rename. Both ride forward.
 */
final class Recipe_Block_Modifier {

	/**
	 * Wire id. Filter-condition rows carry a legacy string hash, loop-filter
	 * rows carry a numeric post id; both ride forward unchanged.
	 *
	 * @var int|string
	 */
	private $id;

	/**
	 * Which kind of modifier this row is — the discriminator the catalog enum
	 * validates and the tree dispatches on.
	 *
	 * @var string
	 */
	private $block_modifier_type;

	/**
	 * Id of the Recipe_Block this modifier qualifies.
	 *
	 * @var int
	 */
	private $parent_block_id;

	/**
	 * Id of the recipe the parent block belongs to.
	 *
	 * @var int
	 */
	private $recipe_id;

	/**
	 * Integration the modifier came from, such as `WP` or `GEN`. Never empty.
	 *
	 * @var string
	 */
	private $integration_code;

	/**
	 * Identifies which modifier of that integration this is, such as
	 * `WP_USER_HAS_ROLE`. Never empty.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * The values the author configured on this modifier, already in the
	 * FieldsOutput shape the wire expects.
	 *
	 * @var Fields_Bag
	 */
	private $fields;

	/**
	 * Last-known display text, shown when the integration can no longer
	 * describe the modifier itself.
	 *
	 * @var Recipe_Backup
	 */
	private $backup;

	/**
	 * Zero-based position among the sibling modifiers on the parent block.
	 *
	 * @var int
	 */
	private $menu_order;

	/**
	 * String id carried by filter-condition rows written before the Hybrid E
	 * rename; null for rows created since.
	 *
	 * @var string|null
	 */
	private $legacy_id;

	/**
	 * Build a modifier, rejecting anything the tree could not project.
	 *
	 * @param int|string    $id                  Wire id — int or legacy string-hash.
	 * @param string        $block_modifier_type Discriminator, validated against Block_Modifier_Types.
	 * @param int           $parent_block_id     Owning Recipe_Block id.
	 * @param int           $recipe_id           Owning recipe id.
	 * @param string        $integration_code    Non-empty integration code (e.g. 'WP', 'GEN').
	 * @param string        $code                Non-empty modifier code (e.g. 'WP_USER_HAS_ROLE').
	 * @param Fields_Bag    $fields              FieldsOutput-shaped field bag.
	 * @param Recipe_Backup $backup              Display fallback envelope.
	 * @param int           $menu_order          Position within parent block, zero-based.
	 * @param string|null   $legacy_id           Legacy string id (filter-condition rows from before Hybrid E rename) or null.
	 *
	 * @throws \InvalidArgumentException When id is not int|string, discriminator is unknown, or required strings are empty.
	 */
	public function __construct(
		$id,
		string $block_modifier_type,
		int $parent_block_id,
		int $recipe_id,
		string $integration_code,
		string $code,
		Fields_Bag $fields,
		Recipe_Backup $backup,
		int $menu_order,
		$legacy_id
	) {
		if ( ! is_int( $id ) && ! is_string( $id ) ) {
			throw new \InvalidArgumentException( 'Recipe_Block_Modifier: id must be int or string' );
		}
		if ( '' === $id || 0 === $id ) {
			throw new \InvalidArgumentException( 'Recipe_Block_Modifier: id must not be empty' );
		}
		if ( ! Block_Modifier_Types::is_valid( $block_modifier_type ) ) {
			throw new \InvalidArgumentException(
				'Recipe_Block_Modifier: unknown block_modifier_type: ' . esc_html( $block_modifier_type )
			);
		}
		if ( '' === $integration_code ) {
			throw new \InvalidArgumentException( 'Recipe_Block_Modifier: integration_code must not be empty' );
		}
		if ( '' === $code ) {
			throw new \InvalidArgumentException( 'Recipe_Block_Modifier: code must not be empty' );
		}
		if ( null !== $legacy_id && ! is_string( $legacy_id ) ) {
			throw new \InvalidArgumentException( 'Recipe_Block_Modifier: legacy_id must be string or null' );
		}

		$this->id                  = $id;
		$this->block_modifier_type = $block_modifier_type;
		$this->parent_block_id     = $parent_block_id;
		$this->recipe_id           = $recipe_id;
		$this->integration_code    = $integration_code;
		$this->code                = $code;
		$this->fields              = $fields;
		$this->backup              = $backup;
		$this->menu_order          = $menu_order;
		$this->legacy_id           = $legacy_id;
	}

	/**
	 * The modifier's wire id, in whichever form storage holds it.
	 *
	 * @return int|string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Which kind of modifier this is — filter_condition or loop_filter.
	 *
	 * @return string
	 */
	public function get_block_modifier_type(): string {
		return $this->block_modifier_type;
	}

	/**
	 * The block this modifier is attached to.
	 *
	 * @return int
	 */
	public function get_parent_block_id(): int {
		return $this->parent_block_id;
	}

	/**
	 * The recipe the owning block belongs to.
	 *
	 * @return int
	 */
	public function get_recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * Integration that supplies this modifier, e.g. "WP".
	 *
	 * @return string
	 */
	public function get_integration_code(): string {
		return $this->integration_code;
	}

	/**
	 * Catalog code identifying which modifier this is.
	 *
	 * @return string
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * The modifier's configured field values.
	 *
	 * @return Fields_Bag
	 */
	public function get_fields(): Fields_Bag {
		return $this->fields;
	}

	/**
	 * Display payload shown when the integration is unavailable.
	 *
	 * @return Recipe_Backup
	 */
	public function get_backup(): Recipe_Backup {
		return $this->backup;
	}

	/**
	 * Position among sibling modifiers on the same block.
	 *
	 * @return int
	 */
	public function get_menu_order(): int {
		return $this->menu_order;
	}

	/**
	 * The pre-rename string id, or null when this row never had one.
	 *
	 * @return string|null
	 */
	public function get_legacy_id() {
		return $this->legacy_id;
	}
}
