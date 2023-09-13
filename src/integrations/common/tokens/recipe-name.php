<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class Recipe_Name extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'COMMON';
		$this->id          = 'recipe_name';
		$this->name        = esc_attr_x( 'Recipe name', 'Token', 'uncanny-automator' );
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

		$recipe = get_post( $this->get_recipe_id() );

		if ( null !== $recipe ) {
			return $recipe->post_title;
		}

		return $replaceable;
	}
}
