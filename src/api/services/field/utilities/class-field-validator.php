<?php
/**
 * Unified Field Validator.
 *
 * Validates component (action/trigger) configuration against the field schema.
 * Consolidates Action_Validator and Trigger_Validator field-validation logic
 * into a single, authoritative implementation.
 *
 * @since   7.1.0
 * @package Uncanny_Automator\Api\Services\Field\Utilities
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Field\Utilities;

use Uncanny_Automator\Api\Components\Shared\Polyfill\Str;
use Uncanny_Automator\Services\Integrations\Fields;
use WP_Error;

/**
 * Field Validator Class.
 *
 * Performs schema-based validation of component configurations including
 * required fields, data types, formats, and business rules.
 *
 * @since 7.1.0
 */
class Field_Validator {

	/**
	 * Validate component configuration against schema.
	 *
	 * @since 7.1.0
	 *
	 * @param string $component_code Component code (action or trigger code).
	 * @param array  $config         Configuration to validate.
	 * @param string $component_type Component type: 'action' or 'trigger'.
	 * @param bool   $partial        When true, skip required-field and empty-config checks (for updates with partial fields).
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate( string $component_code, array $config, string $component_type = 'action', bool $partial = false ) {

		$object_type = 'trigger' === $component_type ? 'triggers' : 'actions';

		try {
			$fields = new Fields();
			$fields->set_config(
				array(
					'code'        => $component_code,
					'object_type' => $object_type,
				)
			);

			$configuration_fields = $fields->get();

			$validation_result = $this->perform_comprehensive_validation(
				$configuration_fields,
				$config,
				$component_code,
				$component_type,
				$partial
			);

			if ( is_wp_error( $validation_result ) ) {
				return $validation_result;
			}

			return true;

		} catch ( \Exception $e ) {
			return new WP_Error(
				'config_validation_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Configuration validation failed: %s', 'Field validator error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Validate configuration against pre-fetched field definitions.
	 *
	 * Unlike validate(), this method accepts field definitions directly instead of
	 * loading them from the Fields service. This supports component types (conditions,
	 * loop filters) whose field definitions come from different registries.
	 *
	 * @since 7.2.0
	 *
	 * @param array  $field_definitions Field definitions in WordPress format (option_code, input_type, options, required).
	 * @param array  $config            Configuration values to validate.
	 * @param string $component_code    Component code for error reporting.
	 * @param string $component_type    Component type label for error messages (e.g. 'condition', 'loop_filter').
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_fields( array $field_definitions, array $config, string $component_code = '', string $component_type = 'component' ) {

		if ( empty( $field_definitions ) ) {
			return true;
		}

		// Wrap flat field definitions into the grouped structure that
		// perform_comprehensive_validation expects: array( array( $field, $field, ... ) ).
		$configuration_fields = $this->normalize_field_definitions( $field_definitions );

		return $this->perform_comprehensive_validation( $configuration_fields, $config, $component_code, $component_type );
	}

	/**
	 * Normalize flat field definitions into grouped structure.
	 *
	 * Field definitions may come as:
	 * - Already grouped: array( array( $field1, $field2, ... ), array( ... ) )
	 * - Flat list: array( $field1, $field2, ... )
	 * - Keyed by option_code: array( 'CODE' => $field, ... )
	 *
	 * Returns the grouped structure that perform_comprehensive_validation expects.
	 *
	 * @since 7.2.0
	 *
	 * @param array $definitions Raw field definitions.
	 * @return array Grouped field definitions.
	 */
	private function normalize_field_definitions( array $definitions ): array {

		// Check if already in grouped format (array of arrays of fields).
		$first = reset( $definitions );
		if ( is_array( $first ) && isset( $first[0] ) && is_array( $first[0] ) && isset( $first[0]['option_code'] ) ) {
			return $definitions;
		}

		// Flat list or keyed by option_code — wrap in single group.
		$fields = array();
		foreach ( $definitions as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			// Keyed by option_code without option_code in the definition — inject it.
			if ( is_string( $key ) && ! isset( $field['option_code'] ) ) {
				$field['option_code'] = $key;
			}
			if ( isset( $field['option_code'] ) ) {
				$fields[] = $field;
			}
		}

		return array( $fields );
	}

