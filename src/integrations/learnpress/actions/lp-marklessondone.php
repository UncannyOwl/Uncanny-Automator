<?php

namespace Uncanny_Automator;

use LearnPress\Models\CourseModel;
use LearnPress\Models\LessonPostModel;
use LearnPress\Models\UserItems\UserLessonModel;

/**
 * Class LP_MARKLESSONDONE
 *
 * @package Uncanny_Automator
 */
class LP_MARKLESSONDONE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'LPMARKLESSONDONE-A';
		$this->action_meta = 'LPLESSON';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/learnpress/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnPress */
			'sentence'           => sprintf( esc_html_x( 'Mark {{a lesson:%1$s}} complete for the user', 'Learnpress', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnPress */
			'select_option_name' => esc_html_x( 'Mark {{a lesson}} complete for the user', 'Learnpress', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'lp_mark_lesson_done' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {

		$args    = array(
			'post_type'      => 'lp_course',
			'posts_per_page' => 999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);
		$options = Automator()->helpers->recipe->options->wp_query( $args, false, esc_html_x( 'Any course', 'Learnpress', 'uncanny-automator' ) );

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->action_meta => array(
						Automator()->helpers->recipe->field->select_field_args(
							array(
								'option_code'              => 'LPCOURSE',
								'options'                  => $options,
								'label'                    => esc_html_x( 'Course', 'Learnpress', 'uncanny-automator' ),
								'required'                 => true,
								'custom_value_description' => esc_html_x( 'Course ID', 'Learnpress', 'uncanny-automator' ),
								'is_ajax'                  => true,
								'target_field'             => 'LPSECTION',
								'endpoint'                 => 'select_section_from_course_LPMARKLESSONDONE',
							)
						),

						Automator()->helpers->recipe->field->select_field_args(
							array(
								'option_code'              => 'LPSECTION',
								'options'                  => array(),
								'label'                    => esc_html_x( 'Section', 'Learnpress', 'uncanny-automator' ),
								'required'                 => true,
								'custom_value_description' => esc_html_x( 'Section ID', 'Learnpress', 'uncanny-automator' ),
								'is_ajax'                  => true,
								'target_field'             => $this->action_meta,
								'endpoint'                 => 'select_lesson_from_section_LPMARKLESSONDONE',
							)
						),

						Automator()->helpers->recipe->field->select_field_args(
							array(
								'option_code'              => $this->action_meta,
								'options'                  => array(),
								'label'                    => esc_html_x( 'Lesson', 'Learnpress', 'uncanny-automator' ),
								'required'                 => true,
								'custom_value_description' => esc_html_x( 'Lesson ID', 'Learnpress', 'uncanny-automator' ),
							)
						),
					),
				),
			)
		);
	}

	/**
	 * Validation function when the action is hit.
	 *
	 * @param string $user_id user id.
	 * @param array $action_data action data.
	 * @param string $recipe_id recipe id.
	 *
	 * @throws \Exception
	 */
	public function lp_mark_lesson_done( $user_id, $action_data, $recipe_id, $args ) {
		$lesson_id = $action_data['meta'][ $this->action_meta ];
		$course_id = $action_data['meta']['LPCOURSE'];

		if ( ! CourseModel::find( $course_id, true ) ) {
			$action_data['complete_with_errors'] = true;
			$error_message                       = esc_html_x( 'Course is invalid!', 'Learnpress', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		if ( ! LessonPostModel::find( $lesson_id, true ) ) {
			$action_data['complete_with_errors'] = true;
			$error_message                       = esc_html_x( 'Lesson is invalid!', 'Learnpress', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$user_lesson_model = Automator()->helpers->recipe->learnpress->options->get_user_lesson_model( $user_id, $lesson_id, $course_id );
		if ( ! $user_lesson_model instanceof UserLessonModel ) {
			$new_user_item_id = Automator()->helpers->recipe->learnpress->options->insert_user_item_model( $user_id, $lesson_id, $course_id );
			if ( $new_user_item_id ) {
				$user_lesson_model = Automator()->helpers->recipe->learnpress->options->get_user_lesson_model( $user_id, $lesson_id, $course_id );
			}
		}

		// Validate that we have a valid UserLessonModel before proceeding
		if ( ! $user_lesson_model instanceof UserLessonModel ) {
			$action_data['complete_with_errors'] = true;
			$error_message                       = esc_html_x( 'Unable to create or retrieve user lesson model!', 'Learnpress', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$user_lesson_model->set_complete();
		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}
}
