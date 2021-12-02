<?php

namespace Uncanny_Automator;

use LP_Global;
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

		$args    = array(
			'post_type'      => 'lp_course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);
		$options = Automator()->helpers->recipe->options->wp_query( $args, false, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/learnpress/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - LearnPress */
			'sentence'           => sprintf( esc_attr__( 'Mark {{a section:%1$s}} complete for the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - LearnPress */
			'select_option_name' => esc_attr__( 'Mark {{a section}} complete for the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'lp_mark_section_done' ),
			'options_group'      => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->field->select_field_args(
						array(
							'option_code'              => 'LPCOURSE',
							'options'                  => $options,
							'label'                    => esc_attr__( 'Course', 'uncanny-automator' ),

							'required'                 => true,
							'custom_value_description' => esc_attr__( 'Course ID', 'uncanny-automator' ),

							'is_ajax'                  => true,
							'target_field'             => 'LPSECTION',
							'endpoint'                 => 'select_section_from_course_LPMARKLESSONDONE',
						)
					),

					Automator()->helpers->recipe->field->select_field_args(
						array(
							'option_code'              => $this->action_meta,
							'options'                  => array(),
							'label'                    => esc_attr__( 'Section', 'uncanny-automator' ),

							'required'                 => true,
							'custom_value_description' => esc_attr__( 'Section ID', 'uncanny-automator' ),
						)
					),
				),
			),
		);

		Automator()->register->action( $action );
	}


	/**
	 * Validation function when the action is hit.
	 *
	 * @param string $user_id user id.
	 * @param array $action_data action data.
	 * @param string $recipe_id recipe id.
	 */
	public function lp_mark_section_done( $user_id, $action_data, $recipe_id, $args ) {

		if ( ! function_exists( 'learn_press_get_current_user' ) ) {
			$error_message = 'The function learn_press_get_current_user does not exist';
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}
		$user = learn_press_get_user( $user_id );

		$course_id  = $action_data['meta']['LPCOURSE'];
		$section_id = $action_data['meta'][ $this->action_meta ];
		// Get All lessons from section.
		$course_curd = new LP_Section_CURD( $course_id );
		$lessons     = $course_curd->get_section_items( $section_id );
		// Mark lesson completed.
		foreach ( $lessons as $lesson ) {
			if ( $lesson['type'] === 'lp_lesson' ) {
				$result = $user->complete_lesson( $lesson['id'], $course_id );
			} elseif ( $lesson['type'] === 'lp_quiz' ) {
				$quiz_id = $lesson['id'];
				$user    = LP_Global::user();

				if ( ! $user->has_item_status( array( 'started', 'completed' ), $quiz_id, $course_id ) ) {
					$quiz_data = $user->start_quiz( $quiz_id, $course_id, false );
					$item      = new LP_User_Item_Course( $quiz_data );
					$item->finish();
				} else {
					$quiz_data = $user->get_item_data( $quiz_id, $course_id );
					$quiz_data->finish();
				}
			}
		}

		if ( ! is_wp_error( $result ) ) {
			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		} else {
			$error_message = $result->get_error_message();
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

	}

}
