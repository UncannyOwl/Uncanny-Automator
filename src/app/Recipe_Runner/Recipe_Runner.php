<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Pipeline_Exception;
use Uncanny_Automator\App\Recipe_Runner\Services\Loop_Processor;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Stages\Stage;
use Uncanny_Automator\App\Recipe_Runner\Services\User_Resolver;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Handler;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Run_Snapshot_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Idempotency_Guard;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\App\Recipe_Runner\Services\Integration_Registry;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Completion_Service;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Data_Provider;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Log_Manager;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Throttle_Service;
use Uncanny_Automator\App\Recipe_Runner\Services\Replay_Service;
use Uncanny_Automator\App\Recipe_Runner\Services\Run_Number_Service;
use Uncanny_Automator\App\Recipe_Runner\Services\Trigger_Numtimes;
use Uncanny_Automator\App\Recipe_Runner\Services\Trigger_Validator;
use Uncanny_Automator\App\Recipe_Runner\Stages\Action_Run_Stage;
use Uncanny_Automator\App\Recipe_Runner\Stages\Closure_Stage;
use Uncanny_Automator\App\Recipe_Runner\Stages\Recipe_Complete_Stage;
use Uncanny_Automator\App\Recipe_Runner\Stages\Trigger_Complete_Stage;
use Uncanny_Automator\App\Recipe_Runner\Stages\Trigger_Entry_Stage;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Unified recipe execution pipeline.
 *
 * Composes all stages of recipe execution into a single entry point:
 * trigger entry -> trigger completion -> action execution -> recipe completion.
 *
 * Uses the pipeline/workflow pattern (forward-only, no rollback).
 * 90% of Automator actions are irreversible (emails, API calls, user creation).
 *
 * @package Uncanny_Automator\App\Recipe_Runner
 * @since   7.2
 */
class Recipe_Runner {

	/**
	 * @var Stage[] Ordered pipeline stages.
	 */
	private $stages = array();

	/**
	 * @var Trigger_Validator
	 */
	private $validator;

	/**
	 * @var Recipe_Log_Manager
	 */
	private $log_manager;

	/**
	 * @var Trigger_Numtimes
	 */
	private $numtimes;

	/**
	 * @var Trigger_Entry_Stage
	 */
	private $trigger_entry;

	/**
	 * @var Trigger_Complete_Stage
	 */
	private $trigger_complete;

	/**
	 * @var Action_Run_Stage
	 */
	private $action_run;

	/**
	 * @var Recipe_Complete_Stage
	 */
	private $recipe_complete;

	/**
	 * @var Closure_Stage
	 */
	private $closure;

	/**
	 * @var Recipe_Completion_Service|null
	 */
	private $completion;

	/**
	 * @var Recipe_Throttle_Service|null
	 */
	private $throttle;

	/**
	 * @var Run_Number_Service|null
	 */
	private $run_number_svc;

	/**
	 * @var User_Resolver|null Pro injects this via set_user_resolver().
	 */
	private $user_resolver;

	/**
	 * @var Loop_Processor|null Pro injects this via set_loop_processor().
	 */
	private $loop_processor;

	/**
	 * @var Execution_Log_Store
	 */
	private $log_store;

	/**
	 * @var Integration_Registry
	 */
	private $integration_registry;

	/**
	 * @var Recipe_Data_Provider
	 */
	private $data_provider;

	/**
	 * @var Error_Handler
	 */
	private $error_handler;

	/**
	 * @var Action_Error_Store
	 */
	private $error_store;

	/**
	 * @var Run_Snapshot_Store
	 */
	private $snapshot_store;

	/**
	 * @var Replay_Service|null
	 */
	private $replay_service;

