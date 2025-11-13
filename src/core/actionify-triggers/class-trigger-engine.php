<?php

namespace Uncanny_Automator\Actionify_Triggers;

/**
 * Main Trigger Engine - orchestrates the entire system.
 *
 * This class handles trigger discovery, hook registration, and event processing.
 * It's designed to be portable and easy to initialize anywhere.
 *
 * @package Uncanny_Automator\Actionify_Triggers
 * @since 6.7
 */
class Trigger_Engine {

	/**
	 * Map of registered WordPress hooks to trigger codes.
	 *
	 * @var array
	 */
	private $registered_hooks = array();

	/**
	 * Trigger query instance.
	 *
	 * @var Trigger_Query
	 */
	private $query;

	/**
	 * Queue processor instance.
	 *
	 * @var Trigger_Queue
	 */
	private $queue;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->query = new Trigger_Query();
		$this->queue = new Trigger_Queue();
	}

	/**
	 * Initialize the trigger engine.
	 *
	 * Call this method to start listening for triggers.
	 *
	 * @return void
	 */
	public function init() {
		$this->register_automation_hooks();
	}

	/**
	 * Register WordPress hooks for all active triggers.
	 *
	 * Discovers all active triggers and registers WordPress action hooks
	 * that will queue events for processing.
	 *
	 * @return void
	 */
	public function register_automation_hooks() {

		$active_triggers = $this->query->get_active_triggers();

		if ( empty( $active_triggers ) ) {
			return;
		}

		$flattened_triggers = $this->flatten_trigger_array( $active_triggers );
		$unique_triggers    = $this->remove_duplicate_triggers( $flattened_triggers );

		foreach ( $unique_triggers as $trigger ) {
			$this->register_single_trigger( (array) $trigger );
		}

		/**
		 * Fires after all automation triggers have been registered.
		 *
		 * @param array $unique_triggers List of unique triggers that were registered.
		 */
		do_action( 'automator_triggers_registered', $unique_triggers );
	}

	/**
	 * Publish an event when a WordPress hook fires.
	 *
	 * This method is called by the WordPress action hooks registered in
	 * register_automation_hooks(). It finds matching triggers and queues
	 * events for processing.
	 *
	 * @param string $hook_name The WordPress hook that was triggered.
	 * @param array  $args The arguments passed to the hook.
	 *
	 * @return void
	 */
	public function publish_event( $hook_name, $args ) {

		// Find triggers that should respond to this hook.
		$matching_triggers = $this->find_triggers_for_hook( $hook_name );

		foreach ( $matching_triggers as $trigger_code ) {
			$this->queue->enqueue( $trigger_code, $hook_name, $args );
		}
	}

	/**
	 * Register a single trigger hook with WordPress.
	 *
	 * @param array $trigger The trigger configuration.
	 *
	 * @return void
	 */
	private function register_single_trigger( array $trigger ) {

		$hook_name    = $trigger['hook'] ?? '';
		$trigger_code = $trigger['code'] ?? '';

		if ( empty( $hook_name ) || empty( $trigger_code ) ) {
			return;
		}

		// Only register each hook once.
		if ( isset( $this->registered_hooks[ $hook_name ] ) ) {
			$this->registered_hooks[ $hook_name ][] = $trigger_code;
			return;
		}

		$this->registered_hooks[ $hook_name ] = array( $trigger_code );

		// Register WordPress hook that publishes to our event system.
		add_action(
			$hook_name,
			function ( ...$args ) use ( $hook_name ) {
				$this->publish_event( $hook_name, $args );
			},
			10,
			99
		);
	}

	/**
	 * Find triggers that should respond to a specific hook.
	 *
	 * @param string $hook_name The WordPress hook name.
	 *
	 * @return array Array of trigger codes that respond to this hook.
	 */
	private function find_triggers_for_hook( $hook_name ) {
		return $this->registered_hooks[ $hook_name ] ?? array();
	}

	/**
	 * Flatten nested trigger array structure.
	 *
	 * @param array $triggers The raw triggers array from discovery.
	 *
	 * @return array Flattened array of trigger configurations.
	 */
	private function flatten_trigger_array( array $triggers ) {
		$result = array();

		foreach ( $triggers as $group ) {
			if ( isset( $group[0] ) ) {
				$result = array_merge( $result, $group );
				continue;
			}

			$result[] = $group;
		}

		return $result;
	}

	/**
	 * Remove duplicate triggers based on code and hook combination.
	 *
	 * @param array $triggers Array of trigger configurations.
	 *
	 * @return array Array with duplicates removed.
	 */
	private function remove_duplicate_triggers( array $triggers ) {

		$unique = array();
		$seen   = array();

		foreach ( $triggers as $trigger ) {
			$key = ( $trigger['code'] ?? '' ) . '|' . ( $trigger['hook'] ?? '' );

			if ( ! isset( $seen[ $key ] ) ) {
				$unique[]     = $trigger;
				$seen[ $key ] = true;
			}
		}

		return $unique;
	}

	/**
	 * Get queue instance for external access.
	 *
	 * @return Trigger_Queue
	 */
	public function get_queue() {
		return $this->queue;
	}

	/**
	 * Get query instance for external access.
	 *
	 * @return Trigger_Query
	 */
	public function get_query() {
		return $this->query;
	}
}
