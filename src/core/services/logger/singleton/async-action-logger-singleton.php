<?php

namespace Uncanny_Automator\Logger\Singleton;

class Async_Actions_Logger_Singleton {

	/**
	 * @var self
	 */
	private static $instance = null;

	/**
	 * @var mixed[]
	 */
	private static $entries = array();

	/**
	 * Returns an instance of the class
	 *
	 * @return self
	 */
	public static function get_instance() {
		return null === self::$instance ? self::$instance = new self() : self::$instance;
	}

	/**
	 * @param int $action_id
	 * @param mixed $postpone_args
	 *
	 * @return void
	 */
	public function add_entry( $action_id, $postpone_args ) {
		if ( empty( $action_id ) ) {
			return;
		}
		self::$entries[ intval( $action_id ) ] = $postpone_args;
	}

	/**
	 * @return mixed[]
	 */
	public function get_entries() {
		return self::$entries;
	}

	/**
	 * Clears all logged async-action entries.
	 *
	 * Called on `automator_recipe_completed` to prevent unbounded growth
	 * in long-running processes (WP-CLI, Action Scheduler batch workers).
	 *
	 * @return void
	 */
	public function clear() {
		self::$entries = array();
	}

	/**
	 * Prevents the object from being unserialized
	 *
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize a singleton.' );
	}

	/**
	 * Prevents the object from being directly instantiated
	 */
	protected function __construct() {
		add_action( 'automator_recipe_completed', array( $this, 'clear' ) );
	}

	/**
	 * Prevents the object from being cloned
	 */
	protected function __clone() {}
}
