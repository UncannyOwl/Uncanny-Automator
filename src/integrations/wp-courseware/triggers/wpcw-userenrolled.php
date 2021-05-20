<?php

namespace Uncanny_Automator;

/**
 * Class WPCW_USERENROLLED
 * @package Uncanny_Automator
 */
class WPCW_USERENROLLED {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPCW';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPCWUSERENROLLED';
		$this->trigger_meta = 'WPCW_ENROLLCOURSE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {



		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-courseware/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP Courseware */
			'sentence'            => sprintf( esc_attr__( 'A user is enrolled in {{a course:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WP Courseware */
			'select_option_name'  => esc_attr__( 'A user is enrolled in {{a course}}', 'uncanny-automator' ),
			'action'              => 'wpcw_enroll_user',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'wpcw_user_enrolled' ),
			'options'             => [
				Automator()->helpers->recipe->wp_courseware->options->all_wpcw_courses( esc_attr__( 'Course', 'uncanny-automator' ), $this->trigger_meta ),
				Automator()->helpers->recipe->options->number_of_times(),
			],
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $courses_enrolled   AssociatedParentData
	 */
	public function wpcw_user_enrolled( $user_id, $courses_enrolled ) {

		if ( empty( $user_id ) ) {
			return;
		}



		foreach ( $courses_enrolled as $course_key ) {

			$course_detail = WPCW_courses_getCourseDetails( $course_key );

			$args = [
				'code'         => $this->trigger_code,
				'meta'         => $this->trigger_meta,
				'post_id'      => intval( $course_detail->course_post_id ),
				'user_id'      => $user_id,
				'is_signed_in' => true,
			];

			Automator()->maybe_add_trigger_entry( $args );
		}
	}
}
