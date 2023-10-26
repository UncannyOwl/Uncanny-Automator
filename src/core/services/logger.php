<?php
namespace Uncanny_Automator\Logger;

use Uncanny_Automator\Services\Resolver\Recipe_Actions_Resolver;
use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Resolver\Conditions\Errors_Mapping;
use Uncanny_Automator\Resolver\Conditions\Errors_Registry;
use Uncanny_Automator\Resolver\Fields_Resolver;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Logs trigger fields to the database.
 *
 * This function resolves trigger fields using the Fields_Resolver class and then logs them to the database
 * using the Trigger_Fields_Logger class.
 *
 * @param int[] $hook_args The hook arguments containing the trigger and log data.
 *
 * @return void
 */
function trigger_fields_logger( $hook_args = array() ) {

	// Default values.
	$hook_args = wp_parse_args(
		$hook_args,
		array(
			'trigger_id'     => 0,
			'recipe_id'      => 0,
			'trigger_log_id' => 0,
			'user_id'        => 0,
		)
	);

	// Include the required classes.
	require_once __DIR__ . '/resolver/fields-resolver.php';
	require_once __DIR__ . '/logger/trigger-fields-logger.php';

	// Initialize run number with value of 1. Run number can be null but it can't be 0.
	$run_number = 1;

	// Check if run number is set in hook arguments, and update the $run_number variable if it is.
	if ( isset( $hook_args['run_number'] ) ) {
		$run_number = absint( $hook_args['run_number'] );
	}

	/** @var int[] $args */
	$args = array(
		'trigger_id'     => absint( $hook_args['trigger_id'] ),
		'recipe_id'      => absint( $hook_args['recipe_id'] ),
		'trigger_log_id' => absint( $hook_args['trigger_log_id'] ),
		'run_number'     => $run_number, // $run_number has already been sanitized.
		'user_id'        => absint( $hook_args['user_id'] ),
	);

	// Resolve the fields with Fields_Resolver class.
	$field_resolver = new Fields_Resolver();

	// Set the recipe ID, object type, and object ID for the field resolver.
	$field_resolver->set_recipe_id( $args['recipe_id'] )
			->set_object_type( 'trigger' )
			->set_object_id( $args['trigger_id'] );

	// Resolve the fields for the trigger.
	$fields = $field_resolver->resolve_object_fields();

	// Log the fields using the Trigger_Fields_Logger.
	$trigger_fields_logger = new Trigger_Fields_Logger();
	$trigger_fields_logger->log( $args, $fields );

}

/**
 * Logs fields related to a specific action
 *
 * This function requires fields-resolver.php and action-fields-logger.php files.
 *
 * @param int[] $hook_args Arguments passed to the hook that triggers the function
 *
 * Required keys:
 * - action_log_id (int): ID of the action log
 * - action_id (int): ID of the action
 * - user_id (int): ID of the user
 *
 * @return void
 */
function action_fields_logger( $hook_args = array() ) {

	$hook_args = wp_parse_args(
		$hook_args,
		array(
			'action_log_id' => 0,
			'action_id'     => 0,
			'recipe_id'     => 0,
			'user_id'       => 0,
		)
	);

	/**
	 * This method is attached into two action hooks: "automator_action_created", and "automator_pro_async_action_execution_after_invoked"
	 *
	 * Action hook "automator_action_created" runs regardless whether the action is scheduled or not.
	 *
	 * The problem is that when the admin edits the action while the Action is delayed, it already saves the field values.
	 *
	 * Therefore it was hook into "automator_pro_async_action_execution_after_invoked" so that it will be log again when the action is run from delay.
	 *
	 * The hooks have different arguments therefore we need to resolve it.
	 */
	if ( 'automator_pro_async_action_execution_after_invoked' === current_action() ) {
		$hook_args['user_id']   = $hook_args['args']['user_id'];
		$hook_args['action_id'] = $hook_args['ID'];
		$hook_args['recipe_id'] = $hook_args['args']['recipe_id'];
	}

	// Extract required arguments.
	$args = array(
		'user_id'       => absint( $hook_args['user_id'] ),
		'action_id'     => absint( $hook_args['action_id'] ),
		'action_log_id' => absint( $hook_args['action_log_id'] ),
	);

	// Create an instance of Fields_Resolver class
	$fields_resolver = new Fields_Resolver();

	// Set the required properties of Fields_Resolver instance
	$fields_resolver->set_recipe_id( absint( $hook_args['recipe_id'] ) );
	$fields_resolver->set_object_id( absint( $hook_args['action_id'] ) );
	$fields_resolver->set_object_type( 'action' );

	// Create an instance of Action_Fields_Logger class
	$action_fields_logger = new Action_Fields_Logger();

	// Call the log method of Action_Fields_Logger instance
	// with extracted arguments and resolved fields
	$action_fields_logger->log( $args, $fields_resolver->resolve_object_fields() );
}

