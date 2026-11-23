<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Sensei_Course_Enrolment;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Sensei_Enroll_User_In_Course
 *
 * Enrols through `Sensei_Course_Enrolment::enrol()`, Sensei's own "Add to course":
 * it restores a learner Sensei removed, then enrols them manually unless another
 * provider already does. Sensei never enrols anyone in an unpublished course, so
 * that stops the action before any write.
 *
 * @property Sensei_Lms_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 * @since 8.1.0
 */
class Sensei_Enroll_User_In_Course extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SENSEI_LMS' );
		$this->set_action_code( 'SENSEI_ENROLL_USER_IN_COURSE' );
		$this->set_action_meta( 'SENSEI_COURSE' );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Course.
				esc_html_x( 'Enroll the user in {{a course:%1$s}}', 'Sensei LMS', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Enroll the user in {{a course}}', 'Sensei LMS', 'uncanny-automator' )
		);
	}

	/**
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'              => $this->get_action_meta(),
				'label'                    => esc_html_x( 'Course', 'Sensei LMS', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'options'                  => array(),
				'supports_custom_value'    => true,
				'custom_value_description' => esc_html_x( 'Course ID', 'Sensei LMS', 'uncanny-automator' ),
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'courses_strict' ),
			),
		);
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {

		$tokens_class = new Sensei_Lms_Tokens();

		return Sensei_Lms_Tokens::to_action_tokens( $tokens_class->course_tokens() );
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

		if ( ! class_exists( '\Sensei_Course_Enrolment' ) ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'Sensei LMS is not active.', 'Sensei LMS', 'uncanny-automator' ) );
		}

		$user_id   = absint( $user_id );
		$course_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $user_id ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'The action needs a user.', 'Sensei LMS', 'uncanny-automator' ) );
		}

		if ( 0 === $course_id || 'course' !== get_post_type( $course_id ) ) {
			throw Action_Error_Exception::not_found(
				sprintf(
					/* translators: %d: Course ID */
					esc_html_x( 'Course %d does not exist.', 'Sensei LMS', 'uncanny-automator' ),
					esc_html( $course_id )
				)
			);
		}

		if ( 'publish' !== get_post_status( $course_id ) ) {
			throw Action_Error_Exception::validation_failed(
				sprintf(
					/* translators: %d: Course ID */
					esc_html_x( 'Course %d is not published. Sensei LMS only enrolls learners in published courses.', 'Sensei LMS', 'uncanny-automator' ),
					esc_html( $course_id )
				)
			);
		}

		$enrolment = Sensei_Course_Enrolment::get_course_instance( $course_id );

		$enrolment->enrol( $user_id );

		// The end state, not the return value: recalculate now instead of at shutdown.
		if ( ! $enrolment->is_enrolled( $user_id, false ) ) {
			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: 1: User ID, 2: Course ID */
					esc_html_x( 'Sensei LMS did not enroll user %1$d in course %2$d.', 'Sensei LMS', 'uncanny-automator' ),
					esc_html( $user_id ),
					esc_html( $course_id )
				)
			);
		}

		$tokens_class = new Sensei_Lms_Tokens();

		$this->hydrate_tokens( $tokens_class->hydrate_course_tokens( $course_id ) );

		return true;
	}
}
