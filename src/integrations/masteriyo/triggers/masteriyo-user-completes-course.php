<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Masteriyo_User_Completes_Course
 *
 * Listens on `masteriyo_course_progress_status_completed` rather than the
 * `started_to_completed` variant: progress normally moves started → progress →
 * completed, so the from/to hook would never fire for the usual path.
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 *
 * @property Masteriyo_Helpers $item_helpers
 */
class Masteriyo_User_Completes_Course extends Trigger {

	/**
	 * The course tokens this trigger's scope lists.
	 *
	 * @var string[]
	 */
	const COURSE_TOKENS = array( 'COURSE_ID', 'COURSE_TITLE', 'COURSE_URL', 'COURSE_AUTHOR', 'COURSE_CATEGORIES', 'COURSE_DIFFICULTY' );

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'MASTERIYO_USER_COMPLETES_COURSE', 'MASTERIYO' )
			->trigger_meta( 'MASTERIYO_COURSE' )
			->hook( 'masteriyo_course_progress_status_completed', 10, 2 );
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
				/* translators: 1: Course selector */
				esc_html_x( 'A user completes {{a course:%1$s}}', 'Masteriyo LMS', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user completes {{a course}}', 'Masteriyo LMS', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array( $this->item_helpers->get_course_field( $this->get_trigger_meta() ) );
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
			array(
				array(
					'tokenId'   => 'PROGRESS_ID',
					'tokenName' => esc_html_x( 'Progress ID', 'Masteriyo LMS', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'STARTED_AT',
					'tokenName' => esc_html_x( 'Started at', 'Masteriyo LMS', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
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

		if ( ! isset( $hook_args[1] ) || ! is_object( $hook_args[1] ) ) {
			return false;
		}

		$progress = $hook_args[1];

		$user_id = absint( $progress->get_user_id() );

		if ( 0 === $user_id ) {
			return false;
		}

		$selected = $this->item_helpers->get_selected_value( $trigger, $this->get_trigger_meta() );

		if ( ! $this->item_helpers->matches_selection( $selected, $progress->get_course_id() ) ) {
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

		$progress  = $hook_args[1];
		$course_id = absint( $progress->get_course_id() );

		$token_class = new Masteriyo_Tokens();

		// A course completed by its last lesson's save gets its completion date
		// written to the stored row only, not to the model the hook passes.
		$stored = function_exists( 'masteriyo_get_course_progress' ) ? masteriyo_get_course_progress( $progress->get_id() ) : null;
		$dates  = is_object( $stored ) ? $stored : $progress;

		$started_at   = $dates->get_started_at();
		$completed_at = $dates->get_completed_at();

		return array_merge(
			$token_class->hydrate_course_tokens( $course_id, self::COURSE_TOKENS ),
			array(
				$this->get_trigger_meta() => 0 === $course_id ? '' : get_the_title( $course_id ),
				'PROGRESS_ID'             => $progress->get_id(),
				'STARTED_AT'              => $started_at instanceof \DateTimeInterface ? $started_at->format( 'Y-m-d H:i:s' ) : (string) $started_at,
				'COMPLETED_AT'            => $completed_at instanceof \DateTimeInterface ? $completed_at->format( 'Y-m-d H:i:s' ) : (string) $completed_at,
			)
		);
	}
}
