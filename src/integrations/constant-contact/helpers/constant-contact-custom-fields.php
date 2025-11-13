<?php
namespace Uncanny_Automator\Integrations\Constant_Contact;

/**
 * Handles Constant Contact custom fields formatting and processing.
 *
 * @package Uncanny_Automator\Integrations\Constant_Contact
 */
class Constant_Contact_Custom_Fields {

	/**
	 * The common [DELETE] value.
	 *
	 * @var string
	 */
	const DELETE_VALUE = '[DELETE]';

	/**
	 * Check if a value is the [DELETE] marker.
	 *
	 * @param string $value The value to check.
	 * @return bool True if value is [DELETE], false otherwise.
	 */
	public static function is_delete_value( $value ) {
		return self::DELETE_VALUE === strtoupper( trim( (string) $value ) );
	}

	/**
	 * Get custom fields formatted for the repeater field (AJAX endpoint).
	 *
	 * @param Constant_Contact_App_Helpers $helpers The helpers instance.
	 * @param Constant_Contact_API $api The API instance.
	 * @param bool $refresh Whether to refresh the cache.
	 *
	 * @return array The formatted field properties for the repeater.
	 */
	public static function get_fields_for_repeater( $helpers, $api, $refresh = false ) {
		// Get raw custom fields data (with caching).
		$custom_fields = self::fetch_custom_fields( $helpers, $api, $refresh );

		if ( empty( $custom_fields ) ) {
			return array();
		}

		// Format fields for repeater.
		$formatted_fields = self::format_fields_for_repeater( $custom_fields );

		return array(
			'fields' => $formatted_fields,
		);
	}

	/**
	 * Process custom fields from parsed action data.
	 *
	 * @param string $custom_fields_json JSON string from transposed repeater.
	 * @param Constant_Contact_App_Helpers $helpers The helpers instance.
	 * @param Constant_Contact_API $api The API instance.
	 * 
	 * @return array The processed custom fields array for API.
	 */
	public static function process_fields_for_api( $custom_fields_json, $helpers, $api ) {
		// Decode and validate JSON.
		$fields = json_decode( $custom_fields_json, true );

		if ( null === $fields || empty( $fields ) ) {
			return array();
		}

		// Transposed repeater returns nested array - extract first element.
		if ( isset( $fields[0] ) && is_array( $fields[0] ) ) {
			$fields = $fields[0];
		}

		$built_fields         = array();
		$cached_custom_fields = self::fetch_custom_fields( $helpers, $api, false );

		// Process each custom field.
		foreach ( $fields as $field_id => $field_value ) {

			// Skip _readable fields (UI display values, not for API).
			if ( '_readable' === substr( $field_id, -9 ) ) {
				continue;
			}

			// Skip _custom fields (custom value storage, not for API).
			if ( '_custom' === substr( $field_id, -7 ) ) {
				continue;
			}

			// Handle array values (multi-select fields).
			if ( is_array( $field_value ) ) {
				// Multi-select: convert array to comma-separated string.
				$field_value = implode( ',', array_map( 'sanitize_text_field', $field_value ) );
			}

			$value = trim( (string) $field_value );

			// Skip completely empty values.
			if ( empty( $value ) ) {
				continue;
			}

			// Format the field based on field type.
			$field_data = self::format_field_for_api( $field_id, $value, $cached_custom_fields );

			// Skip if field returned null.
			if ( null === $field_data ) {
				continue;
			}

			// Add the field.
			$built_fields[] = $field_data;
		}

		return $built_fields;
	}

