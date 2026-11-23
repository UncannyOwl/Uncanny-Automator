<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Sensei_User_Completes_Course
 *
 * Listens to Course_Completed_Dispatcher, which fires once per real completion,
 * so there is no de-dupe here. A teacher's grade or an admin's "complete course"
 * can finish the course, so the run binds the learner from the hook.
 *
 * @property Sensei_Lms_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 * @since 8.1.0
 */
class Sensei_User_Completes_Course extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'SENSEI_USER_COMPLETES_COURSE', 'SENSEI_LMS' )
			->trigger_meta( 'SENSEI_COURSE' )
			->hook( 'automator_sensei_lms_course_completed', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Course.
				esc_html_x( 'A user completes {{a course:%1$s}}', 'Sensei LMS', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user completes {{a course}}', 'Sensei LMS', 'uncanny-automator' )
		);
	}

	/**
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Course', 'Sensei LMS', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'courses' ),
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
			$tokens_class->course_category_tokens(),
			$tokens_class->course_progress_tokens()
		);
	}

	/**
	 * @param array $trigger   The trigger row.
	 * @param array $hook_args $user_id, $course_id.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $course_id ) = array_pad( (array) $hook_args, 2, null );

		$user_id   = $this->item_helpers->positive_id( $user_id );
		$course_id = $this->item_helpers->positive_id( $course_id );

		if ( 0 === $user_id || 0 === $course_id ) {
			return false;
		}

		$selected = $this->item_helpers->get_selected_value( $trigger, $this->get_trigger_meta() );

		if ( ! $this->item_helpers->matches_selection( $selected, $course_id ) ) {
			return false;
		}

		if ( $this->item_helpers->is_temporary_user( $user_id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * @param array $trigger   The trigger row.
	 * @param array $hook_args $user_id, $course_id.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $user_id, $course_id ) = array_pad( (array) $hook_args, 2, null );

		$user_id   = $this->item_helpers->positive_id( $user_id );
		$course_id = $this->item_helpers->positive_id( $course_id );

		$tokens_class = new Sensei_Lms_Tokens();

		return array_merge(
			$tokens_class->hydrate_course_tokens( $course_id ),
			$tokens_class->hydrate_course_category_tokens( $course_id ),
			$tokens_class->hydrate_course_progress_tokens( $course_id, $user_id )
		);
	}
}
