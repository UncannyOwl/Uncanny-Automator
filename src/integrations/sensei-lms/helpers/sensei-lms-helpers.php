<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Sensei_Course_Enrolment;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Sensei_Lms_Helpers
 *
 * Remote-data segments, selection matching and entity resolvers for the Sensei
 * LMS integration. Pro composes this class through Sensei_Lms_Pro_Helpers, which
 * wraps every public method and segment here.
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 */
class Sensei_Lms_Helpers extends Abstract_Helpers {

	// =========================================================================
	// Remote-data handlers. The bare segments carry "Any" for triggers; the
	// `_strict` segments, for actions and conditions, do not.
	// =========================================================================

	/**
	 * Remote-data handler: all courses, with "Any course".
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_courses( $request ): array {
		return $this->remote_data_success( $this->build_course_options( true ) );
	}

	/**
	 * Remote-data handler: all courses, no "Any" option.
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_courses_strict( $request ): array {
		return $this->remote_data_success( $this->build_course_options( false ) );
	}

	/**
	 * Remote-data handler: all quizzes, with "Any quiz".
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_quizzes( $request ): array {
		return $this->remote_data_success( $this->build_quiz_options( true ) );
	}

	/**
	 * Remote-data handler: all quizzes, no "Any" option.
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_quizzes_strict( $request ): array {
		return $this->remote_data_success( $this->build_quiz_options( false ) );
	}

	/**
	 * Remote-data handler: lessons in the selected course, with "Any lesson".
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lessons_from_course( $request ): array {
		return $this->remote_data_success( $this->build_lessons_from_course( $request, true ) );
	}

	/**
	 * Remote-data handler: lessons in the selected course, no "Any" option.
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lessons_from_course_strict( $request ): array {
		return $this->remote_data_success( $this->build_lessons_from_course( $request, false ) );
	}

	// =========================================================================
	// Option builders.
	// =========================================================================

	/**
	 * Build the course option list.
	 *
	 * @param bool $include_any Whether to prepend "Any course".
	 *
	 * @return array
	 */
	private function build_course_options( $include_any ) {

		return automator_wp_query(
			array(
				'post_type'   => 'course',
				'include_any' => $include_any,
				'any_label'   => esc_html_x( 'Any course', 'Sensei LMS', 'uncanny-automator' ),
			)
		);
	}

	/**
	 * Build the quiz option list.
	 *
	 * Sensei titles each quiz after its lesson, so a flat quiz list reads as the
	 * lesson names a site owner already knows.
	 *
	 * @param bool $include_any Whether to prepend "Any quiz".
	 *
	 * @return array
	 */
	private function build_quiz_options( $include_any ) {

		return automator_wp_query(
			array(
				'post_type'   => 'quiz',
				'include_any' => $include_any,
				'any_label'   => esc_html_x( 'Any quiz', 'Sensei LMS', 'uncanny-automator' ),
			)
		);
	}

	/**
	 * Build the "lessons in a course" option list.
	 *
	 * "Any course" offers only "Any lesson"; a specific course offers "Any
	 * lesson" (triggers only) plus that course's published lessons.
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request     The remote-data request.
	 * @param bool                                                                     $include_any Whether to prepend "Any lesson".
	 *
	 * @return array
	 */
	private function build_lessons_from_course( $request, $include_any ) {

		$course_id = $this->select_field_int( $request, 'SENSEI_COURSE' );

		$options = array();

		if ( $include_any || -1 === $course_id ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any lesson', 'Sensei LMS', 'uncanny-automator' ),
			);
		}

		if ( 0 >= $course_id || ! function_exists( 'Sensei' ) ) {
			return $options;
		}

		foreach ( (array) Sensei()->course->course_lessons( $course_id, 'publish', 'ids' ) as $lesson_id ) {
			$title = get_the_title( $lesson_id );

			$options[] = array(
				'value' => (string) $lesson_id,
				'text'  => '' !== $title
					? $title
					/* translators: %1$d: Lesson ID */
					: sprintf( esc_html_x( 'ID: %1$d (no title)', 'Sensei LMS', 'uncanny-automator' ), $lesson_id ),
			);
		}

