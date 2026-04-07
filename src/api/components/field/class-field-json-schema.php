<?php
/**
 * Field JSON Schema.
 *
 * This is Uncanny Automator Fields Business Logic for converting Automator field definitions to JSON Schema.
 *
 * Converts Automator field definitions to JSON Schema for MCP tool schemas.
 * Single source of truth for field → JSON Schema conversion.
 *
 * Mirrors the TypeScript field model at:
 *   src/assets/src/features/recipe-builder/types/recipe/common.d.ts
 *
 * RecipeFieldType = 'select' | 'textarea' | 'text' | 'url' | 'email'
 *                 | 'int' | 'float' | 'checkbox' | 'radio'
 *                 | 'date' | 'time' | 'repeater' | 'file'
 *
 * RecipeFieldValue = { type, value, readable?, backup?: { label, supports_custom_value, supports_multiple_values } }
 *
 * @package Uncanny_Automator\Api\Components\Field
 * @since   7.1.0
 */

declare( strict_types=1 );

namespace Uncanny_Automator\Api\Components\Field;

use Uncanny_Automator\Api\Components\Field\Enums\Field_Types;

/**
 * Converts Automator field definition arrays into JSON Schema objects.
 *
 * Used by Trigger_Registry_Service and Action_Registry_Service when
 * building the inputSchema for get_component_schema responses.
 *
 * @since 7.1.0
 */
class Field_Json_Schema {

	/**
	 * Maps Automator input_type → JSON Schema type.
	 *
	 * Mirrors RecipeFieldType from common.d.ts.
	 * 'int' maps to 'integer' (not the TS 'int' — JSON Schema uses 'integer').
	 *
	 * @var array<string, string>
	 */
	private const JSON_TYPE_MAP = array(
		Field_Types::TEXT     => 'string',
		Field_Types::TEXTAREA => 'string',
		Field_Types::SELECT   => 'string',
		Field_Types::URL      => 'string',
		Field_Types::EMAIL    => 'string',
		Field_Types::INTEGER  => 'integer',
		Field_Types::FLOAT    => 'number',
		Field_Types::CHECKBOX => 'boolean',
		Field_Types::RADIO    => 'string',
		Field_Types::DATE     => 'string',
		Field_Types::TIME     => 'string',
		Field_Types::REPEATER => 'array',
		Field_Types::FILE     => 'string',
		// Internal types (MARKDOWN, HTML) map to string — they're textarea variants.
		Field_Types::MARKDOWN => 'string',
		Field_Types::HTML     => 'string',
	);

	/**
	 * Maps Automator input_type → JSON Schema format hint.
	 *
	 * @var array<string, string>
	 */
	private const FORMAT_MAP = array(
		Field_Types::EMAIL => 'email',
		Field_Types::URL   => 'uri',
		Field_Types::DATE  => 'date',
		Field_Types::TIME  => 'time',
	);

	/**
	 * Convert a grouped fields array to a JSON Schema object.
	 *
	 * Input: nested arrays from Automator's Fields::get() — array of field groups,
	 * each group is an array of field definitions.
	 *
	 * @param array $field_groups Grouped fields from Fields::get().
	 *
	 * @return array JSON Schema { type: 'object', properties: {...}, required: [...] }.
	 */
	public function convert_fields_to_schema( array $field_groups ): array {

		$schema = array(
			'type'       => 'object',
			'properties' => array(),
			'required'   => array(),
		);

		foreach ( $field_groups as $group ) {

			if ( ! is_array( $group ) ) {
				continue;
			}

			foreach ( $group as $field ) {

				if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
					continue;
				}

				$code                          = $field['option_code'];
				$schema['properties'][ $code ] = $this->convert_single_field( $field );

				if ( ! empty( $field['required'] ) ) {
					$schema['required'][] = $code;
				}
			}
		}

