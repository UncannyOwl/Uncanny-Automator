<?php

namespace Uncanny_Automator;

/**
 * Class ZOOM_REGISTERUSERLESS
 *
 * @package Uncanny_Automator
 */
class ZOOM_REGISTERUSERLESS {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ZOOM';

	private $action_code;
	private $action_meta;
	private $helpers;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ZOOMREGISTERUSERLESS';
		$this->action_meta = 'ZOOMMEETING';
		$this->helpers     = new Zoom_Helpers();
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'                => Automator()->get_author_name( $this->action_code ),
			'support_link'          => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/zoom/' ),
			'is_pro'                => false,
			'requires_user'         => false,
			'integration'           => self::$integration,
			'code'                  => $this->action_code,
			/* translators: Meeting topic */
			'sentence'              => sprintf( __( 'Add an attendee to {{a meeting:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name'    => __( 'Add an attendee to {{a meeting}}', 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'execution_function'    => array( $this, 'zoom_register_user' ),
			'options_callback'      => array( $this, 'load_options' ),
			'background_processing' => true,
			'buttons'               => array(
				array(
					'show_in'     => $this->action_meta,
					'text'        => __( 'Get meeting questions', 'uncanny-automator' ),
					'css_classes' => 'uap-btn uap-btn--red',
					'on_click'    => 'uap_zoom_get_meeting_questions',
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

		$account_users_field = array(
			'option_code'           => 'ZOOMUSER',
			'label'                 => __( 'Account user', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'is_ajax'               => true,
			'endpoint'              => 'uap_zoom_api_get_meetings',
			'fill_values_in'        => $this->action_meta,
			'options'               => $this->helpers->get_account_user_options(),
			'relevant_tokens'       => array(),
			'supports_custom_value' => false,
		);

		$user_meetings_field = array(
			'option_code'           => $this->action_meta,
			'label'                 => __( 'Meeting', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_tokens'       => true,
			'supports_custom_value' => true,
			'is_ajax'               => true,
			'endpoint'              => 'uap_zoom_api_get_meeting_occurrences',
			'fill_values_in'        => 'OCCURRENCES',
		);

		$meeting_occurrences_field = array(
			'option_code'              => 'OCCURRENCES',
			'label'                    => __( 'Occurrences', 'uncanny-automator' ),
			'input_type'               => 'select',
			'required'                 => false,
			'options'                  => array(),
			'supports_tokens'          => true,
			'supports_custom_value'    => true,
			'supports_multiple_values' => true,
		);

		$option_fileds = array(
			$email_field,
			$first_name_field,
			$last_name_field,
			$account_users_field,
			$user_meetings_field,
			$meeting_occurrences_field,
			$this->helpers->get_meeting_questions_repeater(),
		);

		//Don't show the user dropdown to old credentials so it's easier to test the update
		if ( $this->helpers->jwt_mode() ) {
			$option_fileds = array(
				$email_field,
				$first_name_field,
				$last_name_field,
				$this->helpers->get_meetings_field(),
				$this->helpers->get_meeting_questions_repeater(),
			);
		}

		return array(
			'options_group' => array(
				$this->action_meta => $option_fileds,
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
	public function zoom_register_user( $user_id, $action_data, $recipe_id, $args ) {

		try {

			$meeting_key = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

			if ( empty( $meeting_key ) ) {
				throw new \Exception( __( 'Meeting was not found.', 'uncanny-automator' ) );
			}

			$meeting_key  = str_replace( '-objectkey', '', $meeting_key );
			$meeting_user = array();

			$meeting_user['email'] = Automator()->parse->text( $action_data['meta']['EMAIL'], $recipe_id, $user_id, $args );

			if ( empty( $meeting_user['email'] ) ) {
				throw new \Exception( __( 'Email address is missing.', 'uncanny-automator' ) );
			}

			if ( false === is_email( $meeting_user['email'] ) ) {
				throw new \Exception( __( 'Invalid email address.', 'uncanny-automator' ) );
			}

			$meeting_user['first_name'] = Automator()->parse->text( $action_data['meta']['FIRSTNAME'], $recipe_id, $user_id, $args );
			$meeting_user['last_name']  = Automator()->parse->text( $action_data['meta']['LASTNAME'], $recipe_id, $user_id, $args );

			$email_parts                = explode( '@', $meeting_user['email'] );
			$meeting_user['first_name'] = empty( $meeting_user['first_name'] ) ? $email_parts[0] : $meeting_user['first_name'];

			if ( ! empty( $action_data['meta']['MEETINGQUESTIONS'] ) ) {
				$meeting_user = $this->helpers->add_custom_questions( $meeting_user, $action_data['meta']['MEETINGQUESTIONS'], $recipe_id, $user_id, $args );
			}

			$meeting_occurrences = array();

			if ( ! empty( $action_data['meta']['OCCURRENCES'] ) ) {
				$meeting_occurrences = json_decode( $action_data['meta']['OCCURRENCES'] );
			}

			$response = $this->helpers->add_to_meeting( $meeting_user, $meeting_key, $meeting_occurrences, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}
}
