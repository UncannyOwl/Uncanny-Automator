<?php

namespace Uncanny_Automator;

/**
 * Class WPP_POLLSUBMIT
 *
 * @package Uncanny_Automator
 */
class WPP_POLLSUBMIT {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPP';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPPOLLSUBMIT';
		$this->trigger_meta = 'WPPOLL';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $wpdb;

		// Get Poll Questions
		$questions = $wpdb->get_results( "SELECT pollq_id, pollq_question FROM $wpdb->pollsq ORDER BY pollq_id DESC" );

		$questions_options = array();

		$questions_options[0] = __( 'Any poll', 'uncanny-automator' );

		foreach ( $questions as $question ) {
			$title = $question->pollq_question;

			if ( empty( $title ) ) {
				$title = sprintf( __( 'ID: %s (no title)', 'uncanny-automator' ), $question->pollq_id );
			}

			$questions_options[ $question->pollq_id ] = $title;
		}

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-polls/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnDash */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a poll:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  => esc_attr__( 'A user submits {{a poll}}', 'uncanny-automator' ),
			'action'              => 'wp_polls_vote_poll_success',
			'priority'            => 1,
			'accepted_args'       => 0,
			'validation_function' => array( $this, 'poll_success' ),
			'options'             => array(
				Automator()->helpers->recipe->field->select_field(
					$this->trigger_meta,
					esc_attr__( 'Poll', 'uncanny-automator' ),
					$questions_options,
					null,
					'',
					false,
					array(
						$this->trigger_meta                   => __( 'Poll question', 'uncanny-automator' ),
						$this->trigger_meta . '_ANSWERS'      => __( 'Poll answers', 'uncanny-automator' ),
						$this->trigger_meta . '_START'        => __( 'Poll start date', 'uncanny-automator' ),
						$this->trigger_meta . '_END'          => __( 'Poll end date', 'uncanny-automator' ),
						$this->trigger_meta . '_WPPOLLANSWER' => __( 'Poll selected answer(s)', 'uncanny-automator' ),
					)
				),
			),
			'options_group'       => array(),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 *
	 */
	public function poll_success() {
		if ( ! automator_filter_has_var( 'action', INPUT_POST ) ) {
			return;
		}
		if ( 'polls' !== sanitize_key( automator_filter_input( 'action', INPUT_POST ) ) ) {
			return;
		}

		// Get Poll ID
		$poll_id = automator_filter_has_var( 'poll_id', INPUT_POST ) ? (int) sanitize_key( automator_filter_input( 'poll_id', INPUT_POST ) ) : 0;

		// Ensure Poll ID Is Valid
		if ( $poll_id === 0 ) {
			return;
		}

		// Verify Referer
		if ( ! check_ajax_referer( 'poll_' . $poll_id . '-nonce', 'poll_' . $poll_id . '_nonce', false ) ) {
			return;
		}

		$view = sanitize_key( automator_filter_input( 'view', INPUT_POST ) );
		if ( 'process' !== $view ) {
			return;
		}
		$poll_aid_array   = array_unique( array_map( 'intval', array_map( 'sanitize_key', explode( ',', automator_filter_input( "poll_$poll_id", INPUT_POST ) ) ) ) );
		$recipes          = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_poll_id = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if (
					isset( $required_poll_id[ $recipe_id ] )
					&& isset( $required_poll_id[ $recipe_id ][ $trigger_id ] )
				) {
					if (
						0 === (int) (string) $required_poll_id[ $recipe_id ][ $trigger_id ] ||
						(string) $poll_id === (string) $required_poll_id[ $recipe_id ][ $trigger_id ]
					) {

						$pass_args = array(
							'code'           => $this->trigger_code,
							'meta'           => $this->trigger_meta,
							'ignore_post_id' => true,
							'user_id'        => get_current_user_id(),
						);

						$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

						if ( isset( $args ) ) {
							foreach ( $args as $result ) {
								if ( true === $result['result'] ) {

									$trigger_meta = array(
										'user_id'        => get_current_user_id(),
										'trigger_id'     => $result['args']['trigger_id'],
										'trigger_log_id' => $result['args']['get_trigger_id'],
										'run_number'     => $result['args']['run_number'],
									);

									$trigger_meta['meta_key']   = $this->trigger_meta;
									$trigger_meta['meta_value'] = $poll_id;
									Automator()->insert_trigger_meta( $trigger_meta );

									$trigger_meta['meta_key']   = 'WPPOLLANSWER';
									$trigger_meta['meta_value'] = maybe_serialize( $poll_aid_array );
									Automator()->insert_trigger_meta( $trigger_meta );

									Automator()->maybe_trigger_complete( $result['args'] );
								}
							}
						}
					}
				}
			}
		}


	}
}
