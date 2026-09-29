<?php

namespace Uncanny_Automator\Integrations\Armember;

use Uncanny_Automator\Recipe\Action;

/**
 * Class ARMEMBER_MEMBERSHIP_PLAN_CANCELLED
 *
 * @package Uncanny_Automator
 *
 * @property Armember_Helpers $item_helpers
 */
class ARMEMBER_MEMBERSHIP_PLAN_CANCELLED extends Action {

	/**
	 * Action setup.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ARMEMBER' );
		$this->set_action_code( 'ARM_PLAN_CANCELED' );
		$this->set_action_meta( 'ARM_PLANS' );
		$this->set_is_pro( false );
		$this->set_requires_user( true );

		// translators: %1$s is the membership plan selector
		$this->set_sentence( sprintf( esc_html_x( "Cancel the user's {{membership plan:%1\$s}}", 'ARMember', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( "Cancel the user's {{membership plan}}", 'ARMember', 'uncanny-automator' ) );
	}

	/**
	 * Action fields.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->get_plan_option_config( $this->get_action_meta(), 'plans_strict', true ),
		);
	}

	/**
	 * Cancel the plan the way ARMember's own cancel flow does: history entry,
	 * the cancel hook, the plan detail cleared, secondary status "cancelled".
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$plan_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $plan_id ) {
			$this->add_log_error( esc_html_x( 'Plan does not exist.', 'ARMember', 'uncanny-automator' ) );
			return false;
		}

		$plans = $this->item_helpers->subscription_plans();

		if ( null === $plans ) {
			$this->add_log_error( esc_html_x( 'ARMember is not available.', 'ARMember', 'uncanny-automator' ) );
			return false;
		}

		// ARMember stores and compares plan ids as strings throughout.
		$plan_arg = (string) $plan_id;

		do_action( 'arm_before_update_user_subscription', $user_id, '0' );
		$plans->arm_add_membership_history( $user_id, $plan_arg, 'cancel_subscription' );
		do_action( 'arm_cancel_subscription', $user_id, $plan_arg );
		$plans->arm_clear_user_plan_detail( $user_id, $plan_arg );
		update_user_meta( $user_id, 'arm_secondary_status', 6 );

		return true;
	}
}
