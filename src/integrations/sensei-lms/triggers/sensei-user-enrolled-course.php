<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Sensei_User_Enrolled_Course
 *
 * Sensei's broadest enrolment hook: every provider (manual, WooCommerce, groups).
 * It reports withdrawals too, so only `$is_enrolled === true` fires. The request
 * can be an admin's "Add to course" or Sensei's end-of-request or cron
 * recalculation, so the run binds the learner from the hook.
 *
 * @property Sensei_Lms_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 * @since 8.1.0
 */
class Sensei_User_Enrolled_Course extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'SENSEI_USER_ENROLLED_COURSE', 'SENSEI_LMS' )
			->trigger_meta( 'SENSEI_COURSE' )
			->hook( 'sensei_course_enrolment_status_changed', 10, 3 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Course.
				esc_html_x( 'A user is enrolled in {{a course:%1$s}}', 'Sensei LMS', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user is enrolled in {{a course}}', 'Sensei LMS', 'uncanny-automator' )
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
			$tokens_class->enrolment_tokens()
		);
	}

	/**
	 * @param array $trigger   The trigger row.
	 * @param array $hook_args $user_id, $course_id, $is_enrolled.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $course_id, $is_enrolled ) = array_pad( (array) $hook_args, 3, null );

		if ( true !== $is_enrolled ) {
			return false;
		}

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
	 * @param array $hook_args $user_id, $course_id, $is_enrolled.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( , $course_id, $is_enrolled ) = array_pad( (array) $hook_args, 3, null );

		$tokens_class = new Sensei_Lms_Tokens();

		return array_merge(
			$tokens_class->hydrate_course_tokens( $this->item_helpers->positive_id( $course_id ) ),
			$tokens_class->hydrate_enrolment_tokens( $is_enrolled )
		);
	}
}