/**
 * Log the triggers of a recipe.
 *
 * @param mixed[] $hook_args The arguments passed to the hook.
 *
 * - recipe_id (int) (Required): The ID of the recipe being triggered.
 * - trigger_id (int) (Required): The ID of the trigger being logged.
 * - trigger_log_id (int) (Required): The ID of the trigger log.
 * - run_number (int) (Optional): The number of times the recipe has been triggered. Default is 1.
 * - user_id (int) (Required): The ID of the user triggering the recipe.
 *
 * @return void
 */
function recipe_triggers_logger( $hook_args = array() ) {

	// Get the data for the recipe being triggered.
	$recipe_data = Automator()->get_recipes_data( false, absint( $hook_args['recipe_id'] ) );

	$current_triggers = array();

	// Retrieve the triggers for the recipe.
	foreach ( $recipe_data as $recipe_id => $recipe ) {
		if ( is_array( $recipe['triggers'] ) && ! empty( $recipe['triggers'] ) ) {
			$triggers_live    = array_filter(
				$recipe['triggers'],
				function( $trigger_item ) {
					return 'publish' === $trigger_item['post_status'];
				}
			);
			$current_triggers = array_column( $triggers_live, 'ID' );
		}
	}

	// Initialize the run number with a default value of 1.
	$run_number = 1;

	// If the run number is specified, use that instead of the default.
	if ( isset( $hook_args['run_number'] ) ) {
		$run_number = $hook_args['run_number'];
	}

	/**
	 * @var int[] $args
	 */
	$args = array(
		'trigger_id'     => absint( $hook_args['trigger_id'] ),
		'recipe_id'      => absint( $hook_args['recipe_id'] ),
		'trigger_log_id' => absint( $hook_args['trigger_log_id'] ),
		'run_number'     => absint( $run_number ),
		'user_id'        => absint( $hook_args['user_id'] ),
	);

	// Instantiate the Recipe Objects Logger.
	require_once __DIR__ . '/logger/recipe-objects-logger.php';
	$recipe_objects_logger = new Recipe_Objects_Logger();

	// Set the key for the logger.
	$recipe_objects_logger->set_key( 'recipe_current_triggers' );

	// Log the triggers.
	$trigger_ids = wp_json_encode( $current_triggers );
	if ( false === $trigger_ids ) {
		$trigger_ids = '';
	}
	$recipe_objects_logger->log_triggers( $args, $trigger_ids );

}

/**
 * Logs the actions flow for a recipe.
 *
 * @param mixed[] $hook_args The arguments passed to the hook.
 *
 * @return void
 */
function recipe_actions_flow_logger( $hook_args = array() ) {

	// Load necessary files
	require_once __DIR__ . '/resolver/recipe-actions-resolver.php';
	require_once __DIR__ . '/logger/recipe-objects-logger.php';

	// Prepare arguments for logging
	$args = array(
		'user_id'       => absint( $hook_args['user_id'] ),
		'recipe_id'     => absint( $hook_args['recipe_id'] ),
		'recipe_log_id' => absint( $hook_args['recipe_log_id'] ),
	);

	// Create resolver and set recipe id
	$resolver = new Recipe_Actions_Resolver( Automator_Functions::get_instance() );
	$resolver->set_recipe_id( $args['recipe_id'] );

	// Create recipe objects logger
	$recipe_objects_logger = new Recipe_Objects_Logger();

	// Log actions flow
	$recorded = $recipe_objects_logger->get_meta( $args, 'actions_flow' );

	if ( empty( $recorded ) ) {
		$recipe_objects_logger->log_actions_flow( $args, $resolver->resolve_recipe_actions() );
	}

}

