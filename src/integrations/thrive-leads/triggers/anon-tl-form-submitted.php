<?php

namespace Uncanny_Automator;

/**
 * Class ANON_TL_FORM_SUBMITTED
 *
 * @package Uncanny_Automator
 */
class ANON_TL_FORM_SUBMITTED {

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
		$this->set_helper( new Thrive_Leads_Helpers() );
		$this->set_integration( 'THRIVELEADS' );
		$this->set_trigger_code( 'TL_ANON_SUBMIT_FORM' );
		$this->set_trigger_meta( 'TL_FORMS' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		/* Translators: Trigger sentence - Thrive leads */
		$this->set_sentence( sprintf( esc_html__( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		/* Translators: Trigger sentence - Thrive leads */
		$this->set_readable_sentence( esc_html__( '{{A form}} is submitted', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->set_action_hook( 'tcb_api_form_submit' );
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
					$this->get_helper()->get_all_thrive_lead_forms(
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

		list( $submit_data ) = $args[0];
		$is_valid            = false;
		if ( isset( $submit_data ) ) {
			$is_valid = true;
		}

		return $is_valid;

	}

	/**
	 * @param $args
	 *
	 * @return void
	 */
	public function prepare_to_run( $args ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check form_id against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list ( $submit_data ) = $args[0];

		// Find form ID
		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $submit_data['thrive_leads']['tl_data']['form_type_id'] ) )
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
