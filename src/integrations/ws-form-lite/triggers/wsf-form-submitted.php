<?php

namespace Uncanny_Automator;

/**
 * Class WSF_FORM_SUBMITTED
 *
 * @package Uncanny_Automator
 */
class WSF_FORM_SUBMITTED {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->set_helper( new Ws_Form_Lite_Helpers() );
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'WSFORMLITE' );
		$this->set_trigger_code( 'WSFORM_FROM_SUBMITTED' );
		$this->set_trigger_meta( 'WSFORM_FORMS' );
		$this->set_is_login_required( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/ws-form/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence */
			sprintf( esc_html__( 'A user submits {{a form:%1$s}}', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->set_action_hook( 'wsf_submit_post_complete' );
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
					$this->get_helper()->get_ws_all_forms( null, $this->get_trigger_meta() ),
				),
			)
		);

	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		list( $ws_form_submitted ) = array_shift( $args );

		if ( empty( $ws_form_submitted->form_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Prepare to run the trigger.
	 *
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check contact status against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $ws_form_submitted ) = $args[0];

		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $ws_form_submitted->form_id ) )
					->format( array( 'intval' ) )
					->get();
	}

}
