<?php
namespace Uncanny_Automator;

/**
 * Class Actionify_Triggers
 *
 * @package Uncanny_Automator
 */
class Actionify_Triggers {

	/**
	 * Property recipes.
	 *
	 * @var array
	 *
	 * @deprecated v4.2
	 */
	private $recipes = array();

	/**
	 * Property actionified_triggers.
	 *
	 * @var array
	 */
	public static $actionified_triggers = array();

	/**
	 * Method constructor.
	 *
	 * Registers all triggers' action hook.
	 *
	 * @return void
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
	 * Load up our activity triggers, so we can add actions to them.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function actionify_triggers() {

		if ( empty( self::$actionified_triggers ) ) {
			self::$actionified_triggers = $this->get_active_integration_triggers();
		}

		// If not, bail.
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
	 * Registers available triggers with its corresponding event hooks and callback function.
	 *
	 * @return void
	 *
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
	 * Retrieves all active triggers from recipes.
	 *
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
	 * Retrieves all active integration triggers.
	 *
	 * @return object
	 */
	public function get_active_integration_triggers() {

		$triggers             = Automator()->get_triggers();
		$active_integrations  = Set_Up_Automator::$active_integrations_code;
		$actionified_triggers = new \stdClass();

		if ( empty( $triggers ) || empty( $active_integrations ) ) {
			return $actionified_triggers;
		}

		$active_add_actions = self::get_active_triggers();

		foreach ( $triggers as $trigger ) {
			if ( empty( $trigger['integration'] ) || empty( $trigger['action'] ) || empty( $trigger['validation_function'] ) ) {
				continue;
			}

			$trigger_code = $trigger['code'];
			$do_action    = $trigger['action'];

			$do_action_validation = is_array( $do_action ) ? $do_action : array( $do_action );

			if ( ! array_intersect( $do_action_validation, $active_add_actions ) ) {
				continue;
			}

			$actionified_triggers->$trigger_code                              = new \stdClass();
			$actionified_triggers->$trigger_code->trigger_actions             = $do_action;
			$actionified_triggers->$trigger_code->trigger_validation_function = $trigger['validation_function'];
			$actionified_triggers->$trigger_code->trigger_priority            = empty( $trigger['priority'] ) ? 10 : absint( $trigger['priority'] );
			$actionified_triggers->$trigger_code->trigger_accepted_args       = empty( $trigger['accepted_args'] ) ? 1 : absint( $trigger['accepted_args'] );

		}

		return apply_filters( 'actionified_triggers', $actionified_triggers, $this );

	}

	/**
	 * Retrieves all active triggers.
	 *
	 * @return array The list of active triggers.
	 */
	public function get_active_triggers() {

		global $wpdb;

		$r = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value
					FROM $wpdb->postmeta pm
					JOIN $wpdb->posts trigger_details ON trigger_details.ID = pm.post_id 
						AND trigger_details.post_status = %s 
						AND trigger_details.post_type = 'uo-trigger'
					JOIN $wpdb->posts recipe_details ON recipe_details.ID = trigger_details.post_parent 
						AND recipe_details.post_status = %s 
						AND recipe_details.post_type = 'uo-recipe'
					WHERE pm.meta_key = 'add_action'",
				'publish',
				'publish'
			)
		);

		if ( empty( $r ) ) {
			return array();
		}

		$active_add_actions = array();

		foreach ( $r as $rr ) {
			$rr = maybe_unserialize( $rr );
			if ( is_array( $rr ) ) {
				foreach ( $rr as $rrr ) {
					$active_add_actions[] = (string) $rrr;
				}
			} else {
				$active_add_actions[] = (string) $rr;
			}
		}

		return array_unique( $active_add_actions );

	}

}
