<?php

namespace Uncanny_Automator\Integrations\DateTime\Tokens;

use Uncanny_Automator\Tokens\Token;

/**
 * class Current_Month_Numeric
 * @package Uncanny_Automator
 */
class Current_Month_Numeric extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'DATETIME';
		$this->id            = 'current_month_numeric';
		$this->name          = esc_attr_x( 'Current month (numeric)', 'Token', 'uncanny-automator' );
		$this->requires_user = false;
		$this->type          = 'date';
		$this->cacheable     = true;
	}

	/**
	 * parse
	 *
	 * @param mixed $replaceable
	 * @param mixed $field_text
	 * @param mixed $match
	 * @param mixed $current_user
	 *
	 * @return mixed
	 */
	public function parse( $replaceable, $field_text, $match, $current_user ) {

		if ( function_exists( 'wp_date' ) ) {
			return wp_date( 'n' );
		}

		return date_i18n( 'n' );
	}
}
