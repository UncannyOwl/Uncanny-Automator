<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects\Field_Context;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Tree modifier entry → ModelRecipeBlockModifier wire shape.
 *
 * Strict 8-key shape:
 *   { type='block_modifier', block_modifier_type, id, integration_code,
 *     code, custom_name, fields, last_known_integration_name }
 *
 * Modifiers NEVER carry `tokens`. The `id` may be int (loop_filter post id)
 * or string (filter_condition legacy hash); preserve the discriminator.
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 */
final class Modifier_Wire_Serializer {

	/**
	 * Projects the modifier's field array into its wire envelope.
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
	 * @param Field_Output_Serializer|null     $fields       Serializes field arrays.
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
	 * Project one tree modifier entry to its strict 8-key wire shape.
	 *
	 * @param array $modifier Tree modifier entry.
	 * @return array Wire-shape ModelRecipeBlockModifier (8 keys).
	 */
	public function serialize( array $modifier ): array {
		$integration_code    = (string) ( $modifier['integration_code'] ?? '' );
		$block_modifier_type = (string) ( $modifier['block_modifier_type'] ?? '' );
		$code                = (string) ( $modifier['code'] ?? '' );

		$id = $modifier['id'] ?? 0;
		if ( ! is_int( $id ) && ! is_string( $id ) ) {
			$id = 0;
		}

		$ctx = '' !== $integration_code && '' !== $block_modifier_type && '' !== $code
			? new Field_Context( $integration_code, $block_modifier_type, $code )
			: null;

		$custom_name_raw = $modifier['custom_name'] ?? null;
		$custom_name     = null === $custom_name_raw ? null : (string) $custom_name_raw;

		return array(
			Wire_Key::TYPE                        => Wire_Node_Type::BLOCK_MODIFIER,
			Wire_Key::BLOCK_MODIFIER_TYPE         => $block_modifier_type,
			Wire_Key::ID                          => $id,
			Wire_Key::INTEGRATION_CODE            => $integration_code,
			Wire_Key::CODE                        => $code,
			Wire_Key::CUSTOM_NAME                 => $custom_name,
			Wire_Key::FIELDS                      => $this->fields->serialize(
				is_array( $modifier['fields'] ?? null ) ? $modifier['fields'] : array(),
				$ctx
			),
			Wire_Key::LAST_KNOWN_INTEGRATION_NAME => '' !== $integration_code
				? $this->integrations->get_integration_name_by_code( $integration_code )
				: '',
		);
	}

	/**
	 * Project a list of modifier tree entries. Skips non-array entries.
	 *
	 * @param iterable<mixed> $modifiers
	 * @return list<array>
	 */
	public function serialize_list( iterable $modifiers ): array {
		$out = array();
		foreach ( $modifiers as $modifier ) {
			if ( is_array( $modifier ) ) {
				$out[] = $this->serialize( $modifier );
			}
		}
		return $out;
	}
}
