<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class Current_Blog_Id extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'COMMON';
		$this->id          = 'current_blog_id';
		$this->name        = esc_attr_x( 'Current site ID', 'Token', 'uncanny-automator' );
		$this->type        = 'int';
	}

	/**
	 * display_in_recipe_ui
	 *
	 * @return bool
	 */
	public function display_in_recipe_ui() {
		return is_multisite() && defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' );
	}

	/**
	 * parse
	 *
	 * @param  mixed $replaceable
	 * @param  mixed $field_text
	 * @param  mixed $match
	 * @param  mixed $current_user
	 * @return mixed
	 */
	public function parse( $replaceable, $field_text, $match, $current_user ) {

		if ( ! is_multisite() ) {
			return __( 'N/A', 'uncanny-automator' );
		}

		return get_current_blog_id();
	}
}
