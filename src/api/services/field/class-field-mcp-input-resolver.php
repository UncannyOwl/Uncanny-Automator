<?php
/**
 * Field MCP Input Resolver.
 *
 * Resolves raw field values from MCP tool calls into the format
 * the recipe builder expects. Mirrors the frontend's valueLegacyMeta
 * and getReadableValue() behavior.
 *
 * Responsibilities:
 * - Multi-select: JSON-encode array values, generate comma-joined _readable
 * - Single select: resolve _readable from option labels
 * - Validate values against option lists when supports_custom_value=false
 *
 * @package Uncanny_Automator\Api\Services\Field
 * @since   7.1.0
 */

declare( strict_types=1 );

namespace Uncanny_Automator\Api\Services\Field;

use Uncanny_Automator\Services\Integrations\Fields;

/**
 * Resolves AI-provided field values into the storage format the recipe builder expects.
 *
 * @since 7.1.0
 */
class Field_Mcp_Input_Resolver {

	/**
	 * Normalize fields for a component.
	 *
	 * Loads field definitions from the registry, then normalizes each
	 * select/multi-select field: validates option values, JSON-encodes
	 * multi-select arrays, and generates _readable suffixes.
	 *
	 * @param string $object_type Component type: 'triggers' or 'actions'.
	 * @param string $code        Component code (e.g. 'FCMRTAGUSER').
	 * @param array  $fields      Raw fields from the AI.
	 *
	 * @return array Normalized fields with _readable suffixes added.
	 */
	public function normalize( string $object_type, string $code, array $fields ): array {

		$definitions = $this->load_field_definitions( $object_type, $code );

		if ( empty( $definitions ) ) {
			return $fields;
		}

		foreach ( $definitions as $def ) {

			$field_code = $def['option_code'] ?? '';

			if ( empty( $field_code ) || ! array_key_exists( $field_code, $fields ) ) {
				continue;
			}

			$input_type = $def['input_type'] ?? '';

			// Repeater normalization: auto-wrap single object in array.
			if ( 'repeater' === $input_type ) {
				$value = $fields[ $field_code ];
				if ( is_array( $value ) && ! wp_is_numeric_array( $value ) ) {
					$fields[ $field_code ] = array( $value );
				}

				// Validate repeater row structure — catch common AI hallucinations.
				// The value may be a JSON string (update path) or an array (create path).
				$repeater_value = $fields[ $field_code ] ?? array();

				// Guard against non-array, non-string values (e.g. int, bool).
				if ( ! is_array( $repeater_value ) && ! is_string( $repeater_value ) ) {
					$fields['__validation_error'] = sprintf(
						'%s must be an array of row objects, got %s.',
						$field_code,
						gettype( $repeater_value )
					);
					continue;
				}

				if ( is_string( $repeater_value ) ) {
					$decoded = json_decode( $repeater_value, true );
					if ( is_array( $decoded ) ) {
						$repeater_value = $decoded;
						$fields[ $field_code ] = $repeater_value;
					} else {
						// Non-JSON string is invalid for a repeater field.
						$fields['__validation_error'] = sprintf(
							'%s must be a JSON array of row objects. The provided string is not valid JSON.',
							$field_code
						);
						continue;
					}
				}
				$error = $this->detect_repeater_garbage( $repeater_value, $field_code );
				if ( null !== $error ) {
					$fields['__validation_error'] = $error;
					continue;
				}

				$error = $this->validate_repeater_row_keys( $repeater_value, $def['fields'] ?? array(), $field_code );
				if ( null !== $error ) {
					$fields['__validation_error'] = $error;
				}

				continue;
			}

			if ( 'select' !== $input_type ) {
				continue;
			}

			$is_multi      = ! empty( $def['supports_multiple_values'] );
			$allows_custom = ! empty( $def['supports_custom_value'] );
			$options_map   = $this->build_options_map( $def['options'] ?? array() );
			$value         = $fields[ $field_code ];

			if ( $is_multi ) {
				$fields = $this->normalize_multi_select( $fields, $field_code, $value, $options_map, $allows_custom );
			} else {
				$fields = $this->normalize_single_select( $fields, $field_code, $value, $options_map, $allows_custom );
			}
		}

		return $fields;
	}

