<?php

namespace Uncanny_Automator;

/**
 * Class LP_LESSONDONE
 * @package Uncanny_Automator
 */
class LP_LESSONDONE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'LP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LPLESSONDONE';
		$this->trigger_meta = 'LPLESSON';
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
			/* translators: Logged-in trigger - LearnPress */
			'sentence'            => sprintf(  esc_attr__( 'A user completes {{a lesson:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnPress */
			'select_option_name'  =>  esc_attr__( 'A user completes {{a lesson}}', 'uncanny-automator' ),
			'action'              => 'learn_press_user_complete_lesson',
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'lp_lesson_completed' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options'             => [
				$uncanny_automator->helpers->recipe->learnpress->options->all_lp_lessons(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $lesson_id
	 * @param $result
	 * @param $user_id
	 */
	public function lp_lesson_completed( $lesson_id, $result, $user_id ) {

		if ( is_null( $lesson_id ) ) {
			return;
		}

		global $uncanny_automator;

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $lesson_id ),
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
