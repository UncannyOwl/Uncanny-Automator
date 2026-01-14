<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Trigger\Utilities;

/**
 * Trigger Schema Converter
 *
 * Converts Automator field configurations to JSON Schema format for MCP tools.
 * Separated from Trigger_Registry_Service to follow Single Responsibility Principle.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */
class Trigger_Schema_Converter {

	/**
	 * Convert fields array to JSON Schema format.
	 *
	 * @since 7.0.0
	 * @param array $fields Fields array from Fields service.
	 * @return array JSON Schema format.
	 */
	public function convert_fields_to_schema( array $fields ): array {

		$schema = array(
			'type'       => 'object',
			'properties' => array(),
			'required'   => array(),
		);

		foreach ( $fields as $field_group ) {

			if ( ! is_array( $field_group ) ) {
				continue;
			}

			foreach ( $field_group as $field ) {

				if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
					continue;
				}

				$option_code  = $field['option_code'];
				$field_schema = $this->convert_single_field_to_schema( $field );

				$schema['properties'][ $option_code ] = $field_schema;

				if ( ! empty( $field['required'] ) ) {
					$schema['required'][] = $option_code;
				}
			}
		}

		return $schema;
	}

	/**
	 * Convert a single Automator field to JSON Schema format.
	 *
	 * @since 7.0.0
	 * @param array $field Single field configuration.
	 * @return array JSON Schema for this field.
	 */
	public function convert_single_field_to_schema( array $field ): array {

		$type_mapping = array(
			'text'     => 'string',
			'email'    => 'string',
			'url'      => 'string',
			'textarea' => 'string',
			'select'   => 'string',
			'checkbox' => 'boolean',
			'int'      => 'integer',
			'float'    => 'number',
			'repeater' => 'array',
			'file'     => 'string',
			'date'     => 'string',
			'time'     => 'string',
			'datetime' => 'string',
			'color'    => 'string',
			'password' => 'string',
		);

		$input_type = $field['input_type'] ?? 'text';
		$schema     = array(
			'type' => $type_mapping[ $input_type ] ?? 'string',
		);

		// Build description
		$description_parts = array_filter(
			array(
				$field['label'] ?? '',
				$field['description'] ?? '',
				$field['custom_value_description'] ?? '',
			)
		);

		if ( $description_parts ) {
			$schema['description'] = implode( '. ', $description_parts );
		}

		// Add format for specific types
		$format_map = array(
			'email'    => 'email',
			'url'      => 'uri',
			'date'     => 'date',
			'time'     => 'time',
			'datetime' => 'date-time',
		);

		if ( isset( $format_map[ $input_type ] ) ) {
			$schema['format'] = $format_map[ $input_type ];
		}

		// Handle select field options using JSON Schema anyOf + const + title pattern.
		// This provides human-readable labels alongside values for AI consumption.
		if ( 'select' === $input_type && ! empty( $field['options'] ) ) {
			$any_of = array();

			foreach ( $field['options'] as $key => $option ) {
				// Modern format: ['value' => x, 'text' => 'Label']
				if ( is_array( $option ) && isset( $option['value'] ) ) {
					$any_of[] = array(
						'const' => $option['value'],
						'title' => $option['text'] ?? (string) $option['value'],
					);
				} elseif ( is_string( $option ) || is_numeric( $option ) ) {
					// Legacy format: [id => 'Label'] (e.g., WPForms uses this)
					$any_of[] = array(
						'const' => $key,
						'title' => (string) $option,
					);
				}
			}

			if ( $any_of ) {
				$schema['anyOf'] = $any_of;
			}
		}

		// Add default value
		if ( isset( $field['default_value'] ) && null !== $field['default_value'] ) {
			$schema['default'] = $field['default_value'];
		}

		// Add numeric constraints
		if ( in_array( $schema['type'], array( 'integer', 'number' ), true ) ) {
			if ( isset( $field['min_number'] ) && null !== $field['min_number'] ) {
				$schema['minimum'] = $field['min_number'];
			}
			if ( isset( $field['max_number'] ) && null !== $field['max_number'] ) {
				$schema['maximum'] = $field['max_number'];
			}
		}

		// Add placeholder as example
		if ( ! empty( $field['placeholder'] ) ) {
			$schema['examples'] = array( $field['placeholder'] );
		}

		// Handle repeater fields - define the structure of each row.
		if ( 'repeater' === $input_type && ! empty( $field['fields'] ) ) {
			$item_properties = array();
			$item_required   = array();

			// Check for AJAX dependency on the repeater (e.g., Google Sheets columns depend on worksheet).
			$ajax_config    = $field['ajax'] ?? array();
			$mapping_column = $ajax_config['mapping_column'] ?? '';
			$listen_fields  = $ajax_config['listen_fields'] ?? array();

			foreach ( $field['fields'] as $sub_field ) {
				if ( ! is_array( $sub_field ) || ! isset( $sub_field['option_code'] ) ) {
					continue;
				}

				$sub_code   = $sub_field['option_code'];
				$sub_schema = $this->convert_single_field_to_schema( $sub_field );

				// If this sub-field is the mapping column for AJAX options, mark it as dynamic.
				if ( $sub_code === $mapping_column && ! empty( $listen_fields ) ) {
					$parent_field              = $listen_fields[0];
					$sub_schema['enum']        = array(); // Empty enum signals dynamic options.
					$sub_schema['description'] = ( $sub_schema['description'] ?? $sub_field['label'] ?? '' ) .
						' [DYNAMIC: Valid values depend on the selected ' . $parent_field . '. ' .
						'Call get_field_options with field_code="' . $sub_code . '" and parent_field="' . $parent_field . '" to get valid values after selecting ' . $parent_field . '.]';
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

				// Build list of field names for description.
				$field_names = array_keys( $item_properties );
			}

			// Enhance description to explain repeater behavior.
			$base_description      = $schema['description'] ?? $field['label'] ?? '';
			$field_list            = ! empty( $field_names ) ? implode( ', ', $field_names ) : 'fields';
			$schema['description'] = $base_description . ' [REPEATER: This is an array - include multiple objects to add multiple rows. Each object needs: ' . $field_list . ']';

			// Add repeater-level description about the AJAX dependency.
			if ( ! empty( $mapping_column ) && ! empty( $listen_fields ) ) {
				$parent_field           = $listen_fields[0];
				$schema['description'] .= ' [WORKFLOW: First select ' . $parent_field . ', then call get_field_options for ' . $mapping_column . ' to get available options.]';
			}
		}

		return $schema;
	}

	/**
	 * Build input schema from trigger definition.
	 *
	 * @since 7.0.0
	 * @param array $definition Trigger definition.
	 * @return array Input schema.
	 */
	public function build_input_schema( array $definition ): array {
		$schema = array(
			'type'       => 'object',
			'properties' => array(),
		);

		// Add configuration fields if available
		if ( ! empty( $definition['fields'] ) ) {
			foreach ( $definition['fields'] as $field_code => $field_def ) {
				$schema['properties'][ $field_code ] = array(
					'type'        => $field_def['type'] ?? 'string',
					'description' => $field_def['label'] ?? $field_code,
				);

				if ( isset( $field_def['options'] ) && is_array( $field_def['options'] ) ) {
					$schema['properties'][ $field_code ]['enum'] = array_keys( $field_def['options'] );
				}

				if ( $field_def['required'] ?? false ) {
					$schema['required'][] = $field_code;
				}
			}
		}

		return $schema;
	}
}
