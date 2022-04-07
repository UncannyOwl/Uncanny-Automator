<?php

namespace Uncanny_Automator;

/**
 * Class ZOOM_WEBINAR_REGISTERUSERLESS
 *
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
			'options_callback'   => array( $this, 'load_options' ),
			'buttons'            => array(
				array(
					'show_in'     => $this->action_meta,
					'text'        => __( 'Get webinar questions', 'uncanny-automator' ),
					'css_classes' => 'uap-btn uap-btn--red',
					'on_click'    => 'uap_zoom_get_webinar_questions',
					'modules'     => array( 'modal', 'markdown' ),
				),
			),
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
			'options_group' => array(
				$this->action_meta => array(
					$email_field,
					$first_name_field,
					$last_name_field,
					Automator()->helpers->recipe->zoom_webinar->get_webinars( null, $this->action_meta ),
					Automator()->helpers->recipe->zoom_webinar->get_webinar_questions_repeater(),
				),
			),
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

		$helpers = Automator()->helpers->recipe->zoom_webinar;

		try {

			$webinar_key = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

			if ( empty( $webinar_key ) ) {
				throw new \Exception( __( 'Webinar was not found.', 'uncanny-automator' ) );
			}

			$webinar_key = str_replace( '-objectkey', '', $webinar_key );
			$webinar_user = array();

			$webinar_user['email'] = Automator()->parse->text( $action_data['meta']['EMAIL'], $recipe_id, $user_id, $args );

			if ( empty( $webinar_user['email'] ) ) {
				throw new \Exception( __( 'Email address is missing.', 'uncanny-automator' ) );
			}

			if ( false === is_email( $webinar_user['email'] ) ) {
				throw new \Exception( __( 'Invalid email address.', 'uncanny-automator' ) );
			}

			$webinar_user['first_name'] = Automator()->parse->text( $action_data['meta']['FIRSTNAME'], $recipe_id, $user_id, $args );
			$webinar_user['last_name'] = Automator()->parse->text( $action_data['meta']['LASTNAME'], $recipe_id, $user_id, $args );

			$email_parts = explode( '@', $webinar_user['email'] );
			$webinar_user['first_name']  = empty( $webinar_user['first_name'] ) ? $email_parts[0] : $webinar_user['first_name'];

			if ( ! empty( $action_data['meta'][ 'WEBINARQUESTIONS' ] ) ) {
				$webinar_user = $helpers->add_custom_questions( $webinar_user, $action_data['meta'][ 'WEBINARQUESTIONS' ], $recipe_id, $user_id, $args );
			}

			$response = $helpers->add_to_webinar( $webinar_user, $webinar_key, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		
		} catch ( \Exception $e ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}
}
