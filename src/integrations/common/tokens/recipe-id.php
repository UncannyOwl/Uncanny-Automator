<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class Recipe_Id extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'COMMON';
		$this->id          = 'recipe_id';
		$this->name        = esc_attr_x( 'Recipe ID', 'Token', 'uncanny-automator' );
		$this->type        = 'int';
	}

	/**
	 * parse
	 *
	 * @param  mixed $replaceable
	 * @param  mixed $field_text
	 * @param  mixed $match
	 * @param  mixed $current_user
	 * @return int
	 */
	public function parse( $replaceable, $field_text, $match, $current_user ) {
		return $this->get_recipe_id();
	}
}
