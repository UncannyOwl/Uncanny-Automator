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
class MPC_MARKLESSONDONE
{

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
	public function __construct()
	{
		$this->action_code = 'MPMARKLESSONEDONE';
		$this->action_meta = 'MPLESSON';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action()
	{

		$args = array(
			'post_type'      => 'mpcs-course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args );

		$action = array(
			'author'             => Automator()->get_author_name(),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/memberpress-courses/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - Memberpress */
			'sentence'           => sprintf( esc_attr__( 'Mark {{a lesson:%1$s}} complete for the user', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - Memberpress */
			'select_option_name' => esc_attr__( 'Mark {{a lesson}} complete for the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'mark_completes_a_lesson' ),
			'options_group'      => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->field->select_field_ajax(
						'MPCOURSE',
						esc_attr__( 'Course', 'uncanny-automator' ),
						$options,
						'',
						'',
						false,
						true,
						array(
							'target_field' => $this->action_meta,
							'endpoint'     => 'select_lesson_from_course_LESSONDONE',
						),
						''
					),
					Automator()->helpers->recipe->field->select_field( $this->action_meta, esc_attr__( 'Lesson', 'uncanny-automator' ), array(), false, false, false, '' ),
				),
			),
		);

		Automator()->register->action($action);
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function mark_completes_a_lesson( $user_id, $action_data, $recipe_id, $args) {
		$course_id = $action_data['meta']['MPCOURSE'];
		$lesson_id = $action_data['meta']['MPLESSON'];

		$this->mark_lesson_completed( $user_id, $course_id, $lesson_id );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

	/**
	 * @param $user_id
	 * @param $course_id
	 * $ @param $lesson_id
	 */
	public function mark_lesson_completed( $user_id, $course_id, $lesson_id ) {

		if ( empty( $lesson_id ) && empty( $course_id ) ) {
			return;
		}

		if ( models\UserProgress::has_completed_course( $user_id, $course_id ) ) {
			return;
		}

		$user_progress               = new models\UserProgress();
		$user_progress->lesson_id    = $lesson_id;
		$user_progress->course_id    = $course_id;
		$user_progress->user_id      = $user_id;
		$user_progress->created_at   = lib\Utils::ts_to_mysql_date( time() );
		$user_progress->completed_at = lib\Utils::ts_to_mysql_date( time() );
		$user_progress->store();

		do_action( base\SLUG_KEY . '_completed_lesson', $user_progress );
	}

}