/**
 * @param mixed[] $hook_args
 *
 * @return void
 */
function closure_logger( $hook_args ) {

	// Load necessary files
	require_once __DIR__ . '/logger/recipe-objects-logger.php';

	// Prepare arguments for logging
	$args = array(
		'user_id'       => absint( $hook_args['user_id'] ),
		'recipe_id'     => absint( $hook_args['recipe_id'] ),
		'recipe_log_id' => absint( $hook_args['recipe_log_id'] ),
	);

	$logger = new Recipe_Objects_Logger();

	global $wpdb;

	$result = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT post.ID, meta.meta_value FROM {$wpdb->posts} as post
			INNER JOIN {$wpdb->postmeta} as meta
			ON post.ID = meta.post_id
			WHERE meta.meta_key = 'REDIRECTURL'
			AND post.post_parent = %d
			AND post.post_type = 'uo-closure'
			",
			$args['recipe_id']
		),
		ARRAY_A
	);

	if ( ! empty( $result ) ) {
		$recorded = $logger->get_meta( $args, 'closures' );
		if ( empty( $recorded ) ) {
			require_once __DIR__ . '/rest/endpoint/log-endpoint/utils/formatters-utils.php';
			$closure_meta = Formatters_Utils::flatten_post_meta( (array) get_post_meta( $result['ID'] ) );
			$logger->add_meta(
				$args,
				'closures',
				array(
					'log'  => $result,
					'meta' => $closure_meta,
				)
			);
		}
	}

}

/**
 * Logs the conditions for a recipe action that failed.
 *
 * @param mixed[] $action The action data.
 * @param string $code The error code for the failed condition.
 * @param string $message The error message for the failed condition.
 *
 * @return void
 */
function recipe_actions_conditions_logger( $action = array(), $code = '', $message = '' ) {

	$action = wp_parse_args(
		$action,
		array(
			'action_data' => array(
				'ID'                        => 0,
				'failed_actions_conditions' => array(),
				'actions_conditions_log'    => array(),
				'args'                      => array(
					'recipe_id' => null,
				),
			),
		)
	);

	if ( empty( $action['action_data']['failed_actions_conditions'] ) ) {
		return;
	}

	if ( empty( $action['action_data']['actions_conditions_log'] ) ) {
		return;
	}

	// Load necessary files
	require_once __DIR__ . '/resolver/conditions/errors-registry.php';
	require_once __DIR__ . '/resolver/conditions/errors-mapping.php';

	if ( ! isset( $action['action_data']['args'] ) ) {
		return;
	}

	// Get recipe id
	$recipe_id = absint( $action['action_data']['args']['recipe_id'] );

	// Create errors registry and errors mapping
	$error_registry = Errors_Registry::get_instance();
	$errors_mapping = new Errors_Mapping();

	// Set errors mapping source and action id
	$actions_conditions = get_post_meta( $recipe_id, 'actions_conditions', true );
	if ( ! is_string( $actions_conditions ) ) {
		$actions_conditions = '';
	}

	$errors_mapping->set_source( $actions_conditions );
	$errors_mapping->set_action_id( $action['action_data']['ID'] );

	// Get condition ids from error code and log errors
	$condition_ids = $errors_mapping->condition_ids_from_code( $code );

	foreach ( $condition_ids as $condition_id ) {
		if ( ! $error_registry->has_error( $condition_id ) ) {
			$error_registry->add( $condition_id, $message );
			// Break from the loop so only the first condition that "does not" contain the message gets populated.
			// Otherwise, the second message that comes up will be ignored.
			break;
		}
	}

}

/**
 * Logs any errors that occurred during recipe conditions check.
 *
 * @param int   $recipe_id     ID of the recipe being checked.
 * @param int   $user_id       ID of the user the recipe belongs to.
 * @param int   $recipe_log_id ID of the recipe log that triggered the condition check.
 *
 * @return void
 */
