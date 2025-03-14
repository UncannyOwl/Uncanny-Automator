<?php

namespace Uncanny_Automator;

/**
 * Class HUBSPOT_CREATECONTACT
 *
 * @package Uncanny_Automator
 */
class HUBSPOT_CREATECONTACT {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'HUBSPOT';

	/**
	 *
	 * @var string
	 */
	private $action_code;

	/**
	 *
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'HUBSPOTCREATECONTACT';
		$this->action_meta = 'HUBSPOTCONTACT';
		$this->define_action();

	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		$action = array(
			'author'                => Automator()->get_author_name( $this->action_code ),
			'support_link'          => Automator()->get_author_support_link( $this->action_code, 'integration/hubspot/' ),
			'integration'           => self::$integration,
			'code'                  => $this->action_code,
			// translators: A contact
			'sentence'              => sprintf( esc_html__( 'Create/Update {{a contact:%1$s}} in HubSpot', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name'    => esc_html__( 'Create/Update {{a contact}} in HubSpot', 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'requires_user'         => false,
			'execution_function'    => array( $this, 'add_contact' ),
			'options_callback'      => array( $this, 'load_options' ),
			'background_processing' => true,
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		return array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => 'HUBSPOTEMAIL',
							'label'       => esc_attr__( 'Email address', 'uncanny-automator' ),
							'input_type'  => 'text',
							'default'     => '',
							'required'    => true,
						)
					),
					array(
						'option_code'       => 'CUSTOM_FIELDS',
						'input_type'        => 'repeater',
						'relevant_tokens'   => array(),
						'label'             => esc_html__( 'Custom fields', 'uncanny-automator' ),
						'description'       => esc_html__( 'Leaving a field value empty will not update the field. To delete a value from a field, set its value to [delete], including the square brackets.', 'uncanny-automator' ),
						'required'          => false,
						'fields'            => array(
							array(
								'option_code'           => 'FIELD_NAME',
								'label'                 => esc_html__( 'Field', 'uncanny-automator' ),
								'input_type'            => 'select',
								'supports_tokens'       => false,
								'supports_custom_value' => false,
								'required'              => true,
								'read_only'             => false,
								'options'               => Automator()->helpers->recipe->hubspot->get_fields( array( 'email' ) ),
							),
							Automator()->helpers->recipe->field->text_field( 'FIELD_VALUE', esc_html__( 'Value', 'uncanny-automator' ), true, 'text', '', false ),
						),
						'add_row_button'    => esc_html__( 'Add field', 'uncanny-automator' ),
						'remove_row_button' => esc_html__( 'Remove field', 'uncanny-automator' ),
						'hide_actions'      => false,
					),
					array(
						'option_code'   => 'UPDATE',
						'input_type'    => 'checkbox',
						'label'         => esc_html__( 'If the contact already exists, update their info', 'uncanny-automator' ),
						'description'   => '',
						'required'      => false,
						'default_value' => true,
					),
				),
			),

		);
	}

	/**
	 * Action validation function.
	 *
	 * @return mixed
	 */
	public function add_contact( $user_id, $action_data, $recipe_id, $args ) {

		$helpers = Automator()->helpers->recipe->hubspot->options;

		$email = trim( Automator()->parse->text( $action_data['meta']['HUBSPOTEMAIL'], $recipe_id, $user_id, $args ) );

		$update = true;

		if ( ! empty( $action_data['meta']['UPDATE'] ) ) {
			$update = filter_var( $action_data['meta']['UPDATE'], FILTER_VALIDATE_BOOLEAN );
		}

		$properties = array();

		$properties[] = array(
			'property' => 'email',
			'value'    => $email,
		);

		if ( ! empty( $action_data['meta']['CUSTOM_FIELDS'] ) ) {

			$json = Automator()->parse->text( $action_data['meta']['CUSTOM_FIELDS'], $recipe_id, $user_id, $args );

			// Replace line breaks to prevent invalid json
			$json = str_replace( "\r\n", '\r\n', $json );

			$custom_fields = json_decode( $json, true );

			if ( ! empty( $custom_fields ) ) {
				foreach ( $custom_fields as $field ) {

					if ( empty( $field['FIELD_NAME'] ) || empty( $field['FIELD_VALUE'] ) ) {
						continue;
					}

					$properties[] = array(
						'property' => $field['FIELD_NAME'],
						'value'    => $field['FIELD_VALUE'],
					);

				}
			}
		}

		$properties = apply_filters(
			'automator_hubspot_add_contact_properties',
			$properties,
			array(
				'user_id'     => $user_id,
				'action_data' => $action_data,
				'recipe_id'   => $recipe_id,
				'args'        => $args,
			)
		);

		try {
			$response = $helpers->create_contact( $properties, $update, $action_data );
			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		} catch ( \Exception $e ) {
			$helpers->log_action_error( $e->getMessage(), $user_id, $action_data, $recipe_id );
		}
	}
}
