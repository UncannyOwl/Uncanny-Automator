<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

use Masteriyo\Enums\QuizPassMarkType;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Masteriyo_Helpers
 *
 * Shared option builders, remote-data handlers, selection matching and model
 * lookups for the Masteriyo LMS integration. Pro composes this class through
 * Masteriyo_Pro_Helpers, which wraps every public method and segment here.
 *
 * @package Uncanny_Automator
 */
class Masteriyo_Helpers extends Abstract_Helpers {

	/**
	 * Course post type.
	 *
	 * @var string
	 */
	const COURSE_POST_TYPE = 'mto-course';

	/**
	 * Lesson post type.
	 *
	 * @var string
	 */
	const LESSON_POST_TYPE = 'mto-lesson';

	/**
	 * Quiz post type.
	 *
	 * @var string
	 */
	const QUIZ_POST_TYPE = 'mto-quiz';

	////////////////////////////////////////////////////////////
	// Option builders
	////////////////////////////////////////////////////////////

	/**
	 * Get published courses as dropdown options.
	 *
	 * @param bool $include_any Whether to prepend the "Any course" sentinel.
	 *
	 * @return array
	 */
	public function get_course_options( $include_any = false ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any course', 'Masteriyo LMS', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		return array_merge( $options, $this->get_post_options( self::COURSE_POST_TYPE ) );
	}

	/**
	 * Get published quizzes as dropdown options.
	 *
	 * @param bool $include_any Whether to prepend the "Any quiz" sentinel.
	 *
	 * @return array
	 */
	public function get_quiz_options( $include_any = false ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any quiz', 'Masteriyo LMS', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		return array_merge( $options, $this->get_post_options( self::QUIZ_POST_TYPE ) );
	}

	/**
	 * Get the lessons belonging to a course as dropdown options.
	 *
	 * A lesson stores its owning course in the `_course_id` post meta;
	 * `post_parent` points at the section, not the course, so the meta is
	 * the reliable lookup.
	 *
	 * @param int  $course_id   The course ID.
	 * @param bool $include_any Whether to prepend the "Any lesson" sentinel.
	 *
	 * @return array
	 */
	public function get_lesson_options( $course_id, $include_any = false ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any lesson', 'Masteriyo LMS', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$course_id = absint( $course_id );

		if ( 0 === $course_id ) {
			return $options;
		}

		return array_merge( $options, $this->get_lessons_for_course( $course_id ) );
	}

