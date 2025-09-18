<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Tokens\Universal_Token;

/**
 * EDD User Spent Token
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads
 */
class EDD_User_Spent_Token extends Universal_Token {

	/**
	 * EDD User Spent
	 *
	 * @var string
	 */
	const EDD_USER_SPENT = 'EDD_USER_SPENT';

	/**
	 * Setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'EDD';
		$this->id            = self::EDD_USER_SPENT;
		$this->name          = esc_attr_x( 'User spent', 'EDD', 'uncanny-automator' );
		$this->requires_user = true;
		$this->cacheable     = false;
	}

	/**
	 * Parse integration token
	 *
	 * @param mixed $default_return Default return value.
	 * @param array $pieces Token pieces.
	 * @param int   $recipe_id Recipe ID.
	 * @param array $trigger_data Trigger data.
	 * @param int   $user_id User ID.
	 * @param array $replace_args Replace arguments.
	 *
	 * @return mixed|string
	 */
	public function parse_integration_token( $default_return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$token_id = $pieces[2];

		// Change the user ID to the current iterated user in the context of a Loop.
		if ( isset( $replace_args['loop'] ) && is_array( $replace_args['loop'] ) && isset( $replace_args['loop']['user_id'] ) ) {
			$user_id = absint( $replace_args['loop']['user_id'] );
		}

		// Handle EDD User Spent token
		if ( self::EDD_USER_SPENT === $token_id ) {
			if ( ! function_exists( 'edd_get_customer_by' ) ) {
				return '0.00';
			}

			$customer = edd_get_customer_by( 'user_id', $user_id );
			if ( ! $customer ) {
				return '0.00';
			}

			// Get customer's total purchase value
			$total_spent = $customer->purchase_value;

			// Format the amount
			if ( function_exists( 'edd_format_amount' ) ) {
				return edd_format_amount( $total_spent );
			}

			return number_format( $total_spent, 2 );
		}

		return $default_return;
	}
}
