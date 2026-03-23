<?php
/**
 * Action Config Validator.
 *
 * Handles comprehensive validation of action configuration against schema.
 * Extracts complex validation logic from Action_Instance_Service for better separation of concerns.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services\Action\Helpers
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Action\Utilities;

use Uncanny_Automator\Services\Integrations\Fields;
use WP_Error;

/**
 * Action Config Validator Class.
 *
 * Performs schema-based validation of action configurations including
 * required fields, data types, formats, and business rules.
 */
class Action_Validator {

	/**
	 * Validate action configuration against schema.
	 *
	 * @param string $action_code Action code.
	 * @param array  $config Configuration to validate.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate( string $action_code, array $config ) {

		try {
			// Get action configuration schema
			$fields = new Fields();

			$filter_params = array(
				'code'        => $action_code,
				'object_type' => 'actions',
			);

			$fields->set_config( $filter_params );

			$configuration_fields = $fields->get();

			// Enhanced validation - check for required fields, formats, and business rules
			$validation_result = $this->perform_comprehensive_validation( $configuration_fields, $config, $action_code );

			if ( is_wp_error( $validation_result ) ) {
				return $validation_result;
			}

			return true;

		} catch ( \Exception $e ) {
			return new WP_Error(
				'config_validation_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Configuration validation failed: %s', 'Action validator error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Perform comprehensive validation of action configuration.
	 *
	 * @param array  $configuration_fields Configuration fields definition.
	 * @param array  $config Configuration values to validate.
	 * @param string $action_code Action code for error reporting.
	 * @return true|\WP_Error True on success, WP_Error on validation failure.
	 */
	private function perform_comprehensive_validation( array $configuration_fields, array $config, string $action_code = '' ) {

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
				if ( str_ends_with( $field, '_readable' ) ) {
					$base_field = substr( $field, 0, -9 ); // Remove '_readable'.
					return ! in_array( $base_field, $valid_field_codes, true );
				}
				return true;
			}
		);

		if ( ! empty( $unknown_fields ) ) {
			$errors[] = sprintf(
				/* translators: %1$s Unknown field list, %2$s Valid field list. */
				esc_html_x( 'Unknown fields provided: %1$s. Valid fields are: %2$s', 'Action validator error', 'uncanny-automator' ),
				implode( ', ', $unknown_fields ),
				implode( ', ', $valid_field_codes )
			);
		}

		// 2. Check for required fields.
		$required_fields = $this->get_required_fields( $configuration_fields );
		$missing_fields  = array_diff( $required_fields, array_keys( $config ) );

		if ( $missing_fields ) {
			$errors[] = sprintf(
				/* translators: %s Field list. */
				esc_html_x( 'Missing required fields: %s', 'Action validator error', 'uncanny-automator' ),
				implode( ', ', $missing_fields )
			);
		}

		// 3. STRICT: For actions that expect configuration, reject completely empty configs.
		if ( empty( $config ) && ! empty( $configuration_fields ) ) {
			$errors[] = esc_html_x( 'Action configuration cannot be empty – this action requires configuration parameters.', 'Action validator error', 'uncanny-automator' );
		}

		// 4. STRICT: Check for required fields that are present but empty.
		foreach ( $required_fields as $required_field ) {
			if ( isset( $config[ $required_field ] ) ) {
				$value = $config[ $required_field ];
				// Skip arrays (repeaters, multi-selects) - they have their own validation
				if ( is_array( $value ) ) {
					continue;
				}
				// Validate scalar values
				if ( empty( trim( (string) $value ) ) ) {
					$errors[] = sprintf(
						/* translators: %s Field code. */
						esc_html_x( 'Required field "%s" cannot be empty.', 'Action validator error', 'uncanny-automator' ),
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

				$field_code  = $field['option_code'];
				$field_value = $config[ $field_code ] ?? '';

				// Skip validation for empty non-required fields
				if ( empty( $field_value ) && empty( $field['required'] ) ) {
					continue;
				}

				// Validate based on field type and attributes
				$field_errors = $this->validate_field_value( $field, $field_value, $field_code );
				if ( ! empty( $field_errors ) ) {
					$errors = array_merge( $errors, $field_errors );
				}
			}
		}

		// Return consolidated errors or success with enhanced reporting
		if ( ! empty( $errors ) ) {
			$error_details = array(
				'validation_errors' => $errors,
				'action_code'       => $action_code,
				'total_errors'      => count( $errors ),
			);

			return new WP_Error(
				'validation_failed',
				sprintf(
					/* translators: %s Error message list. */
					esc_html_x( 'Action configuration validation failed: %s', 'Action validator error', 'uncanny-automator' ),
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
	 * @param array  $field Field definition.
	 * @param mixed  $value Field value.
	 * @param string $field_code Field code for error messages.
	 * @return array Array of error messages (empty if valid).
	 */
	private function validate_field_value( array $field, $value, string $field_code ): array {

		$errors = array();

		// Get field attributes
		$input_type  = $field['input_type'] ?? '';
		$field_label = $field['label'] ?? $field_code;

		// Skip validation for non-scalar values (arrays, objects, etc.)
		if ( ! is_scalar( $value ) ) {
			return array();
		}

		// Don't validate if the field value is a token.
		if ( preg_match( '/\{\{.*\}\}/', (string) $value ) ) {
			return array();
		}

		// Email validation - only for actual email address fields
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

		// URL validation
		if ( 'url' === $input_type ) {
			if ( ! empty( $value ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$errors[] = sprintf( '"%s" must be a valid URL', $field_label );
			}
		}

		// Number validation
		if ( 'number' === $input_type ) {
			if ( ! empty( $value ) && ! is_numeric( $value ) ) {
				$errors[] = sprintf( '"%s" must be a valid number', $field_label );
			}
		}

		// Text length validation
		if ( in_array( $input_type, array( 'text', 'textarea' ), true ) ) {
			if ( ! empty( $value ) ) {
				$max_length = 'text' === $input_type ? 255 : 8000; // Reasonable defaults
				if ( is_string( $value ) && strlen( $value ) > $max_length ) {
					$errors[] = sprintf( '"%s" must not exceed %d characters', $field_label, $max_length );
				}
			}
		}

		// Required field validation (already checked above, but double-check)
		if ( ! empty( $field['required'] ) && empty( $value ) ) {
			$errors[] = sprintf( '"%s" is required and cannot be empty', $field_label );
		}

		// Enum validation - check if field has valid options
		// Skip if field supports custom values (user can enter any value)
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
				if ( is_int( $first_valid ) && is_numeric( $value ) ) {
					$value = (int) $value;
				}

				if ( ! in_array( $value, $valid_values, true ) ) {
					$errors[] = sprintf( '"%s" must be one of: %s', $field_label, implode( ', ', $valid_values ) );
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
	 * Get required fields from configuration fields.
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

				// CRITICAL: Ensure option_code is a string, not an array
				// Failure mode: Repeater fields with nested 'fields' arrays could pass non-string option_codes
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
	 * that AI-provided fields actually exist in the action schema.
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
