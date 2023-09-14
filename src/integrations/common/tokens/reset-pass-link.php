<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class Reset_Pass_Link extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'COMMON';
		$this->id            = 'reset_pass_link';
		$this->name          = esc_attr_x( 'User reset password link', 'Token', 'uncanny-automator' );
		$this->requires_user = true;
		$this->type          = 'url';
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
		return Automator()->parse->generate_reset_token( $current_user );
	}
}
