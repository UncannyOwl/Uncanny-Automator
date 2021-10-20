<?php

namespace Uncanny_Automator;

/**
 * Class HUBSPOT_REMOVECONTACTFROMLIST
 *
 * @package Uncanny_Automator
 */
class HUBSPOT_REMOVECONTACTFROMLIST {

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
		$this->action_code = 'HUBSPOTREMOVECONTACTFROMLIST';
		$this->action_meta = 'HUBSPOTLIST';
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
			// translators: The selected HubSpot static list name
			'sentence'           => sprintf( __( 'Remove a HubSpot contact from {{a static list:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Remove a HubSpot contact from {{a static list}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'requires_user'      => false,
			'execution_function' => array( $this, 'remove_contact_from_list' ),
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
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => 'HUBSPOTEMAIL',
							'label'       => esc_attr__( 'Email address', 'uncanny-automator' ),
							'input_type'  => 'text',
							'default'     => '',
							'required'    => true,
						)
					),
					Automator()->helpers->recipe->field->select(
						array(
							'option_code'           => $this->action_meta,
							'label'                 => esc_attr__( 'HubSpot List', 'uncanny-automator' ),
							'required'              => true,
							'supports_tokens'       => false,
							'supports_custom_value' => false,
							'options'               => Automator()->helpers->recipe->hubspot->get_lists(),
						)
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
	public function remove_contact_from_list( $user_id, $action_data, $recipe_id, $args ) {

		$email = trim( Automator()->parse->text( $action_data['meta']['HUBSPOTEMAIL'], $recipe_id, $user_id, $args ) );
		$list  = trim( Automator()->parse->text( $action_data['meta']['HUBSPOTLIST'], $recipe_id, $user_id, $args ) );

		if ( empty( $email ) ) {
			$error_message                       = __( 'Email is missing', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
			return;
		}

		if ( empty( $list ) ) {
			$error_message                       = __( 'List is missing', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
			return;
		}

		$response = Automator()->helpers->recipe->hubspot->options->remove_contact_from_list( $list, $email );

		if ( is_wp_error( $response ) ) {
			$error_message                       = $response->get_error_message();
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
			return;
		}

		$json_data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== intval( $json_data['statusCode'] ) ) {

			Automator()->helpers->recipe->hubspot->options->log_action_error( $json_data, $user_id, $action_data, $recipe_id );
			return;
		}

		// If the email was not found in contacts
		if ( ! empty( $json_data['data'] ) && ! empty( $json_data['data']['invalidEmails'] ) ) {
			$error_message                       = __( 'Contact with such email address was not found in the list', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
			return;
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
