<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Stages;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Stages\Stage;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Integration_Registry;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Data_Provider;
use Uncanny_Automator\App\Recipe_Runner\Services\Action_Error_Classifier;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Status_Resolver;
use Uncanny_Automator\App\Recipe_Runner\Services\Run_Number_Service;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\App\Bridge\Automator_Action_Tokens_Bridge;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Stage 4: Recipe Completion.
 *
 * Finalizes recipe status, handles error classification, runs closures,
 * and hydrates action tokens.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Stages
 * @since   7.2
 */
class Recipe_Complete_Stage implements Stage {

	/**
	 * @var Run_Number_Service|null
	 */
	private $run_number;

	/**
	 * @var Recipe_Status_Resolver|null
	 */
	private $resolver;

	/**
	 * @var Execution_Log_Store
	 */
	private $log_store;

	/**
	 * @var Integration_Registry
	 */
	private $integrations;

	/**
	 * @var Recipe_Data_Provider
	 */
	private $data_provider;

	/**
	 * @var Action_Error_Store
	 */
	private $error_store;

	/**
	 * @var Closure_Stage
	 */
	private Closure_Stage $closure_stage;

	/**
	 * @param Execution_Log_Store|null  $log_store      Optional log store instance.
	 * @param Integration_Registry|null $integrations   Optional integration registry.
	 * @param Recipe_Data_Provider|null $data_provider  Optional data provider instance.
	 * @param Action_Error_Store|null   $error_store    Optional error store instance.
	 * @param Closure_Stage|null        $closure_stage  Optional closure stage for delegation.
	 * @param Run_Number_Service|null   $run_number     Optional shared run number service.
	 */
	public function __construct(
		?Execution_Log_Store $log_store = null,
		?Integration_Registry $integrations = null,
		?Recipe_Data_Provider $data_provider = null,
		?Action_Error_Store $error_store = null,
		?Closure_Stage $closure_stage = null,
		?Run_Number_Service $run_number = null
	) {
		$this->log_store      = $log_store ?? Database::get_execution_log_store();
		$this->integrations   = $integrations ?? new Integration_Registry();
		$this->data_provider  = $data_provider ?? new Recipe_Data_Provider();
		$this->error_store    = $error_store ?? Database::get_action_error_store();
		$this->closure_stage  = $closure_stage ?? new Closure_Stage( $this->log_store, $this->integrations, $this->data_provider );
		$this->run_number     = $run_number;
	}

	/**
	 * @return Run_Number_Service
	 */
	private function run_number(): Run_Number_Service {
		$this->run_number = $this->run_number ?? new Run_Number_Service();
		return $this->run_number;
	}

	/**
	 * @return Recipe_Status_Resolver
	 */
	private function resolver(): Recipe_Status_Resolver {
		$this->resolver = $this->resolver ?? new Recipe_Status_Resolver();
		return $this->resolver;
	}

	/**
	 * Execute the recipe completion stage (passthrough).
	 *
	 * In the current architecture, recipe completion is triggered by
	 * Action_Run_Stage::complete_actions() → action() → recipe() after
	 * all actions finish. This stage serves as the extraction point for
	 * future standalone pipeline use.
	 *
	 * @param Pipeline_Context $context Immutable pipeline context.
	 * @param Pipeline_Result  $result  Accumulated results from prior stages.
	 *
	 * @return Pipeline_Result
	 */
	public function execute( Pipeline_Context $context, Pipeline_Result $result ): Pipeline_Result {

		if ( ! $result->is_execution_ready() ) {
			return $result;
		}

		// If async work was dispatched (loops, delays), don't finalize now.
		// The async completion handler will call finalize_recipe() when done.
		if ( $result->has_async_work() ) {
			return $result;
		}

		$recipe_id     = $result->get_recipe_id();
		$user_id       = $result->get_user_id();
		$recipe_log_id = $result->get_recipe_log_id();
		$args          = $result->get_execution_args();

		$status = $this->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, $args );

		if ( null !== $status ) {
			$result->set_recipe_status( $status );
		}

