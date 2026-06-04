<?php

namespace Uncanny_Automator\Integrations\Learndash;
use Uncanny_Automator\Learndash_Helpers;

/**
 * Class LD_MARKTOPICDONE
 *
 * @package Uncanny_Automator\Integrations\Learndash
 *
 * @property \Uncanny_Automator\Integrations\Learndash\Ld_Helpers $item_helpers
 */
class LD_MARKTOPICDONE extends \Uncanny_Automator\Recipe\Action {

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
		$this->set_action_code( 'MARKTOPICDONE' );
		$this->set_action_meta( 'LDTOPIC' );

		$this->set_sentence(
			sprintf(
				esc_html_x( 'Mark {{a topic:%1$s}} complete for the user', 'LearnDash', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Mark {{a topic}} complete for the user', 'LearnDash', 'uncanny-automator' )
		);

	}

	/**
	 * Define action tokens for Topic + Lesson + Course.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {
		$tokens_class  = new Ld_Tokens_New_Framework();
		$topic_tokens  = Ld_Tokens_New_Framework::to_action_tokens( $tokens_class->topic_tokens() );
		$lesson_tokens = Ld_Tokens_New_Framework::to_action_tokens( $tokens_class->lesson_tokens() );
		$course_tokens = Ld_Tokens_New_Framework::to_action_tokens( $tokens_class->course_tokens() );
		return $topic_tokens + $lesson_tokens + $course_tokens;
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
				'option_code'              => 'LDLESSON',
				'options'                  => array(),
				'label'                    => esc_html_x( 'Lesson', 'LearnDash', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'custom_value_description' => esc_html_x( 'Lesson ID', 'LearnDash', 'uncanny-automator' ),
				'supports_custom_value'    => true,
				'remote_data'              => $this->item_helpers->remote_data_parent_config( 'lessons_from_course_strict', array( 'LDCOURSE' ) ),
			),
			array(
				'option_code'              => 'LDTOPIC',
				'options'                  => array(),
				'label'                    => esc_html_x( 'Topic', 'LearnDash', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'custom_value_description' => esc_html_x( 'Topic ID', 'LearnDash', 'uncanny-automator' ),
				'supports_custom_value'    => true,
				'remote_data'              => $this->item_helpers->remote_data_parent_config( 'topics_from_lesson_strict', array( 'LDLESSON' ) ),
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

		$topic_id  = absint( $parsed[ $this->get_action_meta() ] );
		$course_id = absint( $parsed['LDCOURSE'] );
		$lesson_id = absint( $parsed['LDLESSON'] );

		// Mark complete a topic quiz.
		$topic_quiz_list = learndash_get_lesson_quiz_list( $topic_id, $user_id, $course_id );

		if ( $topic_quiz_list ) {
			foreach ( $topic_quiz_list as $ql ) {
				$this->quiz_list[ $ql['post']->ID ] = 0;
			}
		}

		$this->mark_quiz_complete( $user_id, $course_id );

		Ld_Helpers::process_mark_complete( $user_id, $topic_id, false, $course_id );

		// Hydrate Topic, Lesson & Course Action Tokens.
		$tokens_class  = new Ld_Tokens_New_Framework();
		$topic_tokens  = $tokens_class->hydrate_topic_tokens( $topic_id );
		$lesson_tokens = $tokens_class->hydrate_lesson_tokens( $lesson_id );
		$course_tokens = $tokens_class->hydrate_course_tokens( $course_id, $user_id );
		$this->hydrate_tokens( $topic_tokens + $lesson_tokens + $course_tokens );

		return true;
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
	}
}
