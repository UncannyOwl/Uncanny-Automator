<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class Admin_Email extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'COMMON';
		$this->id          = 'admin_email';
		$this->name        = esc_attr_x( 'Admin email', 'Token', 'uncanny-automator' );
		$this->type        = 'email';
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
		return get_bloginfo( 'admin_email' );
	}
}
