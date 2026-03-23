<?php
/**
 * Filter Field Normalizer - Domain Service.
 *
 * Handles conversion between flat field structure (from MCP/API)
 * and nested field structure (for domain model).
 *
 * This is a DOMAIN SERVICE because:
 * - It operates on domain concepts (Fields)
 * - It doesn't fit naturally in any single aggregate
 * - It contains pure business logic (no infrastructure)
 * - It's stateless and can be reused across aggregates
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Loop\Filter\Services;

/**
 * Field Normalizer Class.
 *
 * Converts between flat and nested field representations.
 */
class Field_Normalizer {

	/**
	 * Normalize flat fields to nested structure.
	 *
	 * Converts flat API structure:
	 * {FIELD: value, FIELD_readable: label}
	 *
	 * To nested domain structure:
	 * {FIELD: {type, value, readable, backup}}
	 *
	 * @param array $flat_fields    Flat field structure from API.
	 * @param array $meta_structure Field definitions from registry.
	 * @return array Nested field structure for domain model.
	 */
	public function normalize_to_nested( array $flat_fields, array $meta_structure ): array {
		if ( empty( $flat_fields ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $flat_fields as $code => $value ) {
			// Skip _readable suffix fields (they're used in the conversion)
			if ( $this->is_readable_suffix( $code ) ) {
				continue;
			}

			// Already nested? Keep as-is
			if ( $this->is_already_nested( $value ) ) {
				$normalized[ $code ] = $value;
				continue;
			}

			// Convert flat to nested
			$normalized[ $code ] = $this->convert_to_nested(
				$code,
				$value,
				$flat_fields,
				$meta_structure
			);
		}

		return $normalized;
	}

	/**
	 * Check if field code has _readable suffix.
	 *
	 * @param string $code Field code.
	 * @return bool True if has _readable suffix.
	 */
	private function is_readable_suffix( string $code ): bool {
		return str_ends_with( $code, '_readable' );
	}

	/**
	 * Check if value is already in nested format.
	 *
	 * Nested format has 'value' key: {value: ..., readable: ..., backup: ...}
	 *
	 * @param mixed $value Field value to check.
	 * @return bool True if already nested.
	 */
	private function is_already_nested( $value ): bool {
		return is_array( $value ) && isset( $value['value'] );
	}

	/**
	 * Convert flat field to nested format.
	 *
	 * Enriches the field with metadata from registry and readable value.
	 *
	 * @param string $code          Field code.
	 * @param mixed  $value         Field value.
	 * @param array  $flat_fields   All flat fields (to find readable).
	 * @param array  $meta_structure Registry metadata.
	 * @return array Nested field structure.
	 */
	private function convert_to_nested(
		string $code,
		$value,
		array $flat_fields,
		array $meta_structure
	): array {
		$field_config = $meta_structure[ $code ] ?? array();

		return array(
			'type'     => $field_config['type'] ?? 'select',
			'value'    => $value,
			'readable' => $flat_fields[ "{$code}_readable" ] ?? $value,
			'backup'   => array(
				'label'                    => $field_config['label'] ?? $code,
				'show_label_in_sentence'   => $field_config['show_label_in_sentence'] ?? true,
				'supports_custom_value'    => $field_config['supports_custom_value'] ?? true,
				'supports_multiple_values' => $field_config['supports_multiple_values'] ?? false,
			),
		);
	}

	/**
	 * Extract field values for validation.
	 *
	 * Extracts just the raw values (not labels) from flat or nested fields.
	 * Used for token validation which only needs values.
	 *
	 * @param array $fields Flat or nested fields.
	 * @return array Map of field codes to values.
	 */
	public function extract_values_for_validation( array $fields ): array {
		$values = array();

		foreach ( $fields as $code => $field ) {
			// Skip _readable suffix fields
			if ( $this->is_readable_suffix( $code ) ) {
				continue;
			}

			// Extract value from nested format
			if ( is_array( $field ) && isset( $field['value'] ) ) {
				$values[ $code ] = $field['value'];
			} else {
				// Flat format - use directly
				$values[ $code ] = $field;
			}
		}

		return $values;
	}
}
