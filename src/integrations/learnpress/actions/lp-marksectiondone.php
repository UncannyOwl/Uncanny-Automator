<?php

namespace Uncanny_Automator;

use LP_Section_CURD;
use LP_User_Item_Course;

/**
 * Class LP_MARKSECTIONDONE
 *
 * @package Uncanny_Automator
 */
class LP_MARKSECTIONDONE {

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
		$this->action_code = 'LPMARKSECTIONDONE-A';
		$this->action_meta = 'LPSECTION';
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
			'sentence'           => sprintf( esc_html_x( 'Mark {{a section:%1$s}} complete for the user', 'Learnpress', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnPress */
			'select_option_name' => esc_html_x( 'Mark {{a section}} complete for the user', 'Learnpress', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'lp_mark_section_done' ),
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
								'option_code'              => $this->action_meta,
								'options'                  => array(),
								'label'                    => esc_html_x( 'Section', 'Learnpress', 'uncanny-automator' ),
								'required'                 => true,
								'custom_value_description' => esc_html_x( 'Section ID', 'Learnpress', 'uncanny-automator' ),
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
	 */
	public function lp_mark_section_done( $user_id, $action_data, $recipe_id, $args ) {

		if ( ! function_exists( 'learn_press_get_user' ) ) {
			$error_message = esc_html_x( 'The function learn_press_get_user does not exist', 'Learnpress', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}
		$user = learn_press_get_user( $user_id );

		$course_id  = $action_data['meta']['LPCOURSE'];
		$section_id = $action_data['meta'][ $this->action_meta ];
		// Get All lessons from section.
		$course_curd      = new LP_Section_CURD( $course_id );
		$lessons          = $course_curd->get_section_items( $section_id );
		$total_count      = count( $lessons );
		$completed_lesson = 0;
		// Mark lesson completed.
		foreach ( $lessons as $lesson ) {
			if ( 'lp_lesson' === $lesson['type'] ) {
				$user_item = $user->get_user_item( $lesson['id'], $course_id );
				if ( ! $user_item instanceof \LP_User_Item ) {
					Automator()->helpers->recipe->learnpress->options->insert_user_item_model( $user_id, $lesson['id'], $course_id );
				}
				$result = $user->complete_lesson( $lesson['id'], $course_id );
				// @todo: This is a temporary fix to handle the error message. We need to find a better way to handle this.
				$completed_lesson = ( true === $result || ( is_wp_error( $result ) && __( 'You have already completed this lesson.', 'learnpress' ) === $result->get_error_message() ) ) ? ++$completed_lesson : $completed_lesson; // phpcs:ignore
			} elseif ( 'lp_quiz' === $lesson['type'] ) {
				$quiz_id = $lesson['id'];
				if ( ! $user->has_item_status( array( 'started', 'completed' ), $quiz_id, $course_id ) ) {
					$quiz_data = $user->start_quiz( $quiz_id, $course_id, false );
					$result    = $user->finish_quiz( $quiz_id, $course_id );
				} else {
					// Quiz already completed, consider it successful
					$result = true;
				}

				$completed_lesson = ( true === $result ) ? ++$completed_lesson : $completed_lesson;
			}
		}

		if ( $completed_lesson === $total_count ) {
			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		} else {
			$action_data['complete_with_errors'] = true;
			$error_message                       = esc_html_x( 'Unable to complete the section.', 'Learnpress', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}
	}
}
