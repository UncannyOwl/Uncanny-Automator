<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class Site_Tagline extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'COMMON';
		$this->id          = 'site_tagline';
		$this->name        = is_multisite() ? esc_attr_x( 'Current site tagline', 'Token', 'uncanny-automator' ) : esc_attr_x( 'Site tagline', 'Token', 'uncanny-automator' );
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
		return get_bloginfo( 'description' );
	}
}
