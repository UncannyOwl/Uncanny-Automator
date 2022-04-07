<?php

namespace Uncanny_Automator;

/**
 * Class HUBSPOT_ADDUSER
 *
 * @package Uncanny_Automator
 */
class HUBSPOT_ADDUSER {

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
		$this->action_code = 'HUBSPOTADDUSER';
		$this->action_meta = 'HUBSPOTCONTACT';
		$this->define_action();

	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/hubspot/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			// translators: The user
			'sentence'           => sprintf( __( 'Add/Update {{the user:%1$s}} in HubSpot', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Add/Update {{the user}} in HubSpot', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'requires_user'      => true,
			'execution_function' => array( $this, 'add_contact' ),
			'options_callback'   => array( $this, 'load_options' ),
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
					array(
						'option_code'       => 'CUSTOM_FIELDS',
						'input_type'        => 'repeater',
						'label'             => __( 'Custom fields', 'uncanny-automator' ),
						'description'       => __( "* User Email Address, First and Last names will be taken from the user's account.", 'uncanny-automator' ),
						'required'          => false,
						'fields'            => array(
							array(
								'option_code'           => 'FIELD_NAME',
								'label'                 => __( 'Field', 'uncanny-automator' ),
								'input_type'            => 'select',
								'supports_tokens'       => false,
								'supports_custom_value' => false,
								'required'              => true,
								'read_only'             => false,
								'options'               => Automator()->helpers->recipe->hubspot->get_fields( array( 'email', 'firstname', 'lastname' ) ),
							),
							Automator()->helpers->recipe->field->text_field( 'FIELD_VALUE', __( 'Value', 'uncanny-automator' ), true, 'text', '', false ),
						),
						'add_row_button'    => __( 'Add field', 'uncanny-automator' ),
						'remove_row_button' => __( 'Remove field', 'uncanny-automator' ),
						'hide_actions'      => false,
					),
					array(
						'option_code'   => 'UPDATE',
						'input_type'    => 'checkbox',
						'label'         => __( 'If the contact already exists, update their info', 'uncanny-automator' ),
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

		$user_data = get_userdata( $user_id );

		$email      = $user_data->user_email;
		$first_name = $user_data->first_name;
		$last_name  = $user_data->last_name;

		$properties = array();

		$properties[] = array(
			'property' => 'email',
			'value'    => $email,
		);

		if ( ! empty( $first_name ) ) {
			$properties[] = array(
				'property' => 'firstname',
				'value'    => $first_name,
			);
		}

		if ( ! empty( $last_name ) ) {
			$properties[] = array(
				'property' => 'lastname',
				'value'    => $last_name,
			);
		}

		$update = true;

		if ( ! empty( $action_data['meta']['UPDATE'] ) ) {
			$update = filter_var( $action_data['meta']['UPDATE'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( ! empty( $action_data['meta']['CUSTOM_FIELDS'] ) ) {

			$custom_fields = json_decode( Automator()->parse->text( $action_data['meta']['CUSTOM_FIELDS'], $recipe_id, $user_id, $args ), true );

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