	/**
	 * Convert loop filter meta_structure to WordPress field format.
	 *
	 * Loop filter registries store field definitions as:
	 *   'FIELD_CODE' => [ 'type' => 'string', 'options' => [ 'val' => 'Label' ], 'required' => true ]
	 *
	 * This converts them to the WordPress format that validate_field_value expects:
	 *   [ 'option_code' => 'FIELD_CODE', 'input_type' => 'select', 'options' => [ ['value'=>'val','text'=>'Label'] ] ]
	 *
	 * @since 7.2.0
	 *
	 * @param array $meta_structure Meta structure from filter registry definition.
	 * @return array WordPress-format field definitions.
	 */
	public static function convert_meta_structure_to_fields( array $meta_structure ): array {

		$fields = array();

		foreach ( $meta_structure as $field_code => $field_config ) {
			$input_type = 'text';
			$options    = array();

			if ( ! empty( $field_config['options'] ) && is_array( $field_config['options'] ) ) {
				$input_type = 'select';
				foreach ( $field_config['options'] as $value => $label ) {
					$options[] = array(
						'value' => (string) $value,
						'text'  => (string) $label,
					);
				}
			}

			$fields[] = array(
				'option_code'           => $field_code,
				'label'                 => $field_config['label'] ?? $field_config['description'] ?? $field_code,
				'input_type'            => $input_type,
				'required'              => ! empty( $field_config['required'] ),
				'options'               => $options,
				'supports_custom_value' => ! empty( $field_config['supports_custom_value'] ),
			);
		}

		return $fields;
	}

	/**
	 * Perform comprehensive validation of component configuration.
	 *
	 * @since 7.1.0
	 *
	 * @param array  $configuration_fields Configuration fields definition.
	 * @param array  $config               Configuration values to validate.
	 * @param string $component_code       Component code for error reporting.
	 * @param string $component_type       Component type: 'action' or 'trigger'.
	 * @return true|\WP_Error True on success, WP_Error on validation failure.
	 */
	private function perform_comprehensive_validation(
		array $configuration_fields,
		array $config,
		string $component_code = '',
		string $component_type = 'action',
		bool $partial = false
	) {

		$errors = array();

		// Extract all valid field codes from schema.
		$valid_field_codes = $this->extract_all_field_codes_recursive( $configuration_fields );

		// 1. STRICT: Reject unknown fields (catches AI hallucinations).
		$provided_fields = array_keys( $config );
		$unknown_fields  = array_diff( $provided_fields, $valid_field_codes );

		// Filter out _readable suffix fields (used for dropdown display values).
		$unknown_fields = array_filter(
			$unknown_fields,
			function ( $field ) use ( $valid_field_codes ) {
				// Allow *_readable suffix if base field exists.
				if ( Str::ends_with( $field, '_readable' ) ) {
					$base_field = substr( $field, 0, -9 ); // Remove '_readable'.
					return ! in_array( $base_field, $valid_field_codes, true );
				}
				return true;
			}
		);

		if ( ! empty( $unknown_fields ) ) {
			$errors[] = sprintf(
				/* translators: %1$s Unknown field list, %2$s Valid field list. */
				esc_html_x( 'Unknown fields provided: %1$s. Valid fields are: %2$s', 'Field validator error', 'uncanny-automator' ),
				implode( ', ', $unknown_fields ),
				implode( ', ', $valid_field_codes )
			);
		}

		// 2. Check for required fields (skip on partial/update).
		$required_fields = $this->get_required_fields( $configuration_fields );

		if ( ! $partial ) {
			$missing_fields = array_diff( $required_fields, array_keys( $config ) );

			if ( ! empty( $missing_fields ) ) {
				$errors[] = sprintf(
					/* translators: %s Field list. */
					esc_html_x( 'Missing required fields: %s', 'Field validator error', 'uncanny-automator' ),
					implode( ', ', $missing_fields )
				);
			}

			// 3. STRICT: For components that expect configuration, reject completely empty configs.
			if ( empty( $config ) && ! empty( $configuration_fields ) ) {
				$errors[] = sprintf(
					/* translators: %s Component type label. */
					esc_html_x( '%s configuration cannot be empty – this component requires configuration parameters.', 'Field validator error', 'uncanny-automator' ),
					ucfirst( $component_type )
				);
			}
		}

		// 4. Check for required fields that are present but empty.
		foreach ( $required_fields as $required_field ) {
			if ( isset( $config[ $required_field ] ) ) {
				$value = $config[ $required_field ];
				// Skip arrays (repeaters, multi-selects) - they have their own validation.
				if ( is_array( $value ) ) {
					continue;
				}
				// Validate scalar values.
				if ( '' === trim( (string) $value ) ) {
					$errors[] = sprintf(
						/* translators: %s Field code. */
						esc_html_x( 'Required field "%s" cannot be empty.', 'Field validator error', 'uncanny-automator' ),
						$required_field
					);
				}
			}
		}

		// 5. Validate field formats.
		foreach ( $configuration_fields as $field_group ) {

			if ( ! is_array( $field_group ) ) {
				continue;
			}

			foreach ( $field_group as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
					continue;
				}

				$field_code = $field['option_code'];

				// In partial mode, only validate fields actually provided.
				if ( $partial && ! array_key_exists( $field_code, $config ) ) {
					continue;
				}

				$field_value = $config[ $field_code ] ?? '';

				// Skip validation for empty non-required fields.
				if ( empty( $field_value ) && empty( $field['required'] ) ) {
					continue;
				}

				// Validate based on field type and attributes.
				$field_errors = $this->validate_field_value( $field, $field_value, $field_code );
				if ( ! empty( $field_errors ) ) {
					$errors = array_merge( $errors, $field_errors );
				}
			}
		}

