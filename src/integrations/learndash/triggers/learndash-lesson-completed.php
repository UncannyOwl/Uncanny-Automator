<?php

namespace Uncanny_Automator\Integrations\Learndash;

/**
 * Class LD_LESSONDONE
 *
 * @property \Uncanny_Automator\Integrations\Learndash\Ld_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Learndash
 */
class LD_LESSONDONE extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'LESSONDONE', 'LD' )
			->trigger_meta( 'LDLESSON' )
			->hook( 'learndash_lesson_completed', 10, 1 )
			->hook( 'automator_learndash_lesson_completed', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user completes {{a lesson:%1$s}}', 'LearnDash', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user completes {{a lesson}}', 'LearnDash', 'uncanny-automator' ) );

		// Register both hooks — LD fires lesson_completed; Automator fires the custom hook as a fallback.
	}

	/**
	 * Trigger options — cascading course -> lesson select.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'           => 'LDCOURSE',
				'label'                 => esc_html_x( 'Course', 'LearnDash', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'relevant_tokens'       => array(
					'LDCOURSE'           => esc_html_x( 'Course title', 'LearnDash', 'uncanny-automator' ),
					'LDCOURSE_ID'        => esc_html_x( 'Course ID', 'LearnDash', 'uncanny-automator' ),
					'LDCOURSE_URL'       => esc_html_x( 'Course URL', 'LearnDash', 'uncanny-automator' ),
					'LDCOURSE_THUMB_ID'  => esc_html_x( 'Course featured image ID', 'LearnDash', 'uncanny-automator' ),
					'LDCOURSE_THUMB_URL' => esc_html_x( 'Course featured image URL', 'LearnDash', 'uncanny-automator' ),
				),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'courses' ),
			),
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Lesson', 'LearnDash', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'lessons_from_course', array( 'LDCOURSE' ) ),
				'relevant_tokens'       => array(
					$this->get_trigger_meta()                => esc_html_x( 'Lesson title', 'LearnDash', 'uncanny-automator' ),
					$this->get_trigger_meta() . '_ID'        => esc_html_x( 'Lesson ID', 'LearnDash', 'uncanny-automator' ),
					$this->get_trigger_meta() . '_URL'       => esc_html_x( 'Lesson URL', 'LearnDash', 'uncanny-automator' ),
					$this->get_trigger_meta() . '_THUMB_ID'  => esc_html_x( 'Lesson featured image ID', 'LearnDash', 'uncanny-automator' ),
					$this->get_trigger_meta() . '_THUMB_URL' => esc_html_x( 'Lesson featured image URL', 'LearnDash', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * Define additional tokens for this trigger.
	 *
	 * @param array $trigger The trigger definition.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tokens_class = new Ld_Tokens_New_Framework();

		return array_merge(
			$tokens,
			$tokens_class->course_tokens(),
			$tokens_class->lesson_tokens()
		);
	}

	/**
	 * Validate whether the trigger should fire.
	 *
	 * @param array $trigger   The trigger definition and metadata.
	 * @param array $hook_args The arguments from the WP hook.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $data ) = $hook_args;

		if ( empty( $data ) ) {
			return false;
		}

		$user   = $data['user'] ?? null;
		$lesson = $data['lesson'] ?? null;
		$course = $data['course'] ?? null;

		if ( empty( $lesson->ID ) || empty( $user->ID ) ) {
			return false;
		}

		// Dedup via runtime cache — LearnDash may fire the hook multiple times for the same lesson.
		$cache_key   = 'automator_lesson_completed_ ' . $lesson->ID . '_user_' . $user->ID;
		$cache_group = 'automator-ld-lesson-completed';

		if ( false !== Automator()->cache->get( $cache_key, $cache_group ) ) {
			return false;
		}

		$this->set_user_id( $user->ID );

		$selected_course = $trigger['meta']['LDCOURSE'] ?? '';
		$selected_lesson = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';
		$course_id       = $course->ID ?? 0;
		$lesson_id       = $lesson->ID ?? 0;

		// Check course match.
		if ( intval( '-1' ) !== intval( $selected_course ) && absint( $selected_course ) !== absint( $course_id ) ) {
			return false;
		}

		// Check lesson match.
		if ( intval( '-1' ) !== intval( $selected_lesson ) && absint( $selected_lesson ) !== absint( $lesson_id ) ) {
			return false;
		}

		// Set cache to prevent duplicate fires.
		Automator()->cache->set( $cache_key, true, $cache_group );

		return true;
	}

	/**
	 * Hydrate tokens with actual values.
	 *
	 * @param array $trigger   The trigger definition.
	 * @param array $hook_args The arguments from the WP hook.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $data ) = $hook_args;

		$course_id = $data['course']->ID ?? 0;
		$lesson_id = $data['lesson']->ID ?? 0;
		$user_id   = $data['user']->ID ?? 0;

		$tokens_class = new Ld_Tokens_New_Framework();

		return array_merge(
			$tokens_class->hydrate_course_tokens( $course_id, $user_id ),
			$tokens_class->hydrate_lesson_tokens( $lesson_id ),
			array( $this->get_trigger_meta() => get_the_title( $lesson_id ) )
		);
	}
}