	/**
	 * Normalize a multi-select field.
	 *
	 * Mirrors frontend getReadableValue() for multi-select:
	 *   value.map(id => getSelectOptionByValue(id).text).join(', ')
	 *
	 * @param array  $fields       All fields (modified in place).
	 * @param string $field_code   Field option code.
	 * @param mixed  $value        Raw value (should be array).
	 * @param array  $options_map  Option ID → label map.
	 * @param bool   $allows_custom Whether custom values are allowed.
	 *
	 * @return array Modified fields.
	 */
	private function normalize_multi_select( array $fields, string $field_code, $value, array $options_map, bool $allows_custom ): array {

		// Ensure value is an array.
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array( $value );
		}

		if ( ! is_array( $value ) ) {
			$value = array( $value );
		}

		// Cast all values to string (options keys are strings).
		$value = array_map( 'strval', $value );

		// Resolve readable labels.
		$labels = array();
		foreach ( $value as $v ) {
			if ( isset( $options_map[ $v ] ) ) {
				$labels[] = $options_map[ $v ];
			} elseif ( $allows_custom ) {
				$labels[] = $v;
			} else {
				$labels[] = $v; // Best effort — don't block, just pass through.
			}
		}

		// JSON-encode the array for storage (matches frontend behavior).
		$fields[ $field_code ] = wp_json_encode( $value );

		// Generate _readable as comma-joined labels (matches frontend getReadableValue).
		if ( ! isset( $fields[ $field_code . '_readable' ] ) || '' === $fields[ $field_code . '_readable' ] ) {
			$fields[ $field_code . '_readable' ] = implode( ', ', $labels );
		}

