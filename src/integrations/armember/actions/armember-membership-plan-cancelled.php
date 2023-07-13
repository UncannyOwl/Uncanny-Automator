<?php

namespace Uncanny_Automator;

class ARMEMBER_MEMBERSHIP_PLAN_CANCELLED {

	use Recipe\Actions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->setup_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		$this->set_helpers( new Armember_Helpers() );
		$this->set_integration( 'ARMEMBER' );
		$this->set_action_code( 'ARM_PLAN_CANCELED' );
		$this->set_action_meta( 'ARM_PLANS' );
		$this->set_requires_user( true );

		/* translators: Action - ARMember */
		$this->set_sentence( sprintf( esc_attr__( "Cancel the user's {{membership plan:%1\$s}}", 'uncanny-automator-pro' ), $this->get_action_meta() ) );

		/* translators: Action - ARMember */
		$this->set_readable_sentence( esc_attr__( "Cancel the user's {{membership plan}}", 'uncanny-automator-pro' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->get_helpers()->get_all_plans(
						array(
							'option_code'           => $this->get_action_meta(),
							'supports_custom_value' => true,
						)
					),
				),
			)
		);

	}

	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$plan_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';

		if ( empty( $plan_id ) ) {
			$action_data['complete_with_errors'] = true;
			$message                             = __( 'Plan does not exist.', 'uncanny-automator-pro' );
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $message );

			return;
		}

		global $arm_subscription_plans;
		do_action( 'arm_before_update_user_subscription', $user_id, '0' );
		$arm_subscription_plans->arm_add_membership_history( $user_id, $plan_id, 'cancel_subscription' );
		do_action( 'arm_cancel_subscription', $user_id, $plan_id );
		$arm_subscription_plans->arm_clear_user_plan_detail( $user_id, $plan_id );
		update_user_meta( $user_id, 'arm_secondary_status', 6 );

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}

}
