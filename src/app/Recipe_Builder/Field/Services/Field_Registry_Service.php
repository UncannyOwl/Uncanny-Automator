<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Services;

use Uncanny_Automator\App\Bridge\Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Trigger_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Definition_Bridge;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Field\Enums\Field_Types;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities\Field_Value_Normalizer;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_Registry_Service;
use WP_Error;

/**
 * Field Registry Service — single source of truth for catalog-level field
 * materialization across triggers, actions and block_modifiers.
 *
 * Consumed by the FE lazy-field REST routes (Item_Fields_Route,
 * Block_Modifier_Fields_Route) and the MCP services
 * (Trigger_Registry_Service, Action_Registry_Service).
 *
 * All legacy registry access is gated by a Bridge — zero direct
 * Automator()->* calls in this file.
 */
final class Field_Registry_Service {

	/**
	 * Field keys the FE FieldSchema declares `z.boolean().optional()`.
	 * Present-but-non-bool values fail the FE parse, so the boundary
	 * coerces every scalar to a strict bool and drops non-scalar values.
	 *
	 * @var array<string>
	 */
	private const WIRE_BOOL_KEYS = array(
		'required',
		'show_label_in_sentence',
		'supports_tokens',
		'exclude_default_token',
		'only_loopable_tokens',
		'supports_tinymce',
		'supports_fullpage_editing',
		'supports_markdown',
		'supports_media',
		'supports_custom_value',
		'supports_multiple_values',
		'options_show_id',
		'is_toggle',
		'hide_header',
		'hide_actions',
	);

	/**
	 * Field keys the FE FieldSchema declares `z.string().optional()`.
	 * Legacy authors put scalars in them (`description => false` meaning
	 * "none", numeric placeholders); the boundary casts scalars to string
	 * and drops non-scalar values.
	 *
	 * @var array<string>
	 */
	private const WIRE_STRING_KEYS = array(
		'description',
		'placeholder',
		'token_name',
		'custom_value_description',
		'add_row_button',
		'remove_row_button',
		'layout',
	);

	/**
	 * Singleton instance.
	 *
	 * @var Field_Registry_Service|null
	 */
	private static ?Field_Registry_Service $instance = null;

	/**
	 * Legacy-registry seam supplying a trigger's declared field definitions.
	 *
	 * @var Trigger_Definition_Bridge
	 */
	private Trigger_Definition_Bridge $trigger_definitions;

	/**
	 * Legacy-registry seam supplying an action's declared field definitions.
	 *
	 * @var Action_Definition_Bridge
	 */
	private Action_Definition_Bridge $action_definitions;

	/**
	 * Supplies the field definitions declared by filter conditions.
	 *
	 * @var Condition_Registry_Service
	 */
	private Condition_Registry_Service $condition_registry_service;

	/**
	 * Supplies the field definitions declared by loop filters.
	 *
	 * @var Filter_Registry_Service
	 */
	private Filter_Registry_Service $filter_registry_service;

	/**
	 * Per-request item cache. Keyed by "{integration_code}:{item_type}:{item_code}".
	 * Stores the grouped shape; flat output is computed on demand.
	 *
	 * @var array<string, array|WP_Error>
	 */
	private array $item_cache = array();

	/**
	 * Per-request block-modifier cache. Keyed by "{block_modifier_type}:{integration_code}:{modifier_code}".
	 * Stores the flat shape (block modifiers have no grouped form).
	 *
	 * @var array<string, array|WP_Error>
	 */
	private array $block_modifier_cache = array();

	/**
	 * Constructor with lazy-default DI on every dependency.
	 *
	 * @param Trigger_Definition_Bridge|null  $trigger_definitions        Test seam.
	 * @param Action_Definition_Bridge|null   $action_definitions         Test seam.
	 * @param Condition_Registry_Service|null $condition_registry_service Test seam.
	 * @param Filter_Registry_Service|null    $filter_registry_service    Test seam.
	 */
	public function __construct(
		?Trigger_Definition_Bridge $trigger_definitions = null,
		?Action_Definition_Bridge $action_definitions = null,
		?Condition_Registry_Service $condition_registry_service = null,
		?Filter_Registry_Service $filter_registry_service = null
	) {
		$this->trigger_definitions        = $trigger_definitions ?? new Automator_Trigger_Definition_Bridge();
		$this->action_definitions         = $action_definitions ?? new Automator_Action_Definition_Bridge();
		$this->condition_registry_service = $condition_registry_service ?? Condition_Registry_Service::instance();
		$this->filter_registry_service    = $filter_registry_service ?? Filter_Registry_Service::instance();
	}