	/**
	 * Fetch custom fields from cache or API.
	 *
	 * @param Constant_Contact_App_Helpers $helpers The helpers instance.
	 * @param Constant_Contact_API $api The API instance.
	 * @param bool $refresh Whether to refresh the cache.
	 * 
	 * @return array The raw custom fields data.
	 */
	private static function fetch_custom_fields( $helpers, $api, $refresh = false ) {
		// Check cache first unless refresh is requested.
		if ( ! $refresh ) {
			$cached = automator_get_option( $helpers->get_const( 'OPTION_CUSTOM_FIELDS_REPEATER' ), false );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Fetch from API.
		$response = $api->contact_fields_get();

		if ( empty( $response['data']['custom_fields'] ) ) {
			return array();
		}

		$custom_fields = $response['data']['custom_fields'];

		// Cache the results.
		automator_update_option( $helpers->get_const( 'OPTION_CUSTOM_FIELDS_REPEATER' ), $custom_fields );

		return $custom_fields;
	}

	/**
	 * Format custom fields for the repeater field.
	 *
	 * @param array $custom_fields The raw custom fields data.
	 * @return array The formatted fields for repeater.
	 */
	private static function format_fields_for_repeater( $custom_fields ) {
		$formatted_fields = array();

		foreach ( $custom_fields as $field ) {
			$field_config = self::get_field_config_for_type( $field );

			if ( ! empty( $field_config ) ) {
				$formatted_fields[] = $field_config;
			}
		}

		return $formatted_fields;
	}

	/**
	 * Get field configuration based on Constant Contact field type.
	 *
	 * @param array $field The field data from Constant Contact API.
	 * @return array The field configuration for Automator.
	 */
	private static function get_field_config_for_type( $field ) {
		$field_id   = $field['custom_field_id'] ?? '';
		$field_type = $field['type'] ?? 'string';
		$field_name = $field['label'] ?? '';

		// Base configuration for all fields.
		$base_config = array(
			'option_code' => $field_id,
			'label'       => $field_name,
		);

		// Map Constant Contact field types to Automator input types.
		switch ( $field_type ) {
			case 'string':
				return array_merge(
					$base_config,
					array(
						'input_type' => 'text',
					)
				);

			case 'text_area':
				return array_merge(
					$base_config,
					array(
						'input_type'        => 'textarea',
						'supports_markdown' => 'true',
					)
				);

			case 'date':
				$placeholder = self::convert_date_format_to_placeholder( $field['date_format'] ?? 'MM/DD/YYYY' );
				return array_merge(
					$base_config,
					array(
						'input_type'  => 'text',
						'placeholder' => $placeholder,
					)
				);

			case 'datetime':
				return array_merge(
					$base_config,
					array(
						'input_type'  => 'text',
						'placeholder' => 'YYYY-MM-DD HH:MM:SS',
					)
				);

			case 'number':
				return array_merge(
					$base_config,
					array(
						'input_type' => ( isset( $field['number_of_decimal_places'] ) && $field['number_of_decimal_places'] > 0 ) ? 'float' : 'int',
					)
				);

			case 'currency':
				return array_merge(
					$base_config,
					array(
						'input_type'  => 'text',
						'placeholder' => $field['currency_type'] ?? 'USD',
					)
				);

			case 'boolean':
				return array_merge(
					$base_config,
					array(
						'input_type' => 'select',
						'options'    => array(
							self::get_empty_option(),
							array(
								'text'  => 'True',
								'value' => 'true',
							),
							array(
								'text'  => 'False',
								'value' => 'false',
							),
							self::get_delete_option(),
						),
					)
				);

			case 'single_select':
				$options = array_merge(
					array( self::get_empty_option() ),
					self::format_choices_to_options( $field['choices'] ?? array() ),
					array( self::get_delete_option() )
				);

				return array_merge(
					$base_config,
					array(
						'input_type' => 'select',
						'options'    => $options,
					)
				);

			case 'multi_select':
				return array_merge(
					$base_config,
					array(
						'input_type'               => 'select',
						'supports_multiple_values' => true,
						'supports_custom_value'    => true,
						'options'                  => self::format_choices_to_options( $field['choices'] ?? array() ),
					)
				);

			default:
				return array_merge(
					$base_config,
					array(
						'input_type' => 'text',
					)
				);
		}
	}

	/**
	 * Format custom field for API based on field type.
	 *
	 * @param string $field_id The custom field ID.
	 * @param string $value The field value.
	 * @param array $custom_fields The cached custom fields data.
	 * @return array The complete field structure for API.
	 */
	private static function format_field_for_api( $field_id, $value, $custom_fields ) {
		// Find the field configuration.
		$field_config = null;
		foreach ( $custom_fields as $field ) {
			if ( isset( $field['custom_field_id'] ) && $field['custom_field_id'] === $field_id ) {
				$field_config = $field;
				break;
			}
		}

		// Base field structure.
		$field_data = array(
			'custom_field_id' => $field_id,
		);

		// If no config found, use value parameter.
		if ( ! $field_config ) {
			$field_data['value'] = sanitize_text_field( $value );
			return $field_data;
		}

		$field_type = $field_config['type'] ?? 'string';

		// Handle [DELETE] - format based on field type.
		if ( self::is_delete_value( $value ) ) {
			$field_data['value'] = 'multi_select' === $field_type
				? array()
				: '';
			return $field_data;
		}

		// Format based on field type.
		switch ( $field_type ) {
			case 'multi_select':
				// Multi-select uses choice_ids parameter (array of choice IDs).
				// Value comes as comma-separated string of choice IDs from implode earlier.
				$choice_ids = array_map( 'trim', explode( ',', $value ) );
				// Filter out the UI placeholder 'automator_custom_value'.
				$choice_ids = array_filter(
					$choice_ids,
					function ( $id ) {
						return 'automator_custom_value' !== $id && ! empty( $id );
					}
				);

				// If no valid choice_ids remain, skip this field entirely.
				if ( empty( $choice_ids ) ) {
					return null;
				}

				$field_data['choice_ids'] = array_values( $choice_ids );
				break;

			case 'single_select':
				// Single-select uses choice_ids parameter (array with single choice ID).
				// If value is empty somehow, skip this field.
				if ( empty( $value ) ) {
					return null;
				}
				$field_data['choice_ids'] = array( (string) $value );
				break;

			case 'date':
				// Convert to YYYY-MM-DD format.
				$timestamp           = strtotime( $value );
				$field_data['value'] = false !== $timestamp ? gmdate( 'Y-m-d', $timestamp ) : sanitize_text_field( $value );
				break;

			case 'datetime':
				// Convert to ISO 8601 format (YYYY-MM-DDTHH:MM:SSZ).
				$timestamp           = strtotime( $value );
				$field_data['value'] = false !== $timestamp ? gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) : sanitize_text_field( $value );
				break;

			case 'text_area':
				// Preserve line breaks for textarea fields.
				$field_data['value'] = sanitize_textarea_field( $value );
				break;

			case 'number':
			case 'currency':
				// Return as-is for numeric fields.
				$field_data['value'] = $value;
				break;

			default:
				// Default sanitization for other field types.
				$field_data['value'] = sanitize_text_field( $value );
				break;
		}

		return $field_data;
	}