		return $fields;
	}

	/**
	 * Normalize a single-select field.
	 *
	 * Mirrors frontend getReadableValue() for single select:
	 *   field.find(`option[value="${value}"]`).text()
	 *
	 * @param array  $fields       All fields (modified in place).
	 * @param string $field_code   Field option code.
	 * @param mixed  $value        Raw value.
	 * @param array  $options_map  Option ID → label map.
	 * @param bool   $allows_custom Whether custom values are allowed.
	 *
	 * @return array Modified fields.
	 */
	private function normalize_single_select( array $fields, string $field_code, $value, array $options_map, bool $allows_custom ): array {

		$str_value = (string) $value;

		// Generate _readable if not already provided.
		if ( ! isset( $fields[ $field_code . '_readable' ] ) || '' === $fields[ $field_code . '_readable' ] ) {
			if ( isset( $options_map[ $str_value ] ) ) {
				$fields[ $field_code . '_readable' ] = $options_map[ $str_value ];
			} elseif ( $allows_custom ) {
				$fields[ $field_code . '_readable' ] = $str_value;
			}
		}

		return $fields;
	}

	/**
	 * Build a flat option_value → label map from field options.
	 *
	 * Handles both option formats:
	 * - Modern: [ ['value' => 'x', 'text' => 'Label'], ... ]
	 * - Legacy: [ id => 'Label', ... ]
	 *
	 * @param array $options Raw options from field definition.
	 *
	 * @return array<string, string> Value → label map.
	 */
	private function build_options_map( array $options ): array {

		$map = array();

		foreach ( $options as $key => $option ) {
			if ( is_array( $option ) && isset( $option['value'] ) ) {
				$map[ (string) $option['value'] ] = $option['text'] ?? (string) $option['value'];
			} elseif ( is_string( $option ) || is_numeric( $option ) ) {
				$map[ (string) $key ] = (string) $option;
			}
		}

		return $map;
	}

	/**
	 * Detect garbage repeater row structures from AI hallucinations.
	 *
	 * WHY THIS EXISTS:
	 * AI agents don't know repeater field formats (sub-fields are AJAX-loaded,
	 * not in the static schema). When the agent can't discover the format, it
	 * hallucinates one from training data. This detector catches invalid
	 * structures BEFORE persistence so the agent gets an actionable error.
	 *
	 * STRATEGY — ALLOWLIST, NOT BLOCKLIST:
	 * Instead of cataloging every possible hallucination pattern (fragile),
	 * we define what a VALID repeater looks like and reject everything else.
	 * This catches current and future hallucination patterns automatically.
	 *
	 *   Agent input
	 *       |
	 *       v
	 *   [Check 1] All values flat? (string, number, bool, flat array)
	 *       |no --> REJECT: "values must be flat scalars"
	 *       |yes
	 *       v
	 *   [Check 2] All columns in one row object? (not split into N objects)
	 *       |no --> REJECT: "all columns belong in one object"
	 *       |yes
	 *       v
	 *     ACCEPT
	 *
	 * WHAT PASSES (all values flat, correct structure):
	 *   [{"GS_COLUMN_NAME": "Source", "GS_COLUMN_VALUE": "x"}] schema option codes
	 *   [{"field": "{{user_email}}"}]                           tokens
	 *   [{"field": ["opt1", "opt2"]}]                           multi-select
	 *
	 * WHAT FAILS (nested values, wrong structure, or label-based keys):
	 *   [{"COLUMN": {"title": [...]}, "VALUE": {...}}]          nested objects
	 *   [{"key": "code", "value": {"text": {...}}}]             nested value
	 *   [{"Column": "x", "Value": "y"}]                        display labels, not option codes
	 *   [{"Name": "x"}, {"Date": "y"}]                         split columns
	 *   [{"f": {"nested": {"deep": "v"}}}]                     nested value
	 *
	 * @since 7.2.0
	 *
	 * @param array  $rows       Repeater rows (array of row objects).
	 * @param string $field_code The repeater field code (for error messages).
	 *
	 * @return string|null Error message if garbage detected, null if OK.
	 */
	private function detect_repeater_garbage( array $rows, string $field_code ): ?string {

		if ( empty( $rows ) ) {
			return null;
		}

		// Enforce maximum repeater row count to prevent memory issues.
		if ( count( $rows ) > 50 ) {
			return sprintf(
				'%s has %d rows. Maximum allowed is 50 rows per repeater field.',
				$field_code,
				count( $rows )
			);
		}

		$first_row = $rows[0] ?? null;
		if ( ! is_array( $first_row ) ) {
			return null;
		}

		// ── Check 1: Every value must be flat. ──────────────────────────
		// Valid: string, int, float, bool, null, or a flat array of scalars.
		// Invalid: objects, nested arrays, arrays of objects.
		foreach ( $first_row as $key => $value ) {
			// Skip _readable suffixes — presentation metadata.
			if ( false !== strpos( $key, '_readable' ) ) {
				continue;
			}

			if ( ! $this->is_flat_value( $value ) ) {
				return sprintf(
					'%s field "%s" contains a nested object/array. '
					. 'Repeater values must be flat: strings, numbers, booleans, or arrays of scalars. '
					. 'Call get_field_options with field_option_code="%s" and parent_values to get the correct field keys and format.',
					$field_code,
					$key,
					$field_code
				);
			}
		}

		// ── Check 2: All columns must be in one row object. ─────────────
		// Agents sometimes split columns into separate objects:
		//   [{"Name": "x"}, {"Date": "y"}]  <-- wrong (2 rows, 1 col each)
		//   [{"Name": "x", "Date": "y"}]    <-- correct (1 row, 2 cols)
		if ( count( $rows ) > 1 ) {
			$all_single_key = true;
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					$all_single_key = false;
					break;
				}
				$real_keys = array_filter(
					array_keys( $row ),
					function ( $k ) {
						return false === strpos( $k, '_readable' );
					}
				);
				if ( count( $real_keys ) !== 1 ) {
					$all_single_key = false;
					break;
				}
			}

			if ( $all_single_key ) {
				return sprintf(
					'%s has %d objects with 1 field each. Each array element is one ROW, not one column. '
					. 'All column fields must be in the SAME object. '
					. 'Correct format: [{"field_key_1": "value1", "field_key_2": "value2", ...}].',
					$field_code,
					count( $rows )
				);
			}
		}

		return null;
	}

	/**
	 * Validate repeater row keys against the schema option codes.
	 *
	 * MCP callers must use the actual repeater sub-field option_code values,
	 * not display labels such as "Column" or "Value".
	 *
	 * @param array  $rows       Repeater rows.
	 * @param array  $sub_fields Repeater sub-field definitions.
	 * @param string $field_code Parent repeater field code.
	 *
	 * @return string|null Error message when invalid keys are present, null otherwise.
	 */
	private function validate_repeater_row_keys( array $rows, array $sub_fields, string $field_code ): ?string {

		if ( empty( $rows ) || empty( $sub_fields ) ) {
			return null;
		}

		$allowed_keys = $this->get_allowed_repeater_row_keys( $sub_fields );
		if ( empty( $allowed_keys ) ) {
			return null;
		}

		foreach ( $rows as $row_index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			foreach ( array_keys( $row ) as $row_key ) {
				if ( in_array( $row_key, $allowed_keys, true ) ) {
					continue;
				}

				return sprintf(
					'%1$s row %2$d contains unknown sub-field "%3$s". Expected repeater keys: %4$s. '
					. 'Use repeater sub-field option_code values from get_component_schema, not display labels.',
					$field_code,
					$row_index + 1,
					$row_key,
					implode( ', ', $allowed_keys )
				);
			}
		}

		return null;
	}

	/**
	 * Get the allowed key set for a repeater row.
	 *
	 * Each sub-field accepts its option code, plus optional _readable and
	 * _custom companion keys used by the builder.
	 *
	 * @param array $sub_fields Repeater sub-field definitions.
	 *
	 * @return string[]
	 */
	private function get_allowed_repeater_row_keys( array $sub_fields ): array {

		$allowed_keys = array();

		foreach ( $sub_fields as $sub_field ) {
			if ( ! is_array( $sub_field ) || empty( $sub_field['option_code'] ) ) {
				continue;
			}

			$option_code     = (string) $sub_field['option_code'];
			$allowed_keys[]  = $option_code;
			$allowed_keys[]  = $option_code . '_readable';

			if ( ! empty( $sub_field['supports_custom_value'] ) ) {
				$allowed_keys[] = $option_code . '_custom';
			}
		}

		return array_values( array_unique( $allowed_keys ) );
	}

	/**
	 * Check if a value is flat (valid for repeater storage).
	 *
	 * Flat means: scalar, null, or an array where every element is scalar.
	 * This allows strings, numbers, booleans, and flat arrays (multi-select).
	 * Objects and nested arrays are not flat.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool True if the value is flat.
	 */
	private function is_flat_value( $value ): bool {
		// Scalars and null are always flat.
		if ( ! is_array( $value ) ) {
			return true;
		}

		// Arrays are flat only if every element is scalar (or null).
		foreach ( $value as $item ) {
			if ( is_array( $item ) || is_object( $item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Load field definitions for a component from the registry.
	 *
	 * Uses the same Fields class that the schema converter and
	 * save tools use. Returns a flat array of field definitions.
	 *
	 * @param string $object_type 'triggers' or 'actions'.
	 * @param string $code        Component code.
	 *
	 * @return array Flat array of field definitions.
	 */
	private function load_field_definitions( string $object_type, string $code ): array {

		$fields_service = new Fields();
		$fields_service->set_config(
			array(
				'object_type' => $object_type,
				'code'        => $code,
			)
		);

		$groups = $fields_service->get();

		if ( ! is_array( $groups ) ) {
			return array();
		}

		// Flatten grouped fields into a single array.
		$flat = array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			foreach ( $group as $field ) {
				if ( is_array( $field ) && isset( $field['option_code'] ) ) {
					$flat[] = $field;
				}
			}
		}

		return $flat;
	}

	/**
	 * Flatten structured field objects to storage format.
	 *
	 * Converts `{value, readable}` or `{value, label}` objects to flat
	 * `key` + `key_readable` pairs. Already-flat values pass through.
	 *
	 * Used by conditions and loop filters where field definitions
	 * are not available for schema-aware normalization.
	 *
	 * @param array $fields Structured fields from AI input.
	 *
	 * @return array Flattened fields for storage.
	 */
	public static function flatten( array $fields ): array {

		$normalized = array();

		foreach ( $fields as $key => $value ) {

			if ( false !== strpos( $key, '_readable' ) || false !== strpos( $key, '_label' ) ) {
				$normalized[ $key ] = is_array( $value ) ? ( $value['value'] ?? '' ) : $value;
				continue;
			}

			if ( is_array( $value ) && isset( $value['value'] ) ) {
				$display                      = (string) ( $value['readable'] ?? $value['label'] ?? $value['value'] );
				$normalized[ $key ]           = (string) $value['value'];
				$normalized[ $key . '_readable' ] = $display;
				continue;
			}

			$normalized[ $key ] = $value;

			if ( ! isset( $fields[ $key . '_readable' ] ) && is_string( $value ) ) {
				$normalized[ $key . '_readable' ] = $value;
			}
		}

		return $normalized;
	}
}
