<?php
/**
 * Action Executor - Production-grade reflection-based action execution.
 *
 * Executes ANY registered Automator action via reflection.
 * Supports all 4 action styles:
 *   - App_Action (92 actions)
 *   - Abstract Action (82 actions)
 *   - Trait-based (106 actions)
 *   - Legacy (57 actions)
 *
 * Total: 500+ agents with comprehensive error handling coverage.
 *
 * @package Uncanny_Automator\Api\Application\Sub_Tooling
 * @since   7.0.0
 */

namespace Uncanny_Automator\Api\Application\Sub_Tooling;

use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Code;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Field_Label_Resolver;
use Uncanny_Automator\Api\Services\Action\Utilities\Action_Validator;
use WP_Error;

/**
 * Class Action_Executor
 *
 * @since 7.0.0
 */
class Action_Executor {

	/**
	 * Banned action codes that cannot be executed via the AI agent.
	 *
	 * These actions are restricted for safety reasons. Each entry maps an action
	 * code to a human-readable reason that will be shown to the AI.
	 *
	 * @since 7.0.0
	 * @var array<string, string>
	 */
	private const BANNED_ACTIONS = array(
		'DB_QUERY_RUN_QUERY_STRING'  => 'Running raw database queries is restricted. Use the MySQL select tools (mysql_get_tables, mysql_get_table_columns, mysql_select_from_table) for read-only database operations.',
		'DB_QUERY_SELECT_QUERY_RUN'  => 'Use the MySQL select tools (mysql_get_tables, mysql_get_table_columns, mysql_select_from_table) instead.',
		'DELETEUSER'                 => 'Deleting users is restricted for safety.',
	);

	/**
	 * Current action code value object (for logging and downstream use).
	 *
	 * @var Action_Code|null
	 */
	private $action_code = null;

	/**
	 * Cached validator instance (lazy-loaded).
	 *
	 * @var Action_Validator|null
	 */
	private $validator = null;

	/**
	 * Execution start time for metrics.
	 *
	 * @var float
	 */
	private $execution_start = 0.0;

	/**
	 * Execute any action by code using reflection.
	 *
	 * @param string $action_code The action code (e.g., 'SENDEMAIL', 'SLACKSENDMESSAGE').
	 * @param array  $fields      Key-value field data. Keys must match action's option_code values.
	 * @param int    $user_id     User context for the action execution.
	 *
	 * @return array{success: bool, data: array, error?: string, tokens?: array, execution_time_ms?: float}|WP_Error
	 */
	public function run( string $action_code, array $fields, int $user_id ) {

		$this->execution_start = microtime( true );

		// Create Action_Code value object (validates format and length).
		try {
			$this->action_code = new Action_Code( $action_code );
		} catch ( \InvalidArgumentException $e ) {
			// Map exception message to appropriate error code.
			$error_code = 'invalid_action_code';
			if ( strpos( $e->getMessage(), 'empty' ) !== false ) {
				$error_code = 'empty_action_code';
			} elseif ( strpos( $e->getMessage(), 'uppercase' ) !== false ) {
				$error_code = 'invalid_action_code_format';
			}

			$this->log( 'error', 'Action code validation failed: ' . $e->getMessage() );
			return new WP_Error( $error_code, $e->getMessage() );
		}

		// Check if action is banned for AI execution.
		$ban_check = $this->check_action_banned( $this->action_code->get_value() );
		if ( is_wp_error( $ban_check ) ) {
			$this->log( 'warning', 'Banned action attempted: ' . $this->action_code->get_value() );
			return $ban_check;
		}

		// Validate user ID.
		$user_validation = $this->validate_user_id( $user_id );
		if ( is_wp_error( $user_validation ) ) {
			$this->log( 'error', 'User validation failed: ' . $user_validation->get_error_message() );
			return $user_validation;
		}

		// Validate fields against action schema BEFORE normalization (validator expects raw input).
		$field_validation = $this->validate_fields( $this->action_code->get_value(), $fields );
		if ( is_wp_error( $field_validation ) ) {
			$this->log( 'error', 'Field validation failed: ' . $field_validation->get_error_message() );
			return $field_validation;
		}

		// Ensure HTML format for TinyMCE fields (converts plain text newlines to <p> tags).
		$label_resolver       = new Field_Label_Resolver();
		$configuration_fields = $label_resolver->get_configuration_fields( $this->action_code->get_value(), 'actions' );
		$fields               = $label_resolver->ensure_html_format( $fields, $configuration_fields );

		// Normalize fields AFTER validation (JSON-encode arrays for repeater/multi-select fields).
		$fields = $this->normalize_fields( $fields );

		// Get action definition from registry.
		$definition = $this->get_action_definition( $this->action_code );
		if ( is_wp_error( $definition ) ) {
			$this->log( 'error', 'Definition not found: ' . $definition->get_error_message() );
			return $definition;
		}

		$action = $definition['instance'];

		$action_code_str = $this->action_code->get_value();

		/**
		 * Fires before action execution.
		 *
		 * @param string $action_code The action code.
		 * @param array  $fields      Field values provided.
		 * @param int    $user_id     User ID context.
		 * @param object $action      The action instance.
		 *
		 * @since 7.0.0
		 */
		do_action( 'automator_agent_before_execute', $action_code_str, $fields, $user_id, $action );

		// Route to appropriate execution method based on action style.
		if ( method_exists( $action, 'process_action' ) ) {
			$result = $this->invoke_modern_action( $action, $fields, $user_id );
		} else {
			$method = $definition['method'];
			if ( empty( $method ) || ! method_exists( $action, $method ) ) {
				return new WP_Error(
					'no_execution_method',
					/* translators: %s: Action code */
					sprintf( esc_html_x( "Action '%s' has no executable method", 'Error message when action has no execution method', 'uncanny-automator' ), esc_html( $action_code_str ) )
				);
			}
			$result = $this->invoke_legacy_action( $action, $method, $fields, $user_id );
		}

		// Add execution metrics.
		$result['execution_time_ms'] = round( ( microtime( true ) - $this->execution_start ) * 1000, 2 );

		/**
		 * Fires after action execution.
		 *
		 * @param string $action_code The action code.
		 * @param array  $fields      Field values provided.
		 * @param int    $user_id     User ID context.
		 * @param array  $result      Execution result.
		 *
		 * @since 7.0.0
		 */
		do_action( 'automator_agent_after_execute', $action_code_str, $fields, $user_id, $result );

		$this->log(
			$result['success'] ? 'info' : 'error',
			sprintf(
				'Execution %s (%.2fms)',
				$result['success'] ? 'succeeded' : 'failed',
				$result['execution_time_ms']
			)
		);

		return $result;
	}

