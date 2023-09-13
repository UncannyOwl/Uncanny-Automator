<?php

namespace Uncanny_Automator;

/**
 * Class LD_MARKTOPICDONE
 *
 * @package Uncanny_Automator
 */
class LD_MARKTOPICDONE {

	use Recipe\Action_Tokens;

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LD';

	private $action_code;
	private $action_meta;
	private $action_integration;
	private $quiz_list;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MARKTOPICDONE';
		$this->action_meta = 'LDTOPIC';
		$this->define_action();

	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/learndash/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnDash */
			'sentence'           => sprintf( esc_attr__( 'Mark {{a topic:%1$s}} complete for the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnDash */
			'select_option_name' => esc_attr__( 'Mark {{a topic}} complete for the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'mark_completes_a_topic' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		// Set Action Tokens for Topic, Lesson and Course.
		$topic_tokens  = Automator()->helpers->recipe->learndash->options->get_topic_relevant_tokens( 'action', $this->action_meta );
		$lesson_tokens = Automator()->helpers->recipe->learndash->options->get_lesson_relevant_tokens( 'action', 'LDLESSON' );
		$course_tokens = Automator()->helpers->recipe->learndash->options->get_course_relevant_tokens( 'action', 'LDCOURSE' );
		$this->set_action_tokens( $topic_tokens + $lesson_tokens + $course_tokens, $this->action_code );

		Automator()->register->action( $action );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {

		$args = array(
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$course_options = Automator()->helpers->recipe->options->wp_query( $args, false, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$args = array(
			'post_type'      => 'sfwd-lessons',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$lesson_options = Automator()->helpers->recipe->options->wp_query( $args, false, esc_attr__( 'Any lesson', 'uncanny-automator' ) );

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->action_meta => array(
						Automator()->helpers->recipe->field->select_field_args(
							array(
								'option_code'              => 'LDCOURSE',
								'options'                  => $course_options,
								'label'                    => esc_attr__( 'Course', 'uncanny-automator' ),

								'required'                 => true,
								'custom_value_description' => esc_attr__( 'Course ID', 'uncanny-automator' ),

								'is_ajax'                  => true,
								'target_field'             => 'LDLESSON',
								'endpoint'                 => 'select_lesson_from_course_MARKTOPICDONE',
							)
						),

						Automator()->helpers->recipe->field->select_field_args(
							array(
								'option_code'              => 'LDLESSON',
								'options'                  => $lesson_options,
								'label'                    => esc_attr__( 'Lesson', 'uncanny-automator' ),

								'required'                 => true,
								'custom_value_description' => esc_attr__( 'Lesson ID', 'uncanny-automator' ),

								'is_ajax'                  => true,
								'target_field'             => 'LDTOPIC',
								'endpoint'                 => 'select_topic_from_lesson_MARKTOPICDONE',
							)
						),

						Automator()->helpers->recipe->field->select_field_args(
							array(
								'option_code'              => 'LDTOPIC',
								'options'                  => array(),

								'label'                    => esc_attr__( 'Topic', 'uncanny-automator' ),
								'required'                 => true,
								'custom_value_description' => esc_attr__( 'Topic ID', 'uncanny-automator' ),
							)
						),
					),
				),
			)
		);
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function mark_completes_a_topic( $user_id, $action_data, $recipe_id, $args ) {

		$topic_id = $action_data['meta'][ $this->action_meta ];

		//Mark complete a lesson
		$course_id = $action_data['meta']['LDCOURSE'];
		$lesson_id = $action_data['meta']['LDLESSON'];

		//Mark complete a topic quiz
		$topic_quiz_list = learndash_get_lesson_quiz_list( $topic_id, $user_id, $course_id );
		if ( $topic_quiz_list ) {
			foreach ( $topic_quiz_list as $ql ) {
				$this->quiz_list[ $ql['post']->ID ] = 0;
			}
		}

		$this->mark_quiz_complete( $user_id, $course_id );

		learndash_process_mark_complete( $user_id, $topic_id, false, $course_id );

		// Hydrate Lesson & Course Action Tokens.
		$topic_tokens  = Automator()->helpers->recipe->learndash->options->hydrate_ld_topic_action_tokens( $topic_id, $user_id, $this->action_meta );
		$lesson_tokens = Automator()->helpers->recipe->learndash->options->hydrate_ld_lesson_action_tokens( $lesson_id, $user_id, 'LDLESSON' );
		$course_tokens = Automator()->helpers->recipe->learndash->options->hydrate_ld_course_action_tokens( $course_id, $user_id, 'LDCOURSE' );
		$this->hydrate_tokens( $topic_tokens + $lesson_tokens + $course_tokens );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}


	/**
	 * @param      $user_id
	 * @param null $course_id
	 */
	public function mark_quiz_complete( $user_id, $course_id = null ) {

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
