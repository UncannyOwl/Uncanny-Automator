<?php
/**
 * This file is attaches functions to specified action hooks.
 *
 * Some 'listeners' requires the action to be loaded in advance because Async\
 * actions is pushed in to Action Scheduler library. When an action is pushed\
 * to Action Scheduler, the action is run on an Action Scheduler hook. Which means,
 * we have to listen to it in advance.
 *
 * @since 4.15
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delayed action logger.
 **/
add_action(
	'automator_pro_async_action_execution_after_invoked',
	function( $hook_args ) {
		require_once UA_ABSPATH . 'src/core/services/logger.php';
		\Uncanny_Automator\Logger\tokens_logger( $hook_args );
	},
	10,
	1
);

/**
 * Async actions fields are recorded when the action is created.
 *
 * Updates the action fields in the log when the action is run from async.
 *
 * @since 5.0
 */
add_action(
	'automator_pro_async_action_execution_after_invoked',
	'\Uncanny_Automator\Logger\action_fields_logger',
	10,
	1
);

/**
 * Records failed action concitions.
 */
add_action(
	'automator_pro_action_condition_failed',
	function( $action, $code, $message ) {
		require_once UA_ABSPATH . 'src/core/services/logger.php';

		\Uncanny_Automator\Logger\recipe_actions_conditions_logger( $action, $code, $message );

		$user_id       = isset( $action['user_id'] ) ? absint( $action['user_id'] ) : null;
		$recipe_id     = isset( $action['recipe_id'] ) ? absint( $action['recipe_id'] ) : null;
		$recipe_log_id = isset( $action['args']['recipe_log_id'] ) ? absint( $action['args']['recipe_log_id'] ) : null;

		\Uncanny_Automator\Logger\conditions_errors_logger( $recipe_id, $user_id, $recipe_log_id );
	},
	10,
	3
);

/**
 * Records conditions result.
 */
add_action(
	'automator_pro_actions_conditions_result',
	function( $condition_result, $conditions, $action ) {

		$user_id       = isset( $action['user_id'] ) ? absint( $action['user_id'] ) : null;
		$recipe_id     = isset( $action['recipe_id'] ) ? absint( $action['recipe_id'] ) : null;
		$recipe_log_id = isset( $action['args']['recipe_log_id'] ) ? absint( $action['args']['recipe_log_id'] ) : null;

		// Set up the arguments for logging.
		$args = array(
			'user_id'       => $user_id,
			'recipe_id'     => $recipe_id,
			'recipe_log_id' => $recipe_log_id,
		);

		require_once UA_ABSPATH . 'src/core/services/logger/recipe-objects-logger.php';

		$recipe_objects_logger = new \Uncanny_Automator\Logger\Recipe_Objects_Logger();
		// Add the errors to the recipe objects logger as meta data.
		$recipe_objects_logger->add_meta( $args, 'conditions_result', $condition_result, false );

	},
	10,
	3
);

/**
 * Skipped actions or actions that are under a failed conditions are not evaluated.
 * Which means, tokens won't be parse from the action.
 * We have to hook into 'automator_pro_action_condition_failed' instead.
 */
add_action(
	'automator_pro_action_condition_failed',
	function( $action, $code, $message ) {

		require_once UA_ABSPATH . 'src/core/services/logger.php';

		$args = array(
			'args'          => array(
				'recipe_id'  => isset( $action['recipe_id'] ) ? $action['recipe_id'] : 0,
				'run_number' => isset( $action['args']['run_number'] ) ? $action['args']['run_number'] : 1,
			),
			'recipe_log_id' => isset( $action['args']['recipe_log_id'] ) ? $action['args']['recipe_log_id'] : 0,
		);

		\Uncanny_Automator\Logger\tokens_logger( $args );

	},
	10,
	3
);
