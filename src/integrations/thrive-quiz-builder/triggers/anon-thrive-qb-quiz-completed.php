<?php

namespace Uncanny_Automator;

/**
 * Class ANON_THRIVE_QB_QUIZ_COMPLETED
 *
 * @package Uncanny_Automator
 */
class ANON_THRIVE_QB_QUIZ_COMPLETED {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_helper( new Thrive_Quiz_Builder_Helpers() );
		$this->set_integration( 'THRIVE_QB' );
		$this->set_trigger_code( 'TQB_QUIZ_COMPLETED' );
		$this->set_trigger_meta( 'TQB_QUIZ' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		/* Translators: Trigger sentence - Thrive Quiz Builder */
		$this->set_sentence( sprintf( esc_html__( '{{A quiz:%1$s}} is completed', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		/* Translators: Trigger sentence - Thrive Quiz Builder */
		$this->set_readable_sentence( esc_html__( '{{A quiz}} is completed', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->set_action_hook( 'thrive_quizbuilder_quiz_completed' );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();
	}

	/**
	 * @return array
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->get_helper()->get_all_thrive_quizzes(
						array(
							'option_code' => $this->get_trigger_meta(),
							'is_any'      => true,
						)
					),
				),
			)
		);

	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function validate_trigger( ...$args ) {
		list( $quiz_data ) = $args[0];

		if ( isset( $quiz_data['quiz_id'] ) ) {
			return true;
		}

		return false;

	}

	/**
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check quiz_id against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list ( $quiz_data ) = $args[0];

		// Find quiz ID
		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $quiz_data['quiz_id'] ) )
					->format( array( 'intval' ) )
					->get();
	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}

}
