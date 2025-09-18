<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Tokens\Universal_Token;

/**
 * EDD User Address Line 2 Token
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads
 */
class EDD_User_Address_Line_2_Token extends Universal_Token {

	/**
	 * EDD User Address Line 2
	 *
	 * @var string
	 */
	const EDD_USER_ADDRESS_LINE_2 = 'EDD_USER_ADDRESS_LINE_2';

	/**
	 * EDD helpers instance
	 *
	 * @var EDD_Helpers
	 */
	private $edd_helpers;

	/**
	 * Setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'EDD';
		$this->id            = self::EDD_USER_ADDRESS_LINE_2;
		$this->name          = esc_attr_x( 'User address line 2', 'EDD', 'uncanny-automator' );
		$this->requires_user = true;
		$this->cacheable     = false;
		$this->edd_helpers   = new EDD_Helpers();
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

		// Handle EDD User Address Line 2 token
		if ( self::EDD_USER_ADDRESS_LINE_2 === $token_id ) {
			$address_data = $this->edd_helpers->get_user_address_data( $user_id );

			return isset( $address_data['line2'] ) ? $address_data['line2'] : '';
		}

		return $default_return;
	}
}
