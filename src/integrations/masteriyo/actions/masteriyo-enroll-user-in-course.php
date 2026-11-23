<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

use Uncanny_Automator\Recipe\Action;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

/**
 * Class Masteriyo_Enroll_User_In_Course
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 *
 * @property Masteriyo_Helpers $item_helpers
 */
class Masteriyo_Enroll_User_In_Course extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'MASTERIYO' );
		$this->set_action_code( 'MASTERIYO_ENROLL_USER_IN_COURSE' );
		$this->set_action_meta( 'MASTERIYO_COURSE' );
		$this->set_is_pro( false );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Course selector */
				esc_html_x( 'Enroll the user in {{a course:%1$s}}', 'Masteriyo LMS', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Enroll the user in {{a course}}', 'Masteriyo LMS', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array( $this->item_helpers->get_course_field_strict( $this->get_action_meta() ) );
	}

	/**
	 * Define the action's output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {

		return array(
			'ENROLLMENT_ID'   => array(
				'name' => esc_html_x( 'Enrollment ID', 'Masteriyo LMS', 'uncanny-automator' ),
				'type' => 'int',
			),
			'COURSE_TITLE'    => array(
				'name' => esc_html_x( 'Course title', 'Masteriyo LMS', 'uncanny-automator' ),
				'type' => 'text',
			),
			'ENROLLMENT_DATE' => array(
				'name' => esc_html_x( 'Enrollment date', 'Masteriyo LMS', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * `UserCourseRepository::create()` returns without writing whenever the user
	 * has any row for the course, so an inactive row (unpaid, expired or revoked)
	 * is re-activated instead, as Masteriyo's Enrollments screen re-grants.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 * @throws Action_Error_Exception When the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! function_exists( 'masteriyo' ) || ! function_exists( 'masteriyo_get_user_course_by_user_and_course' ) ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'Masteriyo is not available.', 'Masteriyo LMS', 'uncanny-automator' ) );
		}

		$course_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $course_id || 'mto-course' !== get_post_type( $course_id ) ) {
			throw Action_Error_Exception::not_found(
				sprintf(
					/* translators: %d: Course ID */
					esc_html_x( 'Course [%d] does not exist.', 'Masteriyo LMS', 'uncanny-automator' ),
					absint( $course_id )
				)
			);
		}

		if ( $this->item_helpers->is_user_enrolled( $user_id, $course_id ) ) {
			throw Action_Error_Exception::validation_failed(
				sprintf(
					/* translators: %d: Course ID */
					esc_html_x( 'The user is already enrolled in course [%d].', 'Masteriyo LMS', 'uncanny-automator' ),
					absint( $course_id )
				)
			);
		}

		$enrollment_date = current_time( 'mysql' );

		// Any row left after the active check is an inactive one.
		$user_course = masteriyo_get_user_course_by_user_and_course( $user_id, $course_id );

		if ( ! is_object( $user_course ) ) {
			$user_course = masteriyo( 'user-course' );
			$user_course->set_user_id( $user_id );
			$user_course->set_course_id( $course_id );
		}

		$user_course->set_status( 'active' );
		$user_course->set_date_start( $enrollment_date );
		$user_course->save();

		if ( ! $this->item_helpers->is_user_enrolled( $user_id, $course_id ) ) {
			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: %d: Course ID */
					esc_html_x( 'The user could not be enrolled in course [%d].', 'Masteriyo LMS', 'uncanny-automator' ),
					absint( $course_id )
				)
			);
		}

		$this->hydrate_tokens(
			array(
				'ENROLLMENT_ID'   => $user_course->get_id(),
				'COURSE_TITLE'    => get_the_title( $course_id ),
				'ENROLLMENT_DATE' => $enrollment_date,
			)
		);

		return true;
	}
}