	/**
	 * Get published posts of a type as dropdown options.
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return array
	 */
	private function get_post_options( $post_type ) {

		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'posts_per_page'   => 999,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'post_status'      => 'publish',
				'suppress_filters' => false,
			)
		);

		$options = array();

		foreach ( $posts as $post ) {
			$options[] = array(
				'text'  => $post->post_title,
				'value' => (string) $post->ID,
			);
		}

		return $options;
	}

	/**
	 * Get every lesson that belongs to a course, in curriculum order.
	 *
	 * @param int $course_id The course ID.
	 *
	 * @return array
	 */
	private function get_lessons_for_course( $course_id ) {

		$lessons = get_posts(
			array(
				'post_type'        => self::LESSON_POST_TYPE,
				'posts_per_page'   => 999,
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'post_status'      => 'publish',
				'suppress_filters' => false,
				'meta_key'         => '_course_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => (string) $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$options = array();

		foreach ( $lessons as $lesson ) {
			$options[] = array(
				'text'  => $lesson->post_title,
				'value' => (string) $lesson->ID,
			);
		}

		return $options;
	}

	////////////////////////////////////////////////////////////
	// Field builders
	////////////////////////////////////////////////////////////

	/**
	 * Build the course selector field for a trigger ("Any course" included).
	 *
	 * @param string $option_code The field's option code.
	 *
	 * @return array
	 */
	public function get_course_field( $option_code ) {

		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Course', 'Masteriyo LMS', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_load_config( 'courses' ),
		);
	}

	/**
	 * Build the course selector field for an action or condition (no "Any").
	 *
	 * @param string $option_code The field's option code.
	 *
	 * @return array
	 */
	public function get_course_field_strict( $option_code ) {

		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Course', 'Masteriyo LMS', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => true,
			'remote_data'           => $this->remote_data_load_config( 'courses_strict' ),
		);
	}

	/**
	 * Build the quiz selector field for a trigger ("Any quiz" included).
	 *
	 * @param string $option_code The field's option code.
	 *
	 * @return array
	 */
	public function get_quiz_field( $option_code ) {

		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Quiz', 'Masteriyo LMS', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_load_config( 'quizzes' ),
		);
	}

	/**
	 * Build the lesson selector field, cascading on a course field.
	 *
	 * @param string $option_code        The lesson field's option code.
	 * @param string $course_option_code The parent course field's option code.
	 * @param bool   $strict             True for actions (no "Any lesson").
	 *
	 * @return array
	 */
	public function get_lesson_field( $option_code, $course_option_code, $strict = false ) {

		$segment = true === $strict ? 'lessons_strict' : 'lessons';

		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Lesson', 'Masteriyo LMS', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => $strict,
			'remote_data'           => $this->remote_data_parent_config( $segment, array( $course_option_code ) ),
		);
	}

	////////////////////////////////////////////////////////////
	// Remote data handlers
	////////////////////////////////////////////////////////////

	/**
	 * Read a parent field's course ID, unwrapping the custom-value form.
	 *
	 * The course selectors accept a token, and a field in that mode stores the
	 * literal 'automator_custom_value' with the real value under
	 * `{option_code}_custom`. Reading the bare value would resolve to 0 and the
	 * dependent lesson dropdown would come back empty.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return string The raw course value ('-1' sentinel preserved).
	 */
	private function get_requested_course_value( $request ) {

		$value = $request->get_field_value( 'MASTERIYO_COURSE' );

		if ( 'automator_custom_value' !== $value ) {
			return $value;
		}

		return $request->get_field_value( 'MASTERIYO_COURSE_custom' );
	}

	/**
	 * Courses for triggers — includes the "Any course" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_courses( $request ): array {
		return $this->remote_data_success( $this->get_course_options( true ) );
	}

	/**
	 * Courses for actions and conditions — no "Any course" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_courses_strict( $request ): array {
		return $this->remote_data_success( $this->get_course_options( false ) );
	}

	/**
	 * Quizzes for triggers — includes the "Any quiz" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_quizzes( $request ): array {
		return $this->remote_data_success( $this->get_quiz_options( true ) );
	}

	/**
	 * Lessons for triggers, cascading on the selected course.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lessons( $request ): array {

		$course_id = $this->get_requested_course_value( $request );

		// "Any course" spans every course, so no single lesson list applies.
		if ( empty( $course_id ) || '-1' === (string) $course_id ) {
			return $this->remote_data_success(
				array(
					array(
						'text'  => esc_html_x( 'Any lesson', 'Masteriyo LMS', 'uncanny-automator' ),
						'value' => '-1',
					),
				)
			);
		}

		return $this->remote_data_success( $this->get_lesson_options( $course_id, true ) );
	}

	/**
	 * Lessons for actions, cascading on the selected course.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lessons_strict( $request ): array {

		$course_id = $this->get_requested_course_value( $request );

		if ( empty( $course_id ) ) {
			return $this->remote_data_success( array() );
		}

		return $this->remote_data_success( $this->get_lesson_options( $course_id, false ) );
	}

	////////////////////////////////////////////////////////////
	// Model lookups
	////////////////////////////////////////////////////////////

	/**
	 * Get a Masteriyo course model.
	 *
	 * @param int $course_id The course ID.
	 *
	 * @return \Masteriyo\Models\Course|null
	 */
	public function get_course( $course_id ) {

		if ( ! function_exists( 'masteriyo_get_course' ) ) {
			return null;
		}

		$course = masteriyo_get_course( absint( $course_id ) );

		return is_wp_error( $course ) ? null : $course;
	}

	/**
	 * Get a Masteriyo quiz model.
	 *
	 * @param int $quiz_id The quiz ID.
	 *
	 * @return \Masteriyo\Models\Quiz|null
	 */
	public function get_quiz( $quiz_id ) {

		if ( ! function_exists( 'masteriyo_get_quiz' ) ) {
			return null;
		}

		$quiz = masteriyo_get_quiz( absint( $quiz_id ) );

		return is_wp_error( $quiz ) ? null : $quiz;
	}

	/**
	 * Whether a user holds an active enrollment in a course.
	 *
	 * Only `active` counts: an unpaid, expired or revoked enrollment keeps an
	 * `inactive` row, which Masteriyo does not treat as enrolled.
	 *
	 * @param int $user_id   The user ID.
	 * @param int $course_id The course ID.
	 *
	 * @return bool
	 */
	public function is_user_enrolled( $user_id, $course_id ) {

		if ( ! function_exists( 'masteriyo_is_user_already_enrolled' ) ) {
			return false;
		}

		return (bool) masteriyo_is_user_already_enrolled( absint( $user_id ), absint( $course_id ), 'active' );
	}

	/**
	 * Get a user's course progress record for a course.
	 *
	 * @param int $user_id   The user ID.
	 * @param int $course_id The course ID.
	 *
	 * @return \Masteriyo\Models\CourseProgress|null
	 */
	public function get_course_progress( $user_id, $course_id ) {

		if ( ! function_exists( 'masteriyo_get_course_progress_by_user_and_course' ) ) {
			return null;
		}

		$progress = masteriyo_get_course_progress_by_user_and_course( absint( $user_id ), absint( $course_id ) );

		return empty( $progress ) || is_wp_error( $progress ) ? null : $progress;
	}

	/**
	 * Whether a user has completed a course, as Masteriyo decides it.
	 *
	 * @param int $user_id   The user ID.
	 * @param int $course_id The course ID.
	 *
	 * @return bool
	 */
	public function has_user_completed_course( $user_id, $course_id ) {

		if ( ! function_exists( 'masteriyo_user_has_completed_course' ) ) {
			return false;
		}

		return (bool) masteriyo_user_has_completed_course( absint( $course_id ), absint( $user_id ) );
	}

	/**
	 * The course a lesson progress item belongs to.
	 *
	 * The item's own course id is 0 whenever its caller did not set it: the
	 * repository's read never sets it, and Masteriyo's course completion leaves
	 * it out. The lesson knows its course; the progress row is the fallback.
	 *
	 * @param \Masteriyo\Models\CourseProgressItem $item The progress item.
	 *
	 * @return int
	 */
	public function get_progress_item_course_id( $item ) {

		$course_id = absint( $item->get_course_id() );

		if ( 0 !== $course_id ) {
			return $course_id;
		}

		$lesson = function_exists( 'masteriyo_get_lesson' ) ? masteriyo_get_lesson( $item->get_item_id() ) : null;

		if ( is_object( $lesson ) && 0 !== absint( $lesson->get_course_id() ) ) {
			return absint( $lesson->get_course_id() );
		}

		$progress = function_exists( 'masteriyo_get_course_progress' ) ? masteriyo_get_course_progress( $item->get_progress_id() ) : null;

		return is_object( $progress ) ? absint( $progress->get_course_id() ) : 0;
	}

	////////////////////////////////////////////////////////////
	// Quiz decision marks (T4, T5, T10)
	////////////////////////////////////////////////////////////

	/**
	 * Whether an ended attempt passes on the decision marks.
	 *
	 * Masteriyo's pass rule (points against the effective pass mark, or the
	 * earned share against a percentage), applied to the decision marks, which
	 * count each unreviewed manually graded answer correct at its full points.
	 *
	 * @param \Masteriyo\Models\QuizAttempt $attempt The quiz attempt.
	 *
	 * @return bool|null Null when the attempt can't be decided: closed without
	 *                   grading, its quiz is gone, or Masteriyo is older than 3.4.1.
	 */
	public function is_attempt_passed( $attempt ) {

		$marks = $this->get_decision_marks( $attempt );

		if ( null === $marks || ! function_exists( 'masteriyo_get_quiz_effective_pass_mark' ) ) {
			return null;
		}

		$quiz = $this->get_quiz( $attempt->get_quiz_id() );

		if ( empty( $quiz ) ) {
			return null;
		}

		$pass_mark = (float) masteriyo_get_quiz_effective_pass_mark( $quiz, $marks['total'] );

		if ( QuizPassMarkType::POINT === $quiz->get_pass_mark_type() ) {
			return $marks['earned'] >= $pass_mark;
		}

		return ( $marks['earned'] / $marks['total'] * 100 ) >= $pass_mark;
	}

	/**
	 * The attempt's percentage on the decision marks.
	 *
	 * Needs no Masteriyo 3.4.1 function, so T10 keeps working on older builds.
	 *
	 * @param \Masteriyo\Models\QuizAttempt $attempt The quiz attempt.
	 *
	 * @return float|null Null for an attempt closed without grading.
	 */
	public function get_attempt_percentage( $attempt ) {

		$marks = $this->get_decision_marks( $attempt );

		return null === $marks ? null : $marks['earned'] / $marks['total'] * 100;
	}

	/**
	 * Earned and total marks as the triggers decide on them.
	 *
	 * An attempt Masteriyo closed without grading (an admin marking the quiz
	 * complete) has no total marks and can't be decided. Each answer still
	 * waiting for the instructor's review counts at its full points.
	 *
	 * @param \Masteriyo\Models\QuizAttempt $attempt The quiz attempt.
	 *
	 * @return array{earned:float,total:float}|null
	 */
	private function get_decision_marks( $attempt ) {

		$total = (float) $attempt->get_total_marks();

		if ( $total <= 0 ) {
			return null;
		}

		$answers = $attempt->get_answers();

		if ( ! is_array( $answers ) ) {
			$answers = automator_safe_unserialize( $answers );
		}

		$earned = (float) $attempt->get_earned_marks();

		foreach ( is_array( $answers ) ? $answers : array() as $answer ) {
			if ( ! is_array( $answer ) || ! isset( $answer['max_points'] ) || $this->is_answer_reviewed( $answer ) ) {
				continue;
			}

			$earned += (float) $answer['max_points'] - (float) ( $answer['points'] ?? 0 );
		}

		return array(
			'earned' => $earned,
			'total'  => $total,
		);
	}

	/**
	 * Whether an answer entry has been reviewed.
	 *
	 * @param array $answer An entry of the attempt's answers.
	 *
	 * @return bool
	 */
	private function is_answer_reviewed( $answer ) {

		$reviewed = $answer['is_reviewed'] ?? true;

		if ( function_exists( 'masteriyo_string_to_bool' ) && ! is_int( $reviewed ) ) {
			return masteriyo_string_to_bool( $reviewed );
		}

		return (bool) $reviewed;
	}

	////////////////////////////////////////////////////////////
	// Trigger selections
	////////////////////////////////////////////////////////////

	/**
	 * Read a trigger's saved selection for one option code.
	 *
	 * A select set to "Use a custom value" stores `automator_custom_value`; the
	 * real value is under `{option_code}_custom`.
	 *
	 * @param array  $trigger     The trigger settings.
	 * @param string $option_code The option code.
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
	 * Whether a saved selection matches an entity ID.
	 *
	 * "Any" is the string '-1', compared before any cast; a selection that was
	 * never saved fails closed.
	 *
	 * @param string $selected  The saved selection.
	 * @param int    $entity_id The entity the event is about.
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
}
