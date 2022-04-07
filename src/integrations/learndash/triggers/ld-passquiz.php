<?php

namespace Uncanny_Automator;

/**
 * Class LD_PASSQUIZ
 *
 * @package Uncanny_Automator
 */
class LD_PASSQUIZ {

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
		$this->trigger_code = 'LD_PASSQUIZ';
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
			'sentence'            => sprintf( esc_attr__( 'A user passes {{a quiz:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  => esc_attr__( 'A user passes {{a quiz}}', 'uncanny-automator' ),
			'action'              => 'learndash_quiz_completed',
			'priority'            => 15,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'learndash_quiz_completed' ),
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
	public function learndash_quiz_completed( $data, $current_user ) {

		if ( empty( $data ) ) {
			return;
		}

		$q_status = $data['pass'];

		if ( 0 !== (int) $q_status ) {

			$user    = $current_user;
			$quiz    = $data['quiz'];
			$post_id = is_object( $quiz ) ? $quiz->ID : $quiz;

			if ( empty( $user ) ) {
				$user = wp_get_current_user();
			}

			$pass_args = array(
				'code'    => $this->trigger_code,
				'meta'    => $this->trigger_meta,
				'post_id' => (int) $post_id,
				'user_id' => $user->ID,
			);

			$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

			if ( $args ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {
						$insert = array(
							'user_id'        => (int) $result['args']['user_id'],
							'trigger_id'     => (int) $result['args']['trigger_id'],
							'trigger_log_id' => (int) $result['args']['trigger_log_id'],
							'run_number'     => (int) $result['args']['run_number'],
						);

						$insert['meta_key']   = 'quiz_id';
						$insert['meta_value'] = $post_id;
						Automator()->insert_trigger_meta( $insert );

						Automator()->maybe_trigger_complete( $result['args'] );

					}
				}
			}
		}
	}
}
