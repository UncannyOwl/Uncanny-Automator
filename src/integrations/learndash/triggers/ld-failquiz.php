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

		// Migrate saved add_action data.
		add_action(
			'admin_init',
			function () {
				Learndash_Helpers::migrate_trigger_learndash_quiz_submitted_action_data( $this->trigger_code );
			},
			99
		);

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
			'action'              => array(
				'learndash_quiz_submitted',
				'learndash_essay_quiz_data_updated',
			),
			'priority'            => 15,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'validate_trigger' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->learndash->options->all_ld_quiz(),
					Automator()->helpers->recipe->options->number_of_times(),
				),
			)
		);
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $args - Params added to hook.
	 *
	 * @return void
	 */
	public function validate_trigger( ...$args ) {
		$user    = false;
		$quiz    = false;
		$helpers = new Learndash_Helpers( false );

		if ( did_action( 'learndash_quiz_submitted' ) ) {

			list( $data, $current_user ) = $args;
			$passed                      = $helpers->submitted_quiz_pased( $data );
			if ( is_wp_error( $passed ) || ! empty( $passed ) ) {
				return;
			}

			$quiz = $data['quiz'];
			$user = $current_user;

		} elseif ( did_action( 'learndash_essay_quiz_data_updated' ) ) {
			list ( $pro_quiz_id, $question_id, $updated_scoring, $essay ) = $args;
			$passed                                                       = $helpers->graded_quiz_passed( $essay, $pro_quiz_id );
			if ( is_wp_error( $passed ) || ! empty( $passed ) ) {
				return;
			}

			$quiz = get_post_meta( $essay->ID, 'quiz_post_id', true );
			$user = get_user_by( 'id', $essay->post_author );
		}

		// Process failed trigger.
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
