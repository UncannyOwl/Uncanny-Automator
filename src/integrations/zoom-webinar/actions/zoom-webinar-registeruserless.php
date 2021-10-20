<?php

namespace Uncanny_Automator;

/**
 * Class ZOOM_WEBINAR_REGISTERUSERLESS
 * @package Uncanny_Automator
 */
class ZOOM_WEBINAR_REGISTERUSERLESS {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ZOOMWEBINAR';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ZOOMWEBINARREGISTERUSERLESS';
		$this->action_meta = 'ZOOMWEBINAR';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/zoom/' ),
			'is_pro'             => false,
			'requires_user'      => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf( __( 'Add an attendee to {{a webinar:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Add an attendee to {{a webinar}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'zoom_webinar_register_user' ),
			'options_callback'   => array( $this, 'load_options' )
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		
		$email_field_options = array(
			'option_code' => 'EMAIL',
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Email address', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => true,
			'tokens'      => true,
			'default'     => '',
		);

		$email_field = Automator()->helpers->recipe->field->text( $email_field_options );

		$first_name_field_options = array(
			'option_code' => 'FIRSTNAME',
			'input_type'  => 'text',
			'label'       => esc_attr__( 'First name', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => false,
			'tokens'      => true,
			'default'     => '',
		);

		$first_name_field = Automator()->helpers->recipe->field->text( $first_name_field_options );

		$last_name_field_options = array(
			'option_code' => 'LASTNAME',
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Last name', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => false,
			'tokens'      => true,
			'default'     => '',
		);

		$last_name_field = Automator()->helpers->recipe->field->text( $last_name_field_options );

		return array(
			'options_group'      => array(
				$this->action_meta => array(
					$email_field,
					$first_name_field,
					$last_name_field,
					Automator()->helpers->recipe->zoom_webinar->get_webinars( null, $this->action_meta )
				)
			)
		);
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function zoom_webinar_register_user( $user_id, $action_data, $recipe_id, $args ) {

		$webinar_key = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

		$user = array();

		$user['EMAIL'] = Automator()->parse->text( $action_data['meta']['EMAIL'], $recipe_id, $user_id, $args );

		if ( ! is_email( $user['EMAIL'] ) ) {
			$error_msg                           = __( 'Invalid email address.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

		$user['FIRSTNAME'] = Automator()->parse->text( $action_data['meta']['FIRSTNAME'], $recipe_id, $user_id, $args );
		$user['LASTNAME']  = Automator()->parse->text( $action_data['meta']['LASTNAME'], $recipe_id, $user_id, $args );

		if ( empty( $user['EMAIL'] ) ) {
			$error_msg                           = __( 'Email address is missing.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

		if ( empty( $webinar_key ) ) {
			$error_msg                           = __( 'Webinar not found.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

		if ( ! empty( $webinar_key ) ) {
			$webinar_key = str_replace( '-objectkey', '', $webinar_key );
		}

		$result = Automator()->helpers->recipe->zoom_webinar->register_userless( $user, $webinar_key );

		if ( ! $result['result'] ) {
			$error_msg                           = $result['message'];
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );

	}

}
