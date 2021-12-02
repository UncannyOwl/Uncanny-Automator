<?php

namespace Uncanny_Automator;

/**
 * Class LD_TOPICDONE
 *
 * @package Uncanny_Automator
 */
class LD_TOPICDONE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LD';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LD_TOPICDONE';
		$this->trigger_meta = 'LDTOPIC';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$args = array(
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$course_options = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$args = array(
			'post_type'      => 'sfwd-lessons',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$lesson_options         = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any course', 'uncanny-automator' ) );
		$course_relevant_tokens = array(
			'LDCOURSE'           => esc_attr__( 'Course title', 'uncanny-automator' ),
			'LDCOURSE_ID'        => esc_attr__( 'Course ID', 'uncanny-automator' ),
			'LDCOURSE_URL'       => esc_attr__( 'Course URL', 'uncanny-automator' ),
			'LDCOURSE_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
			'LDCOURSE_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
		);
		$lesson_relevant_tokens = array(
			'LDLESSON'           => esc_attr__( 'Lesson title', 'uncanny-automator' ),
			'LDLESSON_ID'        => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
			'LDLESSON_URL'       => esc_attr__( 'Lesson URL', 'uncanny-automator' ),
			'LDLESSON_THUMB_ID'  => esc_attr__( 'Lesson featured image ID', 'uncanny-automator' ),
			'LDLESSON_THUMB_URL' => esc_attr__( 'Lesson featured image URL', 'uncanny-automator' ),
		);
		$relevant_tokens        = array(
			$this->trigger_meta                => esc_attr__( 'Topic title', 'uncanny-automator' ),
			$this->trigger_meta . '_ID'        => esc_attr__( 'Topic ID', 'uncanny-automator' ),
			$this->trigger_meta . '_URL'       => esc_attr__( 'Topic URL', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_ID'  => esc_attr__( 'Topic featured image ID', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_URL' => esc_attr__( 'Topic featured image URL', 'uncanny-automator' ),
		);
		$trigger                = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/learndash/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnDash */
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a topic:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  => esc_attr__( 'A user completes {{a topic}}', 'uncanny-automator' ),
			'action'              => 'learndash_topic_completed',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'topic_completed' ),
			'options'             => array(
				Automator()->helpers->recipe->options->number_of_times(),
			),
			'options_group'       => array(
				$this->trigger_meta => array(
					Automator()->helpers->recipe->field->select_field_ajax(
						'LDCOURSE',
						esc_attr__( 'Course', 'uncanny-automator' ),
						$course_options,
						'',
						'',
						false,
						true,
						array(
							'target_field' => 'LDLESSON',
							'endpoint'     => 'select_lesson_from_course_LD_TOPICDONE',
						),
						$course_relevant_tokens
					),
					Automator()->helpers->recipe->field->select_field_ajax(
						'LDLESSON',
						esc_attr__( 'Lesson', 'uncanny-automator' ),
						$lesson_options,
						'',
						'',
						false,
						true,
						array(
							'target_field' => 'LDTOPIC',
							'endpoint'     => 'select_topic_from_lesson_LD_TOPICDONE',
						),
						$lesson_relevant_tokens
					),
					Automator()->helpers->recipe->field->select_field( 'LDTOPIC', esc_attr__( 'Topic', 'uncanny-automator' ), array(), false, false, false, $relevant_tokens ),
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
	public function topic_completed( $data ) {

		if ( empty( $data ) ) {
			return;
		}

		$user   = $data['user'];
		$topic  = $data['topic'];
		$lesson = $data['lesson'];
		$course = $data['course'];

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $topic->ID,
			'user_id' => $user->ID,
		);

		$args = Automator()->maybe_add_trigger_entry( $args, false );
		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $user->ID,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => 'LDCOURSE',
							'meta_value'     => $course->ID,
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						)
					);
					Automator()->insert_trigger_meta(
						array(
							'user_id'        => $user->ID,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => 'LDLESSON',
							'meta_value'     => $lesson->ID,
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
