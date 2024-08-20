<?php

namespace Uncanny_Automator\Integrations\Get_Response;

use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class GET_RESPONSE_ADD_UPDATE_CONTACT
 *
 * @package Uncanny_Automator
 */
class GET_RESPONSE_ADD_UPDATE_CONTACT extends \Uncanny_Automator\Recipe\Action {

	use Log_Properties;

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'GETRESPONSE' );
		$this->set_action_code( 'GR_ADD_UPDATE_CONTACT_CODE' );
		$this->set_action_meta( 'CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/getresponse/' ) );
		$this->set_requires_user( false );
		/* translators: Contact Email */
		$this->set_sentence( sprintf( esc_attr_x( 'Create or update {{a contact:%1$s}}', 'GetResponse', 'uncanny-automator' ), $this->get_action_meta() ) );
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
			'label'       => _x( 'List', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'is_ajax'     => true,
			'endpoint'    => 'automator_getresponse_get_lists',
		);

		$fields[] = array(
			'option_code' => $this->action_meta,
			'label'       => _x( 'Email', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);

		$fields[] = array(
			'option_code' => 'UPDATE_EXISTING_CONTACT',
			'label'       => _x( 'Update existing contact', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
			'description' => _x( 'To exclude fields from being updated, leave them empty. To delete a value from a field, set its value to [delete], including the square brackets.', 'GetResponse', 'uncanny-automator' ),
		);

		$fields[] = array(
			'option_code' => 'NAME',
			'label'       => _x( 'Name', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$fields[] = array(
			'option_code' => 'SCORING',
			'label'       => _x( 'Scoring', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'int',
			'max_number'  => 99999999,
			'required'    => false,
			'description' => sprintf(
				/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
				_x( '%1$sScoring%2$s helps you track and rate customer actions.', 'GetResponse', 'uncanny-automator' ),
				'<a href="https://www.getresponse.com/help/how-do-i-use-scoring.html" target="_blank">',
				'</a>'
			),
		);

		$fields[] = array(
			'option_code' => 'DAY_OF_CYCLE',
			'label'       => _x( 'Day of cycle', 'GetResponse', 'uncanny-automator' ),
			'input_type'  => 'int',
			'max_number'  => 9999,
			'required'    => false,
			'description' => sprintf(
				/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
				_x( 'The day on which the contact is added to the %1$sAutoresponder cycle.%2$s', 'GetResponse', 'uncanny-automator' ),
				'<a href="https://www.getresponse.com/help/how-do-i-create-an-autoresponder.html" target="_blank">',
				'</a>'
			),
		);

		$fields[] = array(
			'option_code'       => 'CUSTOM_FIELDS',
			'input_type'        => 'repeater',
			'relevant_tokens'   => array(),
			'label'             => _x( 'Custom fields', 'GetResponse', 'uncanny-automator' ),
			'description'       => sprintf(
				/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag */
				_x( "Custom field values must align with how they are defined in GetResponse. Multiple values may be separated with commas. For more details, be sure to check out GetResponse's tutorial on %1\$screating and using custom fields.%2\$s", 'GetResponse', 'uncanny-automator' ),
				'<a href="https://www.getresponse.com/help/how-do-i-create-and-use-custom-fields.html" target="_blank">',
				'</a>'
			),
			'required'          => false,
			'fields'            => array(
				array(
					'option_code'           => 'CUSTOM_FIELD',
					'label'                 => _x( 'Custom field', 'GetResponse', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_tokens'       => false,
					'supports_custom_value' => false,
					'required'              => true,
					'read_only'             => false,
					'options'               => $this->helpers->get_custom_field_options(),
				),
				// TODO REVIEW - Curt - what about a new field called Dynamic Field Type
				// It would get generated with a callback on the custom field select field
				Automator()->helpers->recipe->field->text_field( 'CUSTOM_FIELD_VALUE', _x( 'Custom field value', 'GetResponse', 'uncanny-automator' ), true, 'text', '', true ),
			),
			'add_row_button'    => _x( 'Add custom field', 'GetResponse', 'uncanny-automator' ),
			'remove_row_button' => _x( 'Remove custom field', 'GetResponse', 'uncanny-automator' ),
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
		$list_id = $this->helpers->get_list_id_from_parsed( $parsed, 'LIST_ID' );
		$email   = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );

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
		$response = $this->helpers->api_request(
			'create_update_contact',
			array(
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
					$contact['scoring'] = empty( $value ) || $value == 0 ? null : min( 99999999, (int) $value );
					break;
				case 'DAY_OF_CYCLE':
					// Set to null or enforce min/max value and cast to string.
					$contact['dayOfCycle'] = empty( $value ) || $value == 0 ? null : (string) min( 9999, max( 0, (int) $value ) );
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

		$config = $this->helpers->get_contact_fields();
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
					'label'      => _x( 'Invalid Custom Field(s)', 'WordPress', 'uncanny-automator' ),
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
			return new \WP_Error(
				'invalid_field_id',
				sprintf(
					/* translators: %s: field ID */
					_x( 'Invalid field ID (%1$s)', 'GetResponse', 'uncanny-automator' ),
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
				$error     = ! $date ? _x( 'Invalid date format', 'GetResponse', 'uncanny-automator' ) : false;
				$validated = $date ? date_format( $date, 'Y-m-d' ) : '';
				break;
			case 'datetime':
				// Enforce yyyy-mm-dd hh:mm:ss format.
				$date      = date_create( $value );
				$error     = ! $date ? _x( 'Invalid datetime format', 'GetResponse', 'uncanny-automator' ) : false;
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
					$error = _x( 'Invalid phone number format', 'GetResponse', 'uncanny-automator' );
				}
				break;
			case 'url':
				// Validate URL.
				$validated = filter_var( $value, FILTER_VALIDATE_URL );
				$error     = empty( $validated ) ? _x( 'Invalid URL format', 'GetResponse', 'uncanny-automator' ) : false;
				break;
			default:
				$validated = $value;
				break;
		}

		// Allow filtering of error values.
		if ( $error ) {
			/* translators: %1$s: field key, %2$s invalid value passed */
			$error = new \WP_Error( 'invalid_field_' . $original_type, sprintf( $error . ' key %1$s ( %2$s )', $field_config['name'], $value ) );
			$value = apply_filters( 'automator_getresponse_custom_field_value', $error, $field_id, $value, $field_config );
			if ( is_wp_error( $value ) ) {
				return $value;
			}
		}

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
			$error = new \WP_Error(
				'invalid_field_select',
				sprintf(
					/* translators: %1$s: field key, %2$s invalid field(s) value passed */
					_x( 'Invalid select value(s) key %1$s : %2$s', 'GetResponse', 'uncanny-automator' ),
					$field_config['name'],
					implode( ', ', $invalid )
				)
			);
			$value = apply_filters( 'automator_getresponse_custom_field_value', $error, $field_config['id'], $value, $field_config );
			if ( is_wp_error( $value ) ) {
				return $value;
			}
		}

		$validated = apply_filters( 'automator_getresponse_custom_field_value', $validated, $field_config['id'], $value, $field_config );

		if ( empty( $validated ) ) {
			return new \WP_Error(
				'invalid_field_select',
				sprintf(
					/* translators: %s: field key */
					_x( 'No select value(s) key %s', 'GetResponse', 'uncanny-automator' ),
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
