<?php

namespace Uncanny_Automator;

/**
 * Class LD_LESSONDONE
 *
 * @package Uncanny_Automator
 */
class LD_LESSONDONE {

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
		$this->trigger_code = 'LESSONDONE';
		$this->trigger_meta = 'LDLESSON';
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
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a lesson:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  => esc_attr__( 'A user completes {{a lesson}}', 'uncanny-automator' ),
			'action'              => array( 'learndash_lesson_completed', 'automator_learndash_lesson_completed' ),
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'lesson_completed' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Loads all options.
	 *
	 * @return array[]
	 */
	public function load_options() {

		$args = array(
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$course_relevant_tokens = array(
			'LDCOURSE'           => esc_attr__( 'Course title', 'uncanny-automator' ),
			'LDCOURSE_ID'        => esc_attr__( 'Course ID', 'uncanny-automator' ),
			'LDCOURSE_URL'       => esc_attr__( 'Course URL', 'uncanny-automator' ),
			'LDCOURSE_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
			'LDCOURSE_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
		);

		$relevant_tokens = array(
			$this->trigger_meta                => esc_attr__( 'Lesson title', 'uncanny-automator' ),
			$this->trigger_meta . '_ID'        => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
			$this->trigger_meta . '_URL'       => esc_attr__( 'Lesson URL', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_ID'  => esc_attr__( 'Lesson featured image ID', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_URL' => esc_attr__( 'Lesson featured image URL', 'uncanny-automator' ),
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options'       => array( Automator()->helpers->recipe->options->number_of_times() ),
				'options_group' => array(
					$this->trigger_meta => array(
						Automator()->helpers->recipe->field->select_field_ajax(
							'LDCOURSE',
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
			)
		);
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function lesson_completed( $data ) {

		if ( empty( $data ) ) {
			return;
		}

		$user   = $data['user'];
		$lesson = $data['lesson'];
		$course = $data['course'];

		if ( empty( $lesson->ID ) || empty( $user->ID ) ) {
			return;
		}

		$matched_recipe_ids = array();

		$cache_key   = 'automator_lesson_completed_ ' . $lesson->ID . '_user_' . $user->ID;
		$cache_group = 'automator-ld-lesson-completed';

		/**
		 * Bail if Trigger has already fired during run time.
		 *
		 * This is a LearnDash bug. The action hook `learndash_lesson_completed`
		 * shouldn't fire n times for quiz completions associated with a lesson.
		 *
		 * @ticket 2126631606/46933 - 860pm6a12
		 * @since 4.10
		 */
		if ( false !== wp_cache_get( $cache_key, $cache_group ) ) {
			return;
		}

		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );

		// Just check if the course ID passed into the action hook matches the one selected in the Trigger.
		$trigger_course_field = Automator()->get->meta_from_recipes( $recipes, 'LDCOURSE' );
		$trigger_lesson_field = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );

		foreach ( $trigger_course_field as $recipe_id => $triggers ) {

			foreach ( $triggers as $trigger_id => $field_course_id ) {

				// The lesson ID from the field.
				$field_lesson_id = isset( $trigger_lesson_field[ $recipe_id ][ $trigger_id ] )
					? $trigger_lesson_field[ $recipe_id ][ $trigger_id ] : null;

				// Determine if the lesson and the course matches.
				$lesson_and_course_matches = $this->course_matches( $field_course_id, $course->ID )
					&& $this->lesson_matches( $field_lesson_id, $lesson->ID );

				if ( $lesson_and_course_matches ) {

					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);

				}
			}
		}

		foreach ( $matched_recipe_ids as $matched_recipe_id ) {

			$pass_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user->ID,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'post_id'          => $lesson->ID,
			);

			$args = Automator()->process->user->maybe_add_trigger_entry( $pass_args, false );

			if ( $args ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {
						$meta_args = array(
							'user_id'        => $user->ID,
							'trigger_id'     => $result['args']['trigger_id'],
							'meta_key'       => 'LDCOURSE',
							'meta_value'     => $course->ID,
							'trigger_log_id' => $result['args']['get_trigger_id'],
							'run_number'     => $result['args']['run_number'],
						);
						Automator()->insert_trigger_meta( $meta_args );
						Automator()->process->user->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}

		wp_cache_set( $cache_key, true, 'automator-ld-lesson-completed' );

	}

	/**
	 * Determine if the selected course in the trigger matches the one sent by the action hook.
	 *
	 * @param int $field_course_id The course ID from the field.
	 * @param int $action_hook_course_id The course ID sent from 'learndash_lesson_completed'.
	 *
	 * @return bool True if course matches. Otherwise, false.
	 */
	private function course_matches( $field_course_id = 0, $action_hook_course_id = 0 ) {

		// Determine if selected course matches from received course ID.
		$course_is_any  = intval( $field_course_id ) === -1;
		$course_matches = intval( $field_course_id ) === intval( $action_hook_course_id ) || $course_is_any;

		return $course_matches;

	}

	/**
	 * Determine if the selected lesson in the trigger matches the one sent by the action hook.
	 *
	 * @param int $field_lesson_id The lesson ID from the field.
	 * @param int $action_hook_lesson_id The lesson ID sent from 'learndash_lesson_completed'.
	 *
	 * @return bool True if lesson matches. Otherwise, false.
	 */
	private function lesson_matches( $field_lesson_id = 0, $action_hook_lesson_id = 0 ) {

		// Determine if selected lesson mataches received lesson ID.
		$lesson_is_any  = intval( $field_lesson_id ) === -1;
		$lesson_matches = intval( $field_lesson_id ) === absint( $action_hook_lesson_id ) || $lesson_is_any;

		return $lesson_matches;

	}

}


