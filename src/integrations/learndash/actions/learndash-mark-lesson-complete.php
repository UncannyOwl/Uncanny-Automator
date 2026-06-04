<?php

namespace Uncanny_Automator\Integrations\Learndash;
use Uncanny_Automator\Learndash_Helpers;

/**
 * Class LD_MARKLESSONDONE
 *
 * @package Uncanny_Automator\Integrations\Learndash
 *
 * @property \Uncanny_Automator\Integrations\Learndash\Ld_Helpers $item_helpers
 */
class LD_MARKLESSONDONE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @var array
	 */
	private $quiz_list = array();

	/**
	 * Set up the action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'LD' );
		$this->set_action_code( 'MARKLESSONDONE' );
		$this->set_action_meta( 'LDLESSON' );

		$this->set_sentence(
			sprintf(
				esc_html_x( 'Mark {{a lesson:%1$s}} complete for the user', 'LearnDash', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Mark {{a lesson}} complete for the user', 'LearnDash', 'uncanny-automator' )
		);

	}

	/**
	 * Define action tokens for Lesson + Course.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {
		$tokens_class  = new Ld_Tokens_New_Framework();
		$lesson_tokens = Ld_Tokens_New_Framework::to_action_tokens( $tokens_class->lesson_tokens() );
		$course_tokens = Ld_Tokens_New_Framework::to_action_tokens( $tokens_class->course_tokens() );
		return $lesson_tokens + $course_tokens;
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'              => 'LDCOURSE',
				'options'                  => array(),
				'label'                    => esc_html_x( 'Course', 'LearnDash', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'custom_value_description' => esc_html_x( 'Course ID', 'LearnDash', 'uncanny-automator' ),
				'supports_custom_value'    => true,
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'courses_strict' ),
			),
			array(
				'option_code'              => $this->get_action_meta(),
				'options'                  => array(),
				'label'                    => esc_html_x( 'Lesson', 'LearnDash', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'custom_value_description' => esc_html_x( 'Lesson ID', 'LearnDash', 'uncanny-automator' ),
				'supports_custom_value'    => true,
				'remote_data'              => $this->item_helpers->remote_data_parent_config( 'lessons_from_course_strict', array( 'LDCOURSE' ) ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$course_id = absint( $parsed['LDCOURSE'] );
		$lesson_id = absint( $parsed[ $this->get_action_meta() ] );

		$this->mark_steps_done( $user_id, $lesson_id, $course_id );

		// Hydrate Lesson & Course Action Tokens.
		$tokens_class  = new Ld_Tokens_New_Framework();
		$lesson_tokens = $tokens_class->hydrate_lesson_tokens( $lesson_id );
		$course_tokens = $tokens_class->hydrate_course_tokens( $course_id, $user_id );
		$this->hydrate_tokens( $lesson_tokens + $course_tokens );

		return true;
	}

	/**
	 * Mark lesson steps done (topics, quizzes, then lesson itself).
	 *
	 * @param int $user_id
	 * @param int $lesson_id
	 * @param int $course_id
	 *
	 * @return void
	 */
	private function mark_steps_done( $user_id, $lesson_id, $course_id ) {

		$topic_list = learndash_get_topic_list( $lesson_id, $course_id );

		if ( $topic_list ) {
			foreach ( $topic_list as $topic ) {

				$topic_quiz_list = learndash_get_lesson_quiz_list( $topic->ID, $user_id, $course_id );

				if ( $topic_quiz_list ) {
					foreach ( $topic_quiz_list as $ql ) {
						$this->quiz_list[ $ql['post']->ID ] = 0;
					}
				}

				$this->mark_quiz_complete( $user_id, $course_id );

				Ld_Helpers::process_mark_complete( $user_id, $topic->ID, false, $course_id );
			}
		}

		$lesson_quiz_list = learndash_get_lesson_quiz_list( $lesson_id, $user_id, $course_id );

		if ( $lesson_quiz_list ) {
			foreach ( $lesson_quiz_list as $ql ) {
				$this->quiz_list[ $ql['post']->ID ] = 0;
			}
		}

		$this->mark_quiz_complete( $user_id, $course_id );

		// Mark complete a lesson.
		Ld_Helpers::process_mark_complete( $user_id, $lesson_id, false, $course_id );
	}

	/**
	 * Mark all quizzes complete.
	 *
	 * @param int      $user_id
	 * @param int|null $course_id
	 *
	 * @return void
	 */
	private function mark_quiz_complete( $user_id, $course_id = null ) {

		$quizz_progress = array();

		if ( ! empty( $this->quiz_list ) ) {

			$usermeta       = get_user_meta( $user_id, '_sfwd-quizzes', true );
			$quizz_progress = empty( $usermeta ) ? array() : $usermeta;

			foreach ( $this->quiz_list as $quiz_id => $quiz ) {

				$quiz_meta = get_post_meta( $quiz_id, '_sfwd-quiz', true );

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
					'percentage'       => 0,
					'timespent'        => 0,
					'has_graded'       => false,
					'statistic_ref_id' => 0,
					'm_edit_by'        => 9999999,  // Manual Edit By ID.
					'm_edit_time'      => time(),
					// Manual Edit timestamp.
				);

				$quizz_progress[] = $quizdata;

				if ( true == $quizdata['pass'] ) {
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
						'activity_status'    => $quizdata_pass,
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

		$this->quiz_list = array();
	}
}
