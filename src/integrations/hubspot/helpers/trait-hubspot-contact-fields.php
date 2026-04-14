<?php

namespace Uncanny_Automator\Integrations\HubSpot;

/**
 * Trait for HubSpot custom field handling in create/update contact actions.
 *
 * Provides:
 * - Transposed repeater option configuration
 * - Field processing and validation for API submission
 * - Error handling for action logs
 *
 * @package Uncanny_Automator\Integrations\HubSpot
 *
 * @property HubSpot_App_Helpers $helpers
 * @property HubSpot_Api_Caller $api
 */
trait HubSpot_Contact_Fields {

	/**
	 * Store field processing errors.
	 *
	 * @var array
	 */
	private $field_errors = array();

	////////////////////////////////////////////////////////////
	// Repeater Option Configuration
	////////////////////////////////////////////////////////////

	/**
	 * Get contact fields repeater option configuration (transposed layout).
	 *
	 * Returns a repeater with layout=transposed that lists HubSpot-defined
	 * contact information fields with their proper input types.
	 *
	 * @return array
	 */
	protected function get_contact_fields_option_config() {
		return array(
			'option_code'     => 'CONTACT_FIELDS',
			'input_type'      => 'repeater',
			'hide_actions'    => true,
			'hide_header'     => true,
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Contact fields', 'HubSpot', 'uncanny-automator' ),
			'required'        => true,
			'layout'          => 'transposed',
			'fields'          => array(),
			'ajax'            => array(
				'event'    => 'on_load',
				'endpoint' => 'automator_hubspot_get_fields',
			),
			'description'     => esc_html_x( 'Leave empty to skip setting the field. To delete a value, enter [delete].', 'HubSpot', 'uncanny-automator' ),
		);
	}

	/**
	 * Get custom fields repeater option configuration (transposed layout).
	 *
	 * Returns a repeater with layout=transposed that lists user-defined
	 * custom fields with their proper input types.
	 *
	 * @return array
	 */
	protected function get_custom_fields_option_config() {
		return array(
			'option_code'     => 'CUSTOM_FIELDS',
			'input_type'      => 'repeater',
			'hide_actions'    => true,
			'hide_header'     => true,
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Custom fields', 'HubSpot', 'uncanny-automator' ),
			'required'        => true,
			'layout'          => 'transposed',
			'fields'          => array(),
			'ajax'            => array(
				'event'    => 'on_load',
				'endpoint' => 'automator_hubspot_get_fields',
			),
			'description'     => esc_html_x( 'Leave empty to skip setting the field. To delete a value, enter [delete].', 'HubSpot', 'uncanny-automator' ),
		);
	}

