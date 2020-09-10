<?php

namespace Uncanny_Automator;

/**
 * Class WPCW_COURSECOMPLETED
 * @package Uncanny_Automator
 */
class WPCW_COURSECOMPLETED {

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
		$this->trigger_code = 'WPCWCOURSECOMPLETED';
		$this->trigger_meta = 'WPCW_COURSE';
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
			/* translators: Logged-in trigger - WP Courseware */
			'sentence'            => sprintf(  esc_attr__( 'A user completes {{a course:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WP Courseware */
			'select_option_name'  =>  esc_attr__( 'A user completes {{a course}}', 'uncanny-automator' ),
			'action'              => 'wpcw_user_completed_course',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'wpcw_course_completed' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->wp_courseware->options->all_wpcw_courses(),
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
	 * @param $unit_id
	 * @param $parent   AssociatedParentData
	 */
	public function wpcw_course_completed( $user_id, $unit_id, $parent ) {

		if ( empty( $user_id ) ) {
			return;
		}

		global $uncanny_automator;

		$course_id = $parent->course_post_id;

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $course_id ),
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
