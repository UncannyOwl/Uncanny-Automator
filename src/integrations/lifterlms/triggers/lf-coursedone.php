<?php

namespace Uncanny_Automator;

/**
 * Class LF_COURSEDONE
 * @package uncanny_automator
 */
class LF_COURSEDONE {

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
		$this->trigger_code = 'LFCOURSEDONE';
		$this->trigger_meta = 'LFCOURSE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LifterLMS */
			'sentence'            => sprintf( __( 'A user completes {{a course:%1$s}} {{a number of:%2$s}} times', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LifterLMS */
			'select_option_name'  => __( 'A user completes {{a course}}', 'uncanny-automator' ),
			'action'              => 'lifterlms_course_completed',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'lf_course_done' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->lifterlms->options->all_lf_courses(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $course_id
	 */
	public function lf_course_done( $user_id, $course_id ) {

		if ( empty( $user_id ) ) {
			return;
		}

		global $uncanny_automator;

		$args = [
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'post_id'        => intval( $course_id ),
			'user_id'        => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
