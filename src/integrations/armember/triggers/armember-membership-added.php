<?php

namespace Uncanny_Automator\Integrations\Armember;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class ARMEMBER_MEMBERSHIP_ADDED
 *
 * @package Uncanny_Automator
 *
 * @property Armember_Helpers $item_helpers
 */
class ARMEMBER_MEMBERSHIP_ADDED extends Trigger {

	/**
	 * Declare the trigger so the engine can register its hooks without
	 * constructing the class on every frontend request.
	 *
	 * ARMember fires one hook when a plan is assigned through a purchase or
	 * its own API and another when an admin assigns it from the members
	 * screens; both are the member being added.
	 *
	 * @return object
	 */
	public static function definition() {
		return self::new_definition( 'ARM_MEMBERSHIP_ADDED', 'ARMEMBER' )
			->trigger_meta( 'ARM_ALL_PLANS' )
			->hook( 'arm_after_user_plan_change', 10, 2 )
			->hook( 'arm_after_user_plan_change_by_admin', 10, 2 );
	}

	/**
	 * Trigger setup.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hooks are auto-applied from definition().
		$this->set_is_pro( false );

		// translators: %1$s is the membership plan selector
		$this->set_sentence( sprintf( esc_html_x( 'A user is added to {{a membership plan:%1$s}}', 'ARMember', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user is added to {{a membership plan}}', 'ARMember', 'uncanny-automator' ) );
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
	 * Was the selected plan, or any plan, assigned to a member?
	 *
	 * @param array $trigger
	 * @param array $hook_args ( int $user_id, int|string|array $plans )
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $plans ) = array_pad( $hook_args, 2, null );

		$user_id  = absint( $user_id );
		$plan_ids = $this->item_helpers->normalize_plan_ids( $plans );

		if ( 0 === $user_id || empty( $plan_ids ) ) {
			return false;
		}

		// The member is the subject, not the admin or gateway session that assigned the plan.
		$this->set_user_id( $user_id );

		$selected = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		if ( intval( '-1' ) === intval( $selected ) ) {
			return true;
		}

		return in_array( absint( $selected ), $plan_ids, true );
	}

	/**
	 * Token values for the plan the recipe selected, or the first assigned
	 * plan when it said "Any".
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $user_id, $plans ) = array_pad( $hook_args, 2, null );

		$plan_ids = $this->item_helpers->normalize_plan_ids( $plans );
		$selected = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';
		$selected = intval( '-1' ) === intval( $selected ) ? 0 : absint( $selected );
		$plan_id  = ( $selected > 0 && in_array( $selected, $plan_ids, true ) ) ? $selected : (int) reset( $plan_ids );

		return $this->item_helpers->tokens()->hydrate_membership_tokens( $user_id, $plan_id );
	}
}
