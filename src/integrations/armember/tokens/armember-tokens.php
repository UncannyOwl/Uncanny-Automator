<?php

namespace Uncanny_Automator\Integrations\Armember;

/**
 * Token definitions and hydration for the ARMember integration.
 *
 * Every ARMember trigger offers the same ten membership tokens, and the Pro
 * triggers add the plan's expiry date. All are stored under the trigger code —
 * the framework default, so none declares a `tokenIdentifier` — and recipes
 * address them as {trigger_id}:{trigger code}:{tokenId}, which is why the ids
 * below are fixed.
 *
 * @package Uncanny_Automator
 */
class Armember_Tokens {

	/**
	 * Integration helpers.
	 *
	 * @var Armember_Helpers
	 */
	private $helpers;

	/**
	 * @param Armember_Helpers $helpers
	 */
	public function __construct( Armember_Helpers $helpers ) {
		$this->helpers = $helpers;
	}

	// -------------------------------------------------------------------------
	// Membership tokens
	// -------------------------------------------------------------------------

	/**
	 * The plan and the member it concerns.
	 *
	 * @return array
	 */
	public function membership_tokens() {
		return array(
			array(
				'tokenId'   => 'ARM_MEMBERSHIP_PLAN_ID',
				'tokenName' => esc_html_x( 'Plan ID', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ARM_MEMBERSHIP_PLAN',
				'tokenName' => esc_html_x( 'Membership plan', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBERSHIP_TYPE',
				'tokenName' => esc_html_x( 'Membership type', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_USERNAME',
				'tokenName' => esc_html_x( 'Member username', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_EMAIL',
				'tokenName' => esc_html_x( 'Member email', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_FIRST_NAME',
				'tokenName' => esc_html_x( 'Member first name', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_LAST_NAME',
				'tokenName' => esc_html_x( 'Member last name', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_ROLE',
				'tokenName' => esc_html_x( 'Member role', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_STATUS',
				'tokenName' => esc_html_x( 'Member status', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ARM_MEMBER_JOINED_DATE',
				'tokenName' => esc_html_x( 'Member joined date', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Resolve the membership tokens for a member and a plan.
	 *
	 * @param int $user_id
	 * @param int $plan_id
	 *
	 * @return array Always the full keyset, so a missing plan or member leaves
	 *               empty strings rather than raw placeholders in the output.
	 */
	public function hydrate_membership_tokens( $user_id, $plan_id ) {

		$user_id = absint( $user_id );
		$plan_id = absint( $plan_id );

		$values = array_fill_keys( array_column( $this->membership_tokens(), 'tokenId' ), '' );

		if ( $plan_id > 0 ) {
			// Known from the hook even when the plan row has since been deleted.
			$values['ARM_MEMBERSHIP_PLAN_ID'] = (string) $plan_id;
		}

		$plans = $this->helpers->subscription_plans();
		$plan  = ( null !== $plans && $plan_id > 0 )
			? $plans->arm_get_subscription_plan( $plan_id, 'arm_subscription_plan_name,arm_subscription_plan_options' )
			: false;

		if ( is_array( $plan ) ) {
			$values['ARM_MEMBERSHIP_PLAN'] = isset( $plan['arm_subscription_plan_name'] ) ? (string) $plan['arm_subscription_plan_name'] : '';
			$values['ARM_MEMBERSHIP_TYPE'] = $this->membership_type( isset( $plan['arm_subscription_plan_options'] ) ? (array) $plan['arm_subscription_plan_options'] : array() );
		}

		$members = $this->helpers->members();
		$member  = ( null !== $members && $user_id > 0 ) ? $members->arm_get_member_detail( $user_id ) : false;

		if ( $member instanceof \WP_User ) {
			$values['ARM_MEMBER_USERNAME']   = (string) $member->user_login;
			$values['ARM_MEMBER_EMAIL']      = (string) $member->user_email;
			$values['ARM_MEMBER_FIRST_NAME'] = (string) $member->first_name;
			$values['ARM_MEMBER_LAST_NAME']  = (string) $member->last_name;
			$values['ARM_MEMBER_ROLE']       = implode( ', ', (array) $member->roles );
			// ARMember wraps the label in its badge markup; the token wants the words.
			$values['ARM_MEMBER_STATUS']      = wp_strip_all_tags( (string) $members->armGetMemberStatusText( $user_id ) );
			$values['ARM_MEMBER_JOINED_DATE'] = $this->joined_date( $member->user_registered );
		}

		return $values;
	}

	/**
	 * The member's registration date, or an empty string when it does not parse.
	 *
	 * A user imported from another system can carry `0000-00-00 00:00:00`, which
	 * `strtotime()` turns into a negative timestamp and `wp_date()` renders as a
	 * year-0000 date, or an empty value, which makes `wp_date()` return false —
	 * the one token value that would not be a string.
	 *
	 * @param string $user_registered The user's `user_registered` column.
	 *
	 * @return string
	 */
	private function joined_date( $user_registered ) {

		$timestamp = strtotime( (string) $user_registered );

		if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) {
			return '';
		}

		return (string) wp_date( 'F j, Y', $timestamp );
	}

	/**
	 * "{Access type} - {Payment type}", falling back to the plan's price text.
	 *
	 * The two legacy hydrators disagreed here: the cancel parser had no
	 * fallback and emitted " - " for a plan with no access type.
	 *
	 * @param array $options The plan's `arm_subscription_plan_options`.
	 *
	 * @return string
	 */
	private function membership_type( array $options ) {

		$access_type = isset( $options['access_type'] ) ? (string) $options['access_type'] : '';

		if ( '' === $access_type ) {
			return isset( $options['pricetext'] ) ? (string) $options['pricetext'] : '';
		}

		$payment_type = isset( $options['payment_type'] ) ? (string) $options['payment_type'] : '';

		return ucfirst( $access_type ) . ' - ' . ucfirst( str_replace( '_', ' ', $payment_type ) );
	}

	// -------------------------------------------------------------------------
	// Expiry token — offered by the Pro triggers
	// -------------------------------------------------------------------------

	/**
	 * When the member's plan expires.
	 *
	 * @return array
	 */
	public function expiry_tokens() {
		return array(
			array(
				'tokenId'   => 'ARM_PLAN_EXPIRY_DATE',
				'tokenName' => esc_html_x( 'Expiration date', 'ARMember', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Resolve the expiry date from the plan meta ARMember keeps on the user.
	 *
	 * ARMember stores `arm_expire_plan` as a unix timestamp inside the
	 * `arm_user_plan_{plan_id}` user meta array.
	 *
	 * @param int $user_id
	 * @param int $plan_id
	 *
	 * @return array Always the full keyset.
	 */
	public function hydrate_expiry_tokens( $user_id, $plan_id ) {

		$values  = array( 'ARM_PLAN_EXPIRY_DATE' => '' );
		$user_id = absint( $user_id );
		$plan_id = absint( $plan_id );

		if ( 0 === $user_id || 0 === $plan_id ) {
			return $values;
		}

		$plan_data = (array) get_user_meta( $user_id, 'arm_user_plan_' . $plan_id, true );
		$expires   = isset( $plan_data['arm_expire_plan'] ) ? $plan_data['arm_expire_plan'] : '';

		if ( is_numeric( $expires ) && (int) $expires > 0 ) {
			$values['ARM_PLAN_EXPIRY_DATE'] = wp_date( 'F j, Y', (int) $expires );
		} elseif ( is_string( $expires ) && '' !== $expires && false !== strtotime( $expires ) ) {
			$values['ARM_PLAN_EXPIRY_DATE'] = wp_date( 'F j, Y', strtotime( $expires ) );
		}

		return $values;
	}
}