	/**
	 * Convert Constant Contact date format to placeholder.
	 *
	 * @param string $format The Constant Contact date format.
	 * @return string The placeholder format.
	 */
	private static function convert_date_format_to_placeholder( $format ) {
		// Convert MM/DD/YYYY to YYYY-MM-DD for consistency.
		switch ( $format ) {
			case 'MM/DD/YYYY':
				return 'YYYY-MM-DD';
			case 'DD/MM/YYYY':
				return 'YYYY-MM-DD';
			default:
				return 'YYYY-MM-DD';
		}
	}

		/**
	 * Get the default empty select option.
	 *
	 * @return array The empty option array.
	 */
	private static function get_empty_option() {
		return array(
			'text'  => esc_html_x( 'Select option', 'Constant Contact', 'uncanny-automator' ),
			'value' => '',
		);
	}

	/**
	 * Get the delete select option.
	 *
	 * @return array The delete option array.
	 */
	private static function get_delete_option() {
		return array(
			'text'  => esc_html_x( 'Delete value', 'Constant Contact', 'uncanny-automator' ),
			'value' => self::DELETE_VALUE,
		);
	}

	/**
	 * Format API choices to Automator select options.
	 *
	 * @param array $choices The choices from Constant Contact API.
	 * @return array The formatted options array.
	 */
	private static function format_choices_to_options( $choices ) {
		$options = array();

		if ( ! empty( $choices ) ) {
			foreach ( $choices as $choice ) {
				$options[] = array(
					'text'  => $choice['choice_label'] ?? '',
					'value' => $choice['choice_id'] ?? '',
				);
			}
		}

		return $options;
	}
}
