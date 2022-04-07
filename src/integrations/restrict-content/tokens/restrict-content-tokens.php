<?php

namespace Uncanny_Automator;

use UCTINCAN\Database;

/**
 * Class Restrict_Content_Tokens
 *
 * @package Uncanny_Automator
 */
class Restrict_Content_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'RC';

	public function __construct() {
		//*************************************************************//
		// See this filter generator AT automator-get-data.php
		// in function recipe_trigger_tokens()
		//*************************************************************//
		add_filter( 'automator_maybe_trigger_rc_rcmembershiplevel_tokens', array( $this, 'possible_tokens' ), 9999, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'rc_token' ), 20, 6 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		if ( ! isset( $args['value'] ) || ! isset( $args['meta'] ) ) {
			return $tokens;
		}

		if ( empty( $args['value'] ) || empty( $args['meta'] ) ) {
			return $tokens;
		}

		$id = $args['value'];

		$new_tokens = array();
		if ( ! empty( $id ) && absint( $id ) ) {
			$new_tokens[] = array(
				'tokenId'         => 'RCMEMBERSHIPLEVEL_INITIAL',
				'tokenName'       => _x( 'Membership initial payment', 'Restrict Content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'RCPURCHASESMEMBERSHIP',
			);
			$new_tokens[] = array(
				'tokenId'         => 'RCMEMBERSHIPLEVEL_RECURRING',
				'tokenName'       => _x( 'Membership recurring payment', 'Restrict Content', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'RCPURCHASESMEMBERSHIP',
			);

			$tokens = array_merge( $tokens, $new_tokens );
		}

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function rc_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {

		if ( $pieces ) {
			if ( in_array( 'RCPURCHASESMEMBERSHIP', $pieces ) ) {
				if ( ! absint( $user_id ) ) {
					return $value;
				}

				if ( ! absint( $recipe_id ) ) {
					return $value;
				}

				$replace_pieces = $replace_args['pieces'];
				$trigger_log_id = $replace_args['trigger_log_id'];
				$run_number     = $replace_args['run_number'];
				$user_id        = $replace_args['user_id'];
				$trigger_id     = absint( $replace_pieces[0] );

				$membership_id = Automator()->get->get_trigger_log_meta(
					'RCMEMBERSHIPLEVEL_MEMBERSHIPID',
					$trigger_id,
					$trigger_log_id,
					$run_number,
					$user_id
				);

				if ( $membership_id ) {
					$membership = rcp_get_membership( $membership_id );

					if ( false !== $membership ) {
						switch ( $pieces[2] ) {
							case 'RCMEMBERSHIPLEVEL':
								return $membership->get_membership_level_name();
								break;
							case 'RCMEMBERSHIPLEVEL_INITIAL':
								return $membership->get_initial_amount();
								break;
							case 'RCMEMBERSHIPLEVEL_RECURRING':
								return $membership->get_recurring_amount();
								break;
						}
					}
				}
			}
		}

		return $value;
	}
}