function conditions_errors_logger( $recipe_id = 0, $user_id = 0, $recipe_log_id = 0 ) {

	// Include the errors registry to retrieve any errors that occurred.
	require_once __DIR__ . '/resolver/conditions/errors-registry.php';

	// Bail if there are no errors.
	$error_registry = Errors_Registry::get_instance();

	// Initialize the recipe objects logger.
	$recipe_objects_logger = new Recipe_Objects_Logger();

	// Set up the arguments for logging.
	$args = array(
		'user_id'       => $user_id,
		'recipe_id'     => $recipe_id,
		'recipe_log_id' => $recipe_log_id,
	);

	// Add the errors to the recipe objects logger as meta data.
	$recipe_objects_logger->add_meta( $args, 'conditions_failed', $error_registry->get_errors() );

}

/**
 * Logs tokens for a given integration and code.
 *
 * @param mixed[] $hook_args Arguments passed to the hook.
 *
 * @return void
 */
function tokens_logger( $hook_args = array() ) {

	$hook_args = wp_parse_args(
		$hook_args,
		array(
			'args'          => array(
				'recipe_id'  => null,
				'run_number' => 1,
			),
			'recipe_log_id' => null,
		)
	);

	// Retrieve the tokens for the given integration and code.
	$parsed_token_records = Automator()->parsed_token_records();
	$tokens_record        = $parsed_token_records->get_tokens();

	// Get the recipe ID, run number, and recipe log ID from the hook arguments.
	$recipe_id     = $hook_args['args']['recipe_id'];
	$run_number    = $hook_args['args']['run_number'];
	$recipe_log_id = $hook_args['recipe_log_id'];

	// If tokens exist, log them using the Tokens_Logger class.
	if ( is_array( $tokens_record ) && ! empty( $tokens_record ) ) {

		// Require the Tokens_Logger class file.
		require_once __DIR__ . '/logger/token-logger.php';

		// Create a new instance of Tokens_Logger and log the tokens.
		$logger = new Tokens_Logger();

		$logger->log(
			array(
				'tokens_record' => $tokens_record,
				'recipe_id'     => $recipe_id,
				'recipe_log_id' => $recipe_log_id,
				'run_number'    => $run_number,
			)
		);
	}

}

/**
 * Logs gathered async entries.
 *
 * @param int $recipe_id
 * @param int $user_id
 * @param int $recipe_log_id
 * @param mixed[] $args
 *
 * @return void
 */
function log_async_actions( $recipe_id = 0, $user_id = 0, $recipe_log_id = 0, $args = array() ) {

	$entries = Automator()->async_action_logger()->get_entries();

	if ( empty( $entries ) ) {
		return;
	}

	require_once __DIR__ . '/logger/recipe-objects-logger.php';
	$recipe_objects_logger = new Recipe_Objects_Logger();

	// Prepare the arguments for the logger.
	$args = array(
		'recipe_log_id' => absint( $recipe_log_id ),
		'recipe_id'     => absint( $recipe_id ),
		'user_id'       => absint( $user_id ),
	);

	foreach ( $entries as $entry ) {
		if ( ! is_string( $entry ) ) {
			continue;
		}
		$entry_arr = (array) json_decode( $entry, true );
		if ( empty( $entry_arr['type'] ) ) {
			// Bail if schedule type is null or empty.
			return;
		}
	}

	// Add the evaluated conditions to the recipe objects logger as meta data.
	$recipe_objects_logger->add_meta( $args, 'action_delays', $entries );

}

/**
 * Collects async actions. This does not save data to the db.
 *
 * @param mixed[] $action
 *
 * @return void
 */
