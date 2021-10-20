<?php

namespace Uncanny_Automator;

/**
 * Class ZOOM_REGISTERUSER
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
			'options' => array(
				Automator()->helpers->recipe->zoom->get_meetings( null, $this->action_meta )
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
	public function zoom_register_user( $user_id, $action_data, $recipe_id, $args ) {

		$meeting_key = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );
		if ( empty( $user_id ) ) {
			$error_msg                           = __( 'User not found.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

		if ( empty( $meeting_key ) ) {
			$error_msg                           = __( 'Meeting not found.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

		if ( ! empty( $meeting_key ) ) {
			$meeting_key = str_replace( '-objectkey', '', $meeting_key );
		}

		$result = Automator()->helpers->recipe->zoom->register_user( $user_id, $meeting_key );

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
