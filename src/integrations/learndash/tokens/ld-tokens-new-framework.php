<?php

namespace Uncanny_Automator\Integrations\Learndash;

/**
 * Class Ld_Tokens_New_Framework
 *
 * Shared token definitions and hydration methods for LearnDash triggers.
 *
 * @package Uncanny_Automator\Integrations\Learndash
 */
class Ld_Tokens_New_Framework {

	/**
	 * Course token definitions.
	 *
	 * @return array[]
	 */
	public function course_tokens() {

		return array(
			array(
				'tokenId'   => 'LDCOURSE',
				'tokenName' => esc_html_x( 'Course title', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDCOURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDCOURSE_STATUS',
				'tokenName' => esc_html_x( 'Course status', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDCOURSE_ACCESS_EXPIRY',
				'tokenName' => esc_html_x( 'Course access expiry date', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDCOURSE_URL',
				'tokenName' => esc_html_x( 'Course URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDCOURSE_THUMB_ID',
				'tokenName' => esc_html_x( 'Course featured image ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDCOURSE_THUMB_URL',
				'tokenName' => esc_html_x( 'Course featured image URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate course tokens with actual values.
	 *
	 * @param int $course_id The course post ID.
	 * @param int $user_id   The user ID (optional, used for status and expiry).
	 *
	 * @return array Key-value pairs of token ID => value.
	 */
	public function hydrate_course_tokens( $course_id, $user_id = 0 ) {

		$course = get_post( $course_id );

		$course_title = $course->post_title ?? '';
		$course_url   = null !== $course ? get_permalink( $course_id ) : '';

		$course_status        = '';
		$course_access_expiry = '';

		if ( 0 !== absint( $user_id ) && null !== $course ) {
			$course_status = learndash_course_status( $course_id, $user_id );

			$expires_on = ld_course_access_expires_on( $course_id, $user_id );

			if ( ! empty( $expires_on ) ) {
				$course_access_expiry = gmdate( get_option( 'date_format', 'F j, Y' ), $expires_on );
			}
		}

		$thumb_id  = null !== $course ? get_post_thumbnail_id( $course_id ) : '';
		$thumb_url = null !== $course ? get_the_post_thumbnail_url( $course_id, 'full' ) : '';

		return array(
			'LDCOURSE'              => $course_title,
			'LDCOURSE_ID'           => $course_id,
			'LDCOURSE_STATUS'       => $course_status,
			'LDCOURSE_ACCESS_EXPIRY' => $course_access_expiry,
			'LDCOURSE_URL'          => $course_url,
			'LDCOURSE_THUMB_ID'     => $thumb_id,
			'LDCOURSE_THUMB_URL'    => ! empty( $thumb_url ) ? $thumb_url : '',
		);
	}

	/**
	 * Course completion extra token definitions (for COURSEDONE trigger).
	 *
	 * tokenIdentifier is the LEGACY identity (<= 7.2.5): Ld_Tokens added these
	 * via the ldcourse relevant-tokens filter with tokenIdentifier = LDCOURSE,
	 * so existing recipe pills are {trigger_id}:LDCOURSE:{tokenId}. Omitting it
	 * lets resolve_token_definitions() default to the trigger CODE (COURSEDONE),
	 * which orphans every pre-migration pill — red in the builder, and at parse
	 * time the generic parser matches the LDCOURSE piece and returns the course
	 * TITLE instead. Contract: tests/wpunit/migration snapshot.
	 *
	 * @return array[]
	 */
	public function course_completion_tokens() {

		return array(
			array(
				'tokenId'         => 'LDCOURSE_course_completed_on',
				'tokenName'       => esc_html_x( 'Course completion date', 'LearnDash', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'LDCOURSE',
			),
			array(
				'tokenId'         => 'LDCOURSE_course_points',
				'tokenName'       => esc_html_x( 'Course points', 'LearnDash', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'LDCOURSE',
			),
		);
	}

	/**
	 * Hydrate course completion tokens.
	 *
	 * @param int $course_id The course post ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return array Key-value pairs of token ID => value.
	 */
	public function hydrate_course_completion_tokens( $course_id, $user_id ) {

		$completed_date = learndash_user_get_course_completed_date( $user_id, $course_id );
		$course_points  = learndash_get_course_points( $course_id );

		$formatted_date = '';

		if ( ! empty( $completed_date ) ) {
			// learndash_adjust_date_time_display() is the legacy (<= 7.2.5)
			// formatter: date AND time, localized to the site timezone via
			// LearnDash's own display settings. The interim gmdate(date_format)
			// dropped the time component and rendered in UTC.
			$formatted_date = learndash_adjust_date_time_display( $completed_date );
		}

		return array(
			'LDCOURSE_course_completed_on' => $formatted_date,
			'LDCOURSE_course_points'       => $course_points,
		);
	}

	/**
	 * Lesson token definitions.
	 *
	 * @return array[]
	 */
	public function lesson_tokens() {

		return array(
			array(
				'tokenId'   => 'LDLESSON',
				'tokenName' => esc_html_x( 'Lesson title', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDLESSON_ID',
				'tokenName' => esc_html_x( 'Lesson ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDLESSON_URL',
				'tokenName' => esc_html_x( 'Lesson URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDLESSON_THUMB_ID',
				'tokenName' => esc_html_x( 'Lesson featured image ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDLESSON_THUMB_URL',
				'tokenName' => esc_html_x( 'Lesson featured image URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate lesson tokens with actual values.
	 *
	 * @param int $lesson_id The lesson post ID.
	 *
	 * @return array Key-value pairs of token ID => value.
	 */
	public function hydrate_lesson_tokens( $lesson_id ) {

		$lesson = get_post( $lesson_id );

		$lesson_title = $lesson->post_title ?? '';
		$lesson_url   = null !== $lesson ? get_permalink( $lesson_id ) : '';
		$thumb_id     = null !== $lesson ? get_post_thumbnail_id( $lesson_id ) : '';
		$thumb_url    = null !== $lesson ? get_the_post_thumbnail_url( $lesson_id, 'full' ) : '';

		return array(
			'LDLESSON'           => $lesson_title,
			'LDLESSON_ID'        => $lesson_id,
			'LDLESSON_URL'       => $lesson_url,
			'LDLESSON_THUMB_ID'  => $thumb_id,
			'LDLESSON_THUMB_URL' => ! empty( $thumb_url ) ? $thumb_url : '',
		);
	}

	/**
	 * Topic token definitions.
	 *
	 * @return array[]
	 */
	public function topic_tokens() {

		return array(
			array(
				'tokenId'   => 'LDTOPIC',
				'tokenName' => esc_html_x( 'Topic title', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDTOPIC_ID',
				'tokenName' => esc_html_x( 'Topic ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDTOPIC_URL',
				'tokenName' => esc_html_x( 'Topic URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDTOPIC_THUMB_ID',
				'tokenName' => esc_html_x( 'Topic featured image ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDTOPIC_THUMB_URL',
				'tokenName' => esc_html_x( 'Topic featured image URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate topic tokens with actual values.
	 *
	 * @param int $topic_id The topic post ID.
	 *
	 * @return array Key-value pairs of token ID => value.
	 */
	public function hydrate_topic_tokens( $topic_id ) {

		$topic = get_post( $topic_id );

		$topic_title = $topic->post_title ?? '';
		$topic_url   = null !== $topic ? get_permalink( $topic_id ) : '';
		$thumb_id    = null !== $topic ? get_post_thumbnail_id( $topic_id ) : '';
		$thumb_url   = null !== $topic ? get_the_post_thumbnail_url( $topic_id, 'full' ) : '';

		return array(
			'LDTOPIC'           => $topic_title,
			'LDTOPIC_ID'        => $topic_id,
			'LDTOPIC_URL'       => $topic_url,
			'LDTOPIC_THUMB_ID'  => $thumb_id,
			'LDTOPIC_THUMB_URL' => ! empty( $thumb_url ) ? $thumb_url : '',
		);
	}

	/**
	 * Quiz token definitions (base set shared by all quiz triggers).
	 *
	 * @return array[]
	 */
	public function quiz_tokens() {

		return array(
			array(
				'tokenId'   => 'LDQUIZ',
				'tokenName' => esc_html_x( 'Quiz title', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_ID',
				'tokenName' => esc_html_x( 'Quiz ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_URL',
				'tokenName' => esc_html_x( 'Quiz URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_THUMB_ID',
				'tokenName' => esc_html_x( 'Quiz featured image ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_THUMB_URL',
				'tokenName' => esc_html_x( 'Quiz featured image URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_TIME',
				'tokenName' => esc_html_x( 'Quiz time spent', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_SCORE',
				'tokenName' => esc_html_x( 'Quiz score', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_CORRECT',
				'tokenName' => esc_html_x( 'Quiz number of correct answers', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_CATEGORY_SCORES',
				'tokenName' => esc_html_x( 'Quiz category scores', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_Q_AND_A',
				'tokenName' => esc_html_x( 'Quiz questions and answers', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDQUIZ_Q_AND_A_CSV',
				'tokenName' => esc_html_x( 'Quiz question & answers (unformatted)', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate quiz tokens with actual values.
	 *
	 * @param int   $quiz_id   The quiz post ID.
	 * @param array $quiz_data The quiz attempt data array (keys: timespent, score, count, etc.).
	 *
	 * @return array Key-value pairs of token ID => value.
	 */
	public function hydrate_quiz_tokens( $quiz_id, $quiz_data = array() ) {

		$quiz = get_post( $quiz_id );

		$quiz_title = $quiz->post_title ?? '';
		$quiz_url   = null !== $quiz ? get_permalink( $quiz_id ) : '';
		$thumb_id   = null !== $quiz ? get_post_thumbnail_id( $quiz_id ) : '';
		$thumb_url  = null !== $quiz ? get_the_post_thumbnail_url( $quiz_id, 'full' ) : '';

		$time_spent = $quiz_data['timespent'] ?? '';
		$score      = $quiz_data['score'] ?? '';
		$correct    = $quiz_data['count'] ?? '';

		return array(
			'LDQUIZ'                => $quiz_title,
			'LDQUIZ_ID'             => $quiz_id,
			'LDQUIZ_URL'            => $quiz_url,
			'LDQUIZ_THUMB_ID'       => $thumb_id,
			'LDQUIZ_THUMB_URL'      => ! empty( $thumb_url ) ? $thumb_url : '',
			'LDQUIZ_TIME'           => $time_spent,
			'LDQUIZ_SCORE'          => $score,
			'LDQUIZ_CORRECT'        => $correct,
			// Category scores, Q&A, and Q&A CSV are handled by the legacy Ld_Tokens token parsing filter.
			'LDQUIZ_CATEGORY_SCORES' => '',
			'LDQUIZ_Q_AND_A'        => '',
			'LDQUIZ_Q_AND_A_CSV'    => '',
		);
	}

	/**
	 * Quiz score extra token definition (for LD_QUIZSCORE trigger).
	 *
	 * @return array[]
	 */
	public function quiz_score_tokens() {

		return array(
			array(
				'tokenId'         => 'LDQUIZ_achieved_score',
				'tokenName'       => esc_html_x( "User's quiz score", 'LearnDash', 'uncanny-automator' ),
				'tokenType'       => 'int',
				// Legacy identity (<= 7.2.5) — see course_completion_tokens().
				'tokenIdentifier' => 'LDQUIZ',
			),
		);
	}

	/**
	 * Hydrate quiz score tokens.
	 *
	 * @param mixed $score The achieved quiz score.
	 *
	 * @return array Key-value pairs of token ID => value.
	 */
	public function hydrate_quiz_score_tokens( $score ) {

		return array(
			'LDQUIZ_achieved_score' => $score,
		);
	}

	/**
	 * Quiz percent extra token definitions (for LD_QUIZPERCENT trigger).
	 *
	 * @return array[]
	 */
	public function quiz_percent_tokens() {

		return array(
			array(
				'tokenId'         => 'LDQUIZ_achieved_percent',
				'tokenName'       => esc_html_x( "User's quiz percentage", 'LearnDash', 'uncanny-automator' ),
				'tokenType'       => 'text',
				// Legacy identity (<= 7.2.5) — see course_completion_tokens().
				'tokenIdentifier' => 'LDQUIZ',
			),
			array(
				'tokenId'         => 'LDQUIZ_quiz_passing_percentage',
				'tokenName'       => esc_html_x( 'Passing score %', 'LearnDash', 'uncanny-automator' ),
				'tokenType'       => 'int',
				// Legacy identity (<= 7.2.5) — see course_completion_tokens().
				'tokenIdentifier' => 'LDQUIZ',
			),
		);
	}

	/**
	 * Hydrate quiz percent tokens.
	 *
	 * @param mixed $percent         The achieved quiz percentage.
	 * @param mixed $passing_percent The passing percentage threshold.
	 *
	 * @return array Key-value pairs of token ID => value.
	 */
	public function hydrate_quiz_percent_tokens( $percent, $passing_percent ) {

		return array(
			'LDQUIZ_achieved_percent'        => $percent,
			'LDQUIZ_quiz_passing_percentage' => $passing_percent,
		);
	}

	/**
	 * Quiz points extra token definition (for LD_QUIZPOINT trigger).
	 *
	 * @return array[]
	 */
	public function quiz_points_tokens() {

		return array(
			array(
				'tokenId'         => 'LDQUIZ_achieved_points',
				'tokenName'       => esc_html_x( "User's quiz points", 'LearnDash', 'uncanny-automator' ),
				'tokenType'       => 'int',
				// Legacy identity (<= 7.2.5) — see course_completion_tokens().
				'tokenIdentifier' => 'LDQUIZ',
			),
		);
	}

	/**
	 * Hydrate quiz points tokens.
	 *
	 * @param mixed $points The achieved quiz points.
	 *
	 * @return array Key-value pairs of token ID => value.
	 */
	public function hydrate_quiz_points_tokens( $points ) {

		return array(
			'LDQUIZ_achieved_points' => $points,
		);
	}

	/**
	 * Group token definitions.
	 *
	 * @return array[]
	 */
	public function group_tokens() {

		return array(
			array(
				'tokenId'   => 'LDGROUP',
				'tokenName' => esc_html_x( 'Group title', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDGROUP_ID',
				'tokenName' => esc_html_x( 'Group ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDGROUP_URL',
				'tokenName' => esc_html_x( 'Group URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDGROUP_THUMB_ID',
				'tokenName' => esc_html_x( 'Group featured image ID', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LDGROUP_THUMB_URL',
				'tokenName' => esc_html_x( 'Group featured image URL', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate group tokens with actual values.
	 *
	 * @param int $group_id The group post ID.
	 *
	 * @return array Key-value pairs of token ID => value.
	 */
	public function hydrate_group_tokens( $group_id ) {

		$group = get_post( $group_id );

		$group_title = $group->post_title ?? '';
		$group_url   = null !== $group ? get_permalink( $group_id ) : '';
		$thumb_id    = null !== $group ? get_post_thumbnail_id( $group_id ) : '';
		$thumb_url   = null !== $group ? get_the_post_thumbnail_url( $group_id, 'full' ) : '';

		return array(
			'LDGROUP'           => $group_title,
			'LDGROUP_ID'        => $group_id,
			'LDGROUP_URL'       => $group_url,
			'LDGROUP_THUMB_ID'  => $thumb_id,
			'LDGROUP_THUMB_URL' => ! empty( $thumb_url ) ? $thumb_url : '',
		);
	}

	/**
	 * NUMTIMES token definition (for triggers that count occurrences).
	 *
	 * @return array[]
	 */
	public function numtimes_tokens() {

		return array(
			array(
				'tokenId'   => 'NUMTIMES',
				'tokenName' => esc_html_x( 'Number of times', 'LearnDash', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the NUMTIMES token from the trigger post directly.
	 *
	 * Reads the saved field value via get_post_meta on the trigger ID rather
	 * than relying on $trigger['meta']['NUMTIMES']. The legacy recipe pipeline
	 * populated $trigger['meta'] via get_post_custom, but the modern
	 * Recipe_Runner can pass a $trigger shape whose meta key is absent —
	 * falling back silently to '1' and shadowing the user-saved value.
	 *
	 * @param array $trigger The trigger settings (must contain `ID`).
	 *
	 * @return array<string, mixed> Token ID => value pairs.
	 */
	public function hydrate_numtimes_tokens( $trigger ) {

		$trigger_id = isset( $trigger['ID'] ) ? absint( $trigger['ID'] ) : 0;

		if ( $trigger_id <= 0 ) {
			return array( 'NUMTIMES' => '1' );
		}

		$value = get_post_meta( $trigger_id, 'NUMTIMES', true );

		return array(
			'NUMTIMES' => '' === $value ? '1' : $value,
		);
	}

	/**
	 * Standard NUMTIMES field definition (DRY helper).
	 *
	 * @return array Field definition ready to include in options().
	 */
	public static function numtimes_field() {
		return array(
			'option_code'            => 'NUMTIMES',
			'label'                  => esc_html_x( 'Number of times', 'LearnDash', 'uncanny-automator' ),
			'show_label_in_sentence' => false,
			'placeholder'            => esc_html_x( 'Example: 1', 'LearnDash', 'uncanny-automator' ),
			'input_type'             => 'int',
			'default_value'          => 1,
			'min_number'             => 1,
			'required'               => true,
		);
	}

	/**
	 * Convert trigger-format tokens to action-format tokens.
	 *
	 * Trigger format: array( 'tokenId' => 'X', 'tokenName' => '...', 'tokenType' => 'text' )
	 * Action format:  'TOKEN_ID' => array( 'name' => '...', 'type' => 'text' )
	 *
	 * @param array $trigger_tokens Array of trigger-format token definitions.
	 *
	 * @return array Action-format token definitions.
	 */
	public static function to_action_tokens( array $trigger_tokens ) {

		$action_tokens = array();

		foreach ( $trigger_tokens as $token ) {
			$token_id = $token['tokenId'] ?? '';
			if ( empty( $token_id ) ) {
				continue;
			}
			$action_tokens[ $token_id ] = array(
				'name' => $token['tokenName'] ?? '',
				'type' => $token['tokenType'] ?? 'text',
			);
		}

		return $action_tokens;
	}
}