	/**
	 * Build the pipeline with default stages.
	 *
	 * @param Trigger_Validator|null      $validator       Optional custom validator.
	 * @param Recipe_Log_Manager|null     $log_manager     Optional custom log manager.
	 * @param Trigger_Numtimes|null       $numtimes        Optional custom numtimes service.
	 * @param Trigger_Entry_Stage|null    $trigger_entry   Optional custom trigger entry stage.
	 * @param Trigger_Complete_Stage|null $trigger_complete Optional custom trigger complete stage.
	 * @param Action_Run_Stage|null       $action_run      Optional custom action run stage.
	 * @param Recipe_Complete_Stage|null  $recipe_complete  Optional custom recipe complete stage.
	 */
	public function __construct(
		?Trigger_Validator $validator = null,
		?Recipe_Log_Manager $log_manager = null,
		?Trigger_Numtimes $numtimes = null,
		?Trigger_Entry_Stage $trigger_entry = null,
		?Trigger_Complete_Stage $trigger_complete = null,
		?Action_Run_Stage $action_run = null,
		?Recipe_Complete_Stage $recipe_complete = null,
		?Execution_Log_Store $log_store = null
	) {
		$this->log_store            = $log_store ?? Database::get_execution_log_store();
		$this->integration_registry = new Integration_Registry();
		$this->data_provider        = new Recipe_Data_Provider();
		$this->error_handler        = new Error_Handler();
		$this->error_store          = Database::get_action_error_store();
		$this->snapshot_store       = Database::get_run_snapshot_store();
		$this->validator            = $validator ?? new Trigger_Validator( $this->log_store, $this->integration_registry, $this->data_provider, $this->error_handler );
		$this->run_number_svc       = new Run_Number_Service( $this->log_store );
		$this->log_manager          = $log_manager ?? new Recipe_Log_Manager( $this->log_store, $this->data_provider, new Idempotency_Guard(), $this->run_number_svc );
		$this->numtimes             = $numtimes ?? new Trigger_Numtimes( $this->log_manager, $this->run_number_svc, $this->log_store, $this->data_provider );

		$this->trigger_entry = $trigger_entry ?? new Trigger_Entry_Stage(
			$this->validator,
			$this->log_manager,
			$this->numtimes,
			$this->completion(),
			$this->throttle(),
			$this->run_number_svc
		);

		// Create recipe_complete first — action_run depends on it for error routing.
		// Then action_run — trigger_complete depends on action_run.
		$this->recipe_complete  = $recipe_complete ?? new Recipe_Complete_Stage( $this->log_store, $this->integration_registry, $this->data_provider, $this->error_store, null, $this->run_number_svc );
		$this->action_run       = $action_run ?? new Action_Run_Stage( $this->recipe_complete, $this->loop_processor, $this->log_store, $this->integration_registry, $this->data_provider, $this->error_handler );
		$this->trigger_complete = $trigger_complete ?? new Trigger_Complete_Stage( $this->action_run, null, $this->user_resolver, $this->log_store, $this->integration_registry, $this->data_provider, $this->error_handler, $this->snapshot_store );

		$this->closure = new Closure_Stage( $this->log_store, $this->integration_registry, $this->data_provider, $this->error_handler );

		// All 5 stages are real — each execute() does actual work.
		// State flows through Pipeline_Result. No direct calls between stages.
		$this->stages = array(
			'trigger_entry'    => $this->trigger_entry,
			'trigger_complete' => $this->trigger_complete,
			'action_run'       => $this->action_run,
			'recipe_complete'  => $this->recipe_complete,
			'closure'          => $this->closure,
		);
	}

	/**
	 * Run the full pipeline from trigger entry to recipe completion.
	 *
	 * @param array $args                   Raw trigger args.
	 * @param bool  $mark_trigger_complete  Whether to auto-complete triggers.
	 *
	 * @return Pipeline_Result
	 */
	public function run( array $args, bool $mark_trigger_complete = true ): Pipeline_Result {
		return $this->execute_stages( $this->stages, $args, $mark_trigger_complete );
	}

	/**
	 * Run the pipeline starting from a specific stage.
	 *
	 * @param string $stage_name            Stage name to start from.
	 * @param array  $args                  Raw trigger args.
	 * @param bool   $mark_trigger_complete Whether to auto-complete triggers.
	 *
	 * @return Pipeline_Result
	 */
	public function run_from( string $stage_name, array $args, bool $mark_trigger_complete = true ): Pipeline_Result {

		$stage_keys = array_keys( $this->stages );
		$start      = array_search( $stage_name, $stage_keys, true );

		if ( false === $start ) {
			return new Pipeline_Result();
		}

		return $this->execute_stages( array_slice( $this->stages, $start, null, true ), $args, $mark_trigger_complete );
	}

