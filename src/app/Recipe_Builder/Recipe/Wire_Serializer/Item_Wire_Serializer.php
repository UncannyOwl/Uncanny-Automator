<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects\Field_Context;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Tree item entry → ModelRecipeItem wire shape.
 *
 * Strict 10-key shape:
 *   { type='item', item_type, id, integration_code, code, is_item_on,
 *     custom_name, fields, last_known_integration_name, tokens }
 *
 * Replaces the legacy nested `backup` envelope. The FE Zod schema declares
 * `last_known_integration_name` as top-level optional; the field is
 * resolved live via Integration_Registry_Bridge (returns the integration
 * code itself when no display name is registered).
 *
 * Composes Field_Output_Serializer for the nested field shapes. `tokens` is
 * emitted as an empty placeholder — Recipe_Entity_Token_Decorator derives and
 * fills it at projection time (tree-stamped tokens never pass through).
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 */
final class Item_Wire_Serializer {

	/**
	 * Projects the item's field array into its wire envelope.
	 *
	 * @var Field_Output_Serializer
	 */
	private Field_Output_Serializer $fields;

	/**
	 * Resolves an integration code to the display name the builder shows.
	 *
	 * @var Integration_Registry_Bridge
	 */
	private Integration_Registry_Bridge $integrations;

	/**
	 * Build the serializer, defaulting to the production collaborators.
	 *
	 * @param Field_Output_Serializer|null    $fields       Serializes field arrays.
	 * @param Integration_Registry_Bridge|null $integrations Resolves integration display names.
	 */
	public function __construct(
		?Field_Output_Serializer $fields = null,
		?Integration_Registry_Bridge $integrations = null
	) {
		$this->fields       = $fields ?? new Field_Output_Serializer();
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
	}

	/**
	 * Project one tree item entry to its strict 10-key wire shape.
	 *
	 * @param array $item Tree item entry.
	 * @return array Wire-shape ModelRecipeItem (10 keys).
	 */
	public function serialize( array $item ): array {
		$item_type        = (string) ( $item['item_type'] ?? $item['kind'] ?? '' );
		$integration_code = (string) ( $item['integration_code'] ?? '' );
		$code             = (string) ( $item['code'] ?? '' );

		$custom_name_raw = $item['custom_name'] ?? null;
		$custom_name     = null === $custom_name_raw ? null : (string) $custom_name_raw;

		$ctx = '' !== $integration_code && '' !== $item_type && '' !== $code
			? new Field_Context( $integration_code, $item_type, $code )
			: null;

		return array(
			Wire_Key::TYPE                        => Wire_Node_Type::ITEM,
			Wire_Key::ITEM_TYPE                   => $item_type,
			Wire_Key::ID                          => (int) ( $item['id'] ?? 0 ),
			Wire_Key::INTEGRATION_CODE            => $integration_code,
			Wire_Key::CODE                        => $code,
			Wire_Key::IS_ITEM_ON                  => (bool) ( $item['is_item_on'] ?? false ),
			Wire_Key::CUSTOM_NAME                 => $custom_name,
			Wire_Key::FIELDS                      => $this->fields->serialize(
				is_array( $item['fields'] ?? null ) ? $item['fields'] : array(),
				$ctx
			),
			Wire_Key::LAST_KNOWN_INTEGRATION_NAME => '' !== $integration_code
				? $this->integrations->get_integration_name_by_code( $integration_code )
				: '',
			// Placeholder: Recipe_Entity_Token_Decorator derives tokens at projection time.
			Wire_Key::TOKENS                      => array(),
		);
	}

	/**
	 * Project a list of item tree entries. Skips non-array entries.
	 *
	 * @param iterable<mixed> $items
	 * @return list<array>
	 */
	public function serialize_list( iterable $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$out[] = $this->serialize( $item );
			}
		}
		return $out;
	}
}
