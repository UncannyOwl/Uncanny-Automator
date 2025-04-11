<?php

namespace Uncanny_Automator\Tokens;

/**
 * Abstract class Token
 *
 * @package Uncanny_Automator
 */
abstract class Universal_Token extends Token {

	const PREFIX = 'UT';

	public $id_template;

	public $name_template;

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
			'fields'         => $this->get_fields(),
			'idTemplate'     => $this->id_template,
			'nameTemplate'   => $this->name_template,
		);
	}

	/**
	 * validate_recipe_token_parser
	 *
	 * @param  mixed $retval
	 * @param  mixed $pieces
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger_data
	 * @param  mixed $user_id
	 * @param  mixed $replace_args
	 * @return string
	 */
	public function validate_recipe_token_parser( $retval, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( self::PREFIX !== $pieces[0] ) {
			return $retval;
		}

		if ( $this->get_integration() !== $pieces[1] ) {
			return $retval;
		}

		if ( $this->get_id() !== $pieces[2] ) {
			return $retval;
		}

		return $this->parse_integration_token( $retval, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );
	}

	/**
	 * parse_integration_token
	 *
	 * @param  mixed $retval
	 * @param  mixed $pieces
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger_data
	 * @param  mixed $user_id
	 * @param  mixed $replace_args
	 * @return string
	 */
	public function parse_integration_token( $retval, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		return $retval;
	}
	/**
	 * Get fields.
	 *
	 * @return mixed
	 */
	public function get_fields() {
		return array();
	}

	/**
	 * Resolve the user ID context.
	 *
	 * Returns the user ID from the loop data if it exists.
	 * Otherwise, returns the user ID from the user_id argument.
	 *
	 * @param int|null $user_id      The user ID coming from the trigger or the user selector. Can be null.
	 * @param array    $replace_args The replace arguments.
	 *
	 * @return int|null The resolved user ID.
	 */
	protected function resolve_user_id( $user_id, $replace_args ) {

		// If the replace args is not an array, return the user ID.
		if ( ! is_array( $replace_args ) ) {
			return $user_id;
		}

		// If the replace args contains a loop and the loop contains a user_id, return the user_id.
		if ( isset( $replace_args['loop']['user_id'] ) && intval( $replace_args['loop']['user_id'] ) > 0 ) {
			return $replace_args['loop']['user_id'];
		}

		// Otherwise, return the user ID from the user_id argument.
		return $user_id;
	}
}
