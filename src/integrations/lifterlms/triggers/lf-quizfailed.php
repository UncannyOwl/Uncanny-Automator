<?php

namespace Uncanny_Automator;

/**
 * Class LF_QUIZFAILED
 * @package Uncanny_Automator
 */
class LF_QUIZFAILED {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'LF';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LFQUIZFAILED';
		$this->trigger_meta = 'LFQUIZ';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/lifterlms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LifterLMS */
			'sentence'            => sprintf( esc_attr__( 'A user fails {{a/any quiz:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LifterLMS */
			'select_option_name'  => esc_attr__( 'A user fails {{a quiz}}', 'uncanny-automator' ),
			'action'              => 'lifterlms_quiz_failed',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'lf_quiz_failed' ),
			'options'             => array(
				Automator()->helpers->recipe->lifterlms->options->all_lf_quizs( null, $this->trigger_meta ),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param string $user_id .
	 * @param string $quiz_id .
	 * @param object $quiz_obj .
	 */
	public function lf_quiz_failed( $user_id, $quiz_id, $quiz_obj ) {

		if ( empty( $user_id ) ) {
			return;
		}

		$args = array(
			'code'         => $this->trigger_code,
			'meta'         => $this->trigger_meta,
			'post_id'      => intval( $quiz_id ),
			'user_id'      => $user_id,
			'is_signed_in' => true,
		);

		Automator()->maybe_add_trigger_entry( $args );
	}
}
