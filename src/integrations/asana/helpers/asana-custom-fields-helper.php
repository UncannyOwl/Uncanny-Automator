<?php

namespace Uncanny_Automator\Integrations\Asana;

/**
 * Class Asana_Custom_Fields_Helper
 *
 * Handles custom field processing for Asana actions with smart retry logic.
 * Includes AJAX helpers for generating repeater rows.
 *
 * @link https://developers.asana.com/docs/custom-fields
 *
 * @package Uncanny_Automator
 */
class Asana_Custom_Fields_Helper {

	/**
	 * Store the errors.
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * The main Asana helpers instance.
	 *
	 * @var Asana_App_Helpers
	 */
	private $helpers;

	/**
	 * The API instance.
	 *
	 * @var Asana_Api_Caller
	 */
	private $api;

	/**
	 * The workspace ID.
	 *
	 * @var string
	 */
	private $workspace_id;

	/**
	 * The project ID.
	 *
	 * @var string
	 */
	private $project_id;

	/**
	 * Constructor.
	 *
	 * @param Asana_App_Helpers $helpers The main Asana helpers instance
	 * @param string $project_id The project ID
	 *
	 * @return void
	 */
	public function __construct( $helpers, $api, $workspace_id, $project_id ) {
		$this->helpers      = $helpers;
		$this->api          = $api;
		$this->workspace_id = $workspace_id;
		$this->project_id   = $project_id;
	}

	/**
	 * Process custom fields from repeater data.
	 *
	 * @param array $fields The custom field data from repeater
	 *
	 * @return array The processed custom fields
	 */
	public function process_repeater_fields( $fields ) {

		if ( empty( $fields ) ) {
			return array();
		}

		// Clean and prepare the field data.
		$fields = $this->prepare_field_data( $fields );

		// Get config, refreshing if needed.
		$config = $this->get_custom_fields_config( $fields );

		// Process fields.
		return $this->process_fields( $fields, $config );
	}

	/**
	 * Prepare field data by replacing automator_custom_value and removing _readable/_custom keys.
	 *
	 * @param array $fields The raw field data
	 * @return array The cleaned field data
	 */
	private function prepare_field_data( $fields ) {
		$data   = array();
		$fields = array_shift( $fields );

		foreach ( $fields as $gid => $value ) {

			// Skip empty ids.
			if ( empty( $gid ) ) {
				continue;
			}

			// Skip _readable and _custom keys.
			if ( '_readable' === substr( $gid, -9 ) || '_custom' === substr( $gid, -7 ) ) {
				continue;
			}

			// Map automator_custom_value.
			if ( $this->is_automator_custom_value( $value ) ) {
				$value = $fields[ $gid . '_custom' ] ?? '';
			}

			// Skip empty values, but allow [DELETE] values.
			if ( empty( $value ) && ! $this->is_delete_value( $value ) ) {
				continue;
			}

			$data[ $gid ] = $value;
		}

		return $data;
	}

	/**
	 * Check if the value is the automator custom value.
	 *
	 * @param mixed $value The value
	 *
	 * @return bool True if the value is the automator custom value, false otherwise
	 */
	private function is_automator_custom_value( $value ) {
		return is_array( $value )
			? in_array( 'automator_custom_value', $value, true )
			: 'automator_custom_value' === $value;
	}

	/**
	 * Get custom field configuration, refreshing if any fields are missing.
	 *
	 * @param array $fields The custom field data
	 *
	 * @return array The custom field configuration
	 */
	private function get_custom_fields_config( $fields ) {
		// First try with cached data
		$config = $this->api->get_project_custom_fields( $this->project_id, false );

		// Check if refresh is needed
		if ( $this->requires_refresh( $fields, $config ) ) {
			$config = $this->api->get_project_custom_fields( $this->project_id, true );
		}

		return $config;
	}

	/**
	 * Check if configuration refresh is required.
	 *
	 * @param array $fields The custom field data
	 * @param array $config The current configuration
	 *
	 * @return bool True if refresh is needed
	 */
	private function requires_refresh( $fields, $config ) {
		foreach ( $fields as $gid => $value ) {
			// Check if field exists.
			$field_config = $this->get_field_config_by_gid( $gid, $config );
			if ( ! $field_config ) {
				return true; // Field missing, need refresh.
			}

			if ( $this->is_delete_value( $value ) ) {
				continue;
			}

			// Skip non-enum and multi_enum fields.
			if ( 'enum' !== $field_config['type'] && 'multi_enum' !== $field_config['type'] ) {
				continue;
			}

			// Check if the value can be processed.
			$found = 'enum' === $field_config['type']
				? $this->process_enum( $value, $field_config )
				: $this->process_multi_enum( $value, $field_config );

			if ( false === $found ) {
				return true; // Enum or multi_enum option missing, need refresh
			}
		}

		return false;
	}

