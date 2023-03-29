<?php

namespace Uncanny_Automator;

/**
 * Class THRIVE_OVATION_TESTIMONIAL_CREATED
 *
 * @package Uncanny_Automator
 */
class THRIVE_OVATION_TESTIMONIAL_CREATED {

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
		$this->set_integration( 'THRIVE_OVATION' );
		$this->set_trigger_code( 'TVO_TESTIMONIAL_SUBMITTED' );
		$this->set_trigger_meta( 'TVO_TESTIMONIALS' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		/* Translators: Trigger sentence - Thrive leads */
		$this->set_sentence( esc_html__( 'A testimonial is submitted', 'uncanny-automator' ) );

		/* Translators: Trigger sentence - Thrive leads */
		$this->set_readable_sentence( esc_html__( 'A testimonial is submitted', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->set_action_hook( 'thrive_ovation_testimonial_submit' );
		$this->set_action_args_count( 2 );
		$this->register_trigger();
	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function validate_trigger( ...$args ) {
		list( $testimonial_data, $user_data ) = $args[0];

		if ( isset( $testimonial_data ) ) {
			return true;
		}

		return false;

	}

	/**
	 * @param $args
	 *
	 * @return void
	 */
	public function prepare_to_run( $args ) {
		$this->set_conditional_trigger( false );
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
