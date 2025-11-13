<?php

namespace Uncanny_Automator\Integrations\Get_Response;

use Uncanny_Automator\Recipe\Log_Properties;
use Exception;
use WP_Error;

/**
 * Class GET_RESPONSE_ADD_UPDATE_CONTACT
 *
 * @package Uncanny_Automator
 *
 * @property Get_Response_App_Helpers $helpers
 * @property Get_Response_Api_Caller $api
 */
class GET_RESPONSE_ADD_UPDATE_CONTACT extends \Uncanny_Automator\Recipe\App_Action {

	use Log_Properties;

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'GETRESPONSE' );
		$this->set_action_code( 'GR_ADD_UPDATE_CONTACT_CODE' );
		$this->set_action_meta( 'CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/getresponse/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: Contact Email
				esc_attr_x( 'Create or update {{a contact:%1$s}}', 'GetResponse', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'GetResponse', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array();

		$fields[] = array(
			'option_code' => 'LIST_ID',
			'label'       => esc_html_x( 'List', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'ajax'        => array(
				'endpoint' => 'automator_getresponse_get_lists',
				'event'    => 'on_load',
			),
		);

		$fields[] = array(
			'option_code' => $this->get_action_meta(),
			'label'       => esc_html_x( 'Email', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);

		$fields[] = array(
			'option_code' => 'UPDATE_EXISTING_CONTACT',
			'label'       => esc_html_x( 'Update existing contact', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
			'description' => esc_html_x( 'To exclude fields from being updated, leave them empty. To delete a value from a field, set its value to [delete], including the square brackets.', 'GetResponse', 'uncanny-automator' ),
		);

		$fields[] = array(
			'option_code' => 'NAME',
			'label'       => esc_html_x( 'Name', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$fields[] = array(
			'option_code' => 'SCORING',
			'label'       => esc_html_x( 'Scoring', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'int',
			'max_number'  => 99999999,
			'required'    => false,
			'description' => sprintf(
				// translators: %1$s: opening anchor tag, %2$s: closing anchor tag
				esc_html_x( '%1$sScoring%2$s helps you track and rate customer actions.', 'GetResponse', 'uncanny-automator' ),
				'<a href="https://www.getresponse.com/help/how-do-i-use-scoring.html" target="_blank">',
				'</a>'
			),
		);

		$fields[] = array(
			'option_code' => 'DAY_OF_CYCLE',
			'label'       => esc_html_x( 'Day of cycle', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'int',
			'max_number'  => 9999,
			'required'    => false,
			'description' => sprintf(
				// translators: %1$s: opening anchor tag, %2$s: closing anchor tag
				esc_html_x( 'The day on which the contact is added to the %1$sAutoresponder cycle.%2$s', 'GetResponse', 'uncanny-automator' ),
				'<a href="https://www.getresponse.com/help/how-do-i-create-an-autoresponder.html" target="_blank">',
				'</a>'
			),
		);

		$fields[] = array(
			'option_code'       => 'CUSTOM_FIELDS',
			'input_type'        => 'repeater',
			'relevant_tokens'   => array(),
			'label'             => esc_html_x( 'Custom fields', 'GetResponse', 'uncanny-automator' ),
			'description'       => sprintf(
				// translators: %1$s: opening anchor tag, %2$s: closing anchor tag
				esc_html_x( "Custom field values must align with how they are defined in GetResponse. Multiple values may be separated with commas. For more details, be sure to check out GetResponse's tutorial on %1\$screating and using custom fields.%2\$s", 'GetResponse', 'uncanny-automator' ),
				'<a href="https://www.getresponse.com/help/how-do-i-create-and-use-custom-fields.html" target="_blank">',
				'</a>'
			),
			'required'          => false,
			'fields'            => array(
				array(
					'option_code'           => 'CUSTOM_FIELD',
					'label'                 => esc_html_x( 'Custom field', 'GetResponse', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_tokens'       => false,
					'supports_custom_value' => false,
					'required'              => true,
					'read_only'             => false,
					'options'               => $this->get_custom_field_options(),
				),
				array(
					'option_code' => 'CUSTOM_FIELD_VALUE',
					'label'       => esc_html_x( 'Custom field value', 'GetResponse', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
			),
			'add_row_button'    => esc_html_x( 'Add custom field', 'GetResponse', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove custom field', 'GetResponse', 'uncanny-automator' ),
			'hide_actions'      => false,
		);

		return $fields;
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Required fields - throws error if not set and valid.
		$email   = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );
		$list_id = sanitize_text_field( $this->get_parsed_meta_value( 'LIST_ID', '' ) );

		if ( empty( $list_id ) ) {
			throw new Exception( esc_html_x( 'List ID is required', 'GetResponse', 'uncanny-automator' ) );
		}

		// Optional fields.
		$is_update     = $this->get_parsed_meta_value( 'UPDATE_EXISTING_CONTACT', false );
		$is_update     = filter_var( strtolower( $is_update ), FILTER_VALIDATE_BOOLEAN );
		$contact       = $this->build_contact_data( $parsed, $is_update );
		$repeater      = json_decode( Automator()->parse->text( $action_data['meta']['CUSTOM_FIELDS'], $recipe_id, $user_id, $args ), true );
		$custom_fields = $this->build_custom_fields( $repeater );

		// Add custom fields to request.
		if ( ! empty( $custom_fields ) ) {
			$contact['customFieldValues'] = $custom_fields;
		}

		// Send request.
		$this->api->api_request(
			array(
				'action'  => 'create_update_contact',
				'email'   => $email,
				'list'    => $list_id,
				'contact' => wp_json_encode( $contact ),
				'update'  => $is_update ? '1' : '0',
			),
			$action_data
		);

		return true;
	}

	/**
	 * Get custom field options.
	 *
	 * @return array
	 */
	private function get_custom_field_options() {
		try {
			$fields = $this->helpers->get_contact_fields();
		} catch ( Exception $e ) {
			// Return empty options if API fails
			return array();
		}

		$options = array();
		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field_id => $field ) {
				$options[] = array(
					'value' => $field_id,
					'text'  => $field['name'],
				);
			}
		}

		return $options;
	}

	/**
	 * Build contact data.
	 *
	 * @param array $parsed
	 * @param bool $is_update
	 *
	 * @return array
	 */
	private function build_contact_data( $parsed, $is_update ) {

		$contact         = array();
		$optional_fields = array( 'NAME', 'SCORING', 'DAY_OF_CYCLE' );

		// Parse optional fields.
		foreach ( $optional_fields as $field ) {
			if ( ! isset( $parsed[ $field ] ) ) {
				continue;
			}
			$value = sanitize_text_field( trim( $parsed[ $field ] ) );
			if ( '' === $value ) {
				continue;
			}
			$value = '[delete]' === $value ? '' : $value;
			switch ( $field ) {
				case 'NAME':
					// Limit to 128 characters.
					$contact['name'] = empty( $value ) ? '' : substr( (string) $value, 0, 128 );
					break;
				case 'SCORING':
					// Set to null or enforce max value.
					$contact['scoring'] = empty( $value ) ? null : min( 99999999, (int) $value );
					break;
				case 'DAY_OF_CYCLE':
					// Set to null or enforce min/max value and cast to string.
					$contact['dayOfCycle'] = empty( $value ) ? null : (string) min( 9999, max( 0, (int) $value ) );
					break;
			}
		}

		return $contact;
	}

	/**
	 * Build custom fields data.
	 *
	 * @param array $repeater
	 *
	 * @return array
	 */
	private function build_custom_fields( $repeater ) {

		$custom_fields = array();
		$errors        = array();

		// Bail if no custom fields set.
		if ( empty( $repeater ) ) {
			return $custom_fields;
		}

		try {
			$config = $this->helpers->get_contact_fields();
		} catch ( Exception $e ) {
			// Return empty array if API fails
			return $custom_fields;
		}

		foreach ( $repeater as $field ) {
			$field_id    = isset( $field['CUSTOM_FIELD'] ) ? sanitize_text_field( $field['CUSTOM_FIELD'] ) : '';
			$field_value = isset( $field['CUSTOM_FIELD_VALUE'] ) ? sanitize_text_field( trim( $field['CUSTOM_FIELD_VALUE'] ) ) : '';
			if ( empty( $field_id ) || empty( $field_value ) ) {
				continue;
			}

			// If [delete] is set, remove the field.
			if ( '[delete]' === $field_value ) {
				$custom_fields[] = array(
					'customFieldId' => $field_id,
					'value'         => array(),
				);
				continue;
			}

			// Validate custom field value.
			$validated_value = $this->validate_custom_field_value( $field_id, $field_value, $config );
			if ( is_wp_error( $validated_value ) ) {
				$errors[] = $validated_value->get_error_message();
				continue;
			}

			// Add validated field.
			$custom_fields[] = array(
				'customFieldId' => $field_id,
				'value'         => $validated_value,
			);
		}

		// Log errors.
		if ( ! empty( $errors ) ) {
			$this->set_log_properties(
				array(
					'type'       => 'string',
					'label'      => esc_html_x( 'Invalid Custom Field(s)', 'WordPress', 'uncanny-automator' ),
					'value'      => implode( ', ', $errors ),
					'attributes' => array(),
				)
			);
		}

		return $custom_fields;
	}

	/**
	 * Validate custom field value.
	 *
	 * @param string $field_id
	 * @param mixed $value
	 * @param array $fields_config
	 *
	 * @return mixed - array of string values or WP_Error if not valid.
	 */
	private function validate_custom_field_value( $field_id, $value, $fields_config ) {

		// Get field config by ID.
		$field_config = isset( $fields_config[ $field_id ] ) ? $fields_config[ $field_id ] : false;

		// Field not found.
		if ( empty( $field_config ) ) {
			return new WP_Error(
				'invalid_field_id',
				sprintf(
					// translators: %s: field ID
					esc_html_x( 'Invalid field ID (%1$s)', 'GetResponse', 'uncanny-automator' ),
					$field_id
				)
			);
		}

		$field_config['id'] = $field_id;

		// Validate select field value.
		if ( 'select' === $field_config['type'] ) {
			return $this->validate_select_value( $value, $field_config );
		}

		// Validate - text, textarea, date, datetime, number, phone, url
		$original_type = $field_config['original_type'];
		$error         = false;
		$validated     = '';
		switch ( $original_type ) {
			case 'date':
				// Enforce yyyy-mm-dd format.
				$date      = date_create( $value );
				$error     = ! $date ? esc_html_x( 'Invalid date format', 'GetResponse', 'uncanny-automator' ) : false;
				$validated = $date ? date_format( $date, 'Y-m-d' ) : '';
				break;
			case 'datetime':
				// Enforce yyyy-mm-dd hh:mm:ss format.
				$date      = date_create( $value );
				$error     = ! $date ? esc_html_x( 'Invalid datetime format', 'GetResponse', 'uncanny-automator' ) : false;
				$validated = $date ? date_format( $date, 'Y-m-d H:i:s' ) : '';
				break;
			case 'number':
				// Cast to number value.
				$validated = (float) $value;
				break;
			case 'phone':
				// Validate phone number.
				$phone    = preg_replace( '/[^0-9\+\(\)\/\-\s]/', '', $value );
				$is_valid = preg_match( '/^\+?([0-9\s\-\(\)]*)$/', $phone );
				if ( $is_valid ) {
					// Remove spaces and parentheses.
					$validated = preg_replace( '/[\s\(\)]/', '', $phone );
					$is_valid  = ! empty( $validated );
				}
				if ( ! $is_valid ) {
					$error = esc_html_x( 'Invalid phone number format', 'GetResponse', 'uncanny-automator' );
				}
				break;
			case 'url':
				// Validate URL.
				$validated = filter_var( $value, FILTER_VALIDATE_URL );
				$error     = empty( $validated ) ? esc_html_x( 'Invalid URL format', 'GetResponse', 'uncanny-automator' ) : false;
				break;
			default:
				$validated = $value;
				break;
		}

		// Allow filtering of error values.
		if ( $error ) {
			// translators: %1$s: field key, %2$s invalid value passed
			$error = new WP_Error( 'invalid_field_' . $original_type, sprintf( $error . ' key %1$s ( %2$s )', $field_config['name'], $value ) );

			/**
			 * Filter the error value.
			 *
			 * @param WP_Error $error
			 * @param string $field_id
			 * @param mixed $value
			 * @param array $field_config
			 *
			 * @example
			 * add_filter( 'automator_getresponse_custom_field_value', function( $error, $field_id, $value, $field_config ) {
			 *     if ( $field_id === 'custom_field_id' ) {
			 *         return // non WP_Error value to validate.
			 *     }
			 *     return $error;
			 * }, 10, 4 );
			 *
			 * @return WP_Error
			 */
			$value = apply_filters( 'automator_getresponse_custom_field_value', $error, $field_id, $value, $field_config );
			if ( is_wp_error( $value ) ) {
				return $value;
			}
		}

		/**
		 * Filter the validated value.
		 *
		 * @param mixed $validated
		 * @param string $field_id
		 * @param mixed $value
		 * @param array $field_config
		 *
		 * @return mixed
		 */
		$value = apply_filters( 'automator_getresponse_custom_field_value', $validated, $field_id, $value, $field_config );

		// Return validated value as array of string for API.
		return array( (string) $value );
	}

	/**
	 * Validate select field value.
	 *
	 * @param mixed $value
	 * @param array $field_config
	 *
	 * @return mixed - array of string values or WP_Error if not valid.
	 */
	private function validate_select_value( $value, $field_config ) {

		$error     = false;
		$validated = array();
		$invalid   = array();

		// Check if multiple convert single value to array.
		$values = $field_config['multiple'] ? array_map( 'trim', explode( ',', $value ) ) : array( $value );

		// Validate each value.
		foreach ( $values as $value ) {
			// Check if value is in options.
			if ( in_array( (string) $value, $field_config['options'], true ) ) {
				$validated[] = (string) $value;
			} else {
				$invalid[] = (string) $value;
			}
		}

		// Allow filtering of error values.
		if ( ! empty( $invalid ) ) {
			$error = new WP_Error(
				'invalid_field_select',
				sprintf(
					// translators: %1$s: field key, %2$s invalid field(s) value passed
					esc_html_x( 'Invalid select value(s) key %1$s : %2$s', 'GetResponse', 'uncanny-automator' ),
					$field_config['name'],
					implode( ', ', $invalid )
				)
			);
			/**
			 * Filter the error value.
			 *
			 * @param WP_Error $error
			 * @param string $field_id
			 * @param mixed $value
			 * @param array $field_config
			 *
			 * @return WP_Error
			 */
			$value = apply_filters( 'automator_getresponse_custom_field_value', $error, $field_config['id'], $value, $field_config );
			if ( is_wp_error( $value ) ) {
				return $value;
			}
		}

		/**
		 * Filter the validated value.
		 *
		 * @param array $validated
		 * @param string $field_id
		 * @param mixed $value
		 * @param array $field_config
		 *
		 * @return array
		 *
		 * @example
		 * add_filter( 'automator_getresponse_custom_field_value', function( $validated, $field_id, $value, $field_config ) {
		 *     return $validated;
		 * }, 10, 4 );
		 */
		$validated = apply_filters( 'automator_getresponse_custom_field_value', $validated, $field_config['id'], $value, $field_config );

		if ( empty( $validated ) ) {
			return new WP_Error(
				'invalid_field_select',
				sprintf(
					// translators: %s: field key
					esc_html_x( 'No select value(s) key %s', 'GetResponse', 'uncanny-automator' ),
					$field_config['name'],
				)
			);
		}

		if ( ! is_array( $validated ) ) {
			$validated = array( (string) $validated );
		}

		return $validated;
	}
}