	/**
	 * Process all fields with the given configuration.
	 *
	 * @param array $fields The custom field data
	 * @param array $config The custom field configuration
	 *
	 * @return array The processed custom fields
	 */
	private function process_fields( $fields, $config ) {
		$data = array();

		foreach ( $fields as $gid => $value ) {
			// Process the value based on field type for API formatting.
			$field_config = $this->get_field_config_by_gid( $gid, $config );
			$field_type   = $field_config['type'] ?? 'text';
			$processed    = $this->process_value_by_type( $value, $field_type, $field_config );

			if ( false !== $processed ) {
				$data[ $gid ] = $processed;
			} else {
				$this->add_error( $value, $field_type, $field_config['name'] ?? 'Unknown' );
			}
		}

		return $data;
	}

	/**
	 * Process value based on field type for API formatting.
	 *
	 * @param string $value The field value
	 * @param string $field_type The field type (enum, multi_enum, number, date, text, etc.)
	 * @param array $field_config The field configuration data
	 *
	 * @return mixed The processed value or false if invalid
	 */
	private function process_value_by_type( $value, $field_type, $field_config = array() ) {
		// Handle [DELETE] values.
		if ( $this->is_delete_value( $value ) ) {
			return $this->process_delete_value( $field_type );
		}

		switch ( $field_type ) {
			case 'enum':
				return $this->process_enum( $value, $field_config );
			case 'multi_enum':
				return $this->process_multi_enum( $value, $field_config );
			case 'people':
				return $this->process_people_value( $value );
			case 'number':
				return $this->process_number_value( $value, $field_config );
			case 'date':
				return $this->process_date_value( $value );
			case 'text':
			default:
				return $this->process_text_value( $value, $field_config );
		}
	}

	/**
	 * Process enum field value by mapping text to GID.
	 *
	 * @param string $value The user input value
	 * @param array $config The custom field configuration
	 *
	 * @return string|false The enum option GID or false if not found
	 */
	private function process_enum( $value, $config ) {
		$options = $config['enum_options'] ?? array();

		// First check if the value is already a GID
		if ( $this->helpers->is_valid_gid( $value ) ) {
			$value = (string) $value; // Cast to string for consistent comparison
			foreach ( $options as $option ) {
				$option_gid = (string) $option['value'] ?? '';
				if ( $option_gid === $value ) {
					return $option['value']; // Return original value, not casted
				}
			}
		}

		// Try to find by text (case-insensitive)
		$value = strtolower( (string) $value ); // Cast to string for consistent comparison
		foreach ( $options as $option ) {
			$option_text = strtolower( (string) $option['text'] ?? '' );
			if ( $option_text === $value ) {
				return $option['value']; // Return original value, not casted
			}
		}

		return false;
	}

	/**
	 * Process multi_enum field value.
	 *
	 * @param string $value The user input value
	 * @param array $config The custom field configuration
	 *
	 * @return array|false Array of enum option GIDs or false if invalid
	 */
	private function process_multi_enum( $value, $config ) {
		$values = $this->normalize_to_array( $value );
		$result = array();

		foreach ( $values as $val ) {
			$gid = $this->process_enum( $val, $config );
			if ( false !== $gid ) {
				$result[] = $gid;
			}
		}

		return ! empty( $result ) ? $result : false;
	}

	/**
	 * Normalize value to array.
	 *
	 * @param mixed $value The value to normalize
	 *
	 * @return array The normalized array
	 */
	private function normalize_to_array( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( empty( $value ) ) {
			return array();
		}

		// Handle comma-separated strings
		return array_map( 'trim', explode( ',', $value ) );
	}

	/**
	 * Process people value.
	 *
	 * @param string $value The value
	 *
	 * @return array|false The processed value or false if invalid
	 */
	private function process_people_value( $value ) {
		$values = $this->normalize_to_array( $value );
		if ( empty( $values ) ) {
			return false;
		}

		$people = array();
		foreach ( $values as $val ) {

			if ( $this->is_delete_value( $val ) ) {
				continue;
			}

			// Check if it's an email, if so get the workspace user option.
			if ( is_email( $val ) ) {
				$user_option = $this->helpers->get_workspace_user_option( $this->workspace_id, $val );
				if ( $user_option ) {
					$people[] = $user_option['value'];
				}
			} else {
				// Assume it's already a valid enum value.
				$people[] = $val;
			}
		}

		return ! empty( $people ) ? $people : false;
	}

