<?php

namespace Uncanny_Automator;

/**
 * Class MASTERSTUDY_MARKCOURSECOMPLETE
 *
 * @package Uncanny_Automator
 */
class MASTERSTUDY_MARKCOURSECOMPLETE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MSLMS';

	private $action_code;
	private $action_meta;
	private $quiz_list;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MSLMSMARKCOURSECOMPETE';
		$this->action_meta = 'MSLMSCOURSE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$args = array(
			'post_type'      => 'stm-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, false );

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/masterstudy-lms/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnDash */
			'sentence'           => sprintf( esc_attr__( 'Mark {{a course:%1$s}} complete for the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnDash */
			'select_option_name' => esc_attr__( 'Mark {{a course}} complete for the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 3,
			'execution_function' => array( $this, 'mark_course_complete' ),
			'options'            => array(
				array(
					'option_code'              => $this->action_meta,
					'label'                    => esc_attr__( 'Course', 'uncanny-automator' ),
					'input_type'               => 'select',
					'required'                 => true,
					'options'                  => $options,
					'custom_value_description' => _x( 'Course ID', 'MasterStudy', 'uncanny-automator' ),
				),
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
	public function mark_course_complete( $user_id, $action_data, $recipe_id, $args ) {

		$course_id = $action_data['meta'][ $this->action_meta ];

		/*Check if lesson in course*/
		$curriculum = get_post_meta( $course_id, 'curriculum', true );

		if ( ! empty( $curriculum ) ) {
			$curriculum = \STM_LMS_Helpers::only_array_numbers( explode( ',', $curriculum ) );

			$curriculum_posts = get_posts(
				array(
					'post__in'       => $curriculum,
					'posts_per_page' => 999,
					'post_type'      => array( 'stm-lessons', 'stm-quizzes' ),
					'post_status'    => 'publish',
				)
			);

			if ( ! empty( $curriculum_posts ) ) {

				// Enroll the user is the course if they are not already enrolled
				$course = stm_lms_get_user_course( $user_id, $course_id, array( 'user_course_id' ) );
				if ( ! count( $course ) ) {
					\STM_LMS_Course::add_user_course( $course_id, $user_id, \STM_LMS_Course::item_url( $course_id, '' ), 0 );
					\STM_LMS_Course::add_student( $course_id );
				}

				foreach ( $curriculum_posts as $post ) {

					if ( 'stm-lessons' === $post->post_type ) {

						// Complete Lesson
						$lesson_id = $post->ID;

						if ( \STM_LMS_Lesson::is_lesson_completed( $user_id, $course_id, $lesson_id ) ) {
							continue;
						};

						$end_time   = time();
						$start_time = get_user_meta( $user_id, "stm_lms_course_started_{$course_id}_{$lesson_id}", true );

						stm_lms_add_user_lesson( compact( 'user_id', 'course_id', 'lesson_id', 'start_time', 'end_time' ) );
						\STM_LMS_Course::update_course_progress( $user_id, $course_id );

						do_action( 'stm_lms_lesson_passed', $user_id, $lesson_id );

						delete_user_meta( $user_id, "stm_lms_course_started_{$course_id}_{$lesson_id}" );
					}

					if ( 'stm-quizzes' === $post->post_type ) {

						// Complete quiz
						$quiz_id = $post->ID;

						if ( \STM_LMS_Quiz::quiz_passed( $quiz_id, $user_id ) ) {
							continue;
						}

						$progress  = 100;
						$status    = 'passed';
						$user_quiz = compact( 'user_id', 'course_id', 'quiz_id', 'progress', 'status' );
						stm_lms_add_user_quiz( $user_quiz );
						stm_lms_get_delete_user_quiz_time( $user_id, $quiz_id );

						\STM_LMS_Course::update_course_progress( $user_id, $course_id );

						$user_quiz['progress'] = round( $user_quiz['progress'], 1 );
						do_action( 'stm_lms_quiz_' . $status, $user_id, $quiz_id, $user_quiz['progress'] );

					}
				}
				Automator()->complete_action( $user_id, $action_data, $recipe_id );
			}
		}
	}
}