	/**
	 * Execute an ordered set of stages, halting on request.
	 *
	 * Wraps the stage loop in a try/catch so that a TypeError, DB error,
	 * or unexpected exception in any stage does not kill the request.
	 *
	 * @param Stage[] $stages                Ordered stages to execute.
	 * @param array   $args                  Raw trigger args.
	 * @param bool    $mark_trigger_complete Whether to auto-complete triggers.
	 *
	 * @return Pipeline_Result
	 */
	private function execute_stages( array $stages, array $args, bool $mark_trigger_complete ): Pipeline_Result {

		$context = new Pipeline_Context( $args, $mark_trigger_complete );
		$result  = new Pipeline_Result();

		try {
			foreach ( $stages as $stage ) {

				$result = $stage->execute( $context, $result );

				if ( $result->should_halt() ) {
					break;
				}
			}
		} catch ( \Throwable $e ) {
			automator_log( 'Pipeline exception: ' . $e->getMessage(), 'Recipe_Runner' );
			$this->log_pipeline_error( $result, $e );
			$result->halt( 'Stage exception: ' . $e->getMessage() );
		}

		return $result;
	}

	/**
	 * Execute stages starting from a named stage with a pre-populated result.
	 *
	 * Used by complete_trigger() direct-call path and future resume().
	 * Stages before $stage_name are skipped — their work is already in $result.
	 *
	 * @param string           $stage_name Stage to start from (e.g., 'action_run').
	 * @param Pipeline_Result  $result     Pre-populated result from prior stages.
	 * @param Pipeline_Context $context    Optional — if null, creates a minimal context
	 *                                     (stages 3-5 read from $result, not $context).
	 *
	 * @return Pipeline_Result
	 */
	public function execute_stages_from( string $stage_name, Pipeline_Result $result, ?Pipeline_Context $context = null ): Pipeline_Result {

		if ( null === $context ) {
			$context = new Pipeline_Context( array(), false );
		}

		$stage_keys = array_keys( $this->stages );
		$start      = array_search( $stage_name, $stage_keys, true );

		if ( false === $start ) {
			return $result;
		}

		$remaining = array_slice( $this->stages, $start, null, true );

		try {
			foreach ( $remaining as $stage ) {

				$result = $stage->execute( $context, $result );

				if ( $result->should_halt() ) {
					break;
				}
			}
		} catch ( \Throwable $e ) {
			automator_log( 'Pipeline exception: ' . $e->getMessage(), 'Recipe_Runner' );
			$this->log_pipeline_error( $result, $e );
			$result->halt( 'Stage exception: ' . $e->getMessage() );
		}

		return $result;
	}

	/**
	 * Run only the trigger entry stage (Stage 1).
	 *
	 * This is the primary entry point replacing maybe_add_trigger_entry().
	 *
	 * @param array $args                   Raw trigger args.
	 * @param bool  $mark_trigger_complete  Whether to auto-complete triggers.
	 *
	 * @return Pipeline_Result
	 */
	public function run_trigger_entry( array $args, bool $mark_trigger_complete = true ): Pipeline_Result {

		$context = new Pipeline_Context( $args, $mark_trigger_complete );
		$result  = new Pipeline_Result();

		$result = $this->trigger_entry->execute( $context, $result );

		if ( $result->should_halt() || ! $mark_trigger_complete ) {
			return $result;
		}

		return $this->trigger_complete->execute( $context, $result );
	}

	/**
	 * Complete a trigger (legacy orchestrator).
	 *
	 * @param array $args Trigger args with recipe_id, trigger_id, user_id, log IDs, etc.
	 *
	 * @return Pipeline_Result
	 */
	public function complete_trigger( array $args = array() ) {

		$result = $this->trigger_complete->complete_trigger( $args );

		// If triggers are satisfied, run stages 3-5 via the pipeline loop.
		if ( $result->is_execution_ready() ) {
			$result = $this->execute_stages_from( 'action_run', $result );
		}

		return $result;
	}

	/**
	 * Mark an action complete and persist any error to uap_error_log.
	 *
	 * Single entry point for external callers (webhooks, async handlers, Pro).
	 * Writes status to uap_action_log (without error_message) and error to uap_error_log.
	 *
	 * @param int    $action_id      The action post ID.
	 * @param int    $recipe_log_id  The recipe log ID.
	 * @param int    $completed      Automator_Status constant.
	 * @param string $error_message  Human-readable error (written to uap_error_log, not action_log).
	 * @param array  $context        Optional context (action_code, integration_code, etc.).
	 *
	 * @return void
	 */
	public function complete_action( int $action_id, int $recipe_log_id, int $completed, string $error_message = '', array $context = array() ): void {

		$this->log_store->mark_action_complete( $action_id, $recipe_log_id, $completed );

		if ( ! empty( $error_message ) ) {
			$clean_message = wp_specialchars_decode( $error_message, ENT_QUOTES );
			$code          = Error_Code::infer_from_message( $clean_message );
			$error         = new Action_Error( $code, $clean_message, $context );
			$action_log_id = $this->log_store->get_action_log_id_by_action_and_recipe_log( $action_id, $recipe_log_id );
			$this->error_store->store( $recipe_log_id, $action_log_id, $error );
		}
	}

