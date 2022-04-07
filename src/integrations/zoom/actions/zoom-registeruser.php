<?php

namespace Uncanny_Automator;

/**
 * Class ZOOM_REGISTERUSER
 *
 * @package Uncanny_Automator
 */
class ZOOM_REGISTERUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'ZOOM';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ZOOMREGISTERUSER';
		$this->action_meta = 'ZOOMMEETING';
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
			//'is_deprecated'      => true,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf( __( 'Add the user to {{a meeting:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Add the user to {{a meeting}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'zoom_register_user' ),
			'options_callback'   => array( $this, 'load_options' ),
			'buttons'            => array(
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
		return array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->zoom->get_meetings( null, $this->action_meta ),
					Automator()->helpers->recipe->zoom->get_meeting_questions_repeater(),
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
	public function zoom_register_user( $user_id, $action_data, $recipe_id, $args ) {

		$helpers = Automator()->helpers->recipe->zoom;

		try {

			$meeting_key = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

			if ( empty( $user_id ) ) {
				throw new \Exception( __( 'User was not found.', 'uncanny-automator' ) );
			}

			if ( empty( $meeting_key ) ) {
				throw new \Exception( __( 'Meeting was not found.', 'uncanny-automator' ) );
			}

			$meeting_key = str_replace( '-objectkey', '', $meeting_key );
			$user = get_userdata( $user_id );

			if ( is_wp_error( $user ) ) {
				throw new \Exception( __( 'User was not found.', 'uncanny-automator' ) );
			}

			$meeting_user = array();
			$meeting_user['email'] = $user->user_email;

			$meeting_user['first_name'] = $user->first_name;
			$meeting_user['last_name'] = $user->last_name;

			$email_parts = explode( '@', $meeting_user['email'] );
			$meeting_user['first_name']  = empty( $meeting_user['first_name'] ) ? $email_parts[0] : $meeting_user['first_name'];

			if ( ! empty( $action_data['meta'][ 'MEETINGQUESTIONS' ] ) ) {
				$meeting_user = $helpers->add_custom_questions( $meeting_user, $action_data['meta'][ 'MEETINGQUESTIONS' ], $recipe_id, $user_id, $args );
			}

			$response = Automator()->helpers->recipe->zoom->add_to_meeting( $meeting_user, $meeting_key, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		
		} catch ( \Exception $e ) {
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}
}