	/**
	 * Process number value.
	 *
	 * @param string $value The value
	 * @param array $field_config The field configuration
	 *
	 * @return float|false The processed value or false if invalid
	 */
	private function process_number_value( $value, $field_config ) {
		$number = filter_var( $value, FILTER_VALIDATE_FLOAT );
		if ( false === $number ) {
			return false;
		}

		// Check precision from field config
		if ( ! empty( $field_config ) && isset( $field_config['precision'] ) ) {
			$precision = (int) $field_config['precision'];
			// Round to the allowed decimal places
			$number = round( $number, $precision );
		}

		return $number;
	}

	/**
	 * Process date value.
	 *
	 * @param string $value The value
	 *
	 * @return array|false The processed value or false if invalid
	 */
	private function process_date_value( $value ) {

		$formatted_date = $this->helpers->validate_and_format_date( $value );
		if ( false === $formatted_date ) {
			return false;
		}

		return array( 'date' => $formatted_date );
	}

	/**
	 * Process text value.
	 *
	 * @param string $value The value
	 *
	 * @return string The processed value
	 */
	private function process_text_value( $value, $field_config ) {
		// Check text length limit (Asana limit is 1024 characters).
		$max = 1024;
		if ( strlen( $value ) > $max ) {
			$value = substr( $value, 0, $max );
		}
		return $value;
	}

	/**
	 * Process delete value by field type.
	 *
	 * @param string $field_type The field type
	 *
	 * @return array|null
	 */
	private function process_delete_value( $field_type ) {
		switch ( $field_type ) {
			case 'multi_enum':
			case 'people':
				return array();
			default:
				return null;
		}
	}

	/**
	 * Add appropriate error message for custom field processing failure.
	 *
	 * @param string $value The field value that failed
	 * @param string $field_type The field type
	 * @param string $field_name The field name/label
	 * @return void
	 */
	private function add_error( $value, $field_type, $field_name ) {
		// translators: %1$s: field type, %2$s: field value, %3$s: field name
		$this->errors[] = sprintf(
			esc_html_x( 'Invalid %1$s value "%2$s" for field "%3$s"', 'Asana', 'uncanny-automator' ),
			$field_type,
			$value,
			$field_name
		);
	}

	/**
	 * Find custom field configuration by GID.
	 *
	 * @param string $gid The custom field GID
	 * @param array $config The custom field configuration array
	 *
	 * @return array|false The field configuration or false if not found
	 */
	private function get_field_config_by_gid( $gid, $config ) {
		$gid = (string) $gid; // Cast to string for consistent comparison
		foreach ( $config as $field ) {
			$field_gid = (string) $field['value'] ?? '';
			if ( $field_gid === $gid ) {
				return $field;
			}
		}
		return false;
	}

	/**
	 * Check if the value is or contains the [DELETE] value.
	 *
	 * @param string|array $value The value (string or array)
	 *
	 * @return bool
	 */
	private function is_delete_value( $value ) {
		return $this->helpers->is_delete_value( $value );
	}

	/**
	 * Get the error messages.
	 *
	 * @return array The error messages
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Get imploded string of error messages.
	 *
	 * @return string The imploded string of error messages
	 */
	public function get_error_message() {
		if ( empty( $this->errors ) ) {
			return '';
		}

		return implode( ', ', $this->errors );
	}

