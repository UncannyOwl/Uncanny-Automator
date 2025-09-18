<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Tokens\Universal_Token;

/**
 * EDD User Country Token
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads
 */
class EDD_User_Country_Token extends Universal_Token {

	/**
	 * EDD User Country
	 *
	 * @var string
	 */
	const EDD_USER_COUNTRY = 'EDD_USER_COUNTRY';

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
		$this->id            = self::EDD_USER_COUNTRY;
		$this->name          = esc_attr_x( 'User country', 'EDD', 'uncanny-automator' );
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

		// Handle EDD User Country token
		if ( self::EDD_USER_COUNTRY === $token_id ) {
			$address_data = $this->edd_helpers->get_user_address_data( $user_id );

			return isset( $address_data['country'] ) ? $address_data['country'] : '';
		}

		return $default_return;
	}
}
