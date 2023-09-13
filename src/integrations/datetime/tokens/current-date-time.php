<?php
namespace Uncanny_Automator\Integrations\DateTime\Tokens;

use Uncanny_Automator\Tokens\Token;

class Current_Date_Time extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'DATETIME';
		$this->id            = 'current_date_and_time';
		$this->name          = esc_attr_x( 'Current date and time', 'Token', 'uncanny-automator' );
		$this->requires_user = false;
		$this->type          = 'date';
		$this->cacheable     = true;
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

		$format = sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) );

		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format );
		}

		return date_i18n( $format );
	}
}
