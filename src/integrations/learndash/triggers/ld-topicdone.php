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

		$trigger = array(
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
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
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

		return Automator()->utilities->keep_order_of_options(
			array(
				'options'       => array(
					Automator()->helpers->recipe->options->number_of_times(),
				),
				'options_group' => array(
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
			)
		);
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

		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );

		if ( empty( $recipes ) ) {
			return;
		}

		$course_id = absint( $course->ID );
		$lesson_id = absint( $lesson->ID );
		$topic_id  = absint( $topic->ID );

		$course_ids = Automator()->get->meta_from_recipes( $recipes, 'LDCOURSE' );
		$lesson_ids = Automator()->get->meta_from_recipes( $recipes, 'LDLESSON' );
		$topic_ids  = Automator()->get->meta_from_recipes( $recipes, 'LDTOPIC' );
		if ( empty( $course_ids ) || empty( $lesson_ids ) || empty( $topic_ids ) ) {
			return; // bailout
		}

		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];

				$r_course_id = (int) $course_ids[ $recipe_id ][ $trigger_id ];
				$r_lesson_id = (int) $lesson_ids[ $recipe_id ][ $trigger_id ];
				$r_topic_id  = (int) $topic_ids[ $recipe_id ][ $trigger_id ];

				if ( ( intval( '-1' ) === intval( $r_course_id ) || (int) $course_id === (int) $r_course_id )
					&&
					( intval( '-1' ) === intval( $r_lesson_id ) || (int) $lesson_id === (int) $r_lesson_id )
					&&
					( intval( '-1' ) === intval( $r_topic_id ) || (int) $topic_id === (int) $r_topic_id )
				) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
						'course_id'  => $course_id,
						'lesson_id'  => $lesson_id,
						'topic_id'   => $topic_id,
					);
				}
			}
		}

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		foreach ( $matched_recipe_ids as $matched_recipe_id ) {

			$pass_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'post_id'          => $topic->ID,
				'user_id'          => $user->ID,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
			);

			$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

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


}
