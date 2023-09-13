<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class Site_Url extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'COMMON';
		$this->id          = 'site_url';
		$this->name        = is_multisite() ? esc_attr_x( 'Current site URL', 'Token', 'uncanny-automator' ) : esc_attr_x( 'Site URL', 'Token', 'uncanny-automator' );
		$this->type        = 'url';
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
		return get_site_url();
	}
}