	/**
	 * Check if there are any error messages.
	 *
	 * @return bool True if there are error messages
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}

	////////////////////////////////////////////////////////////
	// Repeater AJAX Helpers.
	////////////////////////////////////////////////////////////

	/**
	 * Scan custom fields for people fields.
	 *
	 * @param array $custom_fields The custom fields
	 *
	 * @return bool True if there are people fields, false otherwise
	 */
	public static function has_people_fields( $custom_fields ) {
		foreach ( $custom_fields as $field ) {
			if ( 'people' === $field['type'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Generate repeater fields for the custom fields.
	 *
	 * @param array $custom_fields The custom fields
	 * @param array $people_options The people options
	 * @param bool $is_update_task Whether this is for an update task action
	 *
	 * @return array The repeater fields
	 */
	public static function generate_repeater_fields( $custom_fields, $people_options = array(), $is_update_task = false, $helpers = null ) {
		$fields = array();

		foreach ( $custom_fields as $field ) {
			if ( self::should_exclude_repeater_field( $field ) ) {
				continue;
			}

			$automator_type = self::convert_asana_field_type_to_automator( $field );

			// Base field configuration using only standard properties
			$field_config = array(
				'option_code'     => $field['value'],
				'label'           => $field['text'],
				'input_type'      => $automator_type,
				'supports_tokens' => true,
				'required'        => false,
			);

			if ( 'select' === $automator_type ) {
				$field_config = self::get_select_field_config( $field, $field_config, $people_options, $is_update_task, $helpers );
			}

			$fields[] = $field_config;
		}

		return $fields;
	}

	/**
	 * Check if the field should be excluded from the repeater.
	 *
	 * @param array $field The field
	 *
	 * @return bool True if the field should be excluded, false otherwise
	 */
	private static function should_exclude_repeater_field( $field ) {
		$is_enabled       = (bool) ( $field['enabled'] ?? false );
		$is_formula_field = (bool) ( $field['is_formula_field'] ?? false );
		$is_read_only     = (bool) ( $field['is_value_read_only'] ?? false );

		return ! $is_enabled || $is_formula_field || $is_read_only;
	}

	/**
	 * Convert Asana field type to Automator field type.
	 *
	 * @param array $field The Asana field
	 *
	 * @return string The Automator field type
	 */
	private static function convert_asana_field_type_to_automator( $field ) {
		switch ( $field['type'] ) {
			case 'enum':
			case 'multi_enum':
			case 'people':
				return 'select';
			case 'number':
				$precision = (int) ( $field['precision'] ?? 0 );
				// There is a bug with float field in repeater.
				// Use text for now.
				return $precision > 0 ? 'float' : 'int';
			case 'date':
			case 'text':
				return $field['type'];
			default:
				return 'text';
		}
	}

	/**
	 * Get select field configuration.
	 *
	 * @param array $field The Asana field
	 * @param array $field_config The field configuration
	 * @param array $people_options The people options
	 * @param bool $is_update_task Whether this is for an update task action
	 * @param Asana_App_Helpers $helpers The Asana app helpers instance
	 *
	 * @return array The select field configuration
	 */
	private static function get_select_field_config( $field, $field_config, $people_options, $is_update_task = false, $helpers = null ) {

		$is_multiple             = self::supports_multiple_values( $field );
		$field_config['options'] = self::get_field_options( $field, $people_options );

		// Prepend an empty option to single-select fields.
		if ( ! $is_multiple && $helpers ) {
			$field_config['options']       = $helpers->prepend_empty_option( $field_config['options'] );
			$field_config['default_value'] = '';
		}

		// Add support for multiple values.
		if ( $is_multiple ) {
			$field_config['supports_custom_value']    = true;
			$field_config['supports_multiple_values'] = true;
			$field_config['placeholder']              = esc_html_x( 'Select options', 'Asana', 'uncanny-automator' );
		}

		// Append the [DELETE] option to update task fields.
		if ( $is_update_task && $helpers ) {
			$field_config['options'] = $helpers->append_delete_option( $field_config['options'] );
		}

		return $field_config;
	}

	/**
	 * Get field options for enum fields.
	 *
	 * @param array $custom_field The custom field configuration
	 * @param array $people_options The people options
	 * @param bool $is_update_task Whether this is for an update task action
	 *
	 * @return array|null The field options or null if not applicable
	 */
	/**
	 * Get field options for enum fields.
	 *
	 * @param array $custom_field The custom field configuration
	 * @param array $people_options The people options
	 *
	 * @return array|null The field options or null if not applicable
	 */
	private static function get_field_options( $custom_field, $people_options = array() ) {

		$is_people_field = 'people' === $custom_field['type'];
		if ( $is_people_field ) {
			return $people_options;
		}

		if ( ! isset( $custom_field['enum_options'] ) ) {
			return null;
		}

		// Filter out disabled enum options.
		$enabled_options = array_filter(
			$custom_field['enum_options'],
			function ( $option ) {
				return (bool) ( $option['enabled'] ?? '' );
			}
		);

		return $enabled_options;
	}

	/**
	 * Check if the field supports multiple values.
	 *
	 * @param array $field The field
	 *
	 * @return bool True if the field supports multiple values, false otherwise
	 */
	private static function supports_multiple_values( $field ) {

		if ( 'multi_enum' === $field['type'] ) {
			return true;
		}

		if ( 'people' === $field['type'] ) {
			return true;
		}

		return false;
	}
}
