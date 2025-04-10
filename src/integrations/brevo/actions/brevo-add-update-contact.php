<?php

namespace Uncanny_Automator\Integrations\Brevo;

use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class BREVO_ADD_UPDATE_CONTACT
 *
 * @package Uncanny_Automator
 */
class BREVO_ADD_UPDATE_CONTACT extends \Uncanny_Automator\Recipe\Action {

	use Log_Properties;

	/**
	 * Prefix key for action.
	 *
	 * @var string
	 */
	public $prefix = 'BREVO_ADD_UPDATE_CONTACT';

	/**
	 * Store the complete with notice messages.
	 *
	 * @var array
	 */
	public $complete_with_notice_messages = array();

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'BREVO' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( 'CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/brevo/' ) );
		$this->set_requires_user( false );
		/* translators: Contact Email */
		$this->set_sentence( sprintf( esc_attr_x( 'Create or update {{a contact:%1$s}}', 'Brevo', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Brevo', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields   = array();
		$fields[] = array(
			'option_code' => $this->action_meta,
			'label'       => _x( 'Email', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);

		$fields[] = array(
			'option_code' => 'FIRSTNAME',
			'label'       => _x( 'First name', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$fields[] = array(
			'option_code' => 'LASTNAME',
			'label'       => _x( 'Last name', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$fields[] = array(
			'option_code' => 'SMS',
			'label'       => _x( 'SMS', 'Brevo', 'uncanny-automator' ),
			'placeholder' => esc_attr_x( '00 987 123 4567', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => _x( '* SMS numbers must include country code.', 'Brevo', 'uncanny-automator' ),
		);

		$fields[] = array(
			'option_code'       => 'CONTACT_ATTRIBUTES',
			'input_type'        => 'repeater',
			'relevant_tokens'   => array(),
			'label'             => _x( 'Contact attributes', 'Brevo', 'uncanny-automator' ),
			'description'       => _x( '* Date fields must follow year-month-day format yyyy-mm-dd, boolean fields must use yes, no, true, false, 1 or 0.', 'Brevo', 'uncanny-automator' ),
			'required'          => false,
			'fields'            => array(
				array(
					'option_code'           => 'ATTRIBUTE_NAME',
					'label'                 => _x( 'Attribute name', 'Brevo', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_tokens'       => false,
					'supports_custom_value' => false,
					'required'              => true,
					'read_only'             => false,
					'options'               => $this->helpers->get_contact_attributes(),
				),
				Automator()->helpers->recipe->field->text_field( 'ATTRIBUTE_VALUE', _x( 'Attribute value', 'Brevo', 'uncanny-automator' ), true, 'text', '', true ),
			),
			'add_row_button'    => _x( 'Add attribute', 'Brevo', 'uncanny-automator' ),
			'remove_row_button' => _x( 'Remove attribute', 'Brevo', 'uncanny-automator' ),
			'hide_actions'      => false,
		);

		$fields[] = array(
			'option_code' => 'UPDATE_EXISTING_CONTACT',
			'label'       => _x( 'Update existing contact', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
		);

		$fields[] = array(
			'option_code' => 'DOUBLE_OPT_IN',
			'label'       => _x( 'Double-opt-in', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
		);

		$fields[] = array(
			'option_code'           => 'DOUBLE_OPT_IN_TEMPLATE',
			'label'                 => _x( 'Double-opt-in template', 'Brevo', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'is_ajax'               => true,
			'endpoint'              => 'automator_brevo_get_templates',
			'supports_custom_value' => false,
			'description'           => _x( 'Template is required when using double-opt-in', 'Brevo', 'uncanny-automator' ),
		);

		$fields[] = array(
			'option_code'           => 'DOUBLE_OPT_IN_LIST',
			'label'                 => _x( 'Double-opt-in list', 'Brevo', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'is_ajax'               => true,
			'endpoint'              => 'automator_brevo_get_lists',
			'supports_custom_value' => false,
			'description'           => _x( 'Double-opt-in list is required when using double-opt-in', 'Brevo', 'uncanny-automator' ),
		);

		$fields[] = array(
			'option_code' => 'DOUBLE_OPT_IN_REDIRECT_URL',
			'label'       => _x( 'Double-opt-in redirect URL', 'Brevo', 'uncanny-automator' ),
			'input_type'  => 'url',
			'required'    => false,
			'description' => _x( 'Redirect URL is required when using double-opt-in', 'Brevo', 'uncanny-automator' ),
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

		$email           = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );
		$double_optin    = $this->get_parsed_meta_value( 'DOUBLE_OPT_IN', false );
		$double_optin    = filter_var( strtolower( $double_optin ), FILTER_VALIDATE_BOOLEAN );
		$update_existing = $this->get_parsed_meta_value( 'UPDATE_EXISTING_CONTACT', false );
		$update_existing = filter_var( strtolower( $update_existing ), FILTER_VALIDATE_BOOLEAN );

		// Generate attributes.
		$attributes = array();

		// Add default attributes.
		$defaults = array( 'FIRSTNAME', 'LASTNAME', 'SMS' );

		foreach ( $defaults as $key ) {

			$value = isset( $parsed[ $key ] ) ? sanitize_text_field( $parsed[ $key ] ) : '';

			if ( empty( $value ) ) {
				continue;
			}

			if ( 'SMS' === $key ) {
				// remove non-numeric characters from phone number.
				$value = preg_replace( '/[^0-9]/', '', $value );
			}

			$attributes[ $key ] = $value;

		}

		// Add repeater attributes.
		$attribute_fields = json_decode( Automator()->parse->text( $action_data['meta']['CONTACT_ATTRIBUTES'], $recipe_id, $user_id, $args ), true );
		if ( ! empty( $attribute_fields ) ) {
			$contact_attributes = $this->helpers->get_contact_attributes();

			foreach ( $attribute_fields as $field ) {
				$attribute_name  = isset( $field['ATTRIBUTE_NAME'] ) ? sanitize_text_field( $field['ATTRIBUTE_NAME'] ) : '';
				$attribute_value = isset( $field['ATTRIBUTE_VALUE'] ) ? sanitize_text_field( $field['ATTRIBUTE_VALUE'] ) : '';
				if ( empty( $attribute_name ) || empty( $attribute_value ) ) {
					continue;
				}

				// Get Attribute config.
				$config = $this->get_attribute_config( $attribute_name, $contact_attributes );

				// Validate Value by config settings.
				$attribute_value = $this->validate_attribute_value( $attribute_value, $config );

				// Skip invalid values.
				if ( 'invalid' === $attribute_value ) {

					// Log invalid attribute.
					$this->complete_with_notice_messages[] = sprintf(
						/* translators: %s: attribute name */
						_x( 'Invalid value for attribute %s', 'Brevo', 'uncanny-automator' ),
						$attribute_name
					);

					continue;
				}

				// Add attribute.
				$attributes[ $attribute_name ] = $attribute_value;
			}
		}

		if ( ! $double_optin ) {

			$response = $this->helpers->create_contact( $email, $attributes, $update_existing, $action_data );

			if ( ! empty( $this->complete_with_notice_messages ) ) {
				$this->set_complete_with_notice( true );
				$this->add_log_error( implode( ', ', $this->complete_with_notice_messages ) );
				return null;
			}

			return true;
		}

		$template_id  = sanitize_text_field( $this->get_parsed_meta_value( 'DOUBLE_OPT_IN_TEMPLATE', false ) );
		$redirect_url = sanitize_text_field( $this->get_parsed_meta_value( 'DOUBLE_OPT_IN_REDIRECT_URL', false ) );
		$list_id      = sanitize_text_field( $this->get_parsed_meta_value( 'DOUBLE_OPT_IN_LIST', false ) );

		if ( ! $template_id || ! $redirect_url || ! $list_id ) {
			$errors = array();
			if ( ! $template_id ) {
				$errors[] = _x( 'Template', 'Brevo', 'uncanny-automator' );
			}
			if ( ! $redirect_url ) {
				$errors[] = _x( 'Redirect URL', 'Brevo', 'uncanny-automator' );
			}
			if ( ! $list_id ) {
				$errors[] = _x( 'List', 'Brevo', 'uncanny-automator' );
			}

			$error_message = sprintf(
				/* translators: %s: list of missing required fields */
				_x( '%s are required fields for double-opt-in', 'Brevo', 'uncanny-automator' ),
				implode( ', ', $errors )
			);

			throw new \Exception( esc_html( $error_message ) );
		}

		$response = $this->helpers->create_contact_with_double_optin( $email, $attributes, $template_id, $redirect_url, $list_id, $update_existing, $action_data );

		if ( ! empty( $this->complete_with_notice_messages ) ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( implode( ', ', $this->complete_with_notice_messages ) );
			return null;
		}

		return true;
	}

	/**
	 * Get attribute config.
	 *
	 * @param string $attribute_name
	 * @param array $contact_attributes
	 *
	 * @return array
	 */
	private function get_attribute_config( $attribute_name, $contact_attributes ) {

		if ( empty( $contact_attributes ) ) {
			return array();
		}

		foreach ( $contact_attributes as $contact_attribute ) {
			if ( $attribute_name === $contact_attribute['text'] ) {
				return $contact_attribute;
			}
		}

		return array();
	}

	/**
	 * Validate attribute value.
	 *
	 * @param string $value
	 * @param array $config
	 *
	 * @return string
	 */
	private function validate_attribute_value( $value, $config ) {

		// If no config return value as is.
		$type = $config['type'] ?? 'text';

		switch ( $type ) {
			case 'text':
				// Return Text as is already sanitized.
				return $value;
			case 'date':
				// Enforce yyyy-mm-dd format.
				$date = date_create( $value );
				return ! $date ? 'invalid' : date_format( $date, 'Y-m-d' );
			case 'number':
				// Cast to number value.
				return (float) $value;
			case 'checkbox':
				// Check for 1 or 0 || true or false || yes or no.
				$value = strtolower( $value );
				if ( in_array( $value, array( '1', 'true', 'yes' ), true ) ) {
					return true;
				}
				if ( in_array( $value, array( '0', 'false', 'no' ), true ) ) {
					return false;
				}
				return 'invalid';
			case 'select':
				return $this->validate_select_value( $value, $config );
			default:
				return 'invalid';
		}
	}

	/**
	 * Validate select value.
	 *
	 * @param string $value
	 * @param array $config
	 *
	 * @return string
	 */
	private function validate_select_value( $value, $config ) {
		$value       = trim( $value );
		$options     = isset( $config['options'] ) && is_array( $config['options'] ) ? $config['options'] : array();
		$is_multiple = (bool) $config['multiple'] ?? false;

		if ( $is_multiple ) {

			// Check for multiple values.
			$values    = array_map( 'trim', array_values( explode( ',', $value ) ) );
			$validated = array();
			$invalid   = array();
			foreach ( $values as $val ) {
				if ( in_array( $val, $options, true ) ) {
					$validated[] = $val;
				} else {
					$invalid[] = $val;
				}
			}

			$has_valid   = ! empty( $validated );
			$has_invalid = ! empty( $invalid );

			// If we have both valid and invalid values, log invalid values.
			if ( $has_invalid && $has_valid ) {
				// Log invalid values.
				$this->complete_with_notice_messages[] = sprintf(
					/* translators: %s: list of invalid values */
					_x( 'Invalid value(s) for attribute %1$s : %2$s', 'Brevo', 'uncanny-automator' ),
					$config['text'],
					implode( ', ', $invalid )
				);
			}

			// Return as array of labels or invalid.
			return $has_valid ? $validated : 'invalid';

		}

		// Check for single value.
		$lables = wp_list_pluck( $options, 'label' );
		$values = wp_list_pluck( $options, 'value' );
		if ( in_array( $value, $lables, true ) ) {
			$key = array_search( $value, $lables, true );
			// Return value as array.
			return array( $values[ $key ] );
		}

		if ( in_array( $value, $values, true ) ) {
			return array( $value );
		}
		return 'invalid';
	}
}
