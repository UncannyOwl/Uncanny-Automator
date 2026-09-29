<?php

namespace Uncanny_Automator\Integrations\Armember;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class ARMEMBER_MEMBERSHIP_CANCELLED
 *
 * @package Uncanny_Automator
 *
 * @property Armember_Helpers $item_helpers
 */
class ARMEMBER_MEMBERSHIP_CANCELLED extends Trigger {

	/**
	 * Declare the trigger so the engine can register its hook without
	 * constructing the class on every frontend request.
	 *
	 * `arm_cancel_subscription` fires once on every path that actually cancels
	 * a plan: an immediate cancellation, the end-of-term cron, a gateway
	 * notification, the admin member forms. The gateway hook the legacy
	 * trigger also listened to fires a second time for recurring plans and
	 * fires alone on plan changes, where nothing was cancelled.
	 *
	 * @return object
	 */
	public static function definition() {
		return self::new_definition( 'ARM_CANCEL_PLAN', 'ARMEMBER' )
			->trigger_meta( 'ARM_ALL_PLANS' )
			->hook( 'arm_cancel_subscription', 10, 2 );
	}

	/**
	 * Trigger setup.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().
		$this->set_is_pro( false );
		// Cancellations also arrive from cron and payment gateways with nobody
		// logged in; the member is set from the payload in validate().
		$this->set_is_login_required( false );

		// translators: %1$s is the membership plan selector
		$this->set_sentence( sprintf( esc_html_x( 'A user cancels {{a membership plan:%1$s}}', 'ARMember', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user cancels {{a membership plan}}', 'ARMember', 'uncanny-automator' ) );
	}

	/**
	 * Trigger fields.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->get_plan_option_config( $this->get_trigger_meta() ),
		);
	}

	/**
	 * Token definitions.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->tokens()->membership_tokens() );
	}

	/**
	 * Was the selected plan, or any plan, cancelled for a member?
	 *
	 * @param array $trigger
	 * @param array $hook_args ( int $user_id, int $plan_id )
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $plan_id ) = array_pad( $hook_args, 2, null );

		$user_id = absint( $user_id );
		$plan_id = absint( $plan_id );

		if ( 0 === $user_id || 0 === $plan_id ) {
			return false;
		}

		// The member is the subject, not the admin, gateway or cron that cancelled the plan.
		$this->set_user_id( $user_id );

		$selected = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		if ( intval( '-1' ) === intval( $selected ) ) {
			return true;
		}

		return absint( $selected ) === $plan_id;
	}

	/**
	 * Token values for the cancelled plan and its member.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $user_id, $plan_id ) = array_pad( $hook_args, 2, null );

		return $this->item_helpers->tokens()->hydrate_membership_tokens( $user_id, $plan_id );
	}
}