		return $result;
	}

	/**
	 * Complete an action — mark its status and trigger recipe completion.
	 *
	 * @param int|null    $user_id       The user ID.
	 * @param array|null  $action_data   The action data.
	 * @param int|null    $recipe_id     The recipe ID.
	 * @param string      $error_message Error message.
	 * @param int|null    $recipe_log_id The recipe log ID.
	 * @param array       $args          Trigger args.
	 *
	 * @return bool|null
	 */
	public function action( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {

		$user_id = (int) ( $user_id ?? get_current_user_id() );

		if ( $this->is_llm_mode( $action_data, $error_message, $user_id ) ) {
			return empty( $error_message );
		}

		$validated = $this->validate_action_params( $action_data, $recipe_id, $recipe_log_id, $args );

		if ( null === $validated ) {
			return null;
		}

		$recipe_log_id = $validated['recipe_log_id'];
		$args          = $validated['args'];

		// 1. Resolve status + fire pre-completion hook.
		$action_data['completed'] = $this->get_action_completed_status( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );

		Dispatcher::action(
			'automator_before_action_completed',
			array(
				'user_id'       => $user_id,
				'action_id'     => (int) $action_data['ID'],
				'recipe_id'     => $recipe_id,
				'error_message' => $error_message,
				'recipe_log_id' => $recipe_log_id,
				'args'          => $args,
			)
		);

		// 2. Resolve error message + allow callers to abort.
		$error_message = $this->get_action_error_message( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );

		$process_further = Dispatcher::filter( 'automator_before_action_created', true, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );

		if ( ! $process_further ) {
			return null;
		}

		// 3. Persist action log + errors.
		$this->persist_action_log( $action_data, $recipe_log_id, $error_message );

		// 4. Fire post-action hooks + hydrate tokens.
		$do_action_args = $this->build_action_event_args( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );

		Dispatcher::action( 'automator_action_created', $do_action_args );

		if ( ! empty( $action_data['complete_with_notice'] ) ) {
			$args['complete_with_notice'] = true;
		}

		self::action_tokens_hydrate_default( $do_action_args );

		return true;
	}

	/**
	 * Persist action completion status and structured errors.
	 *
	 * Marks the action complete in the log store and writes any structured
	 * errors to uap_error_log. If no structured errors exist but an error
	 * message was provided, infers a structured error from the message.
	 *
	 * @param array  $action_data   The action data (must contain 'completed' key).
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $error_message The resolved error message.
	 *
	 * @return void
	 */
	private function persist_action_log( array $action_data, int $recipe_log_id, string $error_message ): void {

		$this->log_store->mark_action_complete( (int) $action_data['ID'], $recipe_log_id, $action_data['completed'] );

		$structured_errors = $action_data['structured_errors'] ?? array();

		// Fallback: if action has errors but no structured errors were created
		// (e.g. process_action() returned false without calling add_log_error()),
		// create one from the error message so every error gets logged.
		if ( empty( $structured_errors ) && ! empty( $error_message ) ) {
			$clean_message = wp_specialchars_decode( $error_message, ENT_QUOTES );
			$inferred_code = Error_Code::infer_from_message( $clean_message );
			$context       = array(
				'action_code'      => $action_data['meta']['code'] ?? '',
				'integration_code' => $action_data['meta']['integration'] ?? '',
				'action_id'        => $action_data['ID'] ?? 0,
			);

			$structured_errors = array(
				new Action_Error( $inferred_code, $clean_message, $context ),
			);
		}

		if ( ! empty( $structured_errors ) ) {
			$action_log_id = isset( $action_data['action_log_id'] ) ? absint( $action_data['action_log_id'] ) : 0;
			foreach ( $structured_errors as $structured_error ) {
				$this->error_store->store( $recipe_log_id, $action_log_id, $structured_error );
			}
		}
	}

	/**
	 * Build the event args array for post-action hooks.
	 *
	 * @param int    $user_id       The user ID.
	 * @param array  $action_data   The action data.
	 * @param int    $recipe_id     The recipe ID.
	 * @param string $error_message The error message.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param array  $args          Trigger args.
	 *
	 * @return array
	 */
	private function build_action_event_args( int $user_id, array $action_data, $recipe_id, string $error_message, int $recipe_log_id, array $args ): array {
		return array(
			'user_id'       => $user_id,
			'action_id'     => (int) $action_data['ID'],
			'action_data'   => $action_data,
			'action_log_id' => isset( $action_data['action_log_id'] ) ? absint( $action_data['action_log_id'] ) : null,
			'recipe_id'     => $recipe_id,
			'error_message' => $error_message,
			'recipe_log_id' => $recipe_log_id,
			'args'          => $args,
		);
	}

	/**
	 * Complete a recipe.
	 *
	 * @deprecated 7.3 Use finalize_recipe() instead. This method uses the old
	 *             determine_recipe_status() + maybe_escalate_action_errors() pattern
	 *             which causes N writes per recipe. Kept for backward compat with
	 *             Pro's loop completion (process-hooks-callbacks.php:285) and
	 *             background action handlers until they switch to finalize_recipe().
	 *
	 * @param int|null $recipe_id     The recipe ID.
	 * @param int|null $user_id       The user ID.
	 * @param int|null $recipe_log_id The recipe log ID.
	 * @param array    $args          Trigger args.
	 *
	 * @return bool|null
	 */
	public function recipe( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array() ) {

		$recipe_id     = null !== $recipe_id ? (int) $recipe_id : null;
		$user_id       = null !== $user_id ? (int) $user_id : null;
		$recipe_log_id = null !== $recipe_log_id ? (int) $recipe_log_id : null;

		$run_number              = $this->run_number()->get_current( (int) $recipe_id, (int) $user_id );
		$scheduled_actions_count = $this->log_store->get_scheduled_actions_count( (int) $recipe_log_id, $args );
		$completed               = $this->determine_recipe_status( $scheduled_actions_count, $args, $run_number );

		Dispatcher::action( 'automator_before_recipe_completed', $recipe_id, $user_id, $recipe_log_id, $args );

		$recipe_log_id = $this->persist_recipe_log( $recipe_id, $user_id, $recipe_log_id, $completed, $run_number, $args );

		if ( null === $recipe_log_id ) {
			return null;
		}

		$this->maybe_escalate_action_errors( $recipe_id, $user_id, $recipe_log_id, $completed, $scheduled_actions_count, $args );

		Dispatcher::action( 'automator_recipe_completed', $recipe_id, $user_id, $recipe_log_id, $args );

		return true;
	}

	/**
	 * Finalize recipe status using the resolver — ONE status computation, ONE DB write.
	 *
	 * Replaces the scattered recipe() + maybe_escalate_action_errors() pattern.
	 * Used by Stage 4 execute(), background action handlers, and loop completion.
	 *
	 * @param int   $recipe_id                 The recipe ID.
	 * @param int   $user_id                   The user ID.
	 * @param int   $recipe_log_id             The recipe log ID.
	 * @param array $args                      Recipe args.
	 * @param bool  $treat_incomplete_as_error When true, a NOT_COMPLETED action row is treated as
	 *                                         a stuck error. Set only by Stuck_Recipe_Recovery;
	 *                                         the live finalize path leaves it false so a transient
	 *                                         NOT_COMPLETED (action mid-completion) is not escalated.
	 *
	 * @return int|null Resolved Automator_Status constant, or null on persist failure.
	 */
	public function finalize_recipe( int $recipe_id, int $user_id, int $recipe_log_id, array $args = array(), bool $treat_incomplete_as_error = false ) {

		$run_number     = $this->run_number()->get_current( $recipe_id, $user_id );
		$completed      = $this->resolver()->resolve( $recipe_log_id, $args, $treat_incomplete_as_error );
		$current_status = $this->get_current_recipe_status( $recipe_log_id );

		// Severity guard — never downgrade terminal statuses.
		// Transient statuses (IN_PROGRESS, QUEUED, etc.) are always overwritable.
		if ( null !== $current_status && $this->resolver()->is_downgrade( $current_status, $completed ) ) {
			$completed = $current_status;
		}

		if ( Automator_Status::DID_NOTHING === $completed ) {
			$run_number = 1;
		}

		Dispatcher::action( 'automator_before_recipe_completed', $recipe_id, $user_id, $recipe_log_id, $args );

		$recipe_log_id = $this->persist_recipe_log( $recipe_id, $user_id, $recipe_log_id, $completed, $run_number, $args );

		if ( null === $recipe_log_id ) {
			return null;
		}

		Dispatcher::action( 'automator_recipe_completed', $recipe_id, $user_id, $recipe_log_id, $args );

		if ( Automator_Status::COMPLETED_WITH_ERRORS === $completed ) {
			Dispatcher::action( 'automator_recipe_completed_with_errors', $recipe_id, $user_id, $recipe_log_id, $args );
		}

		return $completed;
	}

	/**
	 * Run closures for a recipe.
	 *
	 * @deprecated 7.3 Closures now run in Closure_Stage (Stage 5) after recipe
	 *             status is finalized. This method is kept for backward compat
	 *             with the facade (Automator()->complete->closures()) and Pro's
	 *             loop entry point.
	 *
	 * @param int|null   $recipe_id           The recipe ID.
	 * @param int|null   $user_id             The user ID.
	 * @param int|null   $recipe_log_id       The recipe log ID.
	 * @param array      $args                Trigger args.
	 * @param array|null $recipe_closure_data Pre-fetched closure data.
	 *
	 * @return bool
	 */
	public function closures( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array(), $recipe_closure_data = null ): bool {
		$this->closure_stage->run_closures( $recipe_closure_data, $recipe_id, $user_id, $recipe_log_id, $args );
		return true;
	}

	/**
	 * Get action completed status.
	 *
	 * @param int|null $user_id       The user ID.
	 * @param array    $action_data   The action data.
	 * @param int|null $recipe_id     The recipe ID.
	 * @param string   $error_message Error message.
	 * @param int|null $recipe_log_id The recipe log ID.
	 * @param array    $args          Trigger args.
	 *
	 * @return int
	 */
	public function get_action_completed_status( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ): int {

		$completed = $this->resolve_action_status( $action_data, $error_message );

		return (int) Dispatcher::filter( 'automator_get_action_completed_status', $completed, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
	}

	/**
	 * Get action error message.
	 *
	 * @param int|null $user_id       The user ID.
	 * @param array    $action_data   The action data.
	 * @param int|null $recipe_id     The recipe ID.
	 * @param string   $error_message Error message.
	 * @param int|null $recipe_log_id The recipe log ID.
	 * @param array    $args          Trigger args.
	 *
	 * @return string
	 */
	public function get_action_error_message( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ): string {

		$message = '';

		$has_error_keys = array_key_exists( 'complete_with_errors', $action_data )
			|| array_key_exists( 'complete_with_notice', $action_data )
			|| array_key_exists( 'do-nothing', $action_data );

		if ( ! empty( $error_message ) && $has_error_keys ) {
			$message = $error_message;
		}

		if ( array_key_exists( 'user_action_message', $args ) && ! empty( $args['user_action_message'] ) ) {
			$message = $args['user_action_message'];

			if ( ! empty( $error_message ) && $message !== $error_message ) {
				$message .= ' &mdash; ' . $error_message;
			}
		}

		return (string) Dispatcher::filter( 'automator_get_action_error_message', $message, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
	}

	/**
	 * Find the first actionable error from all actions in a recipe.
	 *
	 * @deprecated 7.3 Use Action_Error_Classifier::find_actionable_error() directly.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return object|null
	 */
	public static function find_actionable_error( $recipe_log_id ): ?object {
		return Action_Error_Classifier::find_actionable_error( $recipe_log_id );
	}

	/**
	 * Determine whether an action error should update the recipe status.
	 *
	 * @deprecated 7.3 Use Action_Error_Classifier::is_actionable_error() directly.
	 *
	 * @param object $action_error Object with error_message and completed properties.
	 *
	 * @return bool
	 */
	public static function is_actionable_error( $action_error ): bool {
		return Action_Error_Classifier::is_actionable_error( $action_error );
	}

	/**
	 * Check if error message is from failed condition block.
	 *
	 * @deprecated 7.3 Use Action_Error_Classifier::is_condition_block_failed_message() directly.
	 *
	 * @param string|null $error_message The error message.
	 *
	 * @return bool
	 */
	public static function is_condition_block_failed_message( ?string $error_message ): bool {
		return Action_Error_Classifier::is_condition_block_failed_message( $error_message );
	}

	/**
	 * Check if error message is from user selector user creation.
	 *
	 * @deprecated 7.3 Use Action_Error_Classifier::is_user_selector_user_creation_message() directly.
	 *
	 * @param string|null $error_message The error message.
	 *
	 * @return bool
	 */
	public static function is_user_selector_user_creation_message( ?string $error_message ): bool {
		return Action_Error_Classifier::is_user_selector_user_creation_message( $error_message );
	}

	/**
	 * Check if error message is from user selector matching.
	 *
	 * @deprecated 7.3 Use Action_Error_Classifier::is_user_selector_matching_message() directly.
	 *
	 * @param string|null $error_message The error message.
	 *
	 * @return bool
	 */
	public static function is_user_selector_matching_message( ?string $error_message ): bool {
		return Action_Error_Classifier::is_user_selector_matching_message( $error_message );
	}

	/**
	 * Hydrate default action tokens.
	 *
	 * @param array $process_args The process args.
	 *
	 * @return mixed
	 */
	public static function action_tokens_hydrate_default( array $process_args ) {

		$action_log_id = $process_args['action_log_id'] ?? null;
		$user_id       = $process_args['user_id'] ?? null;
		$action_id     = $process_args['action_id'] ?? null;

		if ( ! isset( $user_id, $action_id, $action_log_id ) ) {
			return false;
		}

		$status = ( Database::get_execution_log_store() )->get_action_completion_status( $action_log_id );

		// Concrete class in static context — can't use DI. Facade calls this statically.
		$bridge = new Automator_Action_Tokens_Bridge();

		return $bridge->hydrate_action_tokens(
			(int) $user_id,
			(int) $action_id,
			(int) $action_log_id,
			$process_args,
			array( 'ACTION_RUN_STATUS' => Automator_Status::name( $status ) )
		);
	}

	// ── Protected helpers ──

	/**
	 * Handle LLM/Agent mode — skip recipe logging, just capture error.
	 *
	 * @param array|null $action_data   The action data.
	 * @param string     $error_message The error message.
	 * @param int        $user_id       The user ID.
	 *
	 * @return bool Whether this is LLM mode (caller should return early).
	 */
	protected function is_llm_mode( $action_data, ?string $error_message, int $user_id ): bool {

		if ( empty( $action_data['from_llm'] ) ) {
			return false;
		}

		if ( ! empty( $error_message ) ) {
			Dispatcher::action( 'automator_llm_action_error', $error_message, $action_data, $user_id );
		}

		return true;
	}

	/**
	 * Validate and resolve action parameters.
	 *
	 * @param array|null $action_data   The action data.
	 * @param int|null   $recipe_id     The recipe ID.
	 * @param int|null   $recipe_log_id The recipe log ID.
	 * @param array      $args          Trigger args.
	 *
	 * @return array|null Resolved params or null if invalid.
	 */
	protected function validate_action_params( $action_data, $recipe_id, $recipe_log_id, array $args ): ?array {

		if ( null === $action_data || ! is_numeric( $action_data['ID'] ?? null ) ) {
			return null;
		}

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			return null;
		}

		$recipe_log_id = $recipe_log_id ?? ( $action_data['recipe_log_id'] ?? null );

		if ( empty( $recipe_log_id ) ) {
			return null;
		}

		$recipe_log_id = absint( $recipe_log_id );

		if ( empty( $args ) && array_key_exists( 'args', $action_data ) ) {
			$args = $action_data['args'];
		}

		return array(
			'recipe_log_id' => $recipe_log_id,
			'args'          => $args,
		);
	}

	/**
	 * Resolve action completion status from action data and error state.
	 *
	 * Priority (last match wins): notice → errors → do-nothing → empty-error=COMPLETED.
	 *
	 * @param array|null $action_data   The action data.
	 * @param string     $error_message The error message.
	 *
	 * @return int
	 */
	protected function resolve_action_status( $action_data, ?string $error_message ): int {

		if ( ! is_array( $action_data ) ) {
			return Automator_Status::NOT_COMPLETED;
		}

		// Priority order: do-nothing > errors > notice > completed.
		// Status is derived from FLAGS, not from error message content.
		// $error_message is kept in the signature for backward compat but not read.
		//
		// do-nothing uses key_exists (presence = intent to skip, even if falsy).
		// complete_with_errors / complete_with_notice use !empty (must be truthy).
		// abstract-action.php:362 always sets complete_with_errors = true on failure,
		// so the absence of the flag genuinely means "didn't fail."

		if ( array_key_exists( 'do-nothing', $action_data ) || array_key_exists( 'do_nothing', $action_data ) ) {
			return Automator_Status::DID_NOTHING;
		}

		if ( ! empty( $action_data['complete_with_errors'] ) ) {
			return Automator_Status::COMPLETED_WITH_ERRORS;
		}

		if ( ! empty( $action_data['complete_with_notice'] ) ) {
			return Automator_Status::COMPLETED_WITH_NOTICE;
		}

		return Automator_Status::COMPLETED;
	}

	/**
	 * Determine recipe completion status from scheduled actions and args flags.
	 *
	 * @deprecated 7.3 Use Recipe_Status_Resolver::resolve() instead. This method
	 *             is stateless (never reads DB) and causes incorrect status when
	 *             used alongside maybe_escalate_action_errors(). Kept for backward
	 *             compat with recipe() which Pro's loop completion still calls.
	 *
	 * @param int   $scheduled_count Number of scheduled actions.
	 * @param array $args            Trigger args.
	 * @param int   $run_number      Current run number (may be overridden).
	 *
	 * @return int
	 */
	protected function determine_recipe_status( int $scheduled_count, array $args, int &$run_number ): int {

		if ( $scheduled_count > 0 ) {
			return Automator_Status::IN_PROGRESS;
		}

		if ( array_key_exists( 'do-nothing', $args ) ) {
			$run_number = 1;
			return Automator_Status::DID_NOTHING;
		}

		if ( array_key_exists( 'complete_with_notice', $args ) ) {
			return Automator_Status::COMPLETED_WITH_NOTICE;
		}

		return Automator_Status::COMPLETED;
	}

	/**
	 * Persist recipe log — create or update.
	 *
	 * @param int|null $recipe_id     The recipe ID.
	 * @param int|null $user_id       The user ID.
	 * @param int|null $recipe_log_id The recipe log ID.
	 * @param int      $completed     The completion status.
	 * @param int      $run_number    The run number.
	 * @param array    $args          Trigger args.
	 *
	 * @return int|null The recipe log ID.
	 */
	protected function persist_recipe_log( $recipe_id, $user_id, $recipe_log_id, int $completed, int $run_number, array $args ): ?int {

		if ( null !== $recipe_log_id ) {
			$completed = Dispatcher::filter( 'automator_recipe_process_complete_status', $completed, $args );

			// Note: finalize_recipe() already applies the severity guard before
			// calling this method. This second guard catches filter-induced
			// downgrades from automator_recipe_process_complete_status above.
			// The deprecated recipe() path also relies on this guard.
			$current_status = $this->get_current_recipe_status( (int) $recipe_log_id );
			if ( null !== $current_status && $this->resolver()->is_downgrade( $current_status, $completed ) ) {
				$completed = $current_status;
			}
			$this->log_store->mark_recipe_complete( $recipe_log_id, $completed );
			return $recipe_log_id;
		}

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			return null;
		}

		return $this->log_store->add_recipe_log( $user_id, $recipe_id, $completed, $run_number );
	}

	/**
	 * Read the current recipe status from the database.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return int|null The current status, or null if no record exists.
	 */
	protected function get_current_recipe_status( int $recipe_log_id ): ?int {
		return $this->log_store->get_recipe_status( $recipe_log_id );
	}

	/**
	 * Check action errors and escalate to recipe status if actionable.
	 *
	 * @deprecated 7.3 Replaced by Recipe_Status_Resolver::resolve() which computes
	 *             the correct status in one pass. This method is the source of the
	 *             double-write bug — it patches the status AFTER persist_recipe_log()
	 *             already wrote it. Kept for backward compat with recipe().
	 *
	 * @param int   $recipe_id              The recipe ID.
	 * @param int   $user_id                The user ID.
	 * @param int   $recipe_log_id          The recipe log ID.
	 * @param int   $completed              Current completion status.
	 * @param int   $scheduled_actions_count Scheduled actions count.
	 * @param array $args                   Trigger args (mutated with error info).
	 *
	 * @return void
	 */
	protected function maybe_escalate_action_errors( int $recipe_id, int $user_id, int $recipe_log_id, int $completed, int $scheduled_actions_count, array &$args ): void {

		$actionable_error = self::find_actionable_error( $recipe_log_id );

		if ( null === $actionable_error ) {
			return;
		}

		$error_status = Automator_Status::DID_NOTHING === absint( $completed )
			? Automator_Status::DID_NOTHING
			: (int) $actionable_error->completed;

		if ( $scheduled_actions_count > 0 ) {
			$error_status = Automator_Status::IN_PROGRESS_WITH_ERROR;
		}

		$this->log_store->mark_recipe_complete_with_error( $recipe_id, $recipe_log_id, $error_status );

		$args['message']   = $actionable_error->error_message;
		$args['completed'] = $actionable_error->completed;

		if ( Automator_Status::COMPLETED_WITH_ERRORS === absint( $error_status ) ) {
			Dispatcher::action( 'automator_recipe_completed_with_errors', $recipe_id, $user_id, $recipe_log_id, $args );
		}
	}

}
