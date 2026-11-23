<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Masteriyo_User_Completes_Lesson
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 *
 * @property Masteriyo_Helpers $item_helpers
 */
class Masteriyo_User_Completes_Lesson extends Trigger {

	/**
	 * The course tokens this trigger's scope lists.
	 *
	 * @var string[]
	 */
	const COURSE_TOKENS = array( 'COURSE_ID', 'COURSE_TITLE' );

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'MASTERIYO_USER_COMPLETES_LESSON', 'MASTERIYO' )
			->trigger_meta( 'MASTERIYO_LESSON' )
			->hook( 'masteriyo_course_progress_item_completion_status_changed', 10, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );
		$this->set_is_login_required( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Lesson selector 2: Course selector */
				esc_html_x( 'A user completes {{a lesson:%1$s}} in {{a course:%2$s}}', 'Masteriyo LMS', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'MASTERIYO_COURSE:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user completes {{a lesson}} in {{a course}}', 'Masteriyo LMS', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * The lesson selector cascades on the course selector, so the course field
	 * is declared first even though the sentence reads lesson-then-course.
	 *
	 * @return array[]
	 */
	public function options() {

		return array(
			$this->item_helpers->get_course_field( 'MASTERIYO_COURSE' ),
			$this->item_helpers->get_lesson_field( $this->get_trigger_meta(), 'MASTERIYO_COURSE', false ),
		);
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$token_class = new Masteriyo_Tokens();

		return array_merge(
			$token_class->course_tokens( self::COURSE_TOKENS ),
			$token_class->lesson_tokens(),
			array(
				array(
					'tokenId'   => 'COMPLETED_AT',
					'tokenName' => esc_html_x( 'Completed at', 'Masteriyo LMS', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $hook_args[0], $hook_args[2] ) || ! is_object( $hook_args[0] ) ) {
			return false;
		}

		$item       = $hook_args[0];
		$new_status = $hook_args[2];

		// The same hook carries quiz progress items; only lessons apply here.
		if ( 'lesson' !== $item->get_item_type() ) {
			return false;
		}

		if ( 'completed' !== $new_status ) {
			return false;
		}

		$user_id = absint( $item->get_user_id() );

		if ( 0 === $user_id ) {
			return false;
		}

		$selected_lesson = $this->item_helpers->get_selected_value( $trigger, $this->get_trigger_meta() );

		if ( ! $this->item_helpers->matches_selection( $selected_lesson, $item->get_item_id() ) ) {
			return false;
		}

		$selected_course = $this->item_helpers->get_selected_value( $trigger, 'MASTERIYO_COURSE' );

		if ( '-1' !== $selected_course && ! $this->item_helpers->matches_selection( $selected_course, $this->item_helpers->get_progress_item_course_id( $item ) ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$item      = $hook_args[0];
		$course_id = $this->item_helpers->get_progress_item_course_id( $item );
		$lesson_id = absint( $item->get_item_id() );

		$token_class = new Masteriyo_Tokens();

		// Completing an existing lesson row writes its completion date to the
		// stored row only, not to the model the hook passes.
		$stored       = function_exists( 'masteriyo_get_course_progress_item' ) ? masteriyo_get_course_progress_item( $item->get_id() ) : null;
		$completed_at = ( is_object( $stored ) && ! is_wp_error( $stored ) ? $stored : $item )->get_completed_at();

		return array_merge(
			$token_class->hydrate_course_tokens( $course_id, self::COURSE_TOKENS ),
			$token_class->hydrate_lesson_tokens( $lesson_id ),
			array(
				$this->get_trigger_meta() => 0 === $lesson_id ? '' : get_the_title( $lesson_id ),
				'MASTERIYO_COURSE'        => 0 === $course_id ? '' : get_the_title( $course_id ),
				'COMPLETED_AT'            => $completed_at instanceof \DateTimeInterface ? $completed_at->format( 'Y-m-d H:i:s' ) : (string) $completed_at,
			)
		);
	}
}
