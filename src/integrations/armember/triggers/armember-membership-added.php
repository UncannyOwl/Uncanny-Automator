<?php

namespace Uncanny_Automator;

/**
 * Class ARMEMBER_MEMBERSHIP_ADDED
 * @package Uncanny_Automator
 */
class ARMEMBER_MEMBERSHIP_ADDED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->set_helper( new Armember_Helpers() );
		$this->set_integration( 'ARMEMBER' );
		$this->set_trigger_code( 'ARM_MEMBERSHIP_ADDED' );
		$this->set_trigger_meta( 'ARM_ALL_PLANS' );
		// translators: ARMember - Membership plan
		$this->set_sentence( sprintf( esc_attr_x( 'A user is added to {{a membership plan:%1$s}}', 'ARMember', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A user is added to {{a membership plan}}', 'ARMember', 'uncanny-automator' ) );
		$this->add_action( array( 'arm_after_user_plan_change', 'arm_after_user_plan_change_by_admin' ), 10, 2 );
	}

	/**
	 * options
	 *
	 * The method to display a default option group
	 *
	 * @return array
	 */
	public function options() {
		$all_plans_data = $this->get_helper()->get_all_plans(
			array(
				'option_code' => $this->get_trigger_meta(),
				'is_any'      => true,
			)
		);
		$all_plans      = array();
		foreach ( $all_plans_data['options'] as $key => $option ) {
			$all_plans[] = array(
				'text'  => $option,
				'value' => $key,
			);
		}

		return array(
			array(
				'input_type'      => 'select',
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Membership plan', 'ARMember', 'uncanny-automator' ),
				'required'        => true,
				'options'         => $all_plans,
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$trigger_tokens = array(
			array(
				'tokenId'   => 'ARM_MEMBERSHIP_PLAN_ID',
				'tokenName' => esc_html_x( 'Plan ID', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ARM_MEMBERSHIP_PLAN',
				'tokenName' => esc_html_x( 'Membership plan', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBERSHIP_TYPE',
				'tokenName' => esc_html_x( 'Membership type', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_USERNAME',
				'tokenName' => esc_html_x( 'Member username', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_EMAIL',
				'tokenName' => esc_html_x( 'Member email', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_FIRST_NAME',
				'tokenName' => esc_html_x( 'Member first name', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_LAST_NAME',
				'tokenName' => esc_html_x( 'Member last name', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_ROLE',
				'tokenName' => esc_html_x( 'Member role', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_STATUS',
				'tokenName' => esc_html_x( 'Member status', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_JOINED_DATE',
				'tokenName' => esc_html_x( 'Member joined date', 'Armember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $tokens, $trigger_tokens );
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args ) ) {
			return false;
		}

		$user_id = $hook_args[0];
		$plan_id = $hook_args[1];

		$selected_plan_id = $trigger['meta'][ $this->get_trigger_meta() ];
		$this->set_user_id( $user_id );

		return ( intval( '-1' ) === intval( $selected_plan_id ) ) || ( $selected_plan_id === $plan_id );
	}

	/**
	 * @param $completed_trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		list( $member_id, $plan_id )             = $hook_args;
		$armember_tokens                         = new Armember_Tokens();
		list( $arm_members, $arm_subscriptions ) = $armember_tokens->get_arm_classes();
		$plan                                    = $arm_subscriptions->arm_get_subscription_plan( $plan_id, 'arm_subscription_plan_name,arm_subscription_plan_options' );
		$member                                  = $arm_members->arm_get_member_detail( $member_id );
		$plan_type                               = isset( $plan['arm_subscription_plan_options']['access_type'] ) ? ucfirst( $plan['arm_subscription_plan_options']['access_type'] ) . ' - ' . ucfirst( str_replace( '_', ' ', $plan['arm_subscription_plan_options']['payment_type'] ) ) : '';
		$token_values                            = array(
			'ARM_MEMBERSHIP_PLAN_ID' => $plan_id,
			'ARM_MEMBERSHIP_PLAN'    => $plan['arm_subscription_plan_name'],
			'ARM_MEMBERSHIP_TYPE'    => ! empty( $plan_type ) ? $plan_type : $plan['arm_subscription_plan_options']['pricetext'],
			'ARM_MEMBER_USERNAME'    => $member->user_login,
			'ARM_MEMBER_EMAIL'       => $member->user_email,
			'ARM_MEMBER_FIRST_NAME'  => $member->first_name,
			'ARM_MEMBER_LAST_NAME'   => $member->last_name,
			'ARM_MEMBER_ROLE'        => join( ', ', $member->roles ),
			'ARM_MEMBER_JOINED_DATE' => wp_date( 'F j, Y', strtotime( $member->user_registered ) ),
			'ARM_MEMBER_STATUS'      => $arm_members->armGetMemberStatusText( $member_id ),
		);

		return $token_values;
	}
}
