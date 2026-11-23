<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Sensei_User_Completes_Lesson
 *
 * Listens to Lesson_Completed_Dispatcher, which relays `sensei_user_lesson_end`
 * before Sensei's course-completed redirect can end the request. A teacher's grade
 * or an admin's "complete course" can complete the lesson, so the run binds the
 * learner from the hook.
 *
 * @property Sensei_Lms_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 * @since 8.1.0
 */
class Sensei_User_Completes_Lesson extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'SENSEI_USER_COMPLETES_LESSON', 'SENSEI_LMS' )
			->trigger_meta( 'SENSEI_LESSON' )
			->hook( 'automator_sensei_lms_lesson_completed', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Lesson. %2$s: Course.
				esc_html_x( 'A user completes {{a lesson:%1$s}} in {{a course:%2$s}}', 'Sensei LMS', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'SENSEI_COURSE:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user completes {{a lesson}} in {{a course}}', 'Sensei LMS', 'uncanny-automator' )
		);
	}

	/**
	 * Course is the drill-down parent: "Any course" offers only "Any lesson".
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'     => 'SENSEI_COURSE',
				'label'           => esc_html_x( 'Course', 'Sensei LMS', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'courses' ),
			),
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Lesson', 'Sensei LMS', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_parent_config( 'lessons_from_course', array( 'SENSEI_COURSE' ) ),
			),
		);
	}

	/**
	 * @param array $trigger The trigger definition.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens_class = new Sensei_Lms_Tokens();

		return array_merge(
			$tokens,
			$tokens_class->course_tokens(),
			$tokens_class->lesson_tokens(),
			$tokens_class->module_tokens()
		);
	}

	/**
	 * @param array $trigger   The trigger row.
	 * @param array $hook_args $user_id, $lesson_id.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $lesson_id ) = array_pad( (array) $hook_args, 2, null );

		$user_id   = $this->item_helpers->positive_id( $user_id );
		$lesson_id = $this->item_helpers->positive_id( $lesson_id );

		if ( 0 === $user_id || 0 === $lesson_id ) {
			return false;
		}

		$selected_lesson = $this->item_helpers->get_selected_value( $trigger, $this->get_trigger_meta() );
		$selected_course = $this->item_helpers->get_selected_value( $trigger, 'SENSEI_COURSE' );

		if ( ! $this->item_helpers->matches_selection( $selected_lesson, $lesson_id ) ) {
			return false;
		}

		if ( '-1' !== $selected_course ) {
			$course_id = $this->item_helpers->get_course_id_from_lesson( $lesson_id );

			if ( ! $this->item_helpers->matches_selection( $selected_course, $course_id ) ) {
				return false;
			}
		}

		if ( $this->item_helpers->is_temporary_user( $user_id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * @param array $trigger   The trigger row.
	 * @param array $hook_args $user_id, $lesson_id.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( , $lesson_id ) = array_pad( (array) $hook_args, 2, null );

		$lesson_id = $this->item_helpers->positive_id( $lesson_id );

		$tokens_class = new Sensei_Lms_Tokens();

		return array_merge(
			$tokens_class->hydrate_course_tokens( $this->item_helpers->get_course_id_from_lesson( $lesson_id ) ),
			$tokens_class->hydrate_lesson_tokens( $lesson_id ),
			$tokens_class->hydrate_module_tokens( $lesson_id )
		);
	}
}