	/**
	 * Get additional fields repeater option configuration (legacy style).
	 *
	 * Returns a repeater for advanced users to set fields not in the other repeaters.
	 * Uses a dropdown of available fields + text value input.
	 *
	 * @return array
	 */
	protected function get_additional_fields_option_config() {
		return array(
			'option_code'     => 'ADDITIONAL_FIELDS',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Additional fields', 'HubSpot', 'uncanny-automator' ),
			'required'        => false,
			'fields'          => array(
				array(
					'option_code'           => 'FIELD_NAME',
					'label'                 => esc_html_x( 'Field', 'HubSpot', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_tokens'       => false,
					'supports_custom_value' => false,
					'required'              => true,
					'options'               => $this->get_additional_field_options(),
				),
				array(
					'option_code'     => 'FIELD_VALUE',
					'label'           => esc_html_x( 'Value', 'HubSpot', 'uncanny-automator' ),
					'input_type'      => 'text',
					'supports_tokens' => true,
					'required'        => false,
					'description'     => esc_html_x( 'Enter [delete] to clear the field value.', 'HubSpot', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * Get additional field options.
	 *
	 * @return array
	 */
	private function get_additional_field_options() {
		$fields = $this->helpers->get_cached_fields();
		return HubSpot_Field_Utils::generate_additional_field_options( $fields );
	}

	////////////////////////////////////////////////////////////
	// Field Processing (for process_action)
	////////////////////////////////////////////////////////////

	/**
	 * Process contact fields from transposed repeater data for API submission.
	 *
	 * @example
	 * // Transposed repeater provides a single-row array with field names as keys:
	 * $json = '[{"firstname":"John","lastname":"Doe","phone":"555-1234"}]';
	 * $properties = $this->process_contact_fields($json);
	 * // Returns: [['property' => 'firstname', 'value' => 'John'], ...]
	 *
	 * @param string $json_string The parsed contact fields JSON string from repeater.
	 *
	 * @return array Array of properties formatted for HubSpot API.
	 */
	protected function process_contact_fields( $json_string ) {
		return $this->process_transposed_repeater( $json_string );
	}

	/**
	 * Process custom fields from repeater data for API submission.
	 *
	 * @example
	 * // Same format as contact fields - transposed repeater with field names as keys:
	 * $json = '[{"my_custom_field":"value","another_field":"data"}]';
	 * $properties = $this->process_custom_fields($json);
	 * // Returns: [['property' => 'my_custom_field', 'value' => 'value'], ...]
	 *
	 * @param string $json_string The parsed custom fields JSON string from repeater.
	 *
	 * @return array Array of properties formatted for HubSpot API.
	 */
	protected function process_custom_fields( $json_string ) {
		$this->custom_field_errors = array();
		return $this->process_transposed_repeater( $json_string );
	}

	/**
	 * Process additional fields from repeater data for API submission.
	 *
	 * @example
	 * // Standard repeater format - array of rows with FIELD_NAME and FIELD_VALUE:
	 * $json = '[{"FIELD_NAME":"custom_prop","FIELD_VALUE":"some value"}]';
	 * $properties = $this->process_additional_fields($json);
	 * // Returns: [['property' => 'custom_prop', 'value' => 'some value']]
	 *
	 * @param string $json_string The parsed additional fields JSON string from repeater.
	 *
	 * @return array Array of properties formatted for HubSpot API.
	 */
	protected function process_additional_fields( $json_string ) {
		if ( empty( $json_string ) ) {
			return array();
		}

		$json_string = $this->escape_json_newlines( $json_string );
		$data        = json_decode( $json_string, true );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return array();
		}

		$properties = array();

		// Standard repeater format: array of rows with FIELD_NAME and FIELD_VALUE.
		foreach ( $data as $row ) {
			$field_name  = $row['FIELD_NAME'] ?? '';
			$field_value = $row['FIELD_VALUE'] ?? '';

			// Skip empty field names.
			if ( empty( $field_name ) ) {
				continue;
			}

			// Skip empty values unless it's a [delete] value.
			if ( empty( $field_value ) && ! $this->is_delete_value( $field_value ) ) {
				continue;
			}

			// Handle [delete] value - convert to empty string for API.
			if ( $this->is_delete_value( $field_value ) ) {
				$field_value = '';
			}

			$properties[] = array(
				'property' => $field_name,
				'value'    => (string) $field_value,
			);
		}

		return $properties;
	}

	/**
	 * Process a transposed repeater (contact or custom fields).
	 *
	 * @param string $json_string The parsed JSON string from repeater.
	 *
	 * @return array Array of properties formatted for HubSpot API.
	 */
	private function process_transposed_repeater( $json_string ) {
		if ( empty( $json_string ) ) {
			return array();
		}

		$json_string = $this->escape_json_newlines( $json_string );
		$data        = json_decode( $json_string, true );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return array();
		}

		// Clean and prepare the field data.
		$fields = $this->prepare_field_data( $data );

		// Get field configuration for type validation (indexed by field name).
		$config = $this->helpers->get_cached_fields();

		// Process fields into HubSpot API format.
		return $this->process_field_values( $fields, $config );
	}

	/**
	 * Prepare field data by extracting values and handling custom values.
	 *
	 * @param array $data The raw repeater data.
	 *
	 * @return array The cleaned field data as property => value pairs.
	 */
	private function prepare_field_data( $data ) {
		$fields = array();

		// Transposed repeater format: array with single row containing all fields.
		$row = array_shift( $data );
		if ( ! is_array( $row ) ) {
			return $fields;
		}

		foreach ( $row as $property => $value ) {
			// Skip empty property names.
			if ( empty( $property ) ) {
				continue;
			}

			// Skip _readable and _custom keys.
			if ( '_readable' === substr( $property, -9 ) || '_custom' === substr( $property, -7 ) ) {
				continue;
			}

			// Map automator_custom_value to custom input.
			if ( $this->is_automator_custom_value( $value ) ) {
				$value = $row[ $property . '_custom' ] ?? '';
			}

			// Skip empty values unless it's a [delete] value.
			if ( empty( $value ) && ! $this->is_delete_value( $value ) ) {
				continue;
			}

			$fields[ $property ] = $value;
		}

		return $fields;
	}

	/**
	 * Check if the value is the automator custom value.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool
	 */
	private function is_automator_custom_value( $value ) {
		return is_array( $value )
			? in_array( 'automator_custom_value', $value, true )
			: 'automator_custom_value' === $value;
	}

	/**
	 * Process all fields with the given configuration.
	 *
	 * @param array $fields The field data.
	 * @param array $config The field configuration (indexed by field name).
	 *
	 * @return array The processed properties for API.
	 */
	private function process_field_values( $fields, $config ) {
		$properties = array();

		foreach ( $fields as $property => $value ) {
			$field_config = $config[ $property ] ?? array();
			$field_type   = $field_config['hubspot_type'] ?? 'string';
			$field_label  = $field_config['label'] ?? $property;

			$processed = $this->process_value_by_type( $value, $field_type, $field_config );

			if ( false !== $processed ) {
				$properties[] = array(
					'property' => $property,
					'value'    => $processed,
				);
			} else {
				$this->add_field_error( $value, $field_type, $field_label );
			}
		}

		return $properties;
	}

	/**
	 * Process value based on HubSpot field type.
	 *
	 * @param mixed  $value The field value.
	 * @param string $field_type The HubSpot field type.
	 * @param array  $field_config The field configuration.
	 *
	 * @return mixed The processed value or false if invalid.
	 */
	private function process_value_by_type( $value, $field_type, $field_config = array() ) {
		// Handle [delete] values - return empty string to clear field.
		if ( $this->is_delete_value( $value ) ) {
			return '';
		}

		switch ( $field_type ) {
			case 'enumeration':
				return $this->process_enumeration( $value, $field_config );

			case 'bool':
				return $this->process_boolean( $value );

			case 'number':
				return $this->process_number( $value );

			case 'date':
			case 'datetime':
				return $this->process_date( $value );

			case 'string':
			case 'phone_number':
			default:
				return $this->process_text( $value );
		}
	}

	/**
	 * Process enumeration (select) field value.
	 *
	 * @param mixed $value The value.
	 * @param array $field_config The field configuration.
	 *
	 * @return string|false The enum value or false if invalid.
	 */
	private function process_enumeration( $value, $field_config ) {
		$options = $field_config['options'] ?? array();

		// Handle multi-select (semicolon-separated in HubSpot).
		if ( is_array( $value ) ) {
			$valid_values = array();
			foreach ( $value as $v ) {
				$validated = $this->validate_enum_value( $v, $options );
				if ( false !== $validated ) {
					$valid_values[] = $validated;
				}
			}
			return ! empty( $valid_values ) ? implode( ';', $valid_values ) : false;
		}

		return $this->validate_enum_value( $value, $options );
	}

	/**
	 * Validate a single enum value against options.
	 *
	 * @param string $value The value to validate.
	 * @param array  $options The available options.
	 *
	 * @return string|false The valid value or false.
	 */
	private function validate_enum_value( $value, $options ) {
		if ( empty( $options ) ) {
			return (string) $value;
		}

		$value_lower = strtolower( trim( $value ) );

		foreach ( $options as $option ) {
			$option_value = $option['value'] ?? '';
			$option_label = $option['label'] ?? '';

			if ( strtolower( $option_value ) === $value_lower || strtolower( $option_label ) === $value_lower ) {
				return $option_value;
			}
		}

		return false;
	}

	/**
	 * Process boolean field value.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string 'true' or 'false'.
	 */
	private function process_boolean( $value ) {
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			if ( in_array( $value, array( 'yes', 'true', '1' ), true ) ) {
				return 'true';
			}
			if ( in_array( $value, array( 'no', 'false', '0', '' ), true ) ) {
				return 'false';
			}
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false';
	}

	/**
	 * Process number field value.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string|false Numeric string or false if invalid.
	 */
	private function process_number( $value ) {
		if ( false === filter_var( $value, FILTER_VALIDATE_FLOAT ) ) {
			return false;
		}

		return (string) $value;
	}

	/**
	 * Process date/datetime field value.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string|false Unix timestamp in milliseconds or false.
	 */
	private function process_date( $value ) {
		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return false;
		}

		// HubSpot expects milliseconds.
		return (string) ( $timestamp * 1000 );
	}

	/**
	 * Process text field value.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string The text value.
	 */
	private function process_text( $value ) {
		return (string) $value;
	}

	/**
	 * Check if the value is or contains the [delete] marker.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 */
	private function is_delete_value( $value ) {
		if ( is_array( $value ) ) {
			return in_array( '[delete]', array_map( 'strtolower', $value ), true );
		}
		return '[delete]' === strtolower( trim( (string) $value ) );
	}

	/**
	 * Escape unescaped newlines in a JSON string to prevent parse errors.
	 *
	 * Token parsing can inject raw newlines into JSON string values,
	 * making the JSON invalid. This escapes them properly so json_decode
	 * will convert them back to actual newlines in the decoded values.
	 *
	 * @param string $json_string The JSON string to process.
	 *
	 * @return string The JSON string with properly escaped newlines.
	 */
	private function escape_json_newlines( $json_string ) {
		// Escape unescaped newlines: CRLF, CR, and LF.
		// Using double-escaped sequences so json_decode converts them back to real newlines.
		return str_replace(
			array( "\r\n", "\r", "\n" ),
			array( '\\r\\n', '\\r', '\\n' ),
			$json_string
		);
	}

	////////////////////////////////////////////////////////////
	// Error Handling
	////////////////////////////////////////////////////////////

	/**
	 * Add error message for field processing failure.
	 *
	 * @param mixed  $value The field value that failed.
	 * @param string $field_type The field type.
	 * @param string $field_name The field name/label.
	 *
	 * @return void
	 */
	private function add_field_error( $value, $field_type, $field_name ) {
		$value_str = is_array( $value ) ? implode( ', ', $value ) : (string) $value;

		// translators: %1$s: field type, %2$s: field value, %3$s: field name.
		$this->custom_field_errors[] = sprintf(
			esc_html_x( 'Invalid %1$s value "%2$s" for field "%3$s"', 'HubSpot', 'uncanny-automator' ),
			$field_type,
			$value_str,
			$field_name
		);
	}

	/**
	 * Get comma-separated string of error messages.
	 *
	 * @return string
	 */
	protected function get_field_errors() {
		if ( empty( $this->custom_field_errors ) ) {
			return '';
		}
		return implode( ', ', $this->custom_field_errors );
	}

	/**
	 * Check if there are any error messages.
	 *
	 * @return bool
	 */
	protected function has_field_errors() {
		return ! empty( $this->custom_field_errors );
	}
}