	/**
	 * Validate user ID.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return true|WP_Error
	 */
	private function validate_user_id( int $user_id ) {

		if ( $user_id < 0 ) {
			return new WP_Error( 'invalid_user_id', 'User ID must be non-negative' );
		}

		// User ID 0 is valid for anonymous/system actions.
		if ( $user_id > 0 && ! get_user_by( 'id', $user_id ) ) {
			return new WP_Error( 'user_not_found', sprintf( 'User ID %d does not exist', $user_id ) );
		}

		return true;
	}

	/**
	 * Check if an action is banned from AI execution.
	 *
	 * Some actions are restricted for safety reasons (e.g., destructive operations).
	 * This method checks the BANNED_ACTIONS constant and returns an error if banned.
	 *
	 * @since 7.0.0
	 *
	 * @param string $action_code The action code to check.
	 *
	 * @return true|WP_Error True if allowed, WP_Error if banned.
	 */
	private function check_action_banned( string $action_code ) {

		// Allow filtering of banned actions for extensibility.
		$banned_actions = apply_filters( 'automator_agent_banned_actions', self::BANNED_ACTIONS );

		if ( ! isset( $banned_actions[ $action_code ] ) ) {
			return true;
		}

		$reason = $banned_actions[ $action_code ];

		// Build a helpful error message for the AI.
		$message = sprintf(
			/* translators: 1: Action code, 2: Reason for restriction */
			"Action '%s' is not available for AI execution. %s If you need to perform this operation, please guide the user to do it manually in WordPress admin or use a recipe instead.",
			$action_code,
			$reason
		);

		return new WP_Error( 'action_banned', $message );
	}

