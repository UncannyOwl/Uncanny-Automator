<?php
/**
 * Keap Custom Fields Trait
 *
 * Provides custom field repeater configuration, validation, and data building
 * methods for contact and company actions.
 *
 * @package Uncanny_Automator\Integrations\Keap
 * @since 7.0
 */

namespace Uncanny_Automator\Integrations\Keap;

/**
 * Trait Keap_Custom_Fields
 *
 * @property Keap_App_Helpers $helpers
 * @property Keap_Api_Caller $api
 */
trait Keap_Custom_Fields {

	/**
	 * Get custom fields repeater configuration.
	 *
	 * @param string $type Entity type ('contact' or 'company').
	 *
	 * @return array Repeater field configuration.
	 */
	protected function get_custom_fields_repeater_config( $type = 'contact' ) {
		return array(
			'option_code'       => 'CUSTOM_FIELDS',
			'input_type'        => 'repeater',
			'label'             => esc_html_x( 'Custom fields', 'Keap', 'uncanny-automator' ),
			'required'          => false,
			'add_row_button'    => esc_html_x( 'Add a field', 'Keap', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove field', 'Keap', 'uncanny-automator' ),
			'description'       => sprintf(
				/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				esc_html_x( "Custom field values must align with how they are defined in your Keap account. To delete a value from a field, set its value to %1\$s, including the square brackets. Multiple values for checkboxes may be separated with commas. For more details, be sure to check out Keap's tutorial on %2\$scustom fields management.%3\$s", 'Keap', 'uncanny-automator' ),
				$this->get_delete_key(),
				'<a href="https://help.keap.com/help/custom-fields-management" target="_blank">',
				'</a>'
			),
			'fields'            => array(),
			'ajax'              => array(
				'event'    => 'on_load',
				'endpoint' => "automator_keap_get_{$type}_custom_fields",
			),
			'relevant_tokens'   => array(),
		);
	}

	/**
	 * Build custom fields request data.
	 *
	 * @param array  $fields Custom fields data from repeater.
	 * @param string $type   Entity type ('contact' or 'company').
	 *
	 * @return array Array with 'fields' and 'errors' keys.
	 */
	protected function build_custom_fields_request_data( $fields, $type = 'contact' ) {

		$data = array(
			'fields' => array(),
			'errors' => array(),
		);

		// Bail if no custom fields set.
		if ( empty( $fields ) ) {
			return $data;
		}

		// Get custom fields config from helpers.
		$config = $this->helpers->get_custom_field_options( $type );

		// Bail if no custom fields config.
		if ( empty( $config ) ) {
			$data['errors'][] = esc_html_x( 'Unable to validate Custom Field(s).', 'Keap', 'uncanny-automator' );
			return $data;
		}

		foreach ( $fields as $field ) {

			$field_id = isset( $field['FIELD'] ) ? sanitize_text_field( $field['FIELD'] ) : '';
			$value    = isset( $field['FIELD_VALUE'] ) ? sanitize_text_field( trim( $field['FIELD_VALUE'] ) ) : '';
			if ( empty( $field_id ) || empty( $value ) ) {
				continue;
			}

			// Bail if no config for key.
			if ( ! key_exists( $field_id, $config ) ) {
				$data['errors'][] = sprintf(
					/* translators: %s: custom field key */
					esc_html_x( 'Invalid custom field id: %s', 'Keap', 'uncanny-automator' ),
					$field_id
				);
				continue;
			}

			// If [delete] is set, remove the field.
			if ( $this->get_delete_key() === $value ) {
				$data['fields'][] = (object) array(
					'id'      => $field_id,
					'content' => '',
				);
				continue;
			}

			// Validate custom field value.
			$validated_value = $this->validate_custom_field_value( $field_id, $value, $config[ $field_id ] );
			if ( is_wp_error( $validated_value ) ) {
				$data['errors'][] = $validated_value->get_error_message();
				continue;
			}

			// Add validated field.
			$data['fields'][] = (object) array(
				'id'      => $field_id,
				'content' => $validated_value,
			);
		}

		// Log errors.
		if ( ! empty( $data['errors'] ) ) {
			$errors         = implode( ', ', $data['errors'] );
			$data['errors'] = esc_html_x( 'Invalid Custom Field(s) :', 'Keap', 'uncanny-automator' ) . ' ' . $errors;
		}

		return $data;
	}

	/**
	 * Validate custom field value.
	 *
	 * @param int    $field_id Custom field ID.
	 * @param string $value    Field value.
	 * @param array  $config   Field configuration.
	 *
	 * @return mixed|\WP_Error Validated value or WP_Error.
	 */
	protected function validate_custom_field_value( $field_id, $value, $config ) {

		// Stash original value for filters.
		$original_value = $value;

		// Sanitize value by type.
		$value = $this->sanitize_custom_field_value_by_type( $value, $config['type'], $config );

		// Validate value by options.
		if ( ! is_wp_error( $value ) && ! empty( $config['options'] ) ) {
			$value = $this->validate_custom_field_value_by_options( $value, $config );
		}

		/**
		 * Filter custom field value.
		 *
		 * @param mixed  $value          The custom field value or WP_Error.
		 * @param string $field_id       The custom field key.
		 * @param string $original_value The original custom field value.
		 * @param array  $config         The custom field config.
		 *
		 * @return mixed
		 */
		$value = apply_filters( 'automator_keap_validate_custom_field_value', $value, $field_id, $original_value, $config );

		return $value;
	}

	/**
	 * Get valid custom field number.
	 *
	 * @param string $number    Number value.
	 * @param string $keap_type Keap field type.
	 *
	 * @return string|false Formatted number or false if invalid.
	 */
	protected function get_valid_custom_field_number( $number, $keap_type ) {
		if ( ! is_numeric( $number ) ) {
			return false;
		}

		$number = sanitize_text_field( $number );

		switch ( $keap_type ) {
			case 'DECIMALNUMBER':
				$number = floatval( $number );
				break;
			case 'PERCENT':
			case 'CURRENCY':
				$number = number_format( floatval( $number ), 2, '.', '' );
				break;
			case 'YEAR':
				$number = absint( $number );
				// Check if year is within the 4-digit range.
				if ( $number < 1000 || $number > 9999 ) {
					return false;
				}
				break;
			default:
				$number = absint( $number );
				break;
		}

		return 0 === $number ? '0' : ( $number ? (string) $number : false );
	}

	/**
	 * Sanitize/format custom field value by type.
	 *
	 * @param string $value  Field value.
	 * @param string $type   Field type.
	 * @param array  $config Field configuration.
	 *
	 * @return mixed|\WP_Error Sanitized value or WP_Error.
	 */
	protected function sanitize_custom_field_value_by_type( $value, $type, $config ) {

		$error     = false;
		$validated = '';

		// Sanitize / Validate by type.
		switch ( $type ) {
			case 'text':
			case 'select':
				$validated = sanitize_text_field( $value );
				break;
			case 'textarea':
				$validated = sanitize_textarea_field( $value );
				break;
			case 'number':
				$validated = $this->get_valid_custom_field_number( $value, $config['keap_type'] );
				$error     = false === $validated ? esc_html_x( 'Invalid number', 'Keap', 'uncanny-automator' ) : false;
				break;
			case 'date':
				$date      = $this->get_formatted_date( $value );
				$validated = is_wp_error( $date ) ? '' : $date;
				$error     = is_wp_error( $date ) ? $date->get_error_message() : false;
				break;
			case 'url':
				$validated = esc_url( $value );
				$error     = empty( $validated ) ? esc_html_x( 'Invalid URL', 'Keap', 'uncanny-automator' ) : '';
				break;
			case 'email':
				$validated = $this->get_valid_email( $value );
				$error     = empty( $validated ) ? esc_html_x( 'Invalid email', 'Keap', 'uncanny-automator' ) : false;
				break;
			default:
				$error = sprintf(
					/* translators: %s: custom field type */
					esc_html_x( 'Invalid custom field type: %s', 'Keap', 'uncanny-automator' ),
					$type
				);
				break;
		}

		if ( $error ) {
			$error .= ' ' . sprintf(
				/* translators: %s: custom field label */
				esc_html_x( 'for field: %s', 'Keap', 'uncanny-automator' ),
				$config['text']
			);
			return new \WP_Error( 'invalid_field_' . $type, $error );
		}

		return $validated;
	}

	/**
	 * Validate custom field value by options.
	 *
	 * @param string $value  Field value.
	 * @param array  $config Field configuration.
	 *
	 * @return mixed String, array (for listbox), or WP_Error.
	 */
	protected function validate_custom_field_value_by_options( $value, $config ) {

		$options   = $config['options'];
		$keap_type = $config['keap_type'];
		$value     = trim( $value );

		// Format value by type.
		$dates = array( 'DAYOFWEEK', 'MONTH' );
		if ( in_array( $keap_type, $dates, true ) ) {
			// Keap wants the number representation of the day of the week or month of the year.
			if ( ! is_numeric( $value ) && false !== strtotime( $value ) ) {
				if ( 'DAYOFWEEK' === $keap_type ) {
					$value = (int) wp_date( 'w', strtotime( $value ) );
					// Adjust for Keap's week numbering, making Sunday = 1, Monday = 2, ..., Saturday = 7.
					$value = 0 === $value ? 1 : $value + 1;
				} elseif ( 'MONTH' === $keap_type ) {
					$value = (int) wp_date( 'm', strtotime( $value ) );
				}
			} elseif ( is_numeric( $value ) ) {
				// Ensure we have no leading zeros.
				$value = (int) $value;
			}
		}

		if ( 'STATE' === $keap_type ) {
			// Keap wants the US 2 character state abbreviation, otherwise capitalized state name.
			$value = 2 === strlen( $value ) ? strtoupper( $value ) : ucwords( strtolower( $value ) );
		}

		if ( 'YESNO' === $keap_type ) {
			// Keap wants 'Yes' or 'No' values.
			$value = ucfirst( strtolower( trim( $value ) ) );
		}

		// Check if we have multiple values.
		if ( 'LISTBOX' === $keap_type ) {
			$values = array_map( 'trim', explode( ',', $value ) );
			if ( count( $values ) > 1 ) {
				// Validate each value.
				$validated = array();
				// Adjust type to avoid checking again.
				$config['keap_type'] = 'LISTBOXITEM';
				foreach ( $values as $val ) {
					$result = $this->validate_custom_field_value_by_options( $val, $config );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					$validated[] = $result;
				}

				return array_values( $validated );
			}
		}

		// Check if value is in option values.
		if ( key_exists( $value, $options ) ) {
			return $value;
		}

		// Check if value is in option labels.
		$labels = wp_list_pluck( $options, 'text', 'value' );
		if ( in_array( $value, $labels, true ) ) {
			return array_search( $value, $labels, true );
		}

		return new \WP_Error(
			'invalid_custom_field_value',
			sprintf(
				/* translators: %1$s: custom field value, %2$s: custom field label */
				esc_html_x( 'Invalid custom field value: %1$s for field: %2$s', 'Keap', 'uncanny-automator' ),
				$value,
				$config['text']
			)
		);
	}
}
