<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Sensei_Course;
use Sensei_Utils;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Sensei_Mark_Lesson_Complete
 *
 * Completes a lesson with `Sensei_Utils::sensei_start_lesson()`, for a learner
 * enrolled in the lesson's course only. Sensei starts the course if needed, fires
 * `sensei_user_lesson_end` and may complete the course; Sensei's course-completed
 * redirect is detached for the call so it can't `exit` mid-recipe.
 *
 * @property Sensei_Lms_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 * @since 8.1.0
 */
class Sensei_Mark_Lesson_Complete extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SENSEI_LMS' );
		$this->set_action_code( 'SENSEI_MARK_LESSON_COMPLETE' );
		$this->set_action_meta( 'SENSEI_LESSON' );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Lesson.
				esc_html_x( 'Mark {{a lesson:%1$s}} complete for the user', 'Sensei LMS', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Mark {{a lesson}} complete for the user', 'Sensei LMS', 'uncanny-automator' )
		);
	}

	/**
	 * Course is the drill-down parent for the lesson list.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'              => 'SENSEI_COURSE',
				'label'                    => esc_html_x( 'Course', 'Sensei LMS', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'options'                  => array(),
				'supports_custom_value'    => true,
				'custom_value_description' => esc_html_x( 'Course ID', 'Sensei LMS', 'uncanny-automator' ),
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'courses_strict' ),
			),
			array(
				'option_code'              => $this->get_action_meta(),
				'label'                    => esc_html_x( 'Lesson', 'Sensei LMS', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'options'                  => array(),
				'supports_custom_value'    => true,
				'custom_value_description' => esc_html_x( 'Lesson ID', 'Sensei LMS', 'uncanny-automator' ),
				'remote_data'              => $this->item_helpers->remote_data_parent_config( 'lessons_from_course_strict', array( 'SENSEI_COURSE' ) ),
			),
		);
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {

		$tokens_class = new Sensei_Lms_Tokens();

		return Sensei_Lms_Tokens::to_action_tokens(
			array_merge( $tokens_class->course_tokens(), $tokens_class->lesson_tokens() )
		);
	}

	/**
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The runtime args.
	 * @param array $parsed      The parsed field values.
	 *
	 * @return bool
	 * @throws Action_Error_Exception When the action cannot complete.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\Sensei_Utils' ) || ! class_exists( '\Sensei_Course' ) ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'Sensei LMS is not active.', 'Sensei LMS', 'uncanny-automator' ) );
		}

		$user_id   = absint( $user_id );
		$lesson_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $user_id ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'The action needs a user.', 'Sensei LMS', 'uncanny-automator' ) );
		}

		if ( 0 === $lesson_id || 'lesson' !== get_post_type( $lesson_id ) ) {
			throw Action_Error_Exception::not_found(
				sprintf(
					/* translators: %d: Lesson ID */
					esc_html_x( 'Lesson %d does not exist.', 'Sensei LMS', 'uncanny-automator' ),
					esc_html( $lesson_id )
				)
			);
		}

		$course_id = $this->item_helpers->get_course_id_from_lesson( $lesson_id );

		if ( 0 === $course_id || ! Sensei_Course::is_user_enrolled( $course_id, $user_id ) ) {
			throw Action_Error_Exception::validation_failed(
				sprintf(
					/* translators: 1: User ID, 2: Lesson ID */
					esc_html_x( "User %1\$d is not enrolled in the course of lesson %2\$d, so Sensei LMS can't complete it.", 'Sensei LMS', 'uncanny-automator' ),
					esc_html( $user_id ),
					esc_html( $lesson_id )
				)
			);
		}

		$redirect_was_hooked = $this->suppress_course_completed_redirect();

		try {
			Sensei_Utils::sensei_start_lesson( $lesson_id, $user_id, true );
		} finally {
			$this->restore_course_completed_redirect( $redirect_was_hooked );
		}

		// The end state, not the return value: it is false only for a non-positive ID.
		if ( ! Sensei_Utils::user_completed_lesson( $lesson_id, $user_id ) ) {
			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: 1: Lesson ID, 2: User ID */
					esc_html_x( 'Sensei LMS did not complete lesson %1$d for user %2$d.', 'Sensei LMS', 'uncanny-automator' ),
					esc_html( $lesson_id ),
					esc_html( $user_id )
				)
			);
		}

		$tokens_class = new Sensei_Lms_Tokens();

		$this->hydrate_tokens(
			array_merge(
				$tokens_class->hydrate_course_tokens( $course_id ),
				$tokens_class->hydrate_lesson_tokens( $lesson_id )
			)
		);

		return true;
	}

	/**
	 * Detach Sensei's course-completed redirect for the write. When the lesson
	 * completes the course, that callback redirects and calls `exit`, which would
	 * end the recipe run. Sensei's own CLI seeder detaches it the same way.
	 *
	 * @return bool Whether the callback was attached and must be restored.
	 */
	private function suppress_course_completed_redirect() {

		if ( ! function_exists( 'Sensei' ) || ! isset( Sensei()->frontend ) || ! is_object( Sensei()->frontend ) ) {
			return false;
		}

		$callback = array( Sensei()->frontend, 'redirect_to_course_completed_page' );

		if ( false === has_action( 'sensei_course_status_updated', $callback ) ) {
			return false;
		}

		remove_action( 'sensei_course_status_updated', $callback, 1000 );

		return true;
	}

	/**
	 * Re-attach Sensei's course-completed redirect with its own priority and arguments.
	 *
	 * @param bool $was_hooked Return value of suppress_course_completed_redirect().
	 *
	 * @return void
	 */
	private function restore_course_completed_redirect( $was_hooked ) {

		if ( ! $was_hooked ) {
			return;
		}

		add_action( 'sensei_course_status_updated', array( Sensei()->frontend, 'redirect_to_course_completed_page' ), 1000, 3 );
	}
}
