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
	 * @deprecated v4.2
	 */
	private $recipes = array();

	/**
	 * @var array
	 */
	public static $actionified_triggers = array();

	/**
	 * Constructor
	 */
	public function __construct() {

		$run_automator_actions = true;

		$run_automator_actions = apply_filters_deprecated(
			'uap_run_automator_actions',
			array( $run_automator_actions ),
			'3.0',
			'automator_run_automator_actions'
		);
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
	public function actionify_triggers() {
		if ( empty( self::$actionified_triggers ) ) {
			self::$actionified_triggers = $this->get_active_integration_triggers();
		}
		// If not, bail
		if ( empty( self::$actionified_triggers ) ) {
			return;
		}
		foreach ( self::$actionified_triggers as $trigger ) {
			$trigger_actions             = $trigger->trigger_actions;
			$trigger_validation_function = $trigger->trigger_validation_function;
			$trigger_priority            = $trigger->trigger_priority;
			$trigger_accepted_args       = $trigger->trigger_accepted_args;

			// Initialize trigger
			if ( empty( $trigger_validation_function ) ) {
				continue;
			}
			if ( is_array( $trigger_actions ) ) {
				foreach ( $trigger_actions as $trigger_action ) {
					add_action( $trigger_action, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
				}
				continue;
			}
			add_action( $trigger_actions, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
		}
	}

	/**
	 * @return void
	 * @deprecated v4.2
	 */
	public function actionify_triggers_from_recipes() {
		self::$actionified_triggers = $this->get_active_triggers_from_recipes();
		if ( empty( self::$actionified_triggers ) ) {
			return;
		}
		foreach ( self::$actionified_triggers as $trigger ) {
			$trigger_actions             = $trigger->trigger_actions;
			$trigger_validation_function = $trigger->trigger_validation_function;
			$trigger_priority            = $trigger->trigger_priority;
			$trigger_accepted_args       = $trigger->trigger_accepted_args;

			// Initialize trigger
			if ( empty( $trigger_validation_function ) ) {
				continue;
			}

			if ( is_array( $trigger_actions ) ) {
				foreach ( $trigger_actions as $trigger_action ) {
					add_action( $trigger_action, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
				}
				continue;
			}
			add_action( $trigger_actions, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
		}
		Automator()->cache->set( 'automator_actionified_triggers', self::$actionified_triggers, 'automator', Automator()->cache->long_expires );
	}

	/**
	 * @return array|mixed
	 *
	 * @deprecated v4.2
	 */
	public function get_active_triggers_from_recipes() {
		$this->recipes = Automator()->get_recipes_data( true );

		if ( empty( $this->recipes ) ) {
			return array();
		}
		// Collect all trigger codes that have been actionified, so we don't double register
		$actionified_triggers = new \stdClass();

		foreach ( $this->recipes as $recipe ) {

			// Only actionify published recipes
			if ( 'publish' !== $recipe['post_status'] ) {
				continue;
			}

			// Only actionify uncompleted recipes
			if ( true === $recipe['completed_by_current_user'] ) {
				continue;
			}
			// Loop through each trigger and add our trigger event to the hook
			foreach ( $recipe['triggers'] as $trigger ) {

				// Map action to specific recipeID/TriggerID combination
				if ( ! array_key_exists( 'code', $trigger['meta'] ) ) {
					continue;
				}

				$trigger_code = $trigger['meta']['code'];

				// We only want to add one action for each trigger
				if ( isset( $actionified_triggers->$trigger_code ) ) {
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

				$actionified_triggers->$trigger_code = new \stdClass();

				$actionified_triggers->$trigger_code->trigger_actions             = $trigger_actions;
				$actionified_triggers->$trigger_code->trigger_validation_function = $trigger_validation_function;
				$actionified_triggers->$trigger_code->trigger_priority            = $trigger_priority;
				$actionified_triggers->$trigger_code->trigger_accepted_args       = $trigger_accepted_args;
			}
		}

		return apply_filters( 'actionified_triggers', $actionified_triggers, $this );
	}

	/**
	 * @return object
	 */
	public function get_active_integration_triggers() {
		$triggers             = Automator()->get_triggers();
		$active_integrations  = Set_Up_Automator::$active_integrations_code;
		$actionified_triggers = new \stdClass();
		if ( empty( $triggers ) || empty( $active_integrations ) ) {
			return $actionified_triggers;
		}
		foreach ( $triggers as $trigger ) {
			if ( empty( $trigger['integration'] ) || empty( $trigger['action'] ) || empty( $trigger['validation_function'] ) ) {
				continue;
			}

			$trigger_code                        = $trigger['code'];
			$actionified_triggers->$trigger_code = new \stdClass();

			$actionified_triggers->$trigger_code->trigger_actions             = $trigger['action'];
			$actionified_triggers->$trigger_code->trigger_validation_function = $trigger['validation_function'];
			$actionified_triggers->$trigger_code->trigger_priority            = empty( $trigger['priority'] ) ? 10 : absint( $trigger['priority'] );
			$actionified_triggers->$trigger_code->trigger_accepted_args       = empty( $trigger['accepted_args'] ) ? 1 : absint( $trigger['accepted_args'] );
		}

		return apply_filters( 'actionified_triggers', $actionified_triggers, $this );
	}
}
