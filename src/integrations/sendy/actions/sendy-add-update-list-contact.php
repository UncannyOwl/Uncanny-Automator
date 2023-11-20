<?php

namespace Uncanny_Automator\Integrations\Sendy;

/**
 * Class SENDY_ADD_UPDATE_LIST_CONTACT
 *
 * @package Uncanny_Automator
 */
class SENDY_ADD_UPDATE_LIST_CONTACT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'SENDY' );
		$this->set_action_code( 'SENDY_ADD_UPDATE_CONTACT' );
		$this->set_action_meta( 'CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/sendy/' ) );
		$this->set_requires_user( false );
		/* translators: Contact Email, List Name */
		$this->set_sentence( sprintf( esc_attr_x( 'Add/Update {{a contact:%1$s}} to {{a list:%2$s}}', 'Sendy', 'uncanny-automator' ), $this->get_action_meta(), 'LIST:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Add/Update {{a contact}} to {{a list}}', 'Sendy', 'uncanny-automator' ) );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array();

		$fields[] = array(
			'option_code'           => 'LIST',
			'label'                 => _x( 'List', 'Sendy', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => true,
			'endpoint'              => 'automator_sendy_get_lists',
			'supports_custom_value' => false,
		);

		$fields[] = array(
			'option_code' => $this->action_meta,
			'label'       => _x( 'Email', 'Sendy', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);

		$fields[] = array(
			'option_code' => 'NAME',
			'label'       => _x( 'Contact name', 'Sendy', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$description = _x( 'Use the personalization tag of the custom field as the key. Example : if your custom field tag is [Birthday,fallback=], you can use the key, "Birthday".', 'Sendy', 'uncanny-automator' );
		$description .= '<br>';
		$description .= _x( 'Standard date format should follow date-month-year patterns or today, tomorrow, 5 days ago, 5 days etc. <a href="https://sendy.co/forum/discussion/1134/date-custom-field-format#gsc.tab=0" target="_blank">More info</a>', 'Sendy', 'uncanny-automator' );

		$fields[] = array(
			'option_code'       => 'CUSTOM_FIELDS',
			'input_type'        => 'repeater',
			'label'             => _x( 'Custom fields', 'Sendy', 'uncanny-automator' ),
			'description'       => $description,
			'required'          => false,
			'fields'            => array(
				Automator()->helpers->recipe->field->text_field( 'FIELD_KEY', _x( 'Field key', 'Sendy', 'uncanny-automator' ), true, 'text', '', true ),
				Automator()->helpers->recipe->field->text_field( 'FIELD_VALUE', _x( 'Field value', 'Sendy', 'uncanny-automator' ), true, 'text', '', true ),
			),
			'add_row_button'    => _x( 'Add field', 'Sendy', 'uncanny-automator' ),
			'remove_row_button' => _x( 'Remove field', 'Sendy', 'uncanny-automator' ),
			'hide_actions'      => false,
		);

		$fields[] = array(
			'option_code' => 'SILENT',
			'label'       => _x( 'Bypass double opt-in', 'Sendy', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'description' => _x( 'Check this if your list is "Double opt-in" but you want to bypass that and signup the user to the list as "Single Opt-in instead"', 'Sendy', 'uncanny-automator' ),
			'required'    => false,
		);

		$fields[] = array(
			'option_code' => 'GDPR',
			'label'       => _x( 'EU GDPR', 'Sendy', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'description' => _x( 'Check this if you are signing up EU users in a GDPR compliant manner', 'Sendy', 'uncanny-automator' ),
			'required'    => false,
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

		$email = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );
		$list  = $this->helpers->get_list_from_parsed( $parsed, 'LIST' );
		$name  = $this->get_parsed_meta_value( 'NAME', false );
		$name  = empty( $name ) ? false : sanitize_text_field( $name );

		// Generate fields.
		$fields = array();
		// Add name.
		if ( ! empty( $name ) ) {
			$fields['name'] = $name;
		}
		// Check boxes.
		$fields = $this->maybe_add_checkbox_field( $fields, 'SILENT', $parsed );
		$fields = $this->maybe_add_checkbox_field( $fields, 'GDPR', $parsed );

		// Add repeater fields.
		$repeater_fields = json_decode( Automator()->parse->text( $action_data['meta']['CUSTOM_FIELDS'], $recipe_id, $user_id, $args ), true );
		if ( ! empty( $repeater_fields ) ) {

			// Restricted fields.
			$not_allowed = array(
				'email'   => 0,
				'name'    => 0,
				'list'    => 0,
				'api_key' => 0,
				'boolean' => 0,
				'gdpr'    => 0,
				'silent'  => 0,
			);

			// Lowercase fields.
			$lower_case = array(
				'country'   => 0,
				'ipaddress' => 0,
				'referrer'  => 0,
			);

			foreach ( $repeater_fields as $field ) {
				$field_key   = isset( $field['FIELD_KEY'] ) ? sanitize_text_field( $field['FIELD_KEY'] ) : '';
				$field_value = isset( $field['FIELD_VALUE'] ) ? sanitize_text_field( $field['FIELD_VALUE'] ) : '';
				if ( empty( $field_key ) || empty( $field_value ) ) {
					continue;
				}

				// Trim whitespace and make lower case.
				$field_key = strtolower( trim( $field_key ) );

				// Make sure it's not one of our default fields.
				if ( isset( $not_allowed[ $field_key ] ) ) {
					continue;
				}

				// Capitalize first letter of each word and remove spaces.
				if ( ! isset( $lower_case[ $field_key ] ) ) {
					$field_key = preg_replace( '/\s+/', '', ucwords( $field_key ) );
				}

				// Add field.
				$fields[ $field_key ] = $field_value;
			}
		}

		$response = $this->helpers->add_contact_to_list( $email, $list, $fields, $action_data );
		return true;
	}

	/**
	 * Maybe add checkbox field.
	 *
	 * @param array $fields
	 * @param string $option_code
	 * @param array $parsed
	 *
	 * @return array
	 */
	private function maybe_add_checkbox_field( $fields, $option_code, $parsed ) {
		$value = $this->get_parsed_meta_value( $option_code, false );
		$value = is_bool( $value ) ? $value : filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( $value ) {
			$fields[ strtolower( $option_code ) ] = 'true';
		}

		return $fields;
	}

}
