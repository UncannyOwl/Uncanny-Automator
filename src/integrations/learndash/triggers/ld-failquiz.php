<?php

namespace Uncanny_Automator;

/**
 * Class LD_FAILQUIZ
 *
 * @package Uncanny_Automator
 */
class LD_FAILQUIZ {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LD';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LD_FAILQUIZ';
		$this->trigger_meta = 'LDQUIZ';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/learndash/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnDash */
			'sentence'            => sprintf( esc_attr__( 'A user fails {{a quiz:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  => esc_attr__( 'A user fails {{a quiz}}', 'uncanny-automator' ),
			'action'              => 'learndash_quiz_submitted',
			'priority'            => 15,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'quiz_submitted' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options'             => array(
				Automator()->helpers->recipe->learndash->options->all_ld_quiz(),
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
	public function quiz_submitted( $data, $current_user ) {
		
		if ( empty( $data ) ) {
			return;
		}

		$q_status = $data['pass'];

		if ( 0 === (int) $q_status ) {

			$user    = $current_user;
			$quiz    = $data['quiz'];
			$post_id = is_object( $quiz ) ? $quiz->ID : $quiz;

			if ( empty( $user ) ) {
				$user = wp_get_current_user();
			}

			$args = array(
				'code'    => $this->trigger_code,
				'meta'    => $this->trigger_meta,
				'post_id' => (int) $post_id,
				'user_id' => $user->ID,
			);

			Automator()->maybe_add_trigger_entry( $args );
		}
	}
}