		return $options;
	}

	/**
	 * Resolve a parent select field to an int, keeping the "Any" sentinel as -1.
	 *
	 * @param \Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Request $request The remote-data request.
	 * @param string                                                                   $key     Option code to read.
	 *
	 * @return int Post ID, -1 for "Any", or 0 when unset.
	 */
	private function select_field_int( $request, $key ) {

		$raw = (string) $request->get_field_value( $key );

		if ( 'automator_custom_value' === $raw ) {
			$raw = (string) $request->get_field_value( $key . '_custom' );
		}

		if ( '-1' === $raw ) {
			return -1;
		}

		return absint( $raw );
	}

	// =========================================================================
	// Selections and hook arguments.
	// =========================================================================

	/**
	 * Read a trigger's saved selection for one option code.
	 *
	 * A select set to "Use a custom value" stores `automator_custom_value`; the
	 * real value is under `{option_code}_custom`.
	 *
	 * @param array  $trigger     The trigger row.
	 * @param string $option_code The option code to read.
	 *
	 * @return string The saved value, '' when nothing is saved.
	 */
	public function get_selected_value( $trigger, $option_code ) {

		$meta  = (array) ( $trigger['meta'] ?? array() );
		$value = $meta[ $option_code ] ?? '';

		if ( 'automator_custom_value' === $value ) {
			$value = $meta[ $option_code . '_custom' ] ?? '';
		}

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Does a saved selection match an entity ID?
	 *
	 * "Any" is the string '-1' and is compared before any cast. A selection that
	 * was never saved fails closed.
	 *
	 * @param string $selected  The saved selection.
	 * @param int    $entity_id The entity the hook reported.
	 *
	 * @return bool
	 */
	public function matches_selection( $selected, $entity_id ) {

		$selected = (string) $selected;

		if ( '-1' === $selected ) {
			return true;
		}

		if ( '' === $selected || ! is_numeric( $selected ) ) {
			return false;
		}

		$entity_id = absint( $entity_id );

		return 0 !== $entity_id && absint( $selected ) === $entity_id;
	}

	/**
	 * A hook argument as a positive ID, or 0.
	 *
	 * Rejects `WP_Error`, `false`, arrays, objects and non-positive numbers
	 * before any cast, so a failure value never becomes an ID.
	 *
	 * @param mixed $value The hook argument.
	 *
	 * @return int
	 */
	public function positive_id( $value ) {

		if ( ! is_int( $value ) && ! ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return 0;
		}

		return 0 < (int) $value ? (int) $value : 0;
	}

	/**
	 * Is this one of Sensei's temporary accounts?
	 *
	 * Sensei creates `guest_student` users for open-access courses and
	 * `preview_student` users when a teacher previews a course, and deletes them
	 * later. The role strings are compared directly so the check doesn't depend
	 * on Sensei's own classes being loaded.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return bool
	 */
	public function is_temporary_user( $user_id ) {

		$user = get_userdata( absint( $user_id ) );

		if ( false === $user ) {
			return false;
		}

		return array() !== array_intersect( (array) $user->roles, array( 'guest_student', 'preview_student' ) );
	}

	// =========================================================================
	// Entity resolvers, through Sensei's own functions.
	// =========================================================================

	/**
	 * The course a lesson belongs to.
	 *
	 * @param int $lesson_id The lesson post ID.
	 *
	 * @return int Course ID, or 0 when the lesson has none.
	 */
	public function get_course_id_from_lesson( $lesson_id ) {

		$lesson_id = absint( $lesson_id );

		if ( 0 === $lesson_id || ! function_exists( 'Sensei' ) ) {
			return 0;
		}

		return absint( Sensei()->lesson->get_course_id( $lesson_id ) );
	}

	/**
	 * The lesson a quiz belongs to.
	 *
	 * The ID is checked first: Sensei reads the global post when it's given 0.
	 *
	 * @param int $quiz_id The quiz post ID.
	 *
	 * @return int Lesson ID, or 0 when the quiz has none.
	 */
	public function get_lesson_id_from_quiz( $quiz_id ) {

		$quiz_id = absint( $quiz_id );

		if ( 0 === $quiz_id || ! function_exists( 'Sensei' ) ) {
			return 0;
		}

		return absint( Sensei()->quiz->get_lesson_id( $quiz_id ) );
	}

	/**
	 * Is a user enrolled in a course?
	 *
	 * @param int $course_id The course post ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return bool
	 */
	public function is_user_enrolled( $course_id, $user_id ) {

		$course_id = absint( $course_id );
		$user_id   = absint( $user_id );

		if ( 0 === $course_id || 0 === $user_id || ! class_exists( '\Sensei_Course_Enrolment' ) ) {
			return false;
		}

		return (bool) Sensei_Course_Enrolment::get_course_instance( $course_id )->is_enrolled( $user_id );
	}

	/**
	 * Every user ID enrolled in a course, from Sensei's learner index.
	 *
	 * @param int $course_id The course post ID.
	 *
	 * @return int[]
	 */
	public function get_enrolled_user_ids( $course_id ) {

		$course_id = absint( $course_id );

		if ( 0 === $course_id || ! class_exists( '\Sensei_Course_Enrolment' ) ) {
			return array();
		}

		$user_ids = Sensei_Course_Enrolment::get_course_instance( $course_id )->get_enrolled_user_ids();

		return array_map( 'absint', (array) $user_ids );
	}
}
