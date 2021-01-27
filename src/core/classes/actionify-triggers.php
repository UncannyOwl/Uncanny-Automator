<?php

namespace Uncanny_Automator;

/**
 * Class Actionify_Triggers
 * @package Uncanny_Automator
 */
class Actionify_Triggers {

	/**
	 * Constructor
	 */
	public function __construct() {

		$run_automator_actions = true;
		$cron_actions_to_match = apply_filters( 'uap_cron_action_exception', [] );

		if ( isset( $_REQUEST['doing_rest'] ) ) {
			//Ignore
			$run_automator_actions = false;
		} elseif ( isset( $_REQUEST['action'] ) && 'heartbeat' === sanitize_text_field( $_REQUEST['action'] ) ) {
			//Ignore
			$run_automator_actions = false;
		} elseif ( isset( $_REQUEST['wc-ajax'] ) && 'checkout' !== (string) sanitize_text_field( $_REQUEST['wc-ajax'] ) && 'complete_order' !== (string) sanitize_text_field( $_REQUEST['wc-ajax'] ) ) {
			//Ignore
			$run_automator_actions = false;
		} elseif ( 'admin-ajax.php' === basename( $_SERVER['REQUEST_URI'] ) && ! isset( $_REQUEST['action'] ) ) {
			//Ignore
			$run_automator_actions = false;
		} elseif ( isset( $_REQUEST['action'] ) && 'run-cron' === sanitize_text_field( $_REQUEST['action'] ) ) {
			if ( ( isset( $_REQUEST['id'] ) && ! in_array( $_REQUEST['id'], $cron_actions_to_match ) ) ) {
				//Ignore
				$run_automator_actions = false;
			}
		} elseif ( isset( $_REQUEST['control_name'] ) ) {
			if ( ! in_array( $_REQUEST['control_name'], $cron_actions_to_match ) ) {
				//Ignore
				$run_automator_actions = false;
			}
		}

		$run_automator_actions = apply_filters( 'uap_run_automator_actions', $run_automator_actions, $_REQUEST );

		if ( $run_automator_actions ) {
			add_action( 'plugins_loaded', array( $this, 'actionify_triggers' ), AUTOMATOR_ACTIONIFY_TRIGGERS_PRIORITY );
		}
	}

	/**
	 * Load up our activity triggers so we can add actions to them
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function actionify_triggers() {

		global $uncanny_automator;

		// Get all published recipes
		$recipes = $uncanny_automator->get_recipes_data( true );
		foreach ( $recipes as $recipe ) {

			// Only actionify published recipes
			if ( 'publish' !== $recipe['post_status'] ) {
				continue;
			}

			// Only actionify uncompleted recipes
			if ( true === $recipe['completed_by_current_user'] ) {
				continue;
			}

			// Collect all trigger codes that have been actionified so we don't double register
			$actionified_triggers = array();

			// Loop through each trigger and add our trigger event to the hook
			foreach ( $recipe['triggers'] as $trigger ) {

				// Map action to specific recipeID/TriggerID combination
				if ( key_exists( 'code', $trigger['meta'] ) ) {

					$trigger_code = $trigger['meta']['code'];

					// We only want to add one action for each trigger
					if ( in_array( $trigger_code, $actionified_triggers ) ) {
						continue;
					}

					// The trigger may exist in the DB but the plugin integration may not be active, if it is not
					$trigger_actions             = $uncanny_automator->get->trigger_actions_from_trigger_code( $trigger_code );
					$trigger_validation_function = $uncanny_automator->get->trigger_validation_function_from_trigger_code( $trigger_code );
					$trigger_priority            = $uncanny_automator->get->trigger_priority_from_trigger_code( $trigger_code );
					$trigger_accepted_args       = $uncanny_automator->get->trigger_accepted_args_from_trigger_code( $trigger_code );

					// Initialize trigger
					if ( ! empty( $trigger_validation_function ) ) {
						if ( is_array( $trigger_actions ) ) {
							foreach ( $trigger_actions as $trigger_action ) {
								add_action( $trigger_action, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
							}
						} else {
							add_action( $trigger_actions, $trigger_validation_function, $trigger_priority, $trigger_accepted_args );
						}

						$actionified_triggers[] = $trigger_code;
					}
				}
			}
		}
	}
}
