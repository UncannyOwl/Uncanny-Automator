<?php
namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Services\Recipe\Action\Token\Registry;
use Uncanny_Automator\Services\Recipe\Action\Token\Store;

/**
 * Trait Action_Tokens
 *
 * Small utility trait for setting up action token and hydrating them with custom values.
 *
 * @since 4.6
 * @since 6.0 - Refactored into a small, and lean class. Also fixed the closure issue where its firing multiple times.
 * @version 2.0
 */
trait Action_Tokens {

	/**
	 * Stores the developer input for hydration.
	 *
	 * @var array
	 */
	private $dev_input = array();

	/**
	 * Unique identifier for each hook call to ensure it only runs once per lifecycle.
	 *
	 * @var array
	 */
	private $executed_hooks = array();

	/**
	 * Use this method to set tokens per action.
	 *
	 * @param array  $tokens      Tokens to be registered.
	 * @param string $action_code Action code associated with tokens.
	 *
	 * @return self
	 */
	public function set_action_tokens( $tokens = array(), $action_code = '' ) {

		$registry = new Registry();
		$registry->register_hooks();
		$registry->register( $tokens, $action_code );

		return $this;
	}

	/**
	 * Use this method to hydrate the tokens.
	 * This registers named functions to the relevant actions instead of closures.
	 *
	 * @param array $dev_input The input to hydrate the tokens.
	 *
	 * @return bool
	 */
	public function hydrate_tokens( $dev_input = array() ) {

		// Store dev input for later use.
		$this->dev_input = $dev_input;

		// Hook to automator_action_created.
		// Remove any existing hooks to prevent duplicate registrations
		// @see https://app.clickup.com/t/868bh7gyr?comment=90110121861333
		remove_action( 'automator_action_created', array( $this, 'handle_automator_action_created' ) );
		add_action( 'automator_action_created', array( $this, 'handle_automator_action_created' ), 10, 1 );

		// Hook to automator_pro_async_action_after_run_execution.
		// Remove any existing hooks to prevent duplicate registrations
		// @see https://app.clickup.com/t/868bh7gyr?comment=90110121861333
		remove_action( 'automator_pro_async_action_after_run_execution', array( $this, 'handle_automator_pro_async_action' ) );
		add_action( 'automator_pro_async_action_after_run_execution', array( $this, 'handle_automator_pro_async_action' ), 10, 1 );

		return true;
	}

	/**
	 * Handles the 'automator_action_created' action.
	 *
	 * @param array $hook_args Hook arguments.
	 */
	public function handle_automator_action_created( $hook_args ) {
		$this->process_token_storage( $hook_args, 'automator_action_created' );
	}

	/**
	 * Handles the 'automator_pro_async_action_after_run_execution' action.
	 *
	 * @param array $hook_args Hook arguments.
	 */
	public function handle_automator_pro_async_action( $hook_args ) {
		$this->process_token_storage( $hook_args, 'automator_pro_async_action_after_run_execution' );
	}

	/**
	 * Processes the storage of tokens.
	 *
	 * Ensures that the process only runs once per action per lifecycle using unique hook identifiers.
	 *
	 * @param array  $hook_args Hook arguments.
	 * @param string $hook_name The name of the hook being processed.
	 */
	private function process_token_storage( $hook_args, $hook_name ) {

		$action_log_id = $hook_args['action_data']['action_log_id'] ?? ''; // "Loops" actions don't have an action log id, however it shouldn't be an issue.

		// Make the hook identifier unique by action log id.
		$hook_identifier = md5( $hook_name . wp_json_encode( $this->dev_input ) . '_' . $action_log_id );

		// Check if this specific hook has already been executed.
		if ( isset( $this->executed_hooks[ $hook_identifier ] ) ) {
			// Bail if not a loop action to prevent duplicate executions.
			if ( ! isset( $hook_args['action_data']['loop'] ) || empty( $hook_args['action_data']['loop'] ) ) {
				return;
			}
		}

		// Mark this hook as executed.
		$this->executed_hooks[ $hook_identifier ] = true;

		// Proceed with storing the tokens.
		if ( ! empty( $this->dev_input ) ) {
			$store = new Store();
			$store->set_key_value_pairs( wp_json_encode( $this->dev_input ) );
			$store->store( $hook_args );
		}
	}
}