	/**
	 * Singleton accessor.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Factory for tests — always returns a fresh instance with the
	 * supplied (or lazy-default) dependencies.
	 */
	public static function create_with_dependencies(
		?Trigger_Definition_Bridge $trigger_definitions = null,
		?Action_Definition_Bridge $action_definitions = null,
		?Condition_Registry_Service $condition_registry_service = null,
		?Filter_Registry_Service $filter_registry_service = null
	): self {
		return new self(
			$trigger_definitions,
			$action_definitions,
			$condition_registry_service,
			$filter_registry_service
		);
	}

	/**
	 * Flat list of raw field configs for an integration item (trigger / action).
	 *
	 * @param string $integration_code
	 * @param string $item_type
	 * @param string $item_code
	 * @return array|WP_Error
	 */
	public function get_item_fields( string $integration_code, string $item_type, string $item_code ) {
		$grouped = $this->get_item_fields_grouped( $integration_code, $item_type, $item_code );

		if ( is_wp_error( $grouped ) ) {
			return $grouped;
		}

		return $this->flatten_grouped( $grouped );
	}

	/**
	 * Whether an item declares any field of its author's own: anything but
	 * the custom item name this registry appends to every item. An item
	 * whose fields cannot be resolved counts as declaring some, so a caller
	 * deciding "nothing to configure" fails closed.
	 *
	 * @param string $integration_code Integration code.
	 * @param string $item_type        Item type (trigger, action).
	 * @param string $item_code        Item code.
	 * @return bool
	 */
	public function declares_author_fields( string $integration_code, string $item_type, string $item_code ): bool {
		$fields = $this->get_item_fields( $integration_code, $item_type, $item_code );
		if ( is_wp_error( $fields ) ) {
			return true;
		}

		foreach ( $fields as $field ) {
			if ( Custom_Item_Name_Field::FIELD_CODE !== ( $field['option_code'] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Grouped raw field configs for an integration item — for MCP JSON-Schema generators.
	 *
	 * @param string $integration_code
	 * @param string $item_type
	 * @param string $item_code
	 * @return array|WP_Error
	 */
	public function get_item_fields_grouped( string $integration_code, string $item_type, string $item_code ) {
		if ( ! Integration_Item_Types::is_valid( $item_type ) ) {
			return new WP_Error(
				'invalid_item_type',
				sprintf(
					/* translators: %s: list of allowed item types */
					esc_html_x( 'Invalid item_type. Allowed values: %s', 'Field_Registry_Service error', 'uncanny-automator' ),
					esc_html( implode( ', ', Integration_Item_Types::get_all() ) )
				),
				array( 'status' => 400 )
			);
		}

		$cache_key = "{$integration_code}:{$item_type}:{$item_code}";

		if ( array_key_exists( $cache_key, $this->item_cache ) ) {
			return $this->item_cache[ $cache_key ];
		}

		$definition = $this->fetch_item_definition( $integration_code, $item_type, $item_code );
		$result     = $this->materialize_definition_grouped( $definition, $integration_code, $item_type, $item_code );

		$this->item_cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Flat list of raw field configs for a block modifier (filter_condition / loop_filter),
	 * with the global custom-label field appended last.
	 *
	 * @param string $integration_code
	 * @param string $block_modifier_type
	 * @param string $modifier_code
	 * @return array|WP_Error
	 */
	public function get_block_modifier_fields( string $integration_code, string $block_modifier_type, string $modifier_code ) {
		if ( ! Block_Modifier_Types::is_valid( $block_modifier_type ) ) {
			return new WP_Error(
				'invalid_block_modifier_type',
				sprintf(
					/* translators: %s: list of allowed block modifier types */
					esc_html_x( 'Invalid block_modifier_type. Allowed values: %s', 'Field_Registry_Service error', 'uncanny-automator' ),
					esc_html( implode( ', ', Block_Modifier_Types::get_all() ) )
				),
				array( 'status' => 400 )
			);
		}

		$cache_key = "{$block_modifier_type}:{$integration_code}:{$modifier_code}";

		if ( array_key_exists( $cache_key, $this->block_modifier_cache ) ) {
			return $this->block_modifier_cache[ $cache_key ];
		}

		switch ( $block_modifier_type ) {
			case Block_Modifier_Types::FILTER_CONDITION:
				$fields = $this->condition_registry_service->get_raw_condition_fields( $integration_code, $modifier_code );
				break;
			case Block_Modifier_Types::LOOP_FILTER:
				$fields = $this->filter_registry_service->get_filter_fields( $modifier_code );
				break;
			default:
				$fields = array();
		}

		// Pro authors sometimes return fields wrapped in a single-key
		// envelope `[ MODIFIER_CODE => [fields] ]` (loop filters do this
		// via Loop_Filter::set_fields( load_options )). Detect and unwrap
		// before normalizing.
		$fields = $this->unwrap_single_key_envelope( $fields );

		// Normalize each field for the wire contract (type→input_type alias,
		// coerce option values + text to string).
		if ( is_array( $fields ) ) {
			$fields = array_map(
				function ( $field ) {
					return is_array( $field ) ? $this->normalize_field_for_wire( $field ) : $field;
				},
				$fields
			);
		}

		// An empty delegate result is ambiguous: the code may be unregistered,
		// or registered and simply fieldless (GEN/loop_filter/ITEM_NOT_EMPTY).
		// Both used to 404, which left a fieldless modifier with nowhere to put
		// its label. Ask the registry to tell them apart — but only when the
		// list is empty, so a configurable modifier never pays for the lookup.
		if ( empty( $fields ) && ! $this->block_modifier_is_registered( $integration_code, $block_modifier_type, $modifier_code ) ) {
			$result = new WP_Error(
				'item_not_found',
				esc_html_x( 'Block modifier not registered.', 'Field_Registry_Service error', 'uncanny-automator' ),
				array( 'status' => 404 )
			);
		} else {
			$fields[] = $this->normalize_field_for_wire( Custom_Item_Name_Field::definition( $block_modifier_type ) );
			$result   = $fields;
		}

		$this->block_modifier_cache[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Empty both caches. Wired to `automator_recipe_completed` via
	 * Application_Bootstrap so long-running workers don't grow unbounded.
	 */
	public function clear_cache(): void {
		$this->item_cache           = array();
		$this->block_modifier_cache = array();
	}

	/**
	 * Whether a block-modifier code is registered at all.
	 *
	 * Only consulted when the delegate returned no fields, to separate
	 * "registered but declares nothing" from "unknown code". The two
	 * registries answer differently: the filter registry returns a bool, the
	 * condition registry an `['exists' => bool]` envelope.
	 *
	 * @param string $integration_code
	 * @param string $block_modifier_type
	 * @param string $modifier_code
	 * @return bool
	 */
	private function block_modifier_is_registered( string $integration_code, string $block_modifier_type, string $modifier_code ): bool {
		switch ( $block_modifier_type ) {
			case Block_Modifier_Types::FILTER_CONDITION:
				$exists = $this->condition_registry_service->condition_exists( $integration_code, $modifier_code );
				return is_array( $exists ) && ! empty( $exists['exists'] );
			case Block_Modifier_Types::LOOP_FILTER:
				return $this->filter_registry_service->is_registered( $modifier_code );
		}

		return false;
	}

	/**
	 * Fetch the raw legacy definition for an item from the matching Bridge.
	 *
	 * Trigger / action codes are globally unique, so the integration_code
	 * arg is unused.
	 *
	 * @param string $integration_code
	 * @param string $item_type
	 * @param string $item_code
	 * @return array|null
	 */
	private function fetch_item_definition( string $integration_code, string $item_type, string $item_code ): ?array {
		switch ( $item_type ) {
			case Integration_Item_Types::TRIGGER:
				return $this->trigger_definitions->get_trigger_definition( $item_code );
			case Integration_Item_Types::ACTION:
				return $this->action_definitions->get_action_definition( $item_code );
		}

		return null;
	}

	/**
	 * Materialize a raw legacy definition into the canonical grouped cache
	 * shape (legacy-mixed-key — see normalize_to_legacy_grouped). Invokes
	 * `options_callback` if present (modern triggers/actions); otherwise
	 * reads static `options` directly (trigger/action definitions that
	 * pre-baked their options).
	 *
	 * The callback's answer then passes through `automator_options_callback_response`,
	 * as it does in the 7.x builder and the run-time resolver, so fields an
	 * extension adds through that filter are part of the item.
	 *
	 * Appends the global custom-label field last, so every item carries it
	 * whether or not its author declared anything.
	 *
	 * @param array|null $definition
	 * @param string     $integration_code Integration code the caller asked for.
	 * @param string     $item_type        Integration_Item_Types literal, for the appended field's label.
	 * @param string     $item_code        Item code.
	 * @return array|WP_Error
	 */
	private function materialize_definition_grouped( ?array $definition, string $integration_code, string $item_type, string $item_code ) {
		if ( null === $definition ) {
			return new WP_Error(
				'item_not_found',
				esc_html_x( 'Item not registered.', 'Field_Registry_Service error', 'uncanny-automator' ),
				array( 'status' => 404 )
			);
		}

		if ( isset( $definition['options_callback'] ) && is_callable( $definition['options_callback'] ) ) {
			$callback = $definition['options_callback'];
			$item     = $this->options_callback_item( $definition, $integration_code, $item_code );

			try {
				$definition = call_user_func( $callback );
			} catch ( \Throwable $e ) {
				automator_log( $e->getMessage(), 'Field_Registry_Service materialization callback threw' );
				return new WP_Error(
					'field_materialization_failed',
					$e->getMessage(),
					array( 'status' => 500 )
				);
			}

			if ( ! is_array( $definition ) ) {
				return new WP_Error(
					'field_materialization_invalid',
					esc_html_x( 'options_callback returned a non-array.', 'Field_Registry_Service error', 'uncanny-automator' ),
					array( 'status' => 500 )
				);
			}

			$definition = $this->filter_options_callback_response( $definition, $callback, $item, $item_type );

			if ( is_wp_error( $definition ) ) {
				return $definition;
			}
		}

		$normalized = $this->normalize_to_legacy_grouped( $definition );

		if ( Integration_Item_Types::ACTION === $item_type ) {
			foreach ( $normalized as $group_code => $group ) {
				$normalized[ $group_code ] = array_map(
					function ( $field ) {
						return is_array( $field ) ? $this->with_action_custom_value_default( $field ) : $field;
					},
					$group
				);
			}
		}

		$normalized[ Custom_Item_Name_Field::FIELD_CODE ] = array(
			$this->normalize_field_for_wire( Custom_Item_Name_Field::definition( $item_type ) ),
		);

		return $normalized;
	}

	/**
	 * The item `automator_options_callback_response` receives: the shape the
	 * 7.x builder passed, with the item's integration and code under `meta`.
	 * The registry is catalog-level, so there is no recipe post behind it.
	 *
	 * The integration is the one the item registered with; codes are globally
	 * unique, so the caller's integration code only stands in for a definition
	 * that does not name one.
	 *
	 * @since 8.0.0
	 *
	 * @param array  $definition       The item's registered definition.
	 * @param string $integration_code Integration code the caller asked for.
	 * @param string $item_code        Item code.
	 * @return array{meta: array{integration: string, code: string}}
	 */
	private function options_callback_item( array $definition, string $integration_code, string $item_code ): array {
		$registered = $definition['integration'] ?? '';

		return array(
			'meta' => array(
				'integration' => ( is_string( $registered ) && '' !== $registered ) ? $registered : $integration_code,
				'code'        => $item_code,
			),
		);
	}

	/**
	 * Pass an options callback's answer through `automator_options_callback_response`
	 * with the arguments the 7.x builder and the run-time resolver give it:
	 * the answer, the callback, the item, the recipe (none here) and the
	 * plural item type. A subscriber that throws or answers with a non-array
	 * fails the materialization the way a broken callback does.
	 *
	 * @since 8.0.0
	 *
	 * @param array    $response  The options callback's answer.
	 * @param callable $callback  The item's options callback.
	 * @param array    $item      The item, from options_callback_item().
	 * @param string   $item_type Integration_Item_Types literal.
	 * @return array|WP_Error
	 */
	private function filter_options_callback_response( array $response, callable $callback, array $item, string $item_type ) {
		$type = Integration_Item_Types::TRIGGER === $item_type ? 'triggers' : 'actions';

		try {
			$filtered = Dispatcher::filter( 'automator_options_callback_response', $response, $callback, $item, array(), $type );
		} catch ( \Throwable $e ) {
			automator_log( $e->getMessage(), 'Field_Registry_Service automator_options_callback_response subscriber threw' );
			return new WP_Error(
				'field_materialization_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}

		if ( ! is_array( $filtered ) ) {
			return new WP_Error(
				'field_materialization_invalid',
				esc_html_x( 'automator_options_callback_response returned a non-array.', 'Field_Registry_Service error', 'uncanny-automator' ),
				array( 'status' => 500 )
			);
		}

		return $filtered;
	}

	/**
	 * Normalize the raw materialized `[ 'options' => [...], 'options_group' => [...] ]`
	 * source into the legacy-mixed-key shape that downstream walkers consume:
	 *
	 *     [ 'OPTION_CODE_A' => [field], 'OPTION_CODE_B' => [field], 'GROUP_CODE' => [field, field, ...] ]
	 *
	 * Top-level `options` entries are keyed by their own `option_code` (each
	 * wrapped in a `[field]` array — matches Fields::normalize_options_fields()
	 * which appends, since the same `option_code` could appear more than once).
	 * `options_group` entries are keyed by the group_code with the existing
	 * array of fields as the value.
	 *
	 * This is the shape that Trigger_Schema_Converter / Field_Json_Schema /
	 * Field_Validator / Field_Label_Resolver / Dropdown_Controller all walk
	 * as `foreach ($fields as $field_group) foreach ($field_group as $field)`.
	 *
	 * Note: this shape is transitional. Field groups themselves are going away
	 * with the new FE builder; when downstream consumers move to flat input,
	 * this normalization disappears and the service returns flat only.
	 *
	 * @param array $source Raw materialized callback output.
	 * @return array<string, array> Single-level keyed map; every value is `[field, ...]`.
	 */
	private function normalize_to_legacy_grouped( array $source ): array {
		$normalized = array();

		if ( isset( $source['options'] ) && is_array( $source['options'] ) ) {
			foreach ( $source['options'] as $option ) {
				if ( ! is_array( $option ) || ! isset( $option['option_code'] ) ) {
					continue;
				}
				$normalized[ $option['option_code'] ][] = $this->normalize_field_for_wire( $option );
			}
		}

		if ( isset( $source['options_group'] ) && is_array( $source['options_group'] ) ) {
			foreach ( $source['options_group'] as $group_code => $group ) {
				if ( ! is_array( $group ) ) {
					continue;
				}
				$normalized[ $group_code ] = array_map(
					function ( $field ) {
						return is_array( $field ) ? $this->normalize_field_for_wire( $field ) : $field;
					},
					$group
				);
			}
		}

		return $normalized;
	}

	/**
	 * Normalize a single field config for the wire contract:
	 *
	 * 1. Alias `type` → `input_type` if `input_type` is missing. Pro
	 *    loop-filter authors historically wrote `'type' => 'select'`; the
	 *    FE Zod schema (`FieldSchema.input_type`) is strict on
	 *    `input_type`. Aliasing at the boundary lets authors keep their
	 *    existing PHP without breaking the FE contract.
	 * 2. Coerce `options[].value` and `options[].text` to string. Integration
	 *    authors historically wrote `'value' => 1527` (int) because the
	 *    legacy WP form bridge tolerated mixed types; FE Zod is strict
	 *    (`z.string()`).
	 *
	 * MCP schema converters were already string-tolerant via internal
	 * casts and read `input_type` directly, so the changes are
	 * invisible to them — only the FE-facing contract is affected.
	 *
	 * Recurses into repeater `fields` children — the FE repeater renderer
	 * re-parses each row with the same FieldSchema, so every normalization
	 * here applies to children too.
	 *
	 * @param array $field Raw field config from the integration.
	 * @return array Field with input_type aliased and options coerced.
	 */
	private function normalize_field_for_wire( array $field ): array {
		// Strip null-valued keys — FE FieldSchema declares fields like
		// `description: z.string().optional()` which accepts string|undefined,
		// not null. Integration authors sometimes return `null` when no value
		// is set rather than omitting the key. Stripping converts to "key
		// absent" which Zod's `.optional()` accepts.
		foreach ( $field as $key => $value ) {
			if ( null === $value ) {
				unset( $field[ $key ] );
			}
		}

		// Unwrap `supports_tokens: [ bool ]` to a bare boolean — some Pro
		// authors wrap the flag in a single-element array.
		if ( isset( $field['supports_tokens'] )
			&& is_array( $field['supports_tokens'] )
			&& count( $field['supports_tokens'] ) === 1
		) {
			$field['supports_tokens'] = (bool) reset( $field['supports_tokens'] );
		}

		// Feature flags are z.boolean().optional() on the FE — 'true', 1,
		// '0', 'yes' all fail the parse, so every scalar becomes a strict
		// bool. Only non-scalars (arrays, objects) are genuinely ambiguous
		// and drop the key: absence is not neutral, because the validator,
		// the MCP schema and the builder each read an absent flag as their
		// own default rather than as the author's declaration.
		foreach ( self::WIRE_BOOL_KEYS as $bool_key ) {
			if ( ! array_key_exists( $bool_key, $field ) || is_bool( $field[ $bool_key ] ) ) {
				continue;
			}
			if ( is_scalar( $field[ $bool_key ] ) ) {
				$field[ $bool_key ] = $this->declared_flag_to_bool( $field[ $bool_key ] );
			} else {
				unset( $field[ $bool_key ] );
			}
		}

		// Descriptive keys are z.string().optional() on the FE. Cast scalars
		// ((string) false === '', matching the legacy falsy no-render); drop
		// non-scalar garbage since the keys are schema-optional.
		foreach ( self::WIRE_STRING_KEYS as $string_key ) {
			if ( ! array_key_exists( $string_key, $field ) || is_string( $field[ $string_key ] ) ) {
				continue;
			}
			if ( is_scalar( $field[ $string_key ] ) ) {
				$field[ $string_key ] = (string) $field[ $string_key ];
			} else {
				unset( $field[ $string_key ] );
			}
		}

		// `relevant_tokens` is declared by legacy options as an associative
		// {token_code: label} map, which wp_json_encode emits as a JSON
		// OBJECT — the FE FieldSchema requires z.array (entries opaque).
		// Map → list of {token_code, label} rows; lists (incl. empty) pass
		// through; non-array garbage drops the key (schema-optional).
		if ( array_key_exists( 'relevant_tokens', $field ) ) {
			$field = $this->normalize_relevant_tokens_for_wire( $field );
		}

		// Alias `type` → `input_type` if needed, then drop the legacy
		// `type` key — `input_type` is the single canonical name (matches
		// FE FieldSchema). Avoids two keys for the same concept and the
		// drift risk that comes with it.
		if ( isset( $field['type'] ) ) {
			if ( ! isset( $field['input_type'] ) ) {
				$field['input_type'] = $field['type'];
			}
			unset( $field['type'] );
		}

		// Options builders return `false`/garbage when a remote API is
		// unreachable; FE `options` is z.array().optional(), so a non-array
		// fails the parse while an absent key is fine — drop the key.
		if ( array_key_exists( 'options', $field ) && ! is_array( $field['options'] ) ) {
			unset( $field['options'] );
		}

		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			$field['options'] = $this->normalize_options_for_wire( $field['options'] );
		}

		// Visibility rules declared with operator OR predate the FE schema,
		// whose operator enum only accepts 'AND'. The runtime evaluates each
		// rule independently (any true rule applies its resulting_visibility,
		// false rules are no-ops), so OR(c1..cn) is exactly equivalent to n
		// single-condition AND rules — split at the boundary.
		if ( isset( $field['dynamic_visibility'] ) && is_array( $field['dynamic_visibility'] ) ) {
			$field['dynamic_visibility'] = $this->normalize_dynamic_visibility_for_wire( $field['dynamic_visibility'] );
		}

		// Repeater children: the FE repeater renderer re-parses every row
		// with the same FieldSchema, so children carry the full invariant
		// set — recurse so every boundary normalization applies to them.
		if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$field['fields'] = array_map(
				function ( $child ) {
					return is_array( $child ) ? $this->normalize_field_for_wire( $child ) : $child;
				},
				$field['fields']
			);
		}

		return $field;
	}

	/**
	 * State the builder's default on an action select that declares nothing
	 * about custom values.
	 *
	 * The builder offers a custom value on a single select of an action unless
	 * the field says `supports_custom_value => false` (recipe-builder legacy
	 * field, `index.js` "Check if it's a select"; the legacy PHP select()
	 * helper defaults the flag to true as well). Stating it here makes the
	 * field validator, the MCP schema and the MCP input resolver follow the
	 * builder instead of reading an absent flag as "no". Multi-selects, and
	 * selects on every other item type, keep the builder's default of no.
	 * Repeater rows inherit the item's type, so their selects get the same.
	 *
	 * @since 8.0.0
	 *
	 * @param array $field Wire-normalized field.
	 * @return array
	 */
	private function with_action_custom_value_default( array $field ): array {
		if ( Field_Types::SELECT === ( $field['input_type'] ?? '' )
			&& ! array_key_exists( 'supports_custom_value', $field )
			&& empty( $field['supports_multiple_values'] )
		) {
			$field['supports_custom_value'] = true;
		}

		if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
			$field['fields'] = array_map(
				function ( $child ) {
					return is_array( $child ) ? $this->with_action_custom_value_default( $child ) : $child;
				},
				$field['fields']
			);
		}

		return $field;
	}

	/**
	 * Read a scalar feature-flag declaration as the bool its author meant.
	 *
	 * A recognized boolish scalar ('false', '0', 'no', 1, 'yes') keeps its
	 * meaning. Any other scalar follows the legacy field contract, which
	 * reads the flag with `boolval()` (Automator_Recipe_Helpers_Field) and
	 * the builder with JS truthiness: Pro's date actions declare
	 * `supports_custom_value => 'Custom format'`, which means "on".
	 *
	 * @since 8.0.0
	 *
	 * @param bool|int|float|string $value Declared flag value.
	 * @return bool
	 */
	private function declared_flag_to_bool( $value ): bool {
		if ( Field_Value_Normalizer::is_boolish( $value ) ) {
			return Field_Value_Normalizer::to_bool( $value );
		}

		return (bool) $value;
	}

	/**
	 * Coerce a field's relevant_tokens declaration to the wire list shape.
	 *
	 * @param array $field Field config carrying a relevant_tokens key.
	 * @return array Field with relevant_tokens as a list of {token_code, label} rows, or the key removed.
	 */
	private function normalize_relevant_tokens_for_wire( array $field ): array {
		$raw = $field['relevant_tokens'];

		if ( ! is_array( $raw ) ) {
			unset( $field['relevant_tokens'] );
			return $field;
		}

		// Already a sequential list (including empty) — pass through.
		if ( array_values( $raw ) === $raw ) {
			return $field;
		}

		$list = array();
		foreach ( $raw as $token_code => $label ) {
			$list[] = array(
				'token_code' => (string) $token_code,
				'label'      => is_scalar( $label ) ? (string) $label : '',
			);
		}
		$field['relevant_tokens'] = $list;

		return $field;
	}

	/**
	 * Coerce an options declaration to the wire row shape.
	 *
	 * Three legacy shapes reach this boundary:
	 * - rows `[{value, text}, …]` — cast value/text to string (FE z.string());
	 * - a `{value: text}` map (LearnDash role/course selects) — JSON-encodes
	 *   as an object and fails the FE z.array; each entry becomes a row with
	 *   the key as value;
	 * - a plain scalar list `['red', 'green']` — each entry becomes a
	 *   self-labeled row.
	 * Anything else (objects, nested garbage) passes through for the FE to
	 * reject visibly rather than being silently dropped.
	 *
	 * @param array $options Raw options declaration.
	 * @return array Sequential list of wire-shaped option rows.
	 */
	private function normalize_options_for_wire( array $options ): array {
		$is_list = array_values( $options ) === $options;

		$normalized = array();
		foreach ( $options as $key => $option ) {
			if ( is_array( $option ) ) {
				if ( array_key_exists( 'value', $option ) ) {
					$option['value'] = (string) $option['value'];
				}
				if ( array_key_exists( 'text', $option ) ) {
					$option['text'] = (string) $option['text'];
				}
				$normalized[] = $option;
				continue;
			}

			if ( is_scalar( $option ) ) {
				$normalized[] = array(
					'value' => $is_list ? (string) $option : (string) $key,
					'text'  => (string) $option,
				);
				continue;
			}

			$normalized[] = $option;
		}

		return $normalized;
	}

	/**
	 * Split OR visibility rules into single-condition AND rules.
	 *
	 * The FE FieldDynamicVisibilityRuleOperatorSchema accepts only 'AND',
	 * while the runtime evaluator treats ANY operator !== 'AND' (explicit
	 * 'OR', missing key, null) as any-match via .some() — so every such
	 * rule is exactly equivalent to one single-condition AND rule per
	 * condition. The split preserves evaluator behavior bit-for-bit (a
	 * rule with no conditions never fires, so it yields zero rules).
	 * Rules whose rule_conditions is not an array pass through untouched.
	 *
	 * @param array $visibility The field's dynamic_visibility config.
	 * @return array Config with visibility_rules free of OR operators.
	 */
	private function normalize_dynamic_visibility_for_wire( array $visibility ): array {
		if ( ! isset( $visibility['visibility_rules'] ) || ! is_array( $visibility['visibility_rules'] ) ) {
			return $visibility;
		}

		$rules = array();
		foreach ( $visibility['visibility_rules'] as $rule ) {
			if ( ! is_array( $rule )
				|| 'AND' === ( $rule['operator'] ?? null )
				|| ! is_array( $rule['rule_conditions'] ?? null )
			) {
				$rules[] = $rule;
				continue;
			}

			foreach ( $rule['rule_conditions'] as $condition ) {
				$split                    = $rule;
				$split['operator']        = 'AND';
				$split['rule_conditions'] = array( $condition );

				$rules[] = $split;
			}
		}

		$visibility['visibility_rules'] = $rules;

		return $visibility;
	}

	/**
	 * Detect and unwrap the single-key envelope that Pro loop-filter
	 * registrations return. `Loop_Filter::set_fields( load_options )` produces
	 * `[ MODIFIER_CODE => [field, field, ...] ]`; the FE expects a flat
	 * array of field configs.
	 *
	 * Detection: if the first value of the array is itself an array AND
	 * does NOT have an `option_code` key, treat the outer as the envelope
	 * and return the inner value. Otherwise (the array is already a flat
	 * list of field configs) return verbatim.
	 *
	 * @param array $fields Either `[ field, field, ... ]` or `[ key => [ field, field, ... ] ]`.
	 * @return array Always a flat list of field configs (or empty array).
	 */
	private function unwrap_single_key_envelope( array $fields ): array {
		if ( empty( $fields ) ) {
			return $fields;
		}

		$first = reset( $fields );

		// Already a flat list — first entry is a field config.
		if ( is_array( $first ) && isset( $first['option_code'] ) ) {
			return $fields;
		}

		// Envelope — first value is the array of field configs.
		if ( is_array( $first ) ) {
			return $first;
		}

		return $fields;
	}

	/**
	 * Flatten the legacy-mixed-key grouped shape into a single ordered list
	 * of raw field configs. Walks the single-level keyed map and yields each
	 * field from every group, in declared order.
	 *
	 * @param array $grouped Output of normalize_to_legacy_grouped().
	 * @return array
	 */
	private function flatten_grouped( array $grouped ): array {
		$fields = array();

		foreach ( $grouped as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			foreach ( $group as $field ) {
				if ( is_array( $field ) && isset( $field['option_code'] ) ) {
					$fields[] = $field;
				}
			}
		}

		return $fields;
	}
}