function collect_async_actions( $action = array() ) {

	$action = wp_parse_args(
		$action,
		array(
			'action_data' => array(
				'ID'    => 0,
				'async' => array(
					'mode'      => null,
					'timestamp' => null,
				),
			),
		)
	);

	$action_id = $action['action_data']['ID'];

	$type       = isset( $action['action_data']['async']['mode'] ) ? $action['action_data']['async']['mode'] : null;
	$time_stamp = isset( $action['action_data']['async']['timestamp'] ) ? $action['action_data']['async']['timestamp'] : null;

	$data = array(
		'type' => $type,
		'time' => $time_stamp,
	);

	if ( 'delay' === $type ) {
		$data['time_number'] = get_post_meta( $action_id, 'async_delay_number', true );
		$data['time_unit']   = get_post_meta( $action_id, 'async_delay_unit', true );
	}

	Automator()->async_action_logger()->add_entry( $action_id, wp_json_encode( $data ) );

}

/**
 * Logs triggers logic during recipe completed and num times insufficent.
 *
 * @param mixed[] $args
 *
 * @return void
 */
function recipe_triggers_logic_logger( $args ) {

	require_once __DIR__ . '/logger/recipe-objects-logger.php';

	$recipe_objects_logger = new Recipe_Objects_Logger();

	$recipe_id = $args['recipe_id'];

	$logic = get_post_meta( $recipe_id, 'automator_trigger_logic', true );

	if ( empty( $logic ) ) {
		$logic = 'all';
	}

	$logger_args = array(
		'recipe_id'     => $recipe_id,
		'user_id'       => $args['user_id'],
		'recipe_log_id' => $args['recipe_log_id'],
	);

	$recipe_objects_logger->add_meta(
		$logger_args,
		'triggers_logic',
		array(
			'logic' => $logic,
		),
		true // <-- Upserts
	);

}

/**
 * Registers listeners to log various events related to recipe triggers and actions.
 *
 * @return void
 */
function fields_logger_register_listeners() {

	// Record field during insufficient num times with the same hooks and callbacks.
	add_action( 'automator_recipe_process_user_trigger_num_times_insufficient', '\Uncanny_Automator\Logger\trigger_fields_logger', 10, 1 );
	add_action( 'automator_recipe_process_user_trigger_num_times_insufficient', '\Uncanny_Automator\Logger\recipe_triggers_logger', 20, 1 );
	add_action( 'automator_recipe_process_user_trigger_num_times_insufficient', '\Uncanny_Automator\Logger\recipe_actions_flow_logger', 30, 1 );
	add_action( 'automator_recipe_process_user_trigger_num_times_insufficient', '\Uncanny_Automator\Logger\closure_logger', 40, 1 );
	add_action( 'automator_recipe_process_user_trigger_num_times_insufficient', '\Uncanny_Automator\Logger\recipe_triggers_logic_logger', 50, 1 );

	// Record trigger - recipe_triggers_logger().
	// Record field during trigger completed - trigger_fields_logger().
	// Record actions through trigger completed because there can be multiple triggers "in-progress" - recipe_actions_flow_logger().
	add_action( 'automator_trigger_completed', '\Uncanny_Automator\Logger\recipe_triggers_logger', 10, 1 );
	add_action( 'automator_trigger_completed', '\Uncanny_Automator\Logger\trigger_fields_logger', 20, 1 );
	add_action( 'automator_trigger_completed', '\Uncanny_Automator\Logger\recipe_actions_flow_logger', 30, 1 );
	add_action( 'automator_trigger_completed', '\Uncanny_Automator\Logger\closure_logger', 40, 1 );
	add_action( 'automator_trigger_completed', '\Uncanny_Automator\Logger\recipe_triggers_logic_logger', 50, 1 );

	// Log all conditions error after the actions has been completed. Just before the closures begin executing.
	// Log all gathered async actions.
	add_action( 'automator_recipe_process_complete_complete_actions_before_closures', '\Uncanny_Automator\Logger\conditions_errors_logger', 10, 3 );
	add_action( 'automator_recipe_process_complete_complete_actions_before_closures', '\Uncanny_Automator\Logger\log_async_actions', 20, 4 );

	// Record field at the start of the action process.
	// Log tokens.
	add_action( 'automator_action_created', '\Uncanny_Automator\Logger\action_fields_logger', 10, 1 );
	add_action( 'automator_action_created', '\Uncanny_Automator\Logger\tokens_logger', 10, 1 );

	// Collects the async actions.
	add_action( 'automator_action_been_process_further', '\Uncanny_Automator\Logger\collect_async_actions' );

};
