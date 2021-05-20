<?php
/**
 * Contains Quiz Attempt Passed Trigger.
 *
 * @version 2.4.0
 * @since   2.4.0
 */

namespace Uncanny_Automator;

defined( '\ABSPATH' ) || exit;

/**
 * Adds Quiz Attempt as Trigger.
 *
 * @since 2.4.0
 */
class TUTORLMS_QUIZPASSED {

	public static $integration = 'TUTORLMS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		$this->trigger_code = 'TUTORLMSQUIZPASSED';
		$this->trigger_meta = 'TUTORLMSQUIZ';

		// hook into automator.
		$this->define_trigger();
	}

	/**
	 * Registers Quiz Attempt Passed trigger.
	 *
	 * @since 2.4.0
	 */
	public function define_trigger() {

		// global automator object.


		// setup trigger configuration.
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/tutor-lms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - TutorLMS */
			'sentence'            => sprintf( esc_attr__( 'A user passes {{a quiz:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - TutorLMS */
			'select_option_name'  => esc_attr__( 'A user passes {{a quiz}}', 'uncanny-automator' ),
			'action'              => 'tutor_quiz/attempt_ended',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'passed' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options'             => [
				Automator()->helpers->recipe->tutorlms->options->all_tutorlms_quizzes( null, $this->trigger_meta, true ),
				Automator()->helpers->recipe->options->number_of_times(),
			],
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validates Trigger.
	 *
	 * @param $attempt_id Post ID of the attempt
	 *
	 * @since 2.4.0
	 */
	public function passed( $attempt_id ) {

		// get the quiz attempt.
		$attempt = tutor_utils()->get_attempt( $attempt_id );

		// Bail if this not the registered quiz post type
		if ( 'tutor_quiz' !== get_post_type( $attempt->quiz_id ) ) {
			return;
		}

		// bail if the attempt isn't finished yet.
		if ( 'attempt_ended' !== $attempt->attempt_status ) {
			return;
		}



		// bail if they haven't passed.
		if ( ! Automator()->helpers->recipe->tutorlms->options->was_quiz_attempt_successful( $attempt ) ) {
			return;
		}

		// current user.
		$user_id = get_current_user_id();

		// trigger entry args.
		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $attempt->quiz_id,
			'user_id' => $user_id,
		];

		// run trigger.
		Automator()->maybe_add_trigger_entry( $args, true );
	}

}
