<?php

namespace Uncanny_Automator;

/**
 * Class HUBSPOT_REMOVEUSERFROMLIST
 *
 * @package Uncanny_Automator
 */
class HUBSPOT_REMOVEUSERFROMLIST {

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
		$this->action_code = 'HUBSPOTREMOVEUSERFROMLIST';
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
			'sentence'           => sprintf( __( "Remove the user's HubSpot contact from {{a static list:%1\$s}}", 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( "Remove the user's HubSpot contact from {{a static list}}", 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'requires_user'      => true,
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

		$user_data = get_userdata( $user_id );

		$email = $user_data->user_email;

		$list = trim( Automator()->parse->text( $action_data['meta']['HUBSPOTLIST'], $recipe_id, $user_id, $args ) );

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
