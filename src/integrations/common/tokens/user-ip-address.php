<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class User_Ip_Address extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'COMMON';
		$this->id          = 'user_ip_address';
		$this->name        = esc_attr_x( 'User IP address', 'Token', 'uncanny-automator' );
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

		$replaceable = 'N/A';

		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		}

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip_array    = array_values( array_filter( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ); //phpcs:ignore
			return sanitize_text_field( wp_unslash( reset( $ip_array ) ) );
		}

		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		}

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $replaceable;
	}
}
