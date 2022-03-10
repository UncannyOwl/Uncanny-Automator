<?php

namespace Uncanny_Automator;

use memberpress\courses as base;

/**
 * Class MPC_COURSEDONE
 *
 * @package Uncanny_Automator
 */
class MPC_COURSEDONE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MPC';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'MPCOURSEDONE';
		$this->trigger_meta = 'MPCOURSE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/memberpress-courses/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Memberpress */
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a course:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Memberpress */
			'select_option_name'  => esc_attr__( 'A user completes {{a course}}', 'uncanny-automator' ),
			'action'              => base\SLUG_KEY . '_completed_course',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'course_done' ),
			'options'             => array(
				Automator()->helpers->recipe->memberpress_courses->options->all_mp_courses(),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);
		Automator()->register->trigger( $trigger );
		
		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function course_done( $data ) {

		if ( empty( $data ) ) {
			return;
		}

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $data->course_id,
			'user_id' => $data->user_id,
		);

		Automator()->maybe_add_trigger_entry( $args );
	}
}

