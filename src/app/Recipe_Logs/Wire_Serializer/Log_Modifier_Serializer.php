<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects\Field_Context;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Field_Output_Serializer;

/**
 * Serializes one block modifier (filter_condition / loop_filter) to the
 * strict ModelRecipeLogBlockModifier shape. Single-evaluation on the parent
 * payload; per-iteration outcomes belong to the iterations sub-resource.
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 */
final class Log_Modifier_Serializer {

	/**
	 * Non-empty sentinel for integration_code / code when unknown — the FE
	 * contract is `z.string().min(1)` on both; empty aborts the whole parse.
	 *
	 * @var string
	 */
	private const UNKNOWN_CODE = 'UNKNOWN';

	/**
	 * Status derivation for modifiers.
	 *
	 * @var Node_Status_Resolver
	 */
	private $resolver;

	/**
	 * Tree-field to FieldsOutput serializer.
	 *
	 * @var Field_Output_Serializer
	 */
	private $fields;

	/**
	 * Integration-name resolver.
	 *
	 * @var Integration_Registry_Bridge
	 */
	private $integrations;

	/**
	 * Wires up the modifier serializer's collaborators, defaulting each to its production implementation.
	 *
	 * @param Node_Status_Resolver|null       $resolver     Status derivation.
	 * @param Field_Output_Serializer|null     $fields       FieldsOutput serializer.
	 * @param Integration_Registry_Bridge|null $integrations Integration-name resolver.
	 */
	public function __construct(
		?Node_Status_Resolver $resolver = null,
		?Field_Output_Serializer $fields = null,
		?Integration_Registry_Bridge $integrations = null
	) {
		$this->resolver     = $resolver ?? new Node_Status_Resolver();
		$this->fields       = $fields ?? new Field_Output_Serializer();
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
	}

	/**
	 * Serialize a block modifier tree node with its run data.
	 *
	 * @param array<string,mixed> $modifier_node Tree modifier entry.
	 * @param int                 $block_id      Parent block post id.
	 * @param Run_Data_Bundle     $bundle        Run data.
	 * @param array<string,mixed> $ctx           is_loop_filter, loop_ran, chosen_path.
	 * @return array<string,mixed> The wire ModelRecipeLogBlockModifier entry.
	 */
	public function serialize( array $modifier_node, int $block_id, Run_Data_Bundle $bundle, array $ctx ): array {
		$integration_code    = (string) ( $modifier_node['integration_code'] ?? '' );
		$code                = (string) ( $modifier_node['code'] ?? '' );
		$id                  = $modifier_node['id'] ?? 0;
		$block_modifier_type = (string) ( $modifier_node['block_modifier_type'] ?? '' );

		$fields    = is_array( $modifier_node['fields'] ?? null ) ? $modifier_node['fields'] : array();
		$field_ctx = ( '' !== $integration_code && '' !== $block_modifier_type && '' !== $code )
			? new Field_Context( $integration_code, $block_modifier_type, $code )
			: null;

		$execution = array(
			'status' => $this->resolver->modifier( $modifier_node, $block_id, $bundle, $ctx )->to_array(),
		);
		if ( ! empty( $ctx['refusal_messages'] ) ) {
			$execution['result_message'] = implode( ' ', $ctx['refusal_messages'] );
		} elseif ( ! empty( $ctx['is_loop_filter'] ) ) {
			$outcome = $bundle->loop_filter_outcome( (int) $id );
			if ( null !== $outcome && ! $outcome['met'] && '' !== $outcome['message'] ) {
				$execution['result_message'] = Log_Message_Text::from_stored( $outcome['message'] );
			}
		} else {
			$legacy_message = $bundle->legacy_condition_message( $block_id, $id );
			if ( null !== $legacy_message ) {
				$execution['result_message'] = Log_Message_Text::from_stored( $legacy_message );
			}
		}

		// `fields` stays the saved configuration, tokens intact, exactly as Log_Item_Serializer
		// ships an item's — it is what the sentence renders pills from. The run's view of those
		// fields is the execution's, never the node's. An iterating modifier ships neither: no
		// single evaluation represents it, so its values live in the iterations sub-resource.
		$tokens = $bundle->tokens_map();
		if ( empty( $ctx['is_loop_filter'] ) && empty( $ctx['inside_loop'] ) && ! empty( $tokens ) && $this->references_tokens( $fields ) ) {
			$execution['tokens'] = $bundle->wire_tokens();
			$execution['fields'] = $this->fields->serialize( $this->evaluated_fields( $fields, $tokens ), $field_ctx );
		}

		$out = array(
			Wire_Key::TYPE                => Wire_Node_Type::BLOCK_MODIFIER,
			Wire_Key::BLOCK_MODIFIER_TYPE => $block_modifier_type,
			Wire_Key::ID                  => is_numeric( $id ) ? (int) $id : (string) $id,
			Wire_Key::INTEGRATION_CODE    => '' !== $integration_code ? $integration_code : self::UNKNOWN_CODE,
			Wire_Key::CODE                => '' !== $code ? $code : self::UNKNOWN_CODE,
			Wire_Key::FIELDS              => $this->fields->serialize( $fields, $field_ctx ),
			'execution'                   => $execution,
		);

		if ( '' !== $integration_code ) {
			$out[ Wire_Key::LAST_KNOWN_INTEGRATION_NAME ] = $this->integrations->get_integration_name_by_code( $integration_code );
		}

		return $out;
	}

	/**
	 * Whether any field value carries a token template.
	 *
	 * @param array<string, mixed> $fields Stored modifier fields, either shape.
	 * @return bool
	 */
	private function references_tokens( array $fields ): bool {
		foreach ( Fields_Bag::from_array( $fields )->to_array() as $entry ) {
			if ( false !== strpos( $entry['value'], '{{' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The fields with every token template replaced by the value the run recorded for it.
	 *
	 * @param array<string, mixed>  $fields Stored modifier fields, either shape.
	 * @param array<string, string> $tokens Run token map, braced keys.
	 * @return array<string, array{value: string, readable: string, custom: string}>
	 */
	private function evaluated_fields( array $fields, array $tokens ): array {
		$out = array();
		foreach ( Fields_Bag::from_array( $fields )->to_array() as $key => $entry ) {
			$entry['value']    = strtr( $entry['value'], $tokens );
			$entry['readable'] = strtr( $entry['readable'], $tokens );
			$out[ $key ]       = $entry;
		}

		return $out;
	}
}
