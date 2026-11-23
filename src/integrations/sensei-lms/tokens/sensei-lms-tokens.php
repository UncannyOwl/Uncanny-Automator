<?php

namespace Uncanny_Automator\Integrations\Sensei_Lms;

use Sensei_Utils;

/**
 * Class Sensei_Lms_Tokens
 *
 * Shared token groups as define/hydrate pairs. Each trigger and action merges the
 * groups its scope lists, so token IDs stay identical across items. Every
 * hydrate_* method returns its full keyset, with '' when the entity is missing.
 *
 * @package Uncanny_Automator\Integrations\Sensei_Lms
 */
class Sensei_Lms_Tokens {

	/**
	 * Course token definitions.
	 *
	 * @return array
	 */
	public function course_tokens() {
		return array(
			array(
				'tokenId'   => 'SENSEI_COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'SENSEI_COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SENSEI_COURSE_URL',
				'tokenName' => esc_html_x( 'Course URL', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Course category token definition.
	 *
	 * @return array
	 */
	public function course_category_tokens() {
		return array(
			array(
				'tokenId'   => 'SENSEI_COURSE_CATEGORY',
				'tokenName' => esc_html_x( 'Course category', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * The learner's progress in a course.
	 *
	 * @return array
	 */
	public function course_progress_tokens() {
		return array(
			array(
				'tokenId'   => 'SENSEI_COURSE_COMPLETION_PERCENTAGE',
				'tokenName' => esc_html_x( 'Completion percentage', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'SENSEI_COURSE_LESSONS_COMPLETED',
				'tokenName' => esc_html_x( 'Lessons completed count', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Enrolment state token definition.
	 *
	 * @return array
	 */
	public function enrolment_tokens() {
		return array(
			array(
				'tokenId'   => 'SENSEI_IS_ENROLLED',
				'tokenName' => esc_html_x( 'Is enrolled', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Lesson token definitions.
	 *
	 * @return array
	 */
	public function lesson_tokens() {
		return array(
			array(
				'tokenId'   => 'SENSEI_LESSON_ID',
				'tokenName' => esc_html_x( 'Lesson ID', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'SENSEI_LESSON_TITLE',
				'tokenName' => esc_html_x( 'Lesson title', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SENSEI_LESSON_URL',
				'tokenName' => esc_html_x( 'Lesson URL', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Module token definition.
	 *
	 * @return array
	 */
	public function module_tokens() {
		return array(
			array(
				'tokenId'   => 'SENSEI_MODULE_NAME',
				'tokenName' => esc_html_x( 'Module name', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Quiz token definitions.
	 *
	 * @return array
	 */
	public function quiz_tokens() {
		return array(
			array(
				'tokenId'   => 'SENSEI_QUIZ_ID',
				'tokenName' => esc_html_x( 'Quiz ID', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'SENSEI_QUIZ_TITLE',
				'tokenName' => esc_html_x( 'Quiz title', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SENSEI_QUIZ_GRADE',
				'tokenName' => esc_html_x( 'Quiz grade', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'SENSEI_QUIZ_PASSMARK',
				'tokenName' => esc_html_x( 'Quiz pass mark', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'SENSEI_QUIZ_GRADE_TYPE',
				'tokenName' => esc_html_x( 'Quiz grade type', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SENSEI_QUIZ_PASSED',
				'tokenName' => esc_html_x( 'Quiz passed', 'Sensei LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate course tokens.
	 *
	 * @param int $course_id The course post ID.
	 *
	 * @return array
	 */
	public function hydrate_course_tokens( $course_id ) {

		$course_id = absint( $course_id );

		if ( 0 === $course_id ) {
			return array(
				'SENSEI_COURSE_ID'    => '',
				'SENSEI_COURSE_TITLE' => '',
				'SENSEI_COURSE_URL'   => '',
			);
		}

		return array(
			'SENSEI_COURSE_ID'    => $course_id,
			'SENSEI_COURSE_TITLE' => get_the_title( $course_id ),
			'SENSEI_COURSE_URL'   => get_permalink( $course_id ),
		);
	}

	/**
	 * Hydrate the course category token: the course's `course-category` term
	 * names, comma separated.
	 *
	 * @param int $course_id The course post ID.
	 *
	 * @return array
	 */
	public function hydrate_course_category_tokens( $course_id ) {

		$course_id = absint( $course_id );
		$terms     = 0 === $course_id ? array() : get_the_terms( $course_id, 'course-category' );

		return array(
			'SENSEI_COURSE_CATEGORY' => is_array( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '',
		);
	}

	/**
	 * Hydrate the learner's course progress, read through Sensei so it follows
	 * the progress-storage setting. Never called with user 0: Sensei falls back
	 * to the logged-in user.
	 *
	 * @param int $course_id The course post ID.
	 * @param int $user_id   The learner.
	 *
	 * @return array
	 */
	public function hydrate_course_progress_tokens( $course_id, $user_id ) {

		$course_id = absint( $course_id );
		$user_id   = absint( $user_id );

		if ( 0 === $course_id || 0 === $user_id || ! function_exists( 'Sensei' ) ) {
			return array(
				'SENSEI_COURSE_COMPLETION_PERCENTAGE' => '',
				'SENSEI_COURSE_LESSONS_COMPLETED'     => '',
			);
		}

		return array(
			'SENSEI_COURSE_COMPLETION_PERCENTAGE' => (float) Sensei()->course->get_completion_percentage( $course_id, $user_id ),
			'SENSEI_COURSE_LESSONS_COMPLETED'     => count( (array) Sensei()->course->get_completed_lesson_ids( $course_id, $user_id ) ),
		);
	}

	/**
	 * Hydrate the enrolment state token.
	 *
	 * @param bool $is_enrolled The enrolment state the hook reported.
	 *
	 * @return array
	 */
	public function hydrate_enrolment_tokens( $is_enrolled ) {
		return array(
			'SENSEI_IS_ENROLLED' => $this->readable_bool( true === $is_enrolled ),
		);
	}

	/**
	 * Hydrate lesson tokens.
	 *
	 * @param int $lesson_id The lesson post ID.
	 *
	 * @return array
	 */
	public function hydrate_lesson_tokens( $lesson_id ) {

		$lesson_id = absint( $lesson_id );

		if ( 0 === $lesson_id ) {
			return array(
				'SENSEI_LESSON_ID'    => '',
				'SENSEI_LESSON_TITLE' => '',
				'SENSEI_LESSON_URL'   => '',
			);
		}

		return array(
			'SENSEI_LESSON_ID'    => $lesson_id,
			'SENSEI_LESSON_TITLE' => get_the_title( $lesson_id ),
			'SENSEI_LESSON_URL'   => get_permalink( $lesson_id ),
		);
	}

	/**
	 * Hydrate the module token: the lesson's module, as Sensei resolves it.
	 *
	 * @param int $lesson_id The lesson post ID.
	 *
	 * @return array
	 */
	public function hydrate_module_tokens( $lesson_id ) {

		$lesson_id = absint( $lesson_id );
		$module    = false;

		if ( 0 !== $lesson_id && function_exists( 'Sensei' ) ) {
			$module = Sensei()->modules->get_lesson_module( $lesson_id );
		}

		return array(
			'SENSEI_MODULE_NAME' => is_object( $module ) && isset( $module->name ) ? (string) $module->name : '',
		);
	}

	/**
	 * Hydrate quiz tokens.
	 *
	 * The grade is a number only when Sensei graded the submission. A manual
	 * quiz reports `false` and a quiz Sensei can't auto-grade reports a
	 * `WP_Error`; both leave the grade and "passed" tokens empty, so a recipe can
	 * tell "not passed" from "not graded yet".
	 *
	 * @param int                 $quiz_id   The quiz post ID.
	 * @param float|false|object  $grade     The grade the hook reported.
	 * @param float|null          $pass_mark The pass mark; read from the quiz when null.
	 * @param string              $type      The grade type; read from the quiz when ''.
	 *
	 * @return array
	 */
	public function hydrate_quiz_tokens( $quiz_id, $grade = false, $pass_mark = null, $type = '' ) {

		$quiz_id = absint( $quiz_id );

		if ( 0 === $quiz_id ) {
			return array(
				'SENSEI_QUIZ_ID'         => '',
				'SENSEI_QUIZ_TITLE'      => '',
				'SENSEI_QUIZ_GRADE'      => '',
				'SENSEI_QUIZ_PASSMARK'   => '',
				'SENSEI_QUIZ_GRADE_TYPE' => '',
				'SENSEI_QUIZ_PASSED'     => '',
			);
		}

		if ( ! is_numeric( $pass_mark ) ) {
			$pass_mark = $this->get_quiz_pass_mark( $quiz_id );
		}

		if ( '' === $type ) {
			$type = (string) get_post_meta( $quiz_id, '_quiz_grade_type', true );
		}

		$is_graded = is_numeric( $grade );

		return array(
			'SENSEI_QUIZ_ID'         => $quiz_id,
			'SENSEI_QUIZ_TITLE'      => get_the_title( $quiz_id ),
			'SENSEI_QUIZ_GRADE'      => $is_graded ? (float) $grade : '',
			'SENSEI_QUIZ_PASSMARK'   => (float) $pass_mark,
			'SENSEI_QUIZ_GRADE_TYPE' => $type,
			'SENSEI_QUIZ_PASSED'     => $is_graded ? $this->readable_bool( (float) $grade >= (float) $pass_mark ) : '',
		);
	}

	/**
	 * The quiz's pass mark as Sensei compares grades with it: the `_quiz_passmark`
	 * meta rounded to two decimals.
	 *
	 * @param int $quiz_id The quiz post ID.
	 *
	 * @return float
	 */
	private function get_quiz_pass_mark( $quiz_id ) {

		if ( ! class_exists( '\Sensei_Utils' ) ) {
			return 0.0;
		}

		return (float) Sensei_Utils::as_absolute_rounded_number( get_post_meta( $quiz_id, '_quiz_passmark', true ), 2 );
	}

	/**
	 * Convert trigger-shaped token definitions to the action token format.
	 *
	 * @param array $tokens Trigger-shaped token definitions.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function to_action_tokens( array $tokens ) {

		$action_tokens = array();

		foreach ( $tokens as $token ) {
			$action_tokens[ $token['tokenId'] ] = array(
				'name' => $token['tokenName'],
				'type' => $token['tokenType'],
			);
		}

		return $action_tokens;
	}

	/**
	 * Render a boolean as a translated Yes/No string.
	 *
	 * @param bool $value The value.
	 *
	 * @return string
	 */
	private function readable_bool( $value ) {
		return $value
			? esc_html_x( 'Yes', 'Sensei LMS', 'uncanny-automator' )
			: esc_html_x( 'No', 'Sensei LMS', 'uncanny-automator' );
	}
}
