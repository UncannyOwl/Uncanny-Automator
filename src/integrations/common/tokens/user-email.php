<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class User_Email extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'COMMON';
		$this->id            = 'user_email';
		$this->name          = esc_attr_x( 'User email', 'Token', 'uncanny-automator' );
		$this->requires_user = true;
		$this->type          = 'email';
	}

	/**
	 * display_in_recipe_ui
	 *
	 * @return bool
	 */
	public function display_in_recipe_ui() {

		if ( 'anonymous' === $this->get_recipe_type() ) {
			$this->remove_supported_item( 'trigger' );
			$this->remove_supported_item( 'user-selector' );
		}

		return true;
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
		$user = get_user_by( 'id', $current_user );
		return isset( $user->user_email ) ? $user->user_email : '';
	}
}
