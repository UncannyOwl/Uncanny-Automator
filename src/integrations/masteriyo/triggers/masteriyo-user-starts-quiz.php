<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Masteriyo_User_Starts_Quiz
 *
 * The attempt insert is the event. An admin completing a quiz the learner never
 * started inserts an attempt already `attempt_ended` on the same hook, which is
 * not a start and is ignored.
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 *
 * @property Masteriyo_Helpers $item_helpers
 */
class Masteriyo_User_Starts_Quiz extends Trigger {

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
		return self::new_definition( 'MASTERIYO_USER_STARTS_QUIZ', 'MASTERIYO' )
			->trigger_meta( 'MASTERIYO_QUIZ' )
			->hook( 'masteriyo_create_quiz_attempt', 10, 2 );
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
				/* translators: 1: Quiz selector */
				esc_html_x( 'A user starts {{a quiz:%1$s}}', 'Masteriyo LMS', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user starts {{a quiz}}', 'Masteriyo LMS', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array( $this->item_helpers->get_quiz_field( $this->get_trigger_meta() ) );
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
			$token_class->quiz_tokens(),
			array(
				array(
					'tokenId'   => 'ATTEMPT_ID',
					'tokenName' => esc_html_x( 'Attempt ID', 'Masteriyo LMS', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'ATTEMPT_STARTED_AT',
					'tokenName' => esc_html_x( 'Attempt started at', 'Masteriyo LMS', 'uncanny-automator' ),
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

		$attempt = $hook_args[1];

		if ( 'attempt_started' !== $attempt->get_attempt_status() ) {
			return false;
		}

		$user_id = absint( $attempt->get_user_id() );

		if ( 0 === $user_id ) {
			return false;
		}

		$selected = $this->item_helpers->get_selected_value( $trigger, $this->get_trigger_meta() );

		if ( ! $this->item_helpers->matches_selection( $selected, $attempt->get_quiz_id() ) ) {
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

		$attempt = $hook_args[1];
		$quiz_id = absint( $attempt->get_quiz_id() );

		$token_class = new Masteriyo_Tokens();

		$started_at = $attempt->get_attempt_started_at();

		return array_merge(
			$token_class->hydrate_course_tokens( $attempt->get_course_id(), self::COURSE_TOKENS ),
			$token_class->hydrate_quiz_tokens( $quiz_id ),
			array(
				$this->get_trigger_meta() => 0 === $quiz_id ? '' : get_the_title( $quiz_id ),
				'ATTEMPT_ID'              => $attempt->get_id(),
				'ATTEMPT_STARTED_AT'      => $started_at instanceof \DateTimeInterface ? $started_at->format( 'Y-m-d H:i:s' ) : (string) $started_at,
			)
		);
	}
}
