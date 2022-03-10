<?php

namespace Uncanny_Automator;

use memberpress\courses as base;
use memberpress\courses\lib as lib;
use memberpress\courses\models as models;

/**
 * Class MPC_MARKCOURSEDONE
 *
 * @package Uncanny_Automator
 */
class MPC_MARKCOURSEDONE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MPC';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MPMARKCOURSEDONE';
		$this->action_meta = 'MPCOURSE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/memberpress-courses/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - Memberpress */
			'sentence'           => sprintf( esc_attr__( 'Mark {{a course:%1$s}} complete for the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - Memberpress */
			'select_option_name' => esc_attr__( 'Mark {{a course}} complete for the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'mark_completes_a_course' ),
			'options'            => array(
				Automator()->helpers->recipe->memberpress_courses->all_mp_courses( null, 'MPCOURSE', false ),
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
		$sections  = [];
		$lessons   = [];
		$course_id = $action_data['meta'][ $this->action_meta ];
		$sections  = Automator()->helpers->recipe->memberpress_courses->find_all_by_course( $course_id );

		if ( is_array( $sections ) && count( $sections ) > 0 ) {
			foreach ( $sections as $section ) {
				$lessons = Automator()->helpers->recipe->memberpress_courses->find_all_by_section( $section );
				if ( is_array( $lessons ) && count( $lessons ) > 0 ) {
					foreach ( $lessons as $lesson ) {
						$this->mark_lesson_completed( $user_id, $course_id, $lesson, $section );
					}
				}
			}
		}
		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

	/**
	 * @param $user_id
	 * @param $course_id
	 * $ @param $lesson_id
	 */
	public function mark_lesson_completed( $user_id, $course_id, $lesson_id, $section ) {

		if ( empty( $section_id ) && empty( $course_id ) ) {
			return;
		}

		if ( models\UserProgress::has_completed_course( $user_id, $course_id ) ) {
			return;
		}

		$has_started_course  = models\UserProgress::has_started_course( $user_id, $course_id );
		$has_started_section = models\UserProgress::has_started_section( $user_id, $section );

		$user_progress               = new models\UserProgress();
		$user_progress->lesson_id    = $lesson_id;
		$user_progress->course_id    = $course_id;
		$user_progress->user_id      = $user_id;
		$user_progress->created_at   = lib\Utils::ts_to_mysql_date( time() );
		$user_progress->completed_at = lib\Utils::ts_to_mysql_date( time() );
		$user_progress->store();

		do_action( base\SLUG_KEY . '_completed_lesson', $user_progress );

		if ( models\UserProgress::has_completed_course( $user_id, $course_id ) ) {
			do_action( base\SLUG_KEY . '_completed_course', $user_progress );
		}

		if ( models\UserProgress::has_completed_section( $user_id, $section ) ) {
			do_action( base\SLUG_KEY . '_completed_section', $user_progress );
		}
	}

}