		// Return consolidated errors or success with enhanced reporting.
		if ( ! empty( $errors ) ) {
			$error_details = array(
				'validation_errors' => $errors,
				'component_code'    => $component_code,
				'component_type'    => $component_type,
				'total_errors'      => count( $errors ),
			);

			return new WP_Error(
				'validation_failed',
				sprintf(
					/* translators: %1$s Component type, %2$s Error message list. */
					esc_html_x( '%1$s configuration validation failed: %2$s', 'Field validator error', 'uncanny-automator' ),
					ucfirst( $component_type ),
					implode( '; ', $errors )
				),
				$error_details
			);
		}

		return true;
	}

	/**
	 * Validate a single field value against its definition.
	 *
	 * @since 7.1.0
	 *
	 * @param array  $field      Field definition.
	 * @param mixed  $value      Field value.
	 * @param string $field_code Field code for error messages.
	 * @return array Array of error messages (empty if valid).
	 */
	private function validate_field_value( array $field, $value, string $field_code ): array {

		$errors = array();

		// Get field attributes.
		$input_type  = $field['input_type'] ?? '';
		$field_label = $field['label'] ?? $field_code;

		// Repeater fields: validate array structure and required sub-fields.
		if ( 'repeater' === $input_type && is_array( $value ) ) {
			return $this->validate_repeater_value( $field, $value, $field_label );
		}

		// Reject non-scalar values for non-repeater, non-multi-select fields.
		if ( ! is_scalar( $value ) ) {
			$is_multi = ! empty( $field['supports_multiple_values'] );
			if ( ! $is_multi ) {
				return array( sprintf( '"%s" expects a scalar value, got %s.', $field_label, gettype( $value ) ) );
			}
			return array();
		}

		// Don't validate if the field value is a token.
		if ( preg_match( '/\{\{.*\}\}/', (string) $value ) ) {
			return array();
		}

		// Email validation - only for actual email address fields.
		if ( 'email' === $input_type ) {
			$recipients   = explode( ',', (string) $value );
			$has_multiple = count( $recipients ) > 1;
			// Validate each email if field supports multiple emails.
			if ( $has_multiple ) {
				foreach ( $recipients as $recipient ) {
					$recipient = trim( $recipient );
					if ( ! is_email( $recipient ) ) {
						$errors[] = sprintf( '"%s" contains an invalid email address: %s', $field_label, $recipient );
					}
				}
			}
			// Only validate single email if field does not support multiple emails.
			if ( ! $has_multiple && ! empty( $value ) && ! is_email( $value ) ) {
				$errors[] = sprintf( '"%s" must be a valid email address', $field_label );
			}
		}

		// URL validation.
		if ( 'url' === $input_type ) {
			if ( ! empty( $value ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$errors[] = sprintf( '"%s" must be a valid URL', $field_label );
			}
		}

		// Number validation.
		if ( in_array( $input_type, array( 'integer', 'float' ), true ) ) {
			if ( ! empty( $value ) && ! is_numeric( $value ) ) {
				$errors[] = sprintf( '"%s" must be a valid number', $field_label );
			}
		}

		// Text length validation.
		if ( in_array( $input_type, array( 'text', 'textarea' ), true ) ) {
			if ( ! empty( $value ) ) {
				$max_length = 'text' === $input_type ? 255 : 8000; // Reasonable defaults.
				if ( is_string( $value ) && strlen( $value ) > $max_length ) {
					$errors[] = sprintf( '"%s" must not exceed %d characters', $field_label, $max_length );
				}
			}
		}

		// Required field validation (already checked above, but double-check).
		if ( ! empty( $field['required'] ) && '' === (string) $value ) {
			$errors[] = sprintf( '"%s" is required and cannot be empty', $field_label );
		}

		// Enum validation - check if field has valid options.
		// Skip if field supports custom values (user can enter any value).
		if ( ! empty( $field['options'] ) && is_array( $field['options'] ) && ! empty( $value ) && empty( $field['supports_custom_value'] ) ) {
			$valid_values = array();
			foreach ( $field['options'] as $option ) {
				if ( is_array( $option ) && isset( $option['value'] ) ) {
					$valid_values[] = $option['value'];
				}
			}

			if ( ! empty( $valid_values ) ) {
				// Normalize type: if valid values are integers, cast input to int for comparison.
				// This handles AI sending "1000" (string) when the field expects 1000 (int).
				$first_valid = reset( $valid_values );

				// Multi-select fields arrive as JSON-encoded arrays (e.g. '["2","1"]')
				// after Field_Mcp_Input_Resolver normalization. Decode to validate each element.
				$values_to_check = array( $value );
				if ( is_string( $value ) && 0 === strpos( $value, '[' ) ) {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						$values_to_check = $decoded;
					}
				}

				foreach ( $values_to_check as $single_value ) {
					if ( is_int( $first_valid ) && is_numeric( $single_value ) ) {
						$single_value = (int) $single_value;
					}

					if ( ! in_array( $single_value, $valid_values, true ) ) {
						$errors[] = sprintf( '"%s" must be one of: %s', $field_label, implode( ', ', $valid_values ) );
						break;
					}
				}
			}
		}

		// WordPress role validation (specific business rule for WPROLE).
		if ( 'WPROLE' === $field_code && ! empty( $value ) ) {
			$wp_roles    = wp_roles();
			$valid_roles = array_keys( $wp_roles->roles );

			if ( ! in_array( $value, $valid_roles, true ) ) {
				$errors[] = sprintf( '"%s" must be a valid WordPress role. Valid roles: %s', $field_label, implode( ', ', $valid_roles ) );
			}
		}

		return $errors;
	}

	/**
	 * Validate a repeater field value.
	 *
	 * Ensures the value is an array of row objects with required sub-fields present.
	 *
	 * @since 7.1.0
	 *
	 * @param array  $field       Field definition (must include 'fields' sub-field definitions).
	 * @param array  $value       Repeater value (array of rows).
	 * @param string $field_label Human-readable field label.
	 *
	 * @return array Array of error messages (empty if valid).
	 */
	private function validate_repeater_value( array $field, array $value, string $field_label ): array {

		$errors = array();

		// Empty array for a required repeater is invalid.
		if ( empty( $value ) && ! empty( $field['required'] ) ) {
			$errors[] = sprintf( '"%s" requires at least one row.', $field_label );
			return $errors;
		}

		// If there are no sub-field definitions, we can't validate further.
		// AJAX-dependent repeaters start with empty fields — skip gracefully.
		$sub_fields = $field['fields'] ?? array();
		if ( empty( $sub_fields ) || ! is_array( $sub_fields ) ) {
			return $errors;
		}

		// Build sub-field lookup and collect required codes.
		$sub_field_map       = array();
		$required_sub_fields = array();

		foreach ( $sub_fields as $sf ) {
			if ( ! is_array( $sf ) || ! isset( $sf['option_code'] ) ) {
				continue;
			}
			$sub_field_map[ $sf['option_code'] ] = $sf;
			if ( ! empty( $sf['required'] ) ) {
				$required_sub_fields[] = $sf['option_code'];
			}
		}

		// Validate each row.
		foreach ( $value as $row_index => $row ) {
			if ( ! is_array( $row ) ) {
				$errors[] = sprintf( '"%s" row %d must be an object with sub-field keys, not a scalar value.', $field_label, $row_index + 1 );
				continue;
			}

			// Check required sub-fields in this row.
			foreach ( $required_sub_fields as $req_code ) {
				if ( ! isset( $row[ $req_code ] ) || '' === $row[ $req_code ] ) {
					$errors[] = sprintf( '"%s" row %d is missing required sub-field "%s".', $field_label, $row_index + 1, $req_code );
				}
			}

			// Validate sub-field values against their type/format/enum definitions.
			foreach ( $row as $sub_code => $sub_value ) {
				// Skip _readable suffix fields — display-only metadata.
				if ( Str::ends_with( $sub_code, '_readable' ) ) {
					continue;
				}

				// No definition for this sub-field — skip (caught by field code validation).
				if ( ! isset( $sub_field_map[ $sub_code ] ) ) {
					continue;
				}

				$sub_field = $sub_field_map[ $sub_code ];

				// Skip empty non-required sub-fields.
				if ( empty( $sub_value ) && empty( $sub_field['required'] ) ) {
					continue;
				}

				// Delegate to validate_field_value for type/format/enum checking.
				$sub_errors = $this->validate_field_value( $sub_field, $sub_value, $sub_code );

				// Prefix errors with row context.
				foreach ( $sub_errors as $sub_error ) {
					$errors[] = sprintf( '"%s" row %d: %s', $field_label, $row_index + 1, $sub_error );
				}
			}
		}

		return $errors;
	}

	/**
	 * Get required fields from configuration fields.
	 *
	 * @since 7.1.0
	 *
	 * @param array $configuration_fields Configuration fields array.
	 * @return string[] Required field names.
	 */
	private function get_required_fields( array $configuration_fields ): array {

		$required_fields = array();

		foreach ( $configuration_fields as $field_group ) {
			if ( ! is_array( $field_group ) ) {
				continue;
			}

			foreach ( $field_group as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
					continue;
				}

				// CRITICAL: Ensure option_code is a string, not an array.
				// Failure mode: Repeater fields with nested 'fields' arrays could pass non-string option_codes.
				if ( ! is_string( $field['option_code'] ) ) {
					continue;
				}

				if ( ! empty( $field['required'] ) ) {
					$required_fields[] = $field['option_code'];
				}
			}
		}

		return $required_fields;
	}

	/**
	 * Recursively extract all field codes from configuration fields.
	 *
	 * Traverses the field schema tree to extract every valid field code,
	 * including nested fields within repeater structures. Used to validate
	 * that AI-provided fields actually exist in the component schema.
	 *
	 * @since 7.1.0
	 *
	 * @param array $configuration_fields Configuration fields array.
	 * @return string[] All field codes found in the schema.
	 */
	private function extract_all_field_codes_recursive( array $configuration_fields ): array {

		$field_codes = array();

		foreach ( $configuration_fields as $field_group ) {
			if ( ! is_array( $field_group ) ) {
				continue;
			}

			foreach ( $field_group as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
					continue;
				}

				// Ensure option_code is a string.
				if ( ! is_string( $field['option_code'] ) ) {
					continue;
				}

				$field_codes[] = $field['option_code'];

				// Handle repeater fields with nested 'fields' array.
				if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
					foreach ( $field['fields'] as $nested_field ) {
						if ( is_array( $nested_field ) && isset( $nested_field['option_code'] ) && is_string( $nested_field['option_code'] ) ) {
							$field_codes[] = $nested_field['option_code'];
						}
					}
				}
			}
		}

		$result = array_unique( $field_codes );

		// Whitelist special fields that are not in schema but allowed.
		$result[] = '_automator_custom_item_name_';
		$result[] = 'added_by_llm';

		return $result;
	}
}
