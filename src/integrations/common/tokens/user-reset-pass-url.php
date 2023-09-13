<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class User_Reset_Pass_Url extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'COMMON';
		$this->id            = 'user_reset_pass_url';
		$this->name          = esc_attr_x( 'User reset password URL', 'Token', 'uncanny-automator' );
		$this->requires_user = true;
	}

	/**
	 * parse
	 *
	 * @param  mixed $replaceable
	 * @param  mixed $field_text
	 * @param  mixed $match
	 * @param  mixed $current_user
	 * @return string
	 */
	public function parse( $replaceable, $field_text, $match, $current_user ) {
		return Automator()->parse->reset_password_url_token( $current_user );
	}
}
