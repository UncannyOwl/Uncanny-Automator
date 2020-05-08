<?php

namespace Uncanny_Automator;

/**
 * Class LP_MARKLESSONDONE
 * @package uncanny_automator
 */
class LP_MARKLESSONDONE {

	/**
	 * Integration code
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

		global $uncanny_automator;
		$args = [
			'post_type'      => 'lp_course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args, false, __( 'Any course', 'uncanny-automator' ) );

		$action = array(
			'author'             => $uncanny_automator->get_author_name( $this->action_code ),
			'support_link'       => $uncanny_automator->get_author_support_link( $this->action_code ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnPress */
			'sentence'           => sprintf( __( 'Mark {{a lesson:%1$s}} complete for the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnPress */
			'select_option_name' => __( 'Mark {{a lesson}} complete for the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'lp_mark_lesson_done' ),
			'options_group'      => [
				$this->action_meta => [
					$uncanny_automator->helpers->recipe->field->select_field_ajax(
						'LPCOURSE',
						__( 'Course', 'uncanny-automator' ),
						$options,
						'',
						'',
						false,
						true,
						[
							'target_field' => 'LPSECTION',
							'endpoint'     => 'select_section_from_course_LPMARKLESSONDONE',
						]
					),
					$uncanny_automator->helpers->recipe->field->select_field_ajax(
						'LPSECTION',
						__( 'Section', 'uncanny-automator' ),
						[],
						'',
						'',
						false,
						true,
						[
							'target_field' => $this->action_meta,
							'endpoint'     => 'select_lesson_from_section_LPMARKLESSONDONE',
						]),
					$uncanny_automator->helpers->recipe->field->select_field( $this->action_meta, __( 'Lesson', 'uncanny-automator' ) ),
				],
			],
		);

		$uncanny_automator->register->action( $action );
	}


	/**
	 * Validation function when the action is hit.
	 *
	 * @param string $user_id user id.
	 * @param array  $action_data action data.
	 * @param string $recipe_id recipe id.
	 */
	public function lp_mark_lesson_done( $user_id, $action_data, $recipe_id ) {

		global $uncanny_automator;

		if ( ! function_exists( 'learn_press_get_current_user' ) ) {
			$error_message = 'The function learn_press_get_current_user does not exist';
			$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id, $error_message );
			return;
		}
		$user      = learn_press_get_user( $user_id );
		$lesson_id = $action_data['meta'][ $this->action_meta ];
		$course_id = $action_data['meta']['LPCOURSE'];

		// Mark lesson completed.
		$result = $user->complete_lesson( $lesson_id, $course_id );

		if ( ! is_wp_error( $result ) ) {
			$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id );
		} else {
			$error_message = $result->get_error_message();
			$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id, $error_message );
			return;
		}

	}
}
