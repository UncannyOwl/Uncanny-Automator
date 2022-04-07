<?php

namespace Uncanny_Automator;

/**
 * Class Actionify_Triggers
 *
 * @package Uncanny_Automator
 */
class Actionify_Triggers {
	/**
	 * @var array
	 */
	private $recipes = array();

	/**
	 * Constructor
	 */
	public function __construct() {

		$run_automator_actions = true;

		$run_automator_actions = apply_filters_deprecated( 'uap_run_automator_actions', array( $run_automator_actions ), '3.0', 'automator_run_automator_actions' );
		$run_automator_actions = apply_filters( 'automator_run_automator_actions', $run_automator_actions );

		if ( $run_automator_actions ) {
			add_action( 'plugins_loaded', array( $this, 'actionify_triggers' ), AUTOMATOR_ACTIONIFY_TRIGGERS_PRIORITY );
		}
	}

	/**
	 * Load up our activity triggers, so we can add actions to them
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function actionify_triggers( $force = false ) {
		$actionified_triggers = Automator()->cache->get( 'automator_actionified_triggers' );
		if ( ! empty( $actionified_triggers ) && false === $force ) {
			$this->cached_actionify_triggers( $actionified_triggers );

			return;
		}
		// Get all published recipes
		if ( empty( $this->recipes ) ) {
			$this->recipes = Automator()->get_recipes_data( true );
		}

		if ( empty( $this->recipes ) ) {
			return;
		}

		foreach ( $this->recipes as $recipe ) {

			// Only actionify published recipes
			if ( 'publish' !== $recipe['post_status'] ) {
				continue;
			}

			// Only actionify uncompleted recipes
			if ( true === $recipe['completed_by_current_user'] ) {
				continue;
			}

			// Collect all trigger codes that have been actionified, so we don't double register
			$actionified_triggers = array();

			// Loop through each trigger and add our trigger event to the hook
			foreach ( $recipe['triggers'] as $trigger ) {

				// Map action to specific recipeID/TriggerID combination
				if ( ! array_key_exists( 'code', $trigger['meta'] ) ) {
					continue;
				}

				$trigger_code = $trigger['meta']['code'];

				// We only want to add one action for each trigger
				if ( array_key_exists( $trigger_code, $actionified_triggers ) ) {
					continue;
				}

				// The trigger may exist in the DB but the plugin integration may not be active, if it is not
				$trigger_actions             = Automator()->get->trigger_actions_from_trigger_code( $trigger_code );
				$trigger_validation_function = Automator()->get->trigger_validation_function_from_trigger_code( $trigger_code );
				$trigger_priority            = Automator()->get->trigger_priority_from_trigger_code( $trigger_code );
				$trigger_accepted_args       = Automator()->get->trigger_accepted_args_from_trigger_code( $trigger_code );

				// Initialize trigger
				if ( empty( $trigger_validation_function ) ) {
					continue;
				}

				if ( is_array( $trigger_actions ) ) {
					foreach ( $trigger_actions as $trigger_action ) {
						add_action( $trigger_action, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
					}
				} else {
					add_action( $trigger_actions, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
				}
				$actionified_triggers[ $trigger_code ] = array(
					'trigger_actions'             => $trigger_actions,
					'trigger_validation_function' => $trigger_validation_function,
					'trigger_priority'            => $trigger_priority,
					'trigger_accepted_args'       => $trigger_accepted_args,
				);
				Automator()->cache->set( 'automator_actionified_triggers', $actionified_triggers, 'automator', Automator()->cache->long_expires );
			}
		}
	}

	/**
	 * @param $actionified_triggers
	 */
	public function cached_actionify_triggers( $actionified_triggers ) {
		if ( empty( $actionified_triggers ) ) {
			$this->actionify_triggers( true );
		}

		foreach ( $actionified_triggers as $data ) {
			$trigger_actions             = $data['trigger_actions'];
			$trigger_validation_function = $data['trigger_validation_function'];
			$trigger_priority            = $data['trigger_priority'];
			$trigger_accepted_args       = $data['trigger_accepted_args'];

			if ( is_array( $trigger_actions ) ) {
				foreach ( $trigger_actions as $trigger_action ) {
					add_action( $trigger_action, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
				}
			} else {
				add_action( $trigger_actions, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
			}
		}
	}
}