	/**
	 * Replay a previous recipe run using its stored snapshot.
	 *
	 * Creates a new recipe log + trigger logs, copies trigger meta from the
	 * snapshot, then runs stages 3-5 (action execution, recipe completion,
	 * closures). The token parser reads from the copied trigger meta naturally.
	 *
	 * @param int $original_recipe_log_id The recipe log ID to replay.
	 *
	 * @return Pipeline_Result
	 */
	public function replay( int $original_recipe_log_id ): Pipeline_Result {

		$result = $this->replay_service()->prepare_replay( $original_recipe_log_id );

		if ( $result->should_halt() ) {
			return $result;
		}

		return $this->execute_stages_from( 'action_run', $result );
	}

	/**
	 * Finalize recipe status — public proxy for external callers.
	 *
	 * Uses the resolver for one-pass status computation + severity guard.
	 * Called by Pro's loop completion, background actions, and async handlers.
	 *
	 * @param int   $recipe_id     The recipe ID.
	 * @param int   $user_id       The user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Recipe args.
	 *
	 * @return bool|null
	 */
	public function finalize_recipe( int $recipe_id, int $user_id, int $recipe_log_id, array $args = array() ) {
		return $this->recipe_complete->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Finalize recipe status when only the recipe_log_id is available.
	 *
	 * Looks up recipe_id and user_id from the recipe log row, then
	 * delegates to finalize_recipe(). Used by async webhook callbacks
	 * (WhatsApp, Instagram, LinkedIn) that only receive the log ID.
	 *
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Recipe args.
	 *
	 * @return int|null Resolved status, or null if log not found.
	 */
	public function finalize_recipe_by_log_id( int $recipe_log_id, array $args = array() ) {

		$row = $this->log_store->get_recipe_log_row( $recipe_log_id );

		if ( null === $row ) {
			return null;
		}

		return $this->finalize_recipe( (int) $row->automator_recipe_id, (int) $row->user_id, $recipe_log_id, $args );
	}

	/**
	 * Process a single trigger for a specific recipe.
	 *
	 * Called by Abstract_Trigger::process() — handles log creation,
	 * validation, and numtimes for one recipe+trigger combination.
	 * Token hydration stays in the trigger class (trigger-specific).
	 *
	 * @param int   $recipe_id The recipe ID.
	 * @param int   $user_id   The user ID.
	 * @param array $trigger   Trigger data with 'ID' and 'meta'.
	 *
	 * @return array{recipe_log_id: int, trigger_log_id: int, run_number: int}
	 * @throws \Exception On recipe completed, log failure, validation failure, or numtimes not met.
	 */
	public function process_trigger( int $recipe_id, int $user_id, array $trigger ): array {

		if ( $this->completion()->is_completed( $recipe_id, $user_id ) ) {
			throw new Pipeline_Exception( 'Recipe has already been completed' );
		}

		$result = $this->log_manager->maybe_create_recipe_log_entry( $recipe_id, $user_id );

		if ( empty( $result['recipe_log_id'] ) ) {
			throw new Pipeline_Exception( 'Unable to create recipe log entry' );
		}

		$recipe_log_id = (int) $result['recipe_log_id'];

		$validation = $this->validator->maybe_get_trigger_id( $user_id, (int) $trigger['ID'], $recipe_id, $recipe_log_id );

		if ( empty( $validation['trigger_log_id'] ) ) {
			throw new Pipeline_Exception( 'Unable to create trigger log entry' );
		}

		$trigger_log_id = (int) $validation['trigger_log_id'];

		$numtimes_result = $this->numtimes->maybe_trigger_num_times_completed(
			array(
				'recipe_id'      => $recipe_id,
				'trigger_id'     => (int) $trigger['ID'],
				'trigger'        => $trigger,
				'user_id'        => $user_id,
				'recipe_log_id'  => $recipe_log_id,
				'trigger_log_id' => $trigger_log_id,
				'is_signed_in'   => is_user_logged_in(),
			)
		);

		if ( ! isset( $numtimes_result['run_number'] ) ) {
			throw new Pipeline_Exception( 'Number of times condition is not completed' );
		}

		return array(
			'recipe_log_id'  => $recipe_log_id,
			'trigger_log_id' => $trigger_log_id,
			'run_number'     => (int) $numtimes_result['run_number'],
		);
	}

	/**
	 * Log a pipeline exception to uap_error_log when execution was in progress.
	 *
	 * Only writes when set_execution_ready() has been called — before that
	 * there's no recipe_log_id to associate the error with.
	 *
	 * @param Pipeline_Result $result The current pipeline result.
	 * @param \Throwable      $e      The caught exception.
	 *
	 * @return void
	 */
	private function log_pipeline_error( Pipeline_Result $result, \Throwable $e ): void {

		if ( ! $result->is_execution_ready() ) {
			return;
		}

		$error = new Action_Error(
			Error_Code::EXECUTION_FAILED,
			$e->getMessage(),
			array(
				'exception_class' => get_class( $e ),
				'file'            => $e->getFile(),
				'line'            => $e->getLine(),
			)
		);

		$this->error_store->store_system_error( $result->get_recipe_log_id(), $error );
	}

	// ── Granular access for callers that need specific sub-classes ──

	/**
	 * @return Trigger_Validator
	 */
	public function validator(): Trigger_Validator {
		return $this->validator;
	}

	/**
	 * @return Recipe_Log_Manager
	 */
	public function log_manager(): Recipe_Log_Manager {
		return $this->log_manager;
	}

	/**
	 * @return Trigger_Numtimes
	 */
	public function numtimes(): Trigger_Numtimes {
		return $this->numtimes;
	}

	/**
	 * @return Trigger_Entry_Stage
	 */
	public function trigger_entry(): Trigger_Entry_Stage {
		return $this->trigger_entry;
	}

	/**
	 * @return Trigger_Complete_Stage
	 */
	public function trigger_complete(): Trigger_Complete_Stage {
		return $this->trigger_complete;
	}

	/**
	 * @return Action_Run_Stage
	 */
	public function action_run(): Action_Run_Stage {
		return $this->action_run;
	}

	/**
	 * @return Recipe_Complete_Stage
	 */
	public function recipe_complete(): Recipe_Complete_Stage {
		return $this->recipe_complete;
	}

	/**
	 * @return Execution_Log_Store
	 */
	public function log_store(): Execution_Log_Store {
		return $this->log_store;
	}

	/**
	 * @return Integration_Registry
	 */
	public function integration_registry(): Integration_Registry {
		return $this->integration_registry;
	}

	/**
	 * @return Recipe_Data_Provider
	 */
	public function data_provider(): Recipe_Data_Provider {
		return $this->data_provider;
	}

	/**
	 * @return Action_Error_Store
	 */
	public function error_store(): Action_Error_Store {
		return $this->error_store;
	}

	/**
	 * @return Run_Snapshot_Store
	 */
	public function snapshot_store(): Run_Snapshot_Store {
		return $this->snapshot_store;
	}

	/**
	 * @return Recipe_Completion_Service
	 */
	public function completion(): Recipe_Completion_Service {
		$this->completion = $this->completion ?? new Recipe_Completion_Service();
		return $this->completion;
	}

	/**
	 * @return Recipe_Throttle_Service
	 */
	public function throttle(): Recipe_Throttle_Service {
		$this->throttle = $this->throttle ?? new Recipe_Throttle_Service();
		return $this->throttle;
	}

	/**
	 * @return Run_Number_Service
	 */
	public function run_number(): Run_Number_Service {
		return $this->run_number_svc;
	}

	/**
	 * @return Replay_Service
	 */
	private function replay_service(): Replay_Service {
		$this->replay_service = $this->replay_service ?? new Replay_Service( $this->snapshot_store, $this->log_manager, $this->log_store );
		return $this->replay_service;
	}

	/**
	 * Set the loop processor for loop item execution.
	 *
	 * Pro calls this at boot to inject its processor.
	 *
	 * @param Loop_Processor $processor The processor implementation.
	 *
	 * @return void
	 */
	public function set_loop_processor( Loop_Processor $processor ): void {
		$this->loop_processor = $processor;
		$this->action_run->set_loop_processor( $processor );
	}

	/**
	 * Set the user resolver for anonymous (Everyone) recipe processing.
	 *
	 * Pro calls this at boot to inject its resolver. Without it, anonymous
	 * recipes fall through to the deprecated filter chain.
	 *
	 * @param User_Resolver $resolver The resolver implementation.
	 *
	 * @return void
	 */
	public function set_user_resolver( User_Resolver $resolver ): void {
		$this->user_resolver = $resolver;
		$this->trigger_complete->set_user_resolver( $resolver );
	}
}
