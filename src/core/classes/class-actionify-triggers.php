<?php

namespace Uncanny_Automator;

/**
 * Class Actionify_Triggers
 *
 * @package Uncanny_Automator
 */
class Actionify_Triggers {

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

		do_action( 'automator_actionify_triggers_after', self::$actionified_triggers );
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

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public hook name, kept for compatibility.
		return apply_filters( 'actionified_triggers', $actionified_triggers, $this );
	}

	/**
	 * Retrieves all active triggers.
	 *
	 * @return array The list of active triggers.
	 */
	public function get_active_triggers() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Joins core posts and postmeta; no WordPress API returns this.
		$r = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value
					FROM $wpdb->postmeta pm
					JOIN $wpdb->posts trigger_details ON trigger_details.ID = pm.post_id
						AND trigger_details.post_status = %s
						AND trigger_details.post_type = %s
					JOIN $wpdb->posts recipe_details ON recipe_details.ID = trigger_details.post_parent
						AND recipe_details.post_status = %s
						AND recipe_details.post_type = %s
					WHERE pm.meta_key = 'add_action'",
				'publish',
				AUTOMATOR_POST_TYPE_TRIGGER,
				'publish',
				AUTOMATOR_POST_TYPE_RECIPE
			)
		);

		if ( empty( $r ) ) {
			return array();
		}

		$active_add_actions = array();

		foreach ( $r as $rr ) {
			$rr = automator_safe_unserialize( $rr );
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
