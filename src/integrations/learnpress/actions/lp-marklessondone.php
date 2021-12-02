<?php

namespace Uncanny_Automator;

/**
 * Class LP_MARKLESSONDONE
 *
 * @package Uncanny_Automator
 */
class LP_MARKLESSONDONE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'LPMARKLESSONDONE-A';
		$this->action_meta = 'LPLESSON';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$args    = array(
			'post_type'      => 'lp_course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);
		$options = Automator()->helpers->recipe->options->wp_query( $args, false, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/learnpress/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnPress */
			'sentence'           => sprintf( esc_attr__( 'Mark {{a lesson:%1$s}} complete for the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnPress */
			'select_option_name' => esc_attr__( 'Mark {{a lesson}} complete for the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'lp_mark_lesson_done' ),
			'options_group'      => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->field->select_field_args(
						array(
							'option_code'              => 'LPCOURSE',
							'options'                  => $options,
							'label'                    => esc_attr__( 'Course', 'uncanny-automator' ),

							'required'                 => true,
							'custom_value_description' => esc_attr__( 'Course ID', 'uncanny-automator' ),

							'is_ajax'                  => true,
							'target_field'             => 'LPSECTION',
							'endpoint'                 => 'select_section_from_course_LPMARKLESSONDONE',
						)
					),

					Automator()->helpers->recipe->field->select_field_args(
						array(
							'option_code'              => 'LPSECTION',
							'options'                  => array(),
							'label'                    => esc_attr__( 'Section', 'uncanny-automator' ),

							'required'                 => true,
							'custom_value_description' => esc_attr__( 'Section ID', 'uncanny-automator' ),

							'is_ajax'                  => true,
							'target_field'             => $this->action_meta,
							'endpoint'                 => 'select_lesson_from_section_LPMARKLESSONDONE',
						)
					),

					Automator()->helpers->recipe->field->select_field_args(
						array(
							'option_code'              => $this->action_meta,
							'options'                  => array(),
							'label'                    => esc_attr__( 'Lesson', 'uncanny-automator' ),

							'required'                 => true,
							'custom_value_description' => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
						)
					),
				),
			),
		);

		Automator()->register->action( $action );
	}


	/**
	 * Validation function when the action is hit.
	 *
	 * @param string $user_id user id.
	 * @param array $action_data action data.
	 * @param string $recipe_id recipe id.
	 */
	public function lp_mark_lesson_done( $user_id, $action_data, $recipe_id, $args ) {

		if ( ! function_exists( 'learn_press_get_current_user' ) ) {
			$error_message = 'The function learn_press_get_current_user does not exist';
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}
		$user      = learn_press_get_user( $user_id );
		$lesson_id = $action_data['meta'][ $this->action_meta ];
		$course_id = $action_data['meta']['LPCOURSE'];

		// Mark lesson completed.
		$result = $user->complete_lesson( $lesson_id, $course_id );

		if ( ! is_wp_error( $result ) ) {
			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		} else {
			$error_message = $result->get_error_message();
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

	}
}
