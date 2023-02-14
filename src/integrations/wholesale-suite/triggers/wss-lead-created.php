<?php

namespace Uncanny_Automator;

/**
 * Class WSS_LEAD_CREATED
 *
 * @package Uncanny_Automator
 */
class WSS_LEAD_CREATED {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		if ( ! function_exists( 'wwlc_check_plugin_dependencies' ) ) {
			return;
		}
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'WHOLESALESUITE' );
		$this->set_trigger_code( 'WSS_LEAD_CREATED' );
		$this->set_trigger_meta( 'WSS_LEAD_CREATED_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/wholesale-suite/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence */
			esc_html__( 'A wholesale lead is created', 'uncanny-automator' )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A wholesale lead is created', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->set_action_hook( array( 'wwlc_action_after_create_user', 'wwlc_action_after_approve_user' ) );
		$this->register_trigger();
	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		list( $new_lead, $email ) = array_shift( $args );

		if ( ! is_object( $new_lead ) ) {
			return false;
		}

		$this->set_user_id( $new_lead->ID );
		$this->set_is_signed_in( true );

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
		$this->set_conditional_trigger( false );
	}

}
