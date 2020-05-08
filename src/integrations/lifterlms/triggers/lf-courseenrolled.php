<?php

namespace Uncanny_Automator;

/**
 * Class LF_COURSEENROLLED
 * @package uncanny_automator
 */
class LF_COURSEENROLLED {

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
		$this->trigger_code = 'LFCOURSEENROLLED';
		$this->trigger_meta = 'LFCOURSEEN';
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
			'sentence'            => sprintf( __( 'A user is enrolled in {{a course:%1$s}} {{a number of:%2$s}} times', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LifterLMS */
			'select_option_name'  => __( 'A user is enrolled in {{a course}}', 'uncanny-automator' ),
			'action'              => 'llms_user_enrolled_in_course',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'lf_course_enrolled' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->lifterlms->options->all_lf_courses( __( 'Course', 'uncanny-automator' ), $this->trigger_meta ),
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
	 * @param $product_id
	 */
	public function lf_course_enrolled( $user_id, $product_id ) {

		if ( empty( $user_id ) ) {
			return;
		}
		global $uncanny_automator;

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $product_id,
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
