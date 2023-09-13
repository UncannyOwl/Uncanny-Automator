<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class Site_Name extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'COMMON';
		$this->id          = 'site_name';
		$this->name        = is_multisite() ? esc_attr_x( 'Current site name', 'Token', 'uncanny-automator' ) : esc_attr_x( 'Site name', 'Token', 'uncanny-automator' );
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
		return get_bloginfo( 'name' );
	}
}
