<?php

namespace Uncanny_Automator;

/**
 * Class WPLMS_COURSESTARTED
 * @package Uncanny_Automator
 */
class WPLMS_COURSESTARTED {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPLMS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPLMSCOURSESTARTED';
		$this->trigger_meta = 'WPLMS_COURSESTART';
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
			/* translators: Logged-in trigger - WP LMS */
			'sentence'            => sprintf(  esc_attr__( 'A user starts {{a course:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WP LMS */
			'select_option_name'  =>  esc_attr__( 'A user starts {{a course}}', 'uncanny-automator' ),
			'action'              => 'wplms_start_course',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'wplms_course_started' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->wplms->options->all_wplms_courses(  esc_attr__( 'Course', 'uncanny-automator' ), $this->trigger_meta ),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param integer $course_id
	 * @param null $marks
	 * @param integer $user_id
	 */
	public function wplms_course_started( $course_id, $marks, $user_id ) {

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( empty( $user_id ) ) {
			return;
		}

		global $uncanny_automator;

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $course_id ),
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
