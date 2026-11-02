<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;

/**
 * Context carrying the catalog coordinates a field-output serializer needs
 * to look up canonical type + label for each field key:
 * - integration_code (e.g. "WP", "WC")
 * - owner_type — discriminator string. For items: trigger|action.
 *   For block modifiers: filter_condition|loop_filter.
 * - code — the trigger/action/modifier code
 *
 * Constructed by Item_Wire_Serializer / Modifier_Wire_Serializer from the
 * tree entry; consumed by Field_Output_Serializer. Empty strings are valid
 * for callers that want "no context" semantics (recipe-level settings,
 * run_as fields) — those callers typically pass null instead.
 */
final class Field_Context {

	/**
	 * Owner-type literals that identify a recipe item.
	 *
	 * @var array<string>
	 */
	private const ITEM_OWNER_TYPES = array( 'trigger', 'action' );

	/**
	 * Integration the owner belongs to, e.g. 'WP'.
	 *
	 * @var string
	 */
	private string $integration_code;

	/**
	 * Item or modifier discriminator.
	 *
	 * @var string
	 */
	private string $owner_type;

	/**
	 * Trigger, action or modifier code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Capture the catalog coordinates of whatever owns a set of fields.
	 *
	 * @param string $integration_code Integration the owner belongs to, e.g. 'WP'.
	 * @param string $owner_type       Item or modifier discriminator.
	 * @param string $code             Trigger, action or modifier code.
	 */
	public function __construct( string $integration_code, string $owner_type, string $code ) {
		$this->integration_code = $integration_code;
		$this->owner_type       = $owner_type;
		$this->code             = $code;
	}

	/**
	 * Integration the owner belongs to, e.g. 'WP'.
	 *
	 * @return string
	 */
	public function get_integration_code(): string {
		return $this->integration_code;
	}

	/**
	 * Item or modifier discriminator.
	 *
	 * @return string
	 */
	public function get_owner_type(): string {
		return $this->owner_type;
	}

	/**
	 * Trigger, action or modifier code.
	 *
	 * @return string
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * Whether these coordinates point at a recipe item rather than a modifier.
	 *
	 * @return bool
	 */
	public function is_item(): bool {
		return in_array( $this->owner_type, self::ITEM_OWNER_TYPES, true );
	}

	/**
	 * Whether these coordinates point at a block modifier rather than an item.
	 *
	 * @return bool
	 */
	public function is_block_modifier(): bool {
		// Delegates to the catalog enum so the modifier allow-list has one
		// owner rather than a second copy here.
		return in_array( $this->owner_type, Block_Modifier_Types::get_all(), true );
	}
}
