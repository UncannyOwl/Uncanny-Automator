<?php

namespace Uncanny_Automator\Tokens;

/**
 * Abstract class Token
 *
 * @package Uncanny_Automator
 */
abstract class Universal_Token extends Token {

	const PREFIX = 'UT';

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup();
		add_filter( 'automator_integration_items', array( $this, 'register_token' ), 10, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'validate_recipe_token_parser' ), 10, 6 );
	}

	/**
	 * as_array
	 *
	 * @return array
	 */
	public function as_array() {
		return array(
			'id'             => self::PREFIX . ':' . $this->integration . ':' . $this->get_id(),
			'name'           => $this->get_name(),
			'cacheable'      => $this->get_cacheable(),
			'requiresUser'   => $this->get_requires_user(),
			'type'           => $this->get_type(),
			'supportedItems' => $this->get_supported_items(),
		);
	}

	/**
	 * validate_recipe_token_parser
	 *
	 * @param  mixed $return
	 * @param  mixed $pieces
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger_data
	 * @param  mixed $user_id
	 * @param  mixed $replace_args
	 * @return string
	 */
	public function validate_recipe_token_parser( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( self::PREFIX !== $pieces[0] ) {
			return $return;
		}

		if ( $this->get_integration() !== $pieces[1] ) {
			return $return;
		}

		return $this->parse_integration_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );
	}

	/**
	 * parse_integration_token
	 *
	 * @param  mixed $return
	 * @param  mixed $pieces
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger_data
	 * @param  mixed $user_id
	 * @param  mixed $replace_args
	 * @return string
	 */
	public function parse_integration_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		return $return;
	}
}
