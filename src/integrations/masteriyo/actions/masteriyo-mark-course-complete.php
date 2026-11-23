<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

use Uncanny_Automator\Recipe\Action;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

/**
 * Class Masteriyo_Mark_Course_Complete
 *
 * Completes the course through Masteriyo's own completion. A learner with
 * no progress row gets a `started` one first, as Masteriyo's webhook handler does:
 * a new row records no status transition, so without it the completion can fire
 * `masteriyo_course_progress_status_completed` zero times.
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 *
 * @property Masteriyo_Helpers $item_helpers
 */
class Masteriyo_Mark_Course_Complete extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'MASTERIYO' );
		$this->set_action_code( 'MASTERIYO_MARK_COURSE_COMPLETE' );
		$this->set_action_meta( 'MASTERIYO_COURSE' );
		$this->set_is_pro( false );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Course selector */
				esc_html_x( 'Mark {{a course:%1$s}} complete for the user', 'Masteriyo LMS', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Mark {{a course}} complete for the user', 'Masteriyo LMS', 'uncanny-automator' ) );
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
			'COURSE_TITLE' => array(
				'name' => esc_html_x( 'Course title', 'Masteriyo LMS', 'uncanny-automator' ),
				'type' => 'text',
			),
			'COMPLETED_AT' => array(
				'name' => esc_html_x( 'Completed at', 'Masteriyo LMS', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action.
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

		if ( ! function_exists( 'masteriyo' ) || ! function_exists( 'masteriyo_complete_specific_course_for_user' ) ) {
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

		$started = $this->maybe_start_progress( $user_id, $course_id );

		$result = masteriyo_complete_specific_course_for_user( $course_id, $user_id );

		if ( is_wp_error( $result ) ) {
			// A refused call leaves nothing behind: Masteriyo can still refuse
			// after the enrollment check, e.g. a course with no lessons or quizzes.
			if ( null !== $started ) {
				$started->delete();
			}

			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: %s: Masteriyo's error message */
					esc_html_x( 'Masteriyo could not complete the course: %s', 'Masteriyo LMS', 'uncanny-automator' ),
					esc_html( $result->get_error_message() )
				)
			);
		}

		if ( ! $this->item_helpers->has_user_completed_course( $user_id, $course_id ) ) {
			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: %d: Course ID */
					esc_html_x( 'Course [%d] is not complete for the user.', 'Masteriyo LMS', 'uncanny-automator' ),
					absint( $course_id )
				)
			);
		}

		$progress     = $this->item_helpers->get_course_progress( $user_id, $course_id );
		$completed_at = null !== $progress ? $progress->get_completed_at() : null;

		$this->hydrate_tokens(
			array(
				'COURSE_TITLE' => get_the_title( $course_id ),
				'COMPLETED_AT' => $completed_at instanceof \DateTimeInterface ? $completed_at->format( 'Y-m-d H:i:s' ) : current_time( 'mysql' ),
			)
		);

		return true;
	}

	/**
	 * Create a `started` progress row for an actively enrolled learner who has none.
	 *
	 * @param int $user_id   The user ID.
	 * @param int $course_id The course ID.
	 *
	 * @return \Masteriyo\Models\CourseProgress|null The row created, or null.
	 */
	private function maybe_start_progress( $user_id, $course_id ) {

		if ( null !== $this->item_helpers->get_course_progress( $user_id, $course_id ) ) {
			return null;
		}

		if ( ! $this->item_helpers->is_user_enrolled( $user_id, $course_id ) ) {
			return null;
		}

		$progress = masteriyo( 'course-progress' );
		$progress->set_user_id( $user_id );
		$progress->set_course_id( $course_id );
		$progress->set_status( 'started' );
		$progress->save();

		return $progress->get_id() ? $progress : null;
	}
}
