<?php

namespace Uncanny_Automator;

/**
 * Class ARMEMBER_CANCEL_MEMBERSHIP
 *
 * @package Uncanny_Automator
 */
class ARMEMBER_CANCEL_MEMBERSHIP {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->set_helper( new Armember_Helpers() );
		$this->setup_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'ARMEMBER' );
		$this->set_trigger_code( 'ARM_CANCEL_PLAN' );
		$this->set_trigger_meta( 'ARM_ALL_PLANS' );
		$this->set_is_login_required( true );
		$this->set_action_args_count( 2 );
		/* Translators: Trigger sentence - ARMember Lite - Membership Plugin */
		$this->set_sentence( sprintf( esc_html__( 'A user cancels {{a membership plan:%1$s}}', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		/* Translators: Trigger sentence - ARMember Lite - Membership Plugin */
		$this->set_readable_sentence( esc_html__( 'A user cancels {{a membership plan}}', 'uncanny-automator' ) ); // Non-active state sentence to show
		$this->set_action_hook( array( 'arm_cancel_subscription_gateway_action', 'arm_cancel_subscription' ) );
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
					$this->get_helper()->get_all_plans(
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
		list( $user_id, $plan_id ) = $args[0];
		if ( isset( $user_id ) ) {
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
	 * Check email subject against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $user_id, $plan_id ) = $args[0];

		// Find plan ID
		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $plan_id ) )
					->format( array( 'intval' ) )
					->get();
	}

}
