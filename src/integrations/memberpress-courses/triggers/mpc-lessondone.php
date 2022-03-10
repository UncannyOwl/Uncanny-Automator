<?php

namespace Uncanny_Automator;

use memberpress\courses as base;

/**
 * Class MP_LESSONDONE
 *
 * @package Uncanny_Automator
 */
class MPC_LESSONDONE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MPC';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'MPLESSONDONE';
		$this->trigger_meta = 'MPLESSON';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$args = array(
			'post_type'      => 'mpcs-course',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args );

		$course_relevant_tokens = array(
			'MPCOURSE'           => esc_attr__( 'Course title', 'uncanny-automator' ),
			'MPCOURSE_ID'        => esc_attr__( 'Course ID', 'uncanny-automator' ),
			'MPCOURSE_URL'       => esc_attr__( 'Course URL', 'uncanny-automator' ),
			'MPCOURSE_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
			'MPCOURSE_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
		);

		$relevant_tokens = array(
			$this->trigger_meta                => esc_attr__( 'Lesson title', 'uncanny-automator' ),
			$this->trigger_meta . '_ID'        => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
			$this->trigger_meta . '_URL'       => esc_attr__( 'Lesson URL', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_ID'  => esc_attr__( 'Lesson featured image ID', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_URL' => esc_attr__( 'Lesson featured image URL', 'uncanny-automator' ),
		);

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/memberpress-courses/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Memberpress */
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a lesson:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Memberpress */
			'select_option_name'  => esc_attr__( 'A user completes {{a lesson}}', 'uncanny-automator' ),
			'action'              => base\SLUG_KEY . '_completed_lesson',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'lesson_done' ),
			'options'             => array(
				Automator()->helpers->recipe->options->number_of_times(),
			),
			'options_group'       => array(
				$this->trigger_meta => array(
					Automator()->helpers->recipe->field->select_field_ajax(
						'MPCOURSE',
						esc_attr__( 'Course', 'uncanny-automator' ),
						$options,
						'',
						'',
						false,
						true,
						array(
							'target_field' => $this->trigger_meta,
							'endpoint'     => 'select_lesson_from_course_LESSONDONE',
						),
						$course_relevant_tokens
					),
					Automator()->helpers->recipe->field->select_field( $this->trigger_meta, esc_attr__( 'Lesson', 'uncanny-automator' ), array(), false, false, false, $relevant_tokens ),
				),
			),
		);
		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function lesson_done( $data ) {

		if ( empty( $data ) ) {
			return;
		}

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $data->lesson_id,
			'user_id' => $data->user_id,
		);

		$args = Automator()->maybe_add_trigger_entry( $args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $data->user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => 'MPCOURSE',
							'meta_value'     => $data->course_id,
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						)
					);
					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}