	/**
	 * Validate fields against action schema.
	 *
	 * Uses Action_Validator to check required fields, formats, and business rules.
	 *
	 * @param string $action_code Action code.
	 * @param array  $fields      Field values (raw, before normalization).
	 *
	 * @return true|WP_Error
	 */
	private function validate_fields( string $action_code, array $fields ) {

		$validator = $this->get_validator();
		$result    = $validator->validate( $action_code, $fields );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get or create validator instance (lazy-loaded singleton).
	 *
	 * @return Action_Validator
	 */
	private function get_validator(): Action_Validator {

		if ( null === $this->validator ) {
			$this->validator = new Action_Validator();
		}

		return $this->validator;
	}

	/**
	 * Normalize field values for action execution.
	 *
	 * Converts array values (repeater fields, multi-select) to JSON strings,
	 * which is the format Automator actions expect.
	 *
	 * @param array $fields Raw field values from AI.
	 *
	 * @return array Normalized fields with arrays JSON-encoded.
	 */
	private function normalize_fields( array $fields ): array {

		$normalized = array();

		foreach ( $fields as $key => $value ) {
			if ( is_array( $value ) ) {
				// Repeater fields and multi-select fields must be JSON strings.
				$normalized[ $key ] = wp_json_encode( $value );
			} else {
				$normalized[ $key ] = $value;
			}
		}

		return $normalized;
	}

	/**
	 * Get the action definition from registry.
	 *
	 * @param Action_Code $action_code Action code value object.
	 *
	 * @return array{instance: object, method: string|null}|WP_Error
	 */
	private function get_action_definition( Action_Code $action_code ) {

		if ( ! function_exists( 'Automator' ) ) {
			return new WP_Error( 'automator_not_loaded', 'Automator not initialized' );
		}

		$code       = $action_code->get_value();
		$definition = \Automator()->get_action( $code );

		if ( false === $definition || empty( $definition ) ) {
			return new WP_Error(
				'action_not_found',
				/* translators: %s: Action code */
				sprintf( esc_html_x( "Action '%s' not found in registry", 'Error message when action is not found', 'uncanny-automator' ), esc_html( $code ) )
			);
		}

		$execution_function = $definition['execution_function'] ?? null;

		if ( ! is_array( $execution_function ) || empty( $execution_function[0] ) ) {
			return new WP_Error(
				'invalid_execution_function',
				/* translators: %s: Action code */
				sprintf( esc_html_x( "Action '%s' has invalid execution_function", 'Error message when action has invalid execution function', 'uncanny-automator' ), esc_html( $code ) )
			);
		}

		$instance = $execution_function[0];
		$method   = $execution_function[1] ?? null;

		if ( ! is_object( $instance ) ) {
			return new WP_Error(
				'instance_not_object',
				/* translators: %s: Action code */
				sprintf( esc_html_x( "Action '%s' instance is not an object", 'Error message when action instance is invalid', 'uncanny-automator' ), esc_html( $code ) )
			);
		}

		return array(
			'instance' => $instance,
			'method'   => $method,
		);
	}

	/**
	 * Invoke modern action via process_action method.
	 *
	 * Modern actions (App_Action, Abstract Action, Trait-based) all have process_action().
	 * Signature: process_action($user_id, $action_data, $recipe_id, $args, $parsed)
	 *
	 * @param object $action  Action instance.
	 * @param array  $fields  Field values.
	 * @param int    $user_id User ID.
	 *
	 * @return array{success: bool, data: array, error?: string}
	 */
	private function invoke_modern_action( object $action, array $fields, int $user_id ): array {

		$action_data = $this->build_action_data( $fields );

		// Inject state into action instance via reflection.
		$this->inject_action_state( $action, $user_id, $action_data, $fields );

		try {
			$method = new \ReflectionMethod( $action, 'process_action' );

			// PHP < 8.1 requires setAccessible for protected/private methods.
			if ( $this->is_php_below_81() ) {
				$method->setAccessible( true );
			}

			// Invoke with standard 5-param signature.
			$result = $method->invoke( $action, $user_id, $action_data, 0, array(), $fields );

			return $this->build_response( $action, $result );

		} catch ( \ReflectionException $e ) {
			return array(
				'success' => false,
				'data'    => array(),
				'error'   => 'Reflection error: ' . $e->getMessage(),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'data'    => array(),
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Invoke legacy action method.
	 *
	 * Legacy actions use custom method names with 4-param signature:
	 * method_name($user_id, $action_data, $recipe_id, $args)
	 *
	 * Error handling: Legacy actions call Automator()->complete_action() with errors.
	 * We capture these via the 'automator_llm_action_error' hook.
	 *
	 * @param object $action  Action instance.
	 * @param string $method  Method name to call.
	 * @param array  $fields  Field values.
	 * @param int    $user_id User ID.
	 *
	 * @return array{success: bool, data: array, error?: string}
	 */
	private function invoke_legacy_action( object $action, string $method, array $fields, int $user_id ): array {

		$action_data = $this->build_action_data( $fields, true );

		// Use closure variable to avoid race conditions with instance property.
		// Each execution gets a unique UUID, allowing concurrent action executions
		// to correctly match errors to their originating action instance.
		$captured_error = null;
		$error_capture  = function ( $error_message, $error_action_data = null ) use ( &$captured_error, $action_data ) {
			// Only capture if this is OUR action (prevents cross-contamination in concurrent execution).
			// The UUID comparison ensures that if multiple actions execute simultaneously,
			// each error is attributed to the correct action instance, not a different one.
			if ( null === $error_action_data || ( isset( $error_action_data['execution_id'] ) && $error_action_data['execution_id'] === $action_data['execution_id'] ) ) {
				$captured_error = $error_message;
			}
		};
		add_action( 'automator_llm_action_error', $error_capture, 10, 2 );

		try {
			$reflection = new \ReflectionMethod( $action, $method );

			if ( $this->is_php_below_81() ) {
				$reflection->setAccessible( true );
			}

			// Legacy 4-param signature.
			$args   = array( 'from_llm' => true );
			$result = $reflection->invoke( $action, $user_id, $action_data, 0, $args );

			return $this->build_response( $action, $result, true, $captured_error );

		} catch ( \ReflectionException $e ) {
			return array(
				'success' => false,
				'data'    => array(),
				'error'   => 'Reflection error: ' . $e->getMessage(),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'data'    => array(),
				'error'   => $e->getMessage(),
			);
		} finally {
			// Always clean up hook to prevent memory leaks.
			remove_action( 'automator_llm_action_error', $error_capture, 10 );
		}
	}

	/**
	 * Build action_data array.
	 *
	 * @param array $fields     Field values.
	 * @param bool  $is_legacy  Whether this is a legacy action.
	 *
	 * @return array
	 */
	private function build_action_data( array $fields, bool $is_legacy = false ): array {

		$action_data = array(
			'ID'           => 0,
			'meta'         => $fields,
			'agent_mode'   => true,
			'execution_id' => wp_generate_uuid4(), // Unique ID to prevent race conditions.
		);

		// Legacy actions need from_llm flag in action_data for error capture.
		if ( $is_legacy ) {
			$action_data['from_llm'] = true;
		}

		return $action_data;
	}

	/**
	 * Inject state into action instance.
	 *
	 * Modern actions expect certain properties to be set before process_action() is called.
	 *
	 * @param object $action      Action instance.
	 * @param int    $user_id     User ID.
	 * @param array  $action_data Action data array.
	 * @param array  $fields      Parsed field values.
	 */
	private function inject_action_state( object $action, int $user_id, array $action_data, array $fields ): void {

		$critical_properties = array(
			'user_id'      => $user_id,
			'action_data'  => $action_data,
			'recipe_id'    => 0,
			'args'         => array(),
			'maybe_parsed' => $fields,
		);

		foreach ( $critical_properties as $name => $value ) {
			$set = $this->set_property( $action, $name, $value );

			// Log warning for critical properties that couldn't be set.
			if ( ! $set && in_array( $name, array( 'maybe_parsed', 'action_data' ), true ) ) {
				$this->log( 'warning', sprintf( 'Could not set critical property: %s', $name ) );
			}
		}
	}

	/**
	 * Build standardized response from action result.
	 *
	 * @param object      $action         Action instance.
	 * @param mixed       $result         Raw result from action method.
	 * @param bool        $is_legacy      Whether this is a legacy action.
	 * @param string|null $captured_error Error captured from legacy hook.
	 *
	 * @return array{success: bool, data: array, error?: string, tokens?: array}
	 */
	private function build_response( object $action, $result, bool $is_legacy = false, ?string $captured_error = null ): array {

		// Collect errors from all possible sources.
		$errors = $this->collect_errors( $action, $result, $is_legacy, $captured_error );

		// Determine success: no errors AND result is not explicitly false.
		$success = empty( $errors ) && false !== $result;

		$response = array(
			'success' => $success,
			'data'    => $this->normalize_result_data( $result ),
		);

		// Capture action tokens (from hydrate_tokens) - wrapped in try-catch for safety.
		$tokens = $this->get_action_tokens( $action );
		if ( ! empty( $tokens ) ) {
			$response['tokens'] = $tokens;
		}

		if ( ! empty( $errors ) ) {
			$response['error'] = $errors;
		}

		return $response;
	}

	/**
	 * Get action tokens from hydrate_tokens() call.
	 *
	 * Actions store output tokens in $this->dev_input via hydrate_tokens().
	 *
	 * @param object $action Action instance.
	 *
	 * @return array
	 */
	private function get_action_tokens( object $action ): array {

		try {
			$ref = new \ReflectionClass( $action );

			while ( $ref ) {
				if ( $ref->hasProperty( 'dev_input' ) ) {
					$prop = $ref->getProperty( 'dev_input' );

					if ( $this->is_php_below_81() ) {
						$prop->setAccessible( true );
					}

					$tokens = $prop->getValue( $action );
					return is_array( $tokens ) ? $tokens : array();
				}
				$ref = $ref->getParentClass();
			}
		} catch ( \ReflectionException $e ) {
			$this->log( 'warning', 'Failed to retrieve action tokens: ' . $e->getMessage() );
		}

		return array();
	}

	/**
	 * Collect errors from all possible sources.
	 *
	 * @param object      $action         Action instance.
	 * @param mixed       $result         Raw result.
	 * @param bool        $is_legacy      Is legacy action.
	 * @param string|null $captured_error Error from legacy hook.
	 *
	 * @return string Combined error message.
	 */
	private function collect_errors( object $action, $result, bool $is_legacy, ?string $captured_error = null ): string {

		$errors = array();

		// 1. Legacy error from hook (passed as parameter to avoid race conditions).
		if ( $is_legacy && ! empty( $captured_error ) ) {
			$errors[] = $captured_error;
		}

		// 2. Modern action errors via get_log_errors().
		if ( method_exists( $action, 'get_log_errors' ) ) {
			$log_errors = $action->get_log_errors();
			if ( ! empty( $log_errors ) ) {
				$errors[] = is_array( $log_errors ) ? implode( '; ', $log_errors ) : $log_errors;
			}
		}

		// 3. WP_Error result.
		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
		}

		return implode( ' | ', array_filter( $errors ) );
	}

	/**
	 * Normalize result data to array.
	 *
	 * @param mixed $result Raw result.
	 *
	 * @return array
	 */
	private function normalize_result_data( $result ): array {

		if ( is_array( $result ) ) {
			return $result;
		}

		if ( is_wp_error( $result ) ) {
			return array(
				'wp_error_code' => $result->get_error_code(),
				'wp_error_data' => $result->get_error_data(),
			);
		}

		if ( null === $result || true === $result ) {
			return array();
		}

		if ( is_scalar( $result ) ) {
			return array( 'result' => $result );
		}

		if ( is_object( $result ) ) {
			return array( 'result' => get_class( $result ) );
		}

		return array();
	}

	/**
	 * Set property via reflection, traversing parent classes.
	 *
	 * @param object $obj   Object instance.
	 * @param string $name  Property name.
	 * @param mixed  $value Value to set.
	 *
	 * @return bool True if property was set, false otherwise.
	 */
	private function set_property( object $obj, string $name, $value ): bool {

		try {
			$ref = new \ReflectionClass( $obj );

			// Traverse class hierarchy to find property.
			while ( $ref ) {
				if ( $ref->hasProperty( $name ) ) {
					$prop = $ref->getProperty( $name );

					if ( $this->is_php_below_81() ) {
						$prop->setAccessible( true );
					}

					$prop->setValue( $obj, $value );
					return true;
				}
				$ref = $ref->getParentClass();
			}
		} catch ( \ReflectionException $e ) {
			$this->log( 'warning', sprintf( 'Failed to set property %s: %s', $name, $e->getMessage() ) );
		}

		return false;
	}

	/**
	 * Check if PHP version is below 8.1.
	 *
	 * PHP 8.1+ allows accessing private/protected properties via reflection
	 * without calling setAccessible(). This method centralizes the check.
	 *
	 * @return bool True if PHP version is below 8.1.
	 */
	private function is_php_below_81(): bool {
		return PHP_VERSION_ID < 80100;
	}

	/**
	 * Log message with context.
	 *
	 * @param string $level   Log level (info, error, warning, debug).
	 * @param string $message Message to log.
	 */
	private function log( string $level, string $message ): void {

		if ( ! function_exists( 'automator_log' ) ) {
			return;
		}

		$code   = $this->action_code ? $this->action_code->get_value() : 'UNKNOWN';
		$prefix = sprintf( '[ActionExecutor:%s] ', $code );

		automator_log( $prefix . $message, ucfirst( $level ) );
	}
}
