<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

/**
 * Class Masteriyo_Tokens
 *
 * Shared token definitions and hydration for the Masteriyo LMS integration.
 * Every group is a define/hydrate pair; each trigger composes the groups, and
 * the course tokens, its scope lists. Pro consumes this class directly.
 *
 * @package Uncanny_Automator
 */
class Masteriyo_Tokens {

	////////////////////////////////////////////////////////////
	// Course
	////////////////////////////////////////////////////////////

	/**
	 * Course token definitions.
	 *
	 * @param string[] $only Token IDs to keep; empty keeps every course token.
	 *
	 * @return array
	 */
	public function course_tokens( array $only = array() ) {

		$tokens = array(
			array(
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'COURSE_URL',
				'tokenName' => esc_html_x( 'Course URL', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'COURSE_PRICE',
				'tokenName' => esc_html_x( 'Course price', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'COURSE_AUTHOR',
				'tokenName' => esc_html_x( 'Course author', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'COURSE_CATEGORIES',
				'tokenName' => esc_html_x( 'Course categories', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'COURSE_DIFFICULTY',
				'tokenName' => esc_html_x( 'Course difficulty', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'COURSE_DURATION',
				'tokenName' => esc_html_x( 'Course duration (minutes)', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);

		if ( empty( $only ) ) {
			return $tokens;
		}

		return array_values(
			array_filter(
				$tokens,
				static function ( $token ) use ( $only ) {
					return in_array( $token['tokenId'], $only, true );
				}
			)
		);
	}

	/**
	 * Hydrate the course tokens.
	 *
	 * Always returns every key so a missing course yields empty strings
	 * rather than a partial map.
	 *
	 * @param int      $course_id The course ID.
	 * @param string[] $only      Token IDs to return; empty returns every course token.
	 *
	 * @return array
	 */
	public function hydrate_course_tokens( $course_id, array $only = array() ) {

		$values = $this->get_course_token_values( $course_id );

		return empty( $only ) ? $values : array_intersect_key( $values, array_flip( $only ) );
	}

	/**
	 * Every course token value, '' for a missing course.
	 *
	 * @param int $course_id The course ID.
	 *
	 * @return array
	 */
	private function get_course_token_values( $course_id ) {

		$course_id = absint( $course_id );

		$values = array(
			'COURSE_ID'         => $course_id,
			'COURSE_TITLE'      => '',
			'COURSE_URL'        => '',
			'COURSE_PRICE'      => '',
			'COURSE_AUTHOR'     => '',
			'COURSE_CATEGORIES' => '',
			'COURSE_DIFFICULTY' => '',
			'COURSE_DURATION'   => '',
		);

		if ( 0 === $course_id || ! function_exists( 'masteriyo_get_course' ) ) {
			return $values;
		}

		$course = masteriyo_get_course( $course_id );

		if ( empty( $course ) || is_wp_error( $course ) ) {
			return $values;
		}

		$author = get_userdata( absint( $course->get_author_id() ) );

		$values['COURSE_TITLE']      = $course->get_name();
		$values['COURSE_URL']        = get_permalink( $course_id );
		$values['COURSE_PRICE']      = $course->get_price();
		$values['COURSE_AUTHOR']     = false !== $author ? $author->display_name : '';
		$values['COURSE_CATEGORIES'] = $this->get_term_names( $course_id, 'course_cat' );
		$values['COURSE_DIFFICULTY'] = $this->get_term_names( $course_id, 'course_difficulty' );
		$values['COURSE_DURATION']   = $course->get_duration();

		return $values;
	}

	////////////////////////////////////////////////////////////
	// Lesson
	////////////////////////////////////////////////////////////

	/**
	 * Lesson token definitions.
	 *
	 * @return array
	 */
	public function lesson_tokens() {

		return array(
			array(
				'tokenId'   => 'LESSON_ID',
				'tokenName' => esc_html_x( 'Lesson ID', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'LESSON_TITLE',
				'tokenName' => esc_html_x( 'Lesson title', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LESSON_URL',
				'tokenName' => esc_html_x( 'Lesson URL', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'SECTION_NAME',
				'tokenName' => esc_html_x( 'Section name', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the lesson tokens.
	 *
	 * @param int $lesson_id The lesson ID.
	 *
	 * @return array
	 */
	public function hydrate_lesson_tokens( $lesson_id ) {

		$lesson_id = absint( $lesson_id );

		$values = array(
			'LESSON_ID'    => $lesson_id,
			'LESSON_TITLE' => '',
			'LESSON_URL'   => '',
			'SECTION_NAME' => '',
		);

		if ( 0 === $lesson_id ) {
			return $values;
		}

		$lesson = get_post( $lesson_id );

		if ( null === $lesson ) {
			return $values;
		}

		$values['LESSON_TITLE'] = $lesson->post_title;
		$values['LESSON_URL']   = get_permalink( $lesson_id );
		$values['SECTION_NAME'] = $this->get_section_name( $lesson_id );

		return $values;
	}

	////////////////////////////////////////////////////////////
	// Quiz
	////////////////////////////////////////////////////////////

	/**
	 * Quiz token definitions.
	 *
	 * @return array
	 */
	public function quiz_tokens() {

		return array(
			array(
				'tokenId'   => 'QUIZ_ID',
				'tokenName' => esc_html_x( 'Quiz ID', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'QUIZ_TITLE',
				'tokenName' => esc_html_x( 'Quiz title', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the quiz tokens.
	 *
	 * @param int $quiz_id The quiz ID.
	 *
	 * @return array
	 */
	public function hydrate_quiz_tokens( $quiz_id ) {

		$quiz_id = absint( $quiz_id );

		$values = array(
			'QUIZ_ID'    => $quiz_id,
			'QUIZ_TITLE' => '',
		);

		if ( 0 === $quiz_id ) {
			return $values;
		}

		$quiz = get_post( $quiz_id );

		if ( null === $quiz ) {
			return $values;
		}

		$values['QUIZ_TITLE'] = $quiz->post_title;

		return $values;
	}

	////////////////////////////////////////////////////////////
	// Quiz attempt
	////////////////////////////////////////////////////////////

	/**
	 * Quiz attempt token definitions.
	 *
	 * @return array
	 */
	public function attempt_tokens() {

		return array(
			array(
				'tokenId'   => 'TOTAL_MARKS',
				'tokenName' => esc_html_x( 'Total marks', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'EARNED_MARKS',
				'tokenName' => esc_html_x( 'Earned marks', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'PASS_MARK',
				'tokenName' => esc_html_x( 'Pass mark', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'SCORE_PERCENTAGE',
				'tokenName' => esc_html_x( 'Score percentage', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'TOTAL_QUESTIONS',
				'tokenName' => esc_html_x( 'Total questions', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CORRECT_ANSWERS',
				'tokenName' => esc_html_x( 'Correct answers', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'INCORRECT_ANSWERS',
				'tokenName' => esc_html_x( 'Incorrect answers', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ATTEMPT_STARTED_AT',
				'tokenName' => esc_html_x( 'Attempt started at', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ATTEMPT_ENDED_AT',
				'tokenName' => esc_html_x( 'Attempt ended at', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the quiz attempt tokens.
	 *
	 * @param \Masteriyo\Models\QuizAttempt|null $attempt The quiz attempt.
	 *
	 * @return array
	 */
	public function hydrate_attempt_tokens( $attempt ) {

		$values = array(
			'TOTAL_MARKS'        => '',
			'EARNED_MARKS'       => '',
			'PASS_MARK'          => '',
			'SCORE_PERCENTAGE'   => '',
			'TOTAL_QUESTIONS'    => '',
			'CORRECT_ANSWERS'    => '',
			'INCORRECT_ANSWERS'  => '',
			'ATTEMPT_STARTED_AT' => '',
			'ATTEMPT_ENDED_AT'   => '',
		);

		if ( empty( $attempt ) ) {
			return $values;
		}

		$total_marks  = (float) $attempt->get_total_marks();
		$earned_marks = (float) $attempt->get_earned_marks();

		$values['TOTAL_MARKS']        = $total_marks;
		$values['EARNED_MARKS']       = $earned_marks;
		$values['PASS_MARK']          = $this->get_pass_mark( $attempt, $total_marks );
		$values['SCORE_PERCENTAGE']   = $total_marks > 0 ? round( ( $earned_marks / $total_marks ) * 100, 2 ) : 0;
		$values['TOTAL_QUESTIONS']    = $attempt->get_total_questions();
		$values['CORRECT_ANSWERS']    = $attempt->get_total_correct_answers();
		$values['INCORRECT_ANSWERS']  = $attempt->get_total_incorrect_answers();
		$values['ATTEMPT_STARTED_AT'] = $this->format_date( $attempt->get_attempt_started_at() );
		$values['ATTEMPT_ENDED_AT']   = $this->format_date( $attempt->get_attempt_ended_at() );

		return $values;
	}

	/**
	 * Resolve the pass mark an attempt was actually measured against.
	 *
	 * Masteriyo rescales a point-mode pass mark onto the attempt's own total,
	 * so the stored `pass_mark` is not what the attempt was graded against.
	 *
	 * @param \Masteriyo\Models\QuizAttempt $attempt     The quiz attempt.
	 * @param float                         $total_marks The attempt's total marks.
	 *
	 * @return float|string
	 */
	private function get_pass_mark( $attempt, $total_marks ) {

		if ( ! function_exists( 'masteriyo_get_quiz_effective_pass_mark' ) ) {
			return '';
		}

		return masteriyo_get_quiz_effective_pass_mark( $attempt->get_quiz_id(), $total_marks );
	}

	////////////////////////////////////////////////////////////
	// Shared utilities
	////////////////////////////////////////////////////////////

	/**
	 * The title of the section a lesson sits in (the lesson's parent).
	 *
	 * @param int $lesson_id The lesson ID.
	 *
	 * @return string
	 */
	private function get_section_name( $lesson_id ) {

		$lesson     = function_exists( 'masteriyo_get_lesson' ) ? masteriyo_get_lesson( $lesson_id ) : null;
		$section_id = is_object( $lesson ) ? absint( $lesson->get_parent_id() ) : 0;

		return 0 === $section_id ? '' : get_the_title( $section_id );
	}

	/**
	 * Get a comma-separated list of term names for a post.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The taxonomy slug.
	 *
	 * @return string
	 */
	private function get_term_names( $post_id, $taxonomy ) {

		$terms = get_the_terms( $post_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}

		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}

	/**
	 * Format a Masteriyo date object as a MySQL-style string.
	 *
	 * @param mixed $date The date value from a Masteriyo model.
	 *
	 * @return string
	 */
	private function format_date( $date ) {

		if ( empty( $date ) ) {
			return '';
		}

		if ( $date instanceof \DateTimeInterface ) {
			return $date->format( 'Y-m-d H:i:s' );
		}

		return (string) $date;
	}
}
