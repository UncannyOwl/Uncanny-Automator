<?php

namespace Uncanny_Automator;

/**
 * Class Pmp_Tokens
 *
 * @package Uncanny_Automator
 */
class Pmp_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'PMP';

	/**
	 * Pmp_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'pmp_token' ), 20, 6 );
		add_action( 'uap_save_pmp_membership_level', array( $this, 'uap_save_pmp_membership_level' ), 10, 4 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( defined( 'PMPRO_BASE_FILE' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	public function uap_save_pmp_membership_level( $membership_id, $args, $user_id, $meta ) {

		$membership_level_details = pmpro_getSpecificMembershipLevelForUser( $user_id, $membership_id );

		$subscription_id = $membership_level_details->subscription_id;
		$code_id         = $membership_level_details->code_id;
		$name            = $membership_level_details->name;
		$billing_cycle   = $membership_level_details->cycle_number;
		$billing_period  = $membership_level_details->cycle_period;
		$billing_amount  = $membership_level_details->billing_amount;
		$billing_start   = $membership_level_details->startdate;
		$billing_end     = $membership_level_details->enddate;
		global $wpdb;
		$code = $wpdb->get_var( $wpdb->prepare( "SELECT `code` FROM $wpdb->pmpro_discount_codes WHERE id = %d", $code_id ) );

		$tokens = array(
			$meta                          => $name,
			$meta . '_ID'                  => $membership_id,
			$meta . '_USER_ID'             => $user_id,
			$meta . '_DISCOUNT_CODE'       => $code,
			$meta . '_DISCOUNT_CODE_ID'    => $code_id,
			$meta . '_SUBSCRIPTION_ID'     => $subscription_id,
			$meta . '_SUBSCRIPTION_AMOUNT' => $billing_amount,
			$meta . '_SUBSCRIPTION_PERIOD' => $billing_period,
			$meta . '_SUBSCRIPTION_CYCLE'  => $billing_cycle,
			$meta . '_SUBSCRIPTION_START'  => ! empty( $billing_start ) ? date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $billing_start ) : '',
			$meta . '_SUBSCRIPTION_END'    => ! empty( $billing_end ) ? date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $billing_start ) : '',
		);

		$args = array(
			'user_id'        => $user_id,
			'trigger_id'     => $args['trigger_id'],
			'meta_key'       => $meta,
			'meta_value'     => $membership_id,
			'run_number'     => $args['run_number'], //get run number
			'trigger_log_id' => $args['trigger_log_id'],
		);

		Automator()->insert_trigger_meta( $args );
		$args = array(
			'user_id'        => $user_id,
			'trigger_id'     => $args['trigger_id'],
			'meta_key'       => 'membership_details',
			'meta_value'     => maybe_serialize( $tokens ),
			'run_number'     => $args['run_number'], //get run number
			'trigger_log_id' => $args['trigger_log_id'],
		);

		Automator()->insert_trigger_meta( $args );
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return string|null
	 */
	public function pmp_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( empty( $pieces ) ) {
			return $value;
		}
		if ( ( isset( $pieces[2] ) && preg_match( '/PMPMEMBERSHIP(\_)?/', $pieces[2] ) ) ) {

			$field = $pieces[2];
			$entry = maybe_unserialize( Automator()->db->token->get( 'membership_details', $replace_args ) );
			if ( $entry && isset( $entry[ $field ] ) ) {
				$value = $entry[ $field ];
			}
		}

		return $value;
	}
}
