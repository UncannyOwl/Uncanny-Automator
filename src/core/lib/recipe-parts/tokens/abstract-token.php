<?php

namespace Uncanny_Automator\Tokens;

/**
 * Abstract class Token
 *
 * @package Uncanny_Automator
 */
abstract class Token {

	protected $name;
	protected $id;
	protected $recipe_id;
	protected $integration;
	protected $requires_user;
	protected $type = 'text';
	protected $cacheable;
	protected $supported_items = array( 'condition', 'action', 'loop-filter', 'trigger', 'user-selector' );
	protected $args;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup();
		add_filter( 'automator_integration_items', array( $this, 'register_token' ), 10, 2 );
		add_filter( "automator_maybe_parse_{$this->id}", array( $this, 'validate_token_parser' ), 10, 5 );
	}

	/**
	 * display_in_recipe_ui
	 *
	 * @return bool
	 */
	public function display_in_recipe_ui() {
		return true;
	}

	/**
	 * register_token
	 *
	 * @param  mixed $items
	 * @param  mixed $structure
	 * @return array
	 */
	public function register_token( $items, $structure ) {

		$this->recipe_id = $structure->get_recipe_id();

		if ( ! isset( $items[ $this->integration ] ) ) {
			$items[ $this->integration ] = array();
		}

		if ( ! isset( $items[ $this->integration ]['tokens'] ) ) {
			$items[ $this->integration ]['tokens'] = array();
		}

		if ( ! $this->display_in_recipe_ui() ) {
			return $items;
		}

		$items[ $this->integration ]['tokens'][] = $this->as_array();

		return $items;
	}

	/**
	 * as_array
	 *
	 * @return array
	 */
	public function as_array() {
		return array(
			'id'             => $this->get_id(),
			'name'           => $this->get_name(),
			'cacheable'      => $this->get_cacheable(),
			'requiresUser'   => $this->get_requires_user(),
			'type'           => $this->get_type(),
			'supportedItems' => $this->get_supported_items(),
		);
	}

	/**
	 * get_integration
	 *
	 * @return string
	 */
	public function get_integration() {
		return $this->integration;
	}

	/**
	 * get_id
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * get_name
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * get_cacheable
	 *
	 * @return bool
	 */
	public function get_cacheable() {
		return $this->cacheable;
	}

	/**
	 * get_requires_user
	 *
	 * @return bool
	 */
	public function get_requires_user() {
		return $this->requires_user;
	}

	/**
	 * get_type
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * get_supported_items
	 *
	 * @return array
	 */
	public function get_supported_items() {
		return $this->supported_items;
	}

	/**
	 * set_integration
	 *
	 * @param  mixed $integration
	 * @return void
	 */
	public function set_integration( $integration ) {
		$this->integration = $integration;
	}

	/**
	 * set_id
	 *
	 * @param  mixed $id
	 * @return void
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * set_name
	 *
	 * @param  mixed $name
	 * @return void
	 */
	public function set_name( $name ) {
		$this->name = $name;
	}

	/**
	 * set_cacheable
	 *
	 * @param  mixed $cacheable
	 * @return void
	 */
	public function set_cacheable( $cacheable ) {
		$this->cacheable = $cacheable;
	}

	/**
	 * set_requires_user
	 *
	 * @param  mixed $requires_user
	 * @return void
	 */
	public function set_requires_user( $requires_user ) {
		$this->requires_user = $requires_user;
	}

	/**
	 * set_type
	 *
	 * @param  mixed $type
	 * @return void
	 */
	public function set_type( $type ) {
		$this->type = $type;
	}

	/**
	 * set_supported_items
	 *
	 * @param  mixed $supported_items
	 * @return void
	 */
	public function set_supported_items( $supported_items ) {
		$this->supported_items = $supported_items;
	}

	/**
	 * add_supported_item
	 *
	 * @param  mixed $item
	 * @return void
	 */
	public function add_supported_item( $item ) {

		// If the item already exists.
		if ( false !== array_search( $item, $this->supported_items, true ) ) {
			return;
		}

		$this->supported_items[] = $item;
	}

	/**
	 * remove_supported_item
	 *
	 * @param  mixed $item
	 * @return void
	 */
	public function remove_supported_item( $item ) {

		$item_index = array_search( $item, $this->supported_items, true );

		// If the item doesn't exist.
		if ( false === $item_index ) {
			return;
		}

		unset( $this->supported_items[ $item_index ] );
	}

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {}



	/**
	 * modal
	 *
	 * @return array
	 */
	public function modal() {
		return array();
	}

	/**
	 * validate_token_parser
	 *
	 * @param  mixed $replaceable
	 * @param  mixed $field_text
	 * @param  mixed $match
	 * @param  mixed $current_user
	 * @param  mixed $args
	 * @return string
	 */
	public function validate_token_parser( $replaceable, $field_text, $match, $current_user, $args ) {

		$this->args = $args;

		if ( $this->id !== $match ) {
			return $replaceable;
		}

		return $this->parse( $replaceable, $field_text, $match, $args['user_id'] );
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
		return $replaceable;
	}

	/**
	 * get_recipe_type
	 *
	 * @return void
	 */
	public function get_recipe_type() {
		return Automator()->utilities->get_recipe_type( $this->recipe_id );
	}

	/**
	 * @param $args
	 *
	 * @return int|null
	 */
	public function get_recipe_id() {
		return array_key_exists( 'recipe_id', $this->args ) ? absint( $this->args['recipe_id'] ) : null;
	}
}
