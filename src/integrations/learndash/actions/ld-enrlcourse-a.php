<?php

namespace Uncanny_Automator;

/**
 * Class LD_ENRLCOURSE_A
 *
 * @package Uncanny_Automator
 */
class LD_ENRLCOURSE_A {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LD';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'ENRLCOURSE-A';
		$this->action_meta = 'LDCOURSE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/learndash/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnDash */
			'sentence'           => sprintf( esc_attr__( 'Enroll the user in {{a course:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnDash */
			'select_option_name' => esc_attr__( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'enroll_in_course' ),
			'options'            => array(
				Automator()->helpers->recipe->learndash->options->all_ld_courses( null, 'LDCOURSE', false ),
			),
		);

		Automator()->register->action( $action );
	}


	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function enroll_in_course( $user_id, $action_data, $recipe_id, $args ) {

		if ( ! function_exists( 'ld_update_course_access' ) ) {
			$error_message = 'The function ld_update_course_access does not exist';
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$course_id = $action_data['meta'][ $this->action_meta ];

		//Enroll to New Course
		ld_update_course_access( $user_id, $course_id );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
