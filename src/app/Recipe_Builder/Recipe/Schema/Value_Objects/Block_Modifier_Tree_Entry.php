<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects;

use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Custom_Item_Name_Field;

/**
 * Tree-entry projection for one block modifier row.
 *
 * Pure row → tree-shape pass-through. The discriminator (which
 * `block_modifier_type` belongs to a given parent block) is owned by the
 * parent {@see Block_Tree_Entry} subclass via its static
 * {@see Block_Tree_Entry::modifier_type()} declaration — this VO just
 * normalizes whatever the store hands back.
 */
final class Block_Modifier_Tree_Entry {

	/**
	 * Which kind of modifier this row is, as the parent block's entry declared it.
	 *
	 * @var string
	 */
	private string $block_modifier_type;

	/**
	 * Modifier id as storage holds it — a legacy string hash for filter conditions, a post id for loop filters.
	 *
	 * @var int|string
	 */
	private $id;

	/**
	 * Integration the modifier came from, such as `WP` or `GEN`.
	 *
	 * @var string
	 */
	private string $integration_code;

	/**
	 * Identifies which modifier of that integration this is, such as `WP_USER_HAS_ROLE`.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Values the author configured on the modifier, normalised from whatever the store returned.
	 *
	 * @var array
	 */
	private array $fields;

	/**
	 * The author's own name for this modifier, or null when they never set
	 * one. Promoted out of {@see self::$fields} so the builder can render it
	 * as the row's main line — the same role `custom_name` plays on an item.
	 *
	 * @var string|null
	 */
	private ?string $custom_name;

	/**
	 * Private by design: build through the named constructors below so every
	 * instance is fully projected before it is handed out.
	 *
	 */
	private function __construct() {}

	/**
	 * Build from a `Block_Modifier_Store::get_by_block` row.
	 *
	 * @param array $row Modifier store row.
	 */
	public static function from_row( array $row ): self {
		$instance                      = new self();
		$instance->block_modifier_type = (string) ( $row['block_modifier_type'] ?? '' );
		$instance->id                  = $row['id'] ?? 0;
		$instance->integration_code    = (string) ( $row['integration_code'] ?? '' );
		$instance->code                = (string) ( $row['code'] ?? '' );
		$instance->fields              = is_array( $row['fields'] ?? null ) ? $row['fields'] : array();
		$instance->custom_name         = self::read_custom_name( $instance->fields );

		return $instance;
	}

	/**
	 * Flatten the modifier to its tree shape.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'block_modifier_type' => $this->block_modifier_type,
			'id'                  => $this->id,
			'integration_code'    => $this->integration_code,
			'code'                => $this->code,
			'custom_name'         => $this->custom_name,
			'fields'              => $this->fields,
		);
	}

	/**
	 * Read the custom label out of a modifier's field bag.
	 *
	 * Goes through {@see Fields_Bag} because these bags hold either the
	 * canonical `{value, readable, custom}` envelope or a bare scalar
	 * depending on which writer last touched them; indexing the raw entry
	 * would yield "Array" for the envelope shape.
	 *
	 * An empty string reads as no label, so the row header falls back to the
	 * catalog sentence instead of rendering blank.
	 *
	 * @param array $fields Raw field bag from the store row.
	 * @return string|null The label, or null when unset or empty.
	 */
	private static function read_custom_name( array $fields ): ?string {
		$entry = ( new Fields_Bag( $fields ) )->get( Custom_Item_Name_Field::FIELD_CODE );
		if ( null === $entry ) {
			return null;
		}

		$value = (string) ( $entry['value'] ?? '' );

		return '' === $value ? null : $value;
	}
}