		return $schema;
	}

	/**
	 * Convert a single Automator field definition to JSON Schema.
	 *
	 * Maps the PHP field array to the JSON Schema representation that MCP
	 * clients (AI agents) consume. Handles:
	 *
	 * - Type mapping (input_type → JSON Schema type)
	 * - Select options (anyOf with const + title)
	 * - Multi-select (type → array, items with anyOf)
	 * - supports_custom_value flag
	 * - Dynamic AJAX dropdowns (empty enum + get_field_options hint)
	 * - Repeater fields (items with sub-field schemas)
	 * - Numeric constraints (min/max)
	 * - Format hints (email, uri, date, time)
	 *
	 * @param array $field Single field definition from the integration.
	 *
	 * @return array JSON Schema for this field.
	 */
	public function convert_single_field( array $field ): array {

		$input_type    = $field['input_type'] ?? 'text';
		$is_multi      = ! empty( $field['supports_multiple_values'] );
		$allows_custom = ! empty( $field['supports_custom_value'] );

		$schema = array(
			'type' => self::JSON_TYPE_MAP[ $input_type ] ?? 'string',
		);

		$this->apply_description( $schema, $field );
		$this->apply_format( $schema, $input_type );

		// ── Select options ───────────────────────────────────────────
		$any_of = $this->build_select_options( $input_type, $field['options'] ?? array() );

		if ( $is_multi ) {
			$this->apply_multi_select( $schema, $any_of, $allows_custom );
		} elseif ( $any_of ) {
			$schema['anyOf'] = $any_of;
		}

		if ( Field_Types::SELECT === $input_type && ! $allows_custom ) {
			$schema['supports_custom_value'] = false;
		}

		// ── Default ──────────────────────────────────────────────────
		if ( isset( $field['default_value'] ) && null !== $field['default_value'] ) {
			$schema['default'] = $field['default_value'];
		}

		// ── Text length constraints ──────────────────────────────────
		if ( Field_Types::TEXT === $input_type ) {
			$schema['maxLength'] = 255;
		} elseif ( Field_Types::TEXTAREA === $input_type ) {
			$schema['maxLength'] = 8000;
		}

		// ── Numeric constraints ──────────────────────────────────────
		$this->apply_numeric_constraints( $schema, $field );

		// ── Placeholder as example ───────────────────────────────────
		if ( ! empty( $field['placeholder'] ) ) {
			$schema['examples'] = array( $field['placeholder'] );
		}

		// ── Dynamic AJAX dropdown ────────────────────────────────────
		if ( Field_Types::SELECT === $input_type && ! $is_multi ) {
			$this->apply_dynamic_dropdown( $schema, $field );
		}

		// ── Repeater sub-fields ──────────────────────────────────────
		if ( Field_Types::REPEATER === $input_type && ! empty( $field['fields'] ) ) {
			$this->apply_repeater( $schema, $field );
		}

		// ── Read-only fields ─────────────────────────────────────────
		if ( ! empty( $field['read_only'] ) ) {
			$schema['readOnly'] = true;
		}

		return $schema;
	}

	// ─── Private helpers ─────────────────────────────────────────────

	/**
	 * Apply description from label + description + custom_value_description.
	 *
	 * @param array $schema Schema to modify (by reference).
	 * @param array $field  Field definition.
	 */
	private function apply_description( array &$schema, array $field ): void {

		$parts = array_filter(
			array(
				$field['label'] ?? '',
				$field['description'] ?? '',
				$field['custom_value_description'] ?? '',
			)
		);

		if ( $parts ) {
			$schema['description'] = implode( '. ', $parts );
		}
	}

	/**
	 * Apply JSON Schema format hint.
	 *
	 * @param array  $schema     Schema to modify (by reference).
	 * @param string $input_type Automator input_type.
	 */
	private function apply_format( array &$schema, string $input_type ): void {

		if ( isset( self::FORMAT_MAP[ $input_type ] ) ) {
			$schema['format'] = self::FORMAT_MAP[ $input_type ];
		}
	}

	/**
	 * Build anyOf entries from select field options.
	 *
	 * Supports two option formats:
	 * - Modern: [ ['value' => 'x', 'text' => 'Label'], ... ]
	 * - Legacy: [ id => 'Label', ... ]
	 *
	 * @param string $input_type Field input type.
	 * @param array  $options    Field options.
	 *
	 * @return array anyOf entries (empty if not a select or no options).
	 */
	private function build_select_options( string $input_type, array $options ): array {

		if ( Field_Types::SELECT !== $input_type || empty( $options ) ) {
			return array();
		}

		$any_of = array();

		foreach ( $options as $key => $option ) {
			if ( is_array( $option ) && isset( $option['value'] ) ) {
				$any_of[] = array(
					'const' => (string) $option['value'],
					'title' => $option['text'] ?? (string) $option['value'],
				);
			} elseif ( is_string( $option ) || is_numeric( $option ) ) {
				$any_of[] = array(
					'const' => (string) $key,
					'title' => (string) $option,
				);
			}
		}

		return $any_of;
	}

	/**
	 * Apply multi-select wrapping: type → array, items with anyOf.
	 *
	 * @param array $schema        Schema to modify (by reference).
	 * @param array $any_of        anyOf entries from select options.
	 * @param bool  $allows_custom Whether custom values are accepted.
	 */
	private function apply_multi_select( array &$schema, array $any_of, bool $allows_custom ): void {

		$item_schema = array( 'type' => 'string' );

		if ( $any_of ) {
			$item_schema['anyOf'] = $any_of;
		}

		if ( ! $allows_custom && $any_of ) {
			$item_schema['description'] = 'Must be one of the listed values (IDs). Use get_field_options if the list is empty.';
		}

		$schema['type']  = 'array';
		$schema['items'] = $item_schema;

		$schema['description'] = ( $schema['description'] ?? '' )
			. ' [MULTI-SELECT: Pass an array of value IDs, e.g. ["1","3"]. Use get_field_options to discover valid IDs.]';
	}

	/**
	 * Apply numeric min/max constraints.
	 *
	 * @param array $schema Schema to modify (by reference).
	 * @param array $field  Field definition.
	 */
	private function apply_numeric_constraints( array &$schema, array $field ): void {

		if ( ! in_array( $schema['type'], array( 'integer', 'number' ), true ) ) {
			return;
		}

		if ( isset( $field['min_number'] ) && null !== $field['min_number'] ) {
			$schema['minimum'] = (float) $field['min_number'];
		}

		if ( isset( $field['max_number'] ) && null !== $field['max_number'] ) {
			$schema['maximum'] = (float) $field['max_number'];
		}
	}

	/**
	 * Apply dynamic dropdown hints for AJAX-backed selects with no static options.
	 *
	 * @param array $schema Schema to modify (by reference).
	 * @param array $field  Field definition.
	 */
	private function apply_dynamic_dropdown( array &$schema, array $field ): void {

		$has_ajax    = ! empty( $field['ajax'] );
		$has_options = ! empty( $field['options'] );

		if ( $has_ajax && ! $has_options ) {
			$schema['enum']        = array();
			$schema['description'] = ( $schema['description'] ?? '' )
				. ' [DROPDOWN: enum is empty because options are loaded dynamically.'
				. ' Call get_field_options with this field code to get valid values.]';
		}
	}

	/**
	 * Apply repeater field schema with sub-field definitions.
	 *
	 * Recursively converts sub-fields. Handles AJAX-dependent sub-fields
	 * (e.g., Google Sheets column mapping).
	 *
	 * @param array $schema Schema to modify (by reference).
	 * @param array $field  Repeater field definition.
	 */
	private function apply_repeater( array &$schema, array $field ): void {

		$item_properties = array();
		$item_required   = array();
		$field_names     = array();

		$ajax_config    = $field['ajax'] ?? array();
		$mapping_column = $ajax_config['mapping_column'] ?? '';
		$listen_fields  = $ajax_config['listen_fields'] ?? array();

		foreach ( $field['fields'] as $sub_field ) {

			if ( ! is_array( $sub_field ) || ! isset( $sub_field['option_code'] ) ) {
				continue;
			}

			$sub_code   = $sub_field['option_code'];
			$sub_schema = $this->convert_single_field( $sub_field );

			// Mark AJAX-dependent sub-fields as dynamic.
			if ( $sub_code === $mapping_column && ! empty( $listen_fields ) ) {
				$parent                    = $listen_fields[0];
				$sub_schema['enum']        = array();
				$sub_schema['description'] = ( $sub_schema['description'] ?? $sub_field['label'] ?? '' )
					. ' [DYNAMIC: Valid values depend on the selected ' . $parent . '.'
					. ' Call get_field_options with field_code="' . $sub_code
					. '" and parent_field="' . $parent
					. '" to get valid values after selecting ' . $parent . '.]';
			}

			$item_properties[ $sub_code ] = $sub_schema;

			if ( ! empty( $sub_field['required'] ) ) {
				$item_required[] = $sub_code;
			}
		}

		if ( ! empty( $item_properties ) ) {
			$schema['items'] = array(
				'type'       => 'object',
				'properties' => $item_properties,
			);

			if ( ! empty( $item_required ) ) {
				$schema['items']['required'] = $item_required;
			}

			$field_names = array_keys( $item_properties );
		}

		$base                  = $schema['description'] ?? $field['label'] ?? '';
		$field_list            = ! empty( $field_names ) ? implode( ', ', $field_names ) : 'fields';
		$schema['description'] = $base
			. ' [REPEATER: This is an array - include multiple objects to add multiple rows.'
			. ' Each object needs: ' . $field_list . ']';

		if ( ! empty( $mapping_column ) && ! empty( $listen_fields ) ) {
			$parent                 = $listen_fields[0];
			$schema['description'] .= ' [WORKFLOW: First select ' . $parent
				. ', then call get_field_options for ' . $mapping_column . ' to get available options.]';
		}
	}
}
