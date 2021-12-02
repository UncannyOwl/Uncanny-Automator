<?php

namespace Uncanny_Automator;

/**
 * Class MASTERSTUDY_QUIZPASSED
 *
 * @package Uncanny_Automator
 */
class MASTERSTUDY_QUIZPASSED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MSLMS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'MSLMSQUIZPASSED';
		$this->trigger_meta = 'MSLMSQUIZ';
		$this->define_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$args = array(
			'post_type'      => 'stm-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, true, __( 'Any course', 'uncanny-automator' ) );

		$course_relevant_tokens = array(
			'MSLMSCOURSE'           => esc_attr__( 'Course title', 'uncanny-automator' ),
			'MSLMSCOURSE_ID'        => esc_attr__( 'Course ID', 'uncanny-automator' ),
			'MSLMSCOURSE_URL'       => esc_attr__( 'Course URL', 'uncanny-automator' ),
			'MSLMSCOURSE_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
			'MSLMSCOURSE_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
		);

		$relevant_tokens = array(
			$this->trigger_meta                => esc_attr__( 'Quiz title', 'uncanny-automator' ),
			$this->trigger_meta . '_ID'        => esc_attr__( 'Quiz ID', 'uncanny-automator' ),
			$this->trigger_meta . '_URL'       => esc_attr__( 'Quiz URL', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_ID'  => esc_attr__( 'Quiz featured image ID', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_URL' => esc_attr__( 'Quiz featured image URL', 'uncanny-automator' ),
			$this->trigger_meta . '_SCORE'     => esc_attr__( 'Quiz score', 'uncanny-automator' ),

		);

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/masterstudy-lms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - MasterStudy LMS */
			'sentence'            => sprintf( esc_attr__( 'A user passes {{a quiz:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - MasterStudy LMS */
			'select_option_name'  => esc_attr__( 'A user passes {{a quiz}}', 'uncanny-automator' ),
			'action'              => 'stm_lms_quiz_passed',
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'quiz_passed' ),
			'options'             => array(),
			'options_group'       => array(
				$this->trigger_meta => array(
					Automator()->helpers->recipe->field->select_field_ajax(
						'MSLMSCOURSE',
						esc_attr_x( 'Course', 'MasterStudy LMS', 'uncanny-automator' ),
						$options,
						'',
						'',
						false,
						true,
						array(
							'target_field' => $this->trigger_meta,
							'endpoint'     => 'select_mslms_quiz_from_course_QUIZ',
						),
						$course_relevant_tokens
					),
					Automator()->helpers->recipe->field->select_field( $this->trigger_meta, esc_attr__( 'Quiz', 'uncanny-automator' ), array(), false, false, false, $relevant_tokens ),
				),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function quiz_passed( $user_id, $quiz_id, $user_quiz_progress ) {

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $quiz_id,
			'user_id' => $user_id,
		);

		$args = Automator()->maybe_add_trigger_entry( $args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {

					$source    = ( ! empty( automator_filter_input( 'source', INPUT_POST ) ) ) ? intval( automator_filter_input( 'source', INPUT_POST ) ) : '';
					$course_id = ( ! empty( automator_filter_input( 'course_id', INPUT_POST ) ) ) ? intval( automator_filter_input( 'course_id', INPUT_POST ) ) : '';
					$course_id = apply_filters( 'user_answers__course_id', $course_id, $source );

					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => 'MSLMSCOURSE',
							'meta_value'     => $course_id,
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						)
					);

					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => $this->trigger_meta . '_SCORE',
							'meta_value'     => $user_quiz_progress . '%',
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						)
					);

					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $user_id,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => $this->trigger_meta,
							'meta_value'     => $quiz_id,
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
