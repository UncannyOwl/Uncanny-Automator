<?php
namespace Uncanny_Automator\Services\Loopable;

/**
 * Abstract class representing an Loopable Token.
 *
 * @package Uncanny_Automator\Services\Loopable\Loopable_Token
 */
abstract class Loopable_Token {

	/**
	 * @var string The ID of the token.
	 */
	protected $id = '';

	/**
	 * @var string The integration type.
	 */
	protected $integration = '';

	/**
	 * @var string The name of the token.
	 */
	protected $name = '';

	/**
	 * @var string The log identifier for the token.
	 */
	protected $log_identifier = '';

	/**
	 * @var array{}|array{mixed[]} The child tokens associated with this token.
	 */
	protected $child_tokens = array();

	/**
	 * Register the loopable token.
	 *
	 * This method should be implemented by the subclass.
	 *
	 * @return void
	 */
	abstract public function register_loopable_token();

	/**
	 * Set the ID of the token.
	 *
	 * @param string $id The ID to set.
	 *
	 * @return void
	 */
	public function set_id( string $id = '' ) {
		$this->id = $id;
	}

	/**
	 * Get the ID of the token.
	 *
	 * @return string The ID of the token.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set the name of the token.
	 *
	 * @param string $name The name to set.
	 *
	 * @return void
	 */
	public function set_name( string $name = '' ) {
		$this->name = $name;
	}

	/**
	 * Get the name of the token.
	 *
	 * @return string The name of the token.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Set the log identifier of the token.
	 *
	 * @param string $log_identifier The log identifier to set.
	 *
	 * @return void
	 */
	public function set_log_identifier( string $log_identifier = '' ) {
		$this->log_identifier = $log_identifier;
	}

	/**
	 * Get the log identifier of the token.
	 *
	 * @return string The log identifier of the token.
	 */
	public function get_log_identifier() {
		return $this->log_identifier;
	}

	/**
	 * Set the child tokens associated with this token.
	 *
	 * @param array{}|array{mixed[]} $child_tokens The child tokens to set.
	 *
	 * @return void
	 */
	public function set_child_tokens( array $child_tokens = array() ) {

		$args = array(
			'instance' => $this,
		);

		$this->child_tokens = apply_filters( "automator_universal_loopable_token_{$this->get_id()}", $child_tokens, $args );

	}

	/**
	 * Get the child tokens associated with this token.
	 *
	 * @return array The child tokens associated with this token.
	 *
	 * @return array{mixed[]}
	 */
	public function get_child_tokens() {
		return $this->child_tokens;
	}

	/**
	 * Set the integration type.
	 *
	 * @param string $integration The integration type to set.
	 *
	 * @return void
	 */
	public function set_integration( $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Get the integration type.
	 *
	 * @return string The integration type.
	 */
	public function get_integration() {
		return $this->integration;
	}

	/**
	 * Get the definitions of the token.
	 *
	 * @return mixed[] An array containing the definitions of the token.
	 */
	public function get_definitions() {
		$definition_id = $this->get_id();
		$definition    = array(
			'name'            => $this->get_name(),
			'children_tokens' => $this->get_child_tokens(),
			'log_identifier'  => self::get_log_identifier(),
		);
		return array(
			$definition_id => $definition,
		);
	}
}
