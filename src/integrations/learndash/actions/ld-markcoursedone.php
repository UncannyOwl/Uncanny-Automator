<?php

namespace Uncanny_Automator;

/**
 * Class LD_MARKCOURSEDONE
 *
 * @package Uncanny_Automator
 */
class LD_MARKCOURSEDONE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LD';

	private $action_code;
	private $action_meta;
	private $quiz_list;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MARKCOURSEDONE';
		$this->action_meta = 'LDCOURSE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/learndash/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnDash */
			'sentence'           => sprintf( esc_attr__( 'Mark {{a course:%1$s}} complete for the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnDash */
			'select_option_name' => esc_attr__( 'Mark {{a course}} complete for the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'mark_completes_a_course' ),
			'options'            => array(
				Automator()->helpers->recipe->learndash->options->all_ld_courses( null, 'LDCOURSE', false ),
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function mark_completes_a_course( $user_id, $action_data, $recipe_id, $args ) {

		$course_id = $action_data['meta'][ $this->action_meta ];
		//$courses   = learndash_user_get_enrolled_courses( $user_id, array(), true );
		//if ( in_array( $course_id, $courses ) ) {
		$this->mark_steps_done( $user_id, $course_id );
		//all steps done.. mark course complete
		learndash_process_mark_complete( $user_id, $course_id );
		//}
		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}


	/**
	 * @param $user_id
	 * @param $course_id
	 */
	public function mark_steps_done( $user_id, $course_id ) {
		$lessons = learndash_get_lesson_list( $course_id, array( 'num' => 0 ) );
		foreach ( $lessons as $lesson ) {
			$this->mark_topics_done( $user_id, $lesson->ID, $course_id );
			$lesson_quiz_list = learndash_get_lesson_quiz_list( $lesson->ID, $user_id, $course_id );

			if ( $lesson_quiz_list ) {
				foreach ( $lesson_quiz_list as $ql ) {
					$this->quiz_list[ $ql['post']->ID ] = 0;
				}
			}

			learndash_process_mark_complete( $user_id, $lesson->ID, false, $course_id );
		}

		$this->mark_quiz_complete( $user_id, $course_id );
	}

	/**
	 * @param $user_id
	 * @param $lesson_id
	 * @param $course_id
	 */
	public function mark_topics_done( $user_id, $lesson_id, $course_id ) {
		$topic_list = learndash_get_topic_list( $lesson_id, $course_id );
		if ( $topic_list ) {
			foreach ( $topic_list as $topic ) {
				learndash_process_mark_complete( $user_id, $topic->ID, false, $course_id );
				$topic_quiz_list = learndash_get_lesson_quiz_list( $topic->ID, $user_id, $course_id );
				if ( $topic_quiz_list ) {
					foreach ( $topic_quiz_list as $ql ) {
						$this->quiz_list[ $ql['post']->ID ] = 0;
					}
				}
			}
		}
	}


	/**
	 * @param      $user_id
	 * @param null $course_id
	 */
	public function mark_quiz_complete( $user_id, $course_id = null ) {
		$quizzes = learndash_get_course_quiz_list( $course_id, $user_id );
		if ( $quizzes ) {
			foreach ( $quizzes as $quiz ) {
				$this->quiz_list[ $quiz['post']->ID ] = 0;
			}
		}
		$quizz_progress = array();
		if ( ! empty( $this->quiz_list ) ) {
			$usermeta       = get_user_meta( $user_id, '_sfwd-quizzes', true );
			$quizz_progress = empty( $usermeta ) ? array() : $usermeta;

			foreach ( $this->quiz_list as $quiz_id => $quiz ) {
				$quiz_meta = get_post_meta( $quiz_id, '_sfwd-quiz', true );

				if ( learndash_is_quiz_complete( $user_id, $quiz_id, $course_id ) ) {
					continue;
				}

				$quizdata = array(
					'quiz'             => $quiz_id,
					'score'            => 0,
					'count'            => 0,
					'pass'             => true,
					'rank'             => '-',
					'time'             => time(),
					'pro_quizid'       => $quiz_meta['sfwd-quiz_quiz_pro'],
					'course'           => $course_id,
					'points'           => 0,
					'total_points'     => 0,
					'percentage'       => 100,
					'timespent'        => 0,
					'has_graded'       => false,
					'statistic_ref_id' => 0,
					'm_edit_by'        => 9999999,  // Manual Edit By ID.
					'm_edit_time'      => time(),          // Manual Edit timestamp.
				);

				$quizz_progress[] = $quizdata;

				if ( $quizdata['pass'] == true ) {
					$quizdata_pass = true;
				} else {
					$quizdata_pass = false;
				}

				// Then we add the quiz entry to the activity database.
				learndash_update_user_activity(
					array(
						'course_id'          => $course_id,
						'user_id'            => $user_id,
						'post_id'            => $quiz_id,
						'activity_type'      => 'quiz',
						'activity_action'    => 'insert',
						'activity_status'    => true,
						'activity_started'   => $quizdata['time'],
						'activity_completed' => $quizdata['time'],
						'activity_meta'      => $quizdata,
					)
				);

			}
		}

		if ( ! empty( $quizz_progress ) ) {
			update_user_meta( $user_id, '_sfwd-quizzes', $quizz_progress );
		}
	}
}
