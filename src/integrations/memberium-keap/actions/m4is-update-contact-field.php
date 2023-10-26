<?php

namespace Uncanny_Automator\Integrations\M4IS;

/**
 * Class M4IS_UPDATE_CONTACT_FIELD
 *
 * @package Uncanny_Automator
 */
class M4IS_UPDATE_CONTACT_FIELD extends \Uncanny_Automator\Recipe\Action {

	public $prefix = 'M4IS_UPDATE_CONTACT_FIELD';

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'M4IS' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/memberium-keap/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s Contact Email, %2$s Field*/
				esc_attr_x( 'Update {{a contact:%1$s}} {{field(s):%2$s}}', 'M4IS - update contact field action', 'uncanny-automator' ),
				$this->prefix . '_EMAIL:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Update {{a contact}} {{field(s)}}', 'M4IS - update contact field action', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields   = array();
		$fields[] = array(
			'option_code' => $this->prefix . '_EMAIL',
			'label'       => _x( 'Email', 'M4IS - update contact field action', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);

		$fields[] = array(
			'option_code'       => 'CONTACT_FIELDS',
			'input_type'        => 'repeater',
			'label'             => _x( 'Contact fields', 'M4IS - update contact field action', 'uncanny-automator' ),
			'required'          => false,
			'fields'            => array(
				array(
					'option_code'           => 'CONTACT_FIELD_NAME',
					'label'                 => _x( 'Contact field name', 'M4IS - update contact field action', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_tokens'       => false,
					'supports_custom_value' => false,
					'required'              => true,
					'read_only'             => false,
					'options'               => $this->helpers->get_contact_fields(),
				),
				Automator()->helpers->recipe->field->text_field( 'CONTACT_FIELD_VALUE', _x( 'Contact field value', 'M4IS - update contact field action', 'uncanny-automator' ), true, 'text', '', true ),
			),
			'add_row_button'    => _x( 'Add contact field', 'M4IS - update contact field action', 'uncanny-automator' ),
			'remove_row_button' => _x( 'Remove contact field', 'M4IS - update contact field action', 'uncanny-automator' ),
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

		$email          = $this->helpers->get_email_from_parsed( $parsed, $this->prefix . '_EMAIL' );
		$contact_fields = json_decode( Automator()->parse->text( $action_data['meta']['CONTACT_FIELDS'], $recipe_id, $user_id, $args ), true );
		$fields         = array();

		if ( ! empty( $contact_fields ) ) {

			foreach ( $contact_fields as $field ) {

				$name  = isset( $field['CONTACT_FIELD_NAME'] ) ? sanitize_text_field( $field['CONTACT_FIELD_NAME'] ) : '';
				$value = isset( $field['CONTACT_FIELD_VALUE'] ) ? sanitize_text_field( $field['CONTACT_FIELD_VALUE'] ) : '';
				if ( empty( $name ) ) {
					continue;
				}

				// Add field.
				$fields[ $name ] = $value;
			}
		}

		$response = $this->helpers->update_contact( $email, $fields );

		if ( is_wp_error( $response ) ) {
			throw new \Exception(
				sprintf(
					/* translators: %s - error message */
					esc_attr_x( 'Error updating contact field(s). %s', 'M4IS - update contact field action', 'uncanny-automator' ),
					$error_message
				)
			);
		}

		return true;
	}

}
