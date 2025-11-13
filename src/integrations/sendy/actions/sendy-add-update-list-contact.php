<?php

namespace Uncanny_Automator\Integrations\Sendy;

/**
 * Class SENDY_ADD_UPDATE_LIST_CONTACT
 *
 * @package Uncanny_Automator
 * @property Sendy_App_Helpers $helpers
 * @property Sendy_Api_Caller $api
 */
class SENDY_ADD_UPDATE_LIST_CONTACT extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'SENDY' );
		$this->set_action_code( 'SENDY_ADD_UPDATE_CONTACT' );
		$this->set_action_meta( 'CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/sendy/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Contact Email,%2$s: List Name
				esc_attr_x( 'Add/Update {{a contact:%1$s}} to {{a list:%2$s}}', 'Sendy', 'uncanny-automator' ),
				$this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add/Update {{a contact}} to {{a list}}', 'Sendy', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array(
			$this->helpers->get_list_field_config(),
			$this->helpers->get_email_field_config( $this->get_action_meta() ),
		);

		$fields[] = array(
			'option_code' => 'NAME',
			'label'       => esc_html_x( 'Contact name', 'Sendy', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		$description  = esc_html_x( 'Use the personalization tag of the custom field as the key. Example : if your custom field tag is [Birthday,fallback=], you can use the key, "Birthday".', 'Sendy', 'uncanny-automator' );
		$description .= '<br>';
		$description .= sprintf(
			// translators: %s: More info link
			esc_html_x( 'Standard date format should follow date-month-year patterns or today, tomorrow, 5 days ago, 5 days etc. %s', 'Sendy', 'uncanny-automator' ),
			sprintf(
				'<a href="%s" target="_blank">%s <uo-icon id="external-link"></uo-icon></a>',
				'https://sendy.co/forum/discussion/1134/date-custom-field-format#gsc.tab=0',
				esc_html_x( 'More info', 'Sendy', 'uncanny-automator' )
			)
		);

		$fields[] = array(
			'option_code'       => 'CUSTOM_FIELDS',
			'input_type'        => 'repeater',
			'relevant_tokens'   => array(),
			'label'             => esc_html_x( 'Custom fields', 'Sendy', 'uncanny-automator' ),
			'description'       => $description,
			'required'          => false,
			'fields'            => array(
				array(
					'option_code' => 'FIELD_KEY',
					'label'       => esc_html_x( 'Field key', 'Sendy', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
				array(
					'option_code' => 'FIELD_VALUE',
					'label'       => esc_html_x( 'Field value', 'Sendy', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
			),
			'add_row_button'    => esc_html_x( 'Add field', 'Sendy', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove field', 'Sendy', 'uncanny-automator' ),
			'hide_actions'      => false,
		);

		$fields[] = array(
			'option_code' => 'SILENT',
			'label'       => esc_html_x( 'Bypass double opt-in', 'Sendy', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'description' => esc_html_x( 'Check this if your list is "Double opt-in" but you want to bypass that and signup the user to the list as "Single Opt-in instead"', 'Sendy', 'uncanny-automator' ),
			'required'    => false,
		);

		$fields[] = array(
			'option_code' => 'GDPR',
			'label'       => esc_html_x( 'EU GDPR', 'Sendy', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'description' => esc_html_x( 'Check this if you are signing up EU users in a GDPR compliant manner', 'Sendy', 'uncanny-automator' ),
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
	 * @throws Exception
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
		$fields = $this->maybe_add_checkbox_field( $fields, 'SILENT' );
		$fields = $this->maybe_add_checkbox_field( $fields, 'GDPR' );

		// Add repeater fields.
		$repeater_fields = json_decode( Automator()->parse->text( $action_data['meta']['CUSTOM_FIELDS'], $recipe_id, $user_id, $args ), true );
		if ( ! empty( $repeater_fields ) ) {
			$fields = $this->add_custom_fields( $fields, $repeater_fields );
		}

		$this->api->add_contact_to_list( $email, $list, $fields, $action_data );

		return true;
	}

	/**
	 * Maybe add checkbox field.
	 *
	 * @param array $fields
	 * @param string $option_code
	 *
	 * @return array
	 */
	private function maybe_add_checkbox_field( $fields, $option_code ) {
		$value = $this->get_parsed_meta_value( $option_code, false );
		$value = is_bool( $value ) ? $value : filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( $value ) {
			$fields[ strtolower( $option_code ) ] = 'true';
		}

		return $fields;
	}

	/**
	 * Add custom fields.
	 *
	 * @param array $fields
	 * @param array $repeater_fields
	 *
	 * @return array
	 */
	private function add_custom_fields( $fields, $repeater_fields ) {

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
			$field_key   = sanitize_text_field( $field['FIELD_KEY'] ?? '' );
			$field_value = sanitize_text_field( $field['FIELD_VALUE'] ?? '' );
			if ( empty( $field_key ) || empty( $field_value ) ) {
				continue;
			}

			// Clean user input: trim and remove all whitespace.
			$field_key = preg_replace( '/\s+/', '', trim( $field_key ) );

			// Check against restricted fields (case-insensitive).
			$field_key_lower = strtolower( $field_key );
			if ( isset( $not_allowed[ $field_key_lower ] ) ) {
				continue;
			}

			// Handle lowercase fields or ensure first letter is capitalized.
			// This is just a helper method to try and ensure users have entered in their custom fields exactly as they are in the Sendy dashboard.
			$field_key = isset( $lower_case[ $field_key_lower ] )
				? $field_key_lower
				: ucfirst( $field_key );

			// Add field.
			$fields[ $field_key ] = $field_value;
		}

		return $fields;
	}
}
