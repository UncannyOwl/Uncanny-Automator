<?php

namespace Uncanny_Automator;

/**
 * Class LD_COURSEDONE
 * @package Uncanny_Automator
 */
class LD_COURSEDONE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'LD';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'COURSEDONE';
		$this->trigger_meta = 'LDCOURSE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name(),
			'support_link'        => $uncanny_automator->get_author_support_link(),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnDash */
			'sentence'            => sprintf(  esc_attr__( 'A user completes {{a course:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  =>  esc_attr__( 'A user completes {{a course}}', 'uncanny-automator' ),
			'action'              => 'learndash_course_completed',
			'priority'            => 20,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'course_done' ),
			'options'             => [

				$uncanny_automator->helpers->recipe->learndash->options->all_ld_courses(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function course_done( $data ) {

		global $uncanny_automator;

		if ( empty( $data ) ) {
			return;
		}

		$user   = $data['user'];
		$course = $data['course'];

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $course->ID,
			'user_id' => $user->ID,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
