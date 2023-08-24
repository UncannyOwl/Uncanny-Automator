<?php

namespace Uncanny_Automator;

/**
 * Class Armember_Tokens
 *
 * @package Uncanny_Automator
 */
class Armember_Tokens {

	/**
	 * @var \ARM_subscription_plans|\ARM_subscription_plans_Lite|string
	 */
	private $armember_subscription_class = '';
	/**
	 * @var \ARM_members|\ARM_members_Lite|string
	 */
	private $armember_class = '';

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		// If LITE version is active
		if ( defined( 'MEMBERSHIPLITE_DIR_NAME' ) && ! defined( 'MEMBERSHIP_DIR_NAME' ) ) {
			$this->armember_subscription_class = new \ARM_subscription_plans_Lite();
			$this->armember_class              = new \ARM_members_Lite();
		}
		// If Pro version is active
		if ( defined( 'MEMBERSHIP_DIR_NAME' ) ) {
			$this->armember_subscription_class = new \ARM_subscription_plans();
			$this->armember_class              = new \ARM_members();
		}
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_armember_tokens', array( $this, 'armember_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_armember_tokens' ), 20, 6 );
	}

	/**
	 * save_token_data
	 *
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_armember_validate_common_trigger_codes',
			array( 'ARM_CANCEL_PLAN' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations ) ) {
			$trigger_log_entry = $args['trigger_entry'];
			if ( isset( $args['trigger_args'][0], $args['trigger_args'][1] ) ) {
				Automator()->db->token->save( 'save_user_id', $args['trigger_args'][0], $trigger_log_entry );
				Automator()->db->token->save( 'save_plan_id', $args['trigger_args'][1], $trigger_log_entry );
			}
		}
	}

	/**
	 * Affiliate possible tokens.
	 *
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed|\string[][]
	 */
	public function armember_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_armember_validate_common_tokens_trigger_code',
			array( 'ARM_CANCEL_PLAN' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'ARM_MEMBERSHIP_PLAN',
					'tokenName'       => __( 'Membership plan', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'ARM_MEMBERSHIP_TYPE',
					'tokenName'       => __( 'Membership type', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'ARM_MEMBERSHIP_PLAN_ID',
					'tokenName'       => __( 'Plan ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'ARM_MEMBER_USERNAME',
					'tokenName'       => __( 'Member username', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'ARM_MEMBER_EMAIL',
					'tokenName'       => __( 'Member email', 'uncanny-automator' ),
					'tokenType'       => 'email',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'ARM_MEMBER_FIRST_NAME',
					'tokenName'       => __( 'Member first name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'ARM_MEMBER_LAST_NAME',
					'tokenName'       => __( 'Member last name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'ARM_MEMBER_ROLE',
					'tokenName'       => __( 'Member role', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'ARM_MEMBER_STATUS',
					'tokenName'       => __( 'Member status', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'ARM_MEMBER_JOINED_DATE',
					'tokenName'       => __( 'Member joined date', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * parse_tokens
	 *
	 * @param mixed $value
	 * @param mixed $pieces
	 * @param mixed $recipe_id
	 * @param mixed $trigger_data
	 * @param mixed $user_id
	 * @param mixed $replace_args
	 *
	 * @return void
	 */
	public function parse_armember_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_armember_parse_common_trigger_code',
			array( 'ARM_CANCEL_PLAN' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		if ( ! array_intersect( $trigger_meta_validations, $pieces ) ) {
			return $value;
		}

		$to_replace        = $pieces[2];
		$member_id         = Automator()->db->token->get( 'save_user_id', $replace_args );
		$plan_id           = Automator()->db->token->get( 'save_plan_id', $replace_args );
		$arm_members       = $this->armember_class;
		$arm_subscriptions = $this->armember_subscription_class;
		$plan              = $arm_subscriptions->arm_get_subscription_plan( $plan_id, 'arm_subscription_plan_name,arm_subscription_plan_options' );
		$member            = $arm_members->arm_get_member_detail( $member_id );

		switch ( $to_replace ) {
			case 'ARM_MEMBERSHIP_PLAN':
				$value = $plan['arm_subscription_plan_name'];
				break;
			case 'ARM_MEMBERSHIP_PLAN_ID':
				$value = $plan_id;
				break;
			case 'ARM_MEMBERSHIP_TYPE':
				$value = ucfirst( $plan['arm_subscription_plan_options']['access_type'] ) . ' - ' . ucfirst( str_replace( '_', ' ', $plan['arm_subscription_plan_options']['payment_type'] ) );
				break;
			case 'ARM_MEMBER_STATUS';
				$status_arm = array(
					1 => 'Active',
					2 => 'Inactive',
					3 => 'Pending',
				);
				$value      = $status_arm[ arm_get_member_status( $member_id ) ];
				break;
			case 'ARM_MEMBER_JOINED_DATE':
				$value = date( 'F j, Y', strtotime( $member->user_registered ) );
				break;
			case 'ARM_MEMBER_USERNAME':
				$value = $member->user_login;
				break;
			case 'ARM_MEMBER_EMAIL':
				$value = $member->user_email;
				break;
			case 'ARM_MEMBER_FIRST_NAME':
				$value = $member->first_name;
				break;
			case 'ARM_MEMBER_LAST_NAME':
				$value = $member->last_name;
				break;
			case 'ARM_MEMBER_ROLE':
				$value = join( ', ', $member->roles );
				break;
		}

		return $value;
	}

}
