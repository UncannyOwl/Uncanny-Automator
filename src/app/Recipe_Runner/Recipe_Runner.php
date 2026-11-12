<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Pipeline_Exception;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Stages\Stage;
use Uncanny_Automator\App\Recipe_Runner\Services\Loop_Processor;
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
use Uncanny_Automator\App\Recipe_Runner\Stages\Recipe_Complete_Stage;
use Uncanny_Automator\App\Recipe_Runner\Stages\Trigger_Complete_Stage;
use Uncanny_Automator\App\Recipe_Runner\Stages\Trigger_Entry_Stage;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Children_Runner;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Control;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Control_Registry;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Processor;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Tree_Walk;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Execution_Context;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Background_Job_Store;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Dispatch_Policy;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Wait_Set;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job_Result;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Run_Snapshot;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;

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
	 * The statuses a run holds before its actions start: -1 (pending) when the runner
	 * opens it, NOT_COMPLETED once a trigger fire is counted on it. The action stage
	 * marks it IN_PROGRESS before its walk.
	 *
	 * @since 8.0.0
	 *
	 * @var int[]
	 */
	private const RUN_STATUSES_BEFORE_ACTIONS = array( -1, Automator_Status::NOT_COMPLETED );

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
	 * Background app-action jobs.
	 *
	 * @var Background_Job_Store
	 */
	private $job_store;

	/**
	 * @var Replay_Service|null
	 */
	private $replay_service;

	/**
	 * @var Block_Control_Registry What a user may do to a block of a run; Pro registers the concrete controls.
	 */
	private Block_Control_Registry $block_controls;

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
	 * @param Background_Job_Store|null   $job_store        Optional job store.
	 */
	public function __construct(
		?Trigger_Validator $validator = null,
		?Recipe_Log_Manager $log_manager = null,
		?Trigger_Numtimes $numtimes = null,
		?Trigger_Entry_Stage $trigger_entry = null,
		?Trigger_Complete_Stage $trigger_complete = null,
		?Action_Run_Stage $action_run = null,
		?Recipe_Complete_Stage $recipe_complete = null,
		?Execution_Log_Store $log_store = null,
		?Background_Job_Store $job_store = null
	) {
		$this->log_store            = $log_store ?? Database::get_execution_log_store();
		$this->integration_registry = new Integration_Registry();
		$this->data_provider        = new Recipe_Data_Provider();
		$this->error_handler        = new Error_Handler();
		$this->error_store          = Database::get_action_error_store();
		$this->snapshot_store       = Database::get_run_snapshot_store();
		$this->job_store            = $job_store ?? Database::get_background_job_store();
		$this->block_controls       = new Block_Control_Registry();
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
		$this->recipe_complete  = $recipe_complete ?? new Recipe_Complete_Stage( $this->log_store, $this->integration_registry, $this->data_provider, $this->error_store, $this->run_number_svc );
		$this->action_run       = $action_run ?? new Action_Run_Stage( $this->recipe_complete, null, $this->log_store, $this->integration_registry, $this->data_provider, $this->error_handler, $this->error_store );
		$this->trigger_complete = $trigger_complete ?? new Trigger_Complete_Stage( $this->action_run, null, $this->user_resolver, $this->log_store, $this->integration_registry, $this->data_provider, $this->error_handler, $this->snapshot_store );

		// All 4 stages are real — each execute() does actual work.
		// State flows through Pipeline_Result. No direct calls between stages.
		$this->stages = array(
			'trigger_entry'    => $this->trigger_entry,
			'trigger_complete' => $this->trigger_complete,
			'action_run'       => $this->action_run,
			'recipe_complete'  => $this->recipe_complete,
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
	 * Fires `automator_recipe_run_leg_finished` with the result after the last stage, whether the leg completed, halted or
	 * deferred async work; a listener that must act once per request-side leg, such as redirect delivery, hangs here.
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

		return $this->finish_leg( $result );
	}

	/**
	 * Execute stages starting from a named stage with a pre-populated result.
	 *
	 * Used by complete_trigger() direct-call path and future resume().
	 * Stages before $stage_name are skipped — their work is already in $result.
	 * Fires the same terminal event as {@see self::execute_stages()}.
	 *
	 * @param string           $stage_name Stage to start from (e.g., 'action_run').
	 * @param Pipeline_Result  $result     Pre-populated result from prior stages.
	 * @param Pipeline_Context $context    Optional — if null, creates a minimal context
	 *                                     (stages 3-4 read from $result, not $context).
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

		return $this->finish_leg( $result );
	}

	/**
	 * The end of every pipeline leg, whether it completed, halted or deferred: the terminal event listeners act
	 * on once per request-side leg (redirect delivery hangs here), then the closure-era hook for a ready leg.
	 *
	 * @since 8.0.0
	 *
	 * @param Pipeline_Result $result The leg's result.
	 *
	 * @return Pipeline_Result The same result, for the caller to return.
	 */
	private function finish_leg( Pipeline_Result $result ): Pipeline_Result {
		Dispatcher::action( 'automator_recipe_run_leg_finished', $result );

		if ( $result->is_execution_ready() ) {
			// @deprecated 8.0.0 Closures are gone; nothing replaces this moment. Removed in 8.1.
			Dispatcher::action_deprecated( 'automator_closures_completed', array( $result->get_recipe_id(), $result->get_user_id(), $result->get_execution_args() ), '8.0.0' );
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

		// If triggers are satisfied, run stages 3-4 via the pipeline loop.
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
	 * snapshot, then runs stages 3-4 (action execution, recipe completion).
	 * The token parser reads from the copied trigger meta naturally.
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
	 * @param int   $recipe_id                 The recipe ID.
	 * @param int   $user_id                   The user ID.
	 * @param int   $recipe_log_id             The recipe log ID.
	 * @param array $args                      Recipe args.
	 * @param bool  $treat_incomplete_as_error Recovery-only: treat NOT_COMPLETED action rows as
	 *                                         stuck errors. Defaults false for the live path.
	 *
	 * @return bool|null
	 */
	public function finalize_recipe( int $recipe_id, int $user_id, int $recipe_log_id, array $args = array(), bool $treat_incomplete_as_error = false ) {
		return $this->recipe_complete->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, $args, $treat_incomplete_as_error );
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
	 * Close a run whose trigger failed after the run was opened.
	 *
	 * Called by Trigger::process() when the trigger throws once process_trigger()
	 * has opened the run: while it hydrates its tokens, in a trigger hook, or while
	 * the trigger completes. The failure is stored on the run and the run closes as
	 * Completed with errors. Left open, it would read In progress with no reason, and
	 * nothing would close it: stuck-run recovery only claims runs with action rows.
	 *
	 * Two runs are left as they are, and the method returns null for them:
	 * - a run that moved past its triggers: its actions own its status from there;
	 * - a run of a recipe that waits for every one of two or more triggers: it stays
	 *   open between trigger fires to collect them, so this trigger's next fire can
	 *   still complete it.
	 *
	 * @since 8.0.0
	 *
	 * @param int        $recipe_id     The recipe ID.
	 * @param int        $user_id       The user ID.
	 * @param int        $recipe_log_id The run the trigger opened.
	 * @param int        $trigger_id    The trigger that failed.
	 * @param \Throwable $failure       What the trigger threw.
	 *
	 * @return int|null The run's closing status, or null when the run was left as it is.
	 */
	public function fail_trigger( int $recipe_id, int $user_id, int $recipe_log_id, int $trigger_id, \Throwable $failure ): ?int {

		if ( ! in_array( $this->log_store->get_recipe_status( $recipe_log_id ), self::RUN_STATUSES_BEFORE_ACTIONS, true ) ) {
			return null;
		}

		if ( $this->trigger_complete->collects_several_triggers( $recipe_id ) ) {
			return null;
		}

		$this->error_store->store_system_error(
			$recipe_log_id,
			new Action_Error(
				Error_Code::EXECUTION_FAILED,
				$failure->getMessage(),
				array(
					'trigger_id'      => $trigger_id,
					'exception_class' => get_class( $failure ),
					'file'            => $failure->getFile(),
					'line'            => $failure->getLine(),
				)
			)
		);

		return $this->finalize_recipe(
			$recipe_id,
			$user_id,
			$recipe_log_id,
			array(
				'recipe_id'            => $recipe_id,
				'user_id'              => $user_id,
				'recipe_log_id'        => $recipe_log_id,
				'trigger_id'           => $trigger_id,
				'complete_with_errors' => true,
			)
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
	 * Register the processor for a block type. Pro calls this at boot for filter, delay and loop.
	 *
	 * @param string          $block_type One of the `Block_Type::*` strings.
	 * @param Block_Processor $processor  The implementation.
	 *
	 * @return void
	 */
	public function register_block_processor( string $block_type, Block_Processor $processor ): void {
		$this->action_run->processors()->register( $block_type, $processor );
	}

	/**
	 * Register the control for a block type. Pro calls this at boot for delay and loop.
	 *
	 * @param string        $block_type One of the `Block_Type::*` strings.
	 * @param Block_Control $control    The implementation.
	 *
	 * @return void
	 */
	public function register_block_control( string $block_type, Block_Control $control ): void {
		$this->block_controls->register( $block_type, $control );
	}

	/**
	 * The block-control registry the log wire reads and the block-control route dispatches through.
	 *
	 * @return Block_Control_Registry
	 */
	public function block_controls(): Block_Control_Registry {
		return $this->block_controls;
	}

	/**
	 * A children runner over the action stage with a fresh result, for walks that start outside the pipeline.
	 *
	 * @param Pipeline_Result|null $result Result to mark async work on; a fresh one when null.
	 *
	 * @return Block_Children_Runner
	 */
	public function children_runner( ?Pipeline_Result $result = null ): Block_Children_Runner {
		return new Block_Children_Runner( $this->action_run, $result ?? new Pipeline_Result(), $this->log_store );
	}

	/**
	 * Resume a deferred block: run one of its paths on the run's existing rows, then finalize unless something deferred again.
	 *
	 * A recipe that is no longer published is refused and every unfinished row under the block is cancelled; a block the
	 * recipe no longer has, or a path that throws before its walk ends, settles the rows the payload was waiting on with an
	 * error. A tree read that fails is rethrown so the scheduler records the failure and the retry cron owns the next attempt.
	 *
	 * The walk is a continuation: a detach an action requests here is dropped, since a background leg has no visitor to release.
	 *
	 * @param int    $recipe_log_id        The run.
	 * @param int    $block_id             The block whose path resumes.
	 * @param string $path_code            The path to run.
	 * @param int    $user_id              User the resumed path runs as.
	 * @param array  $args                 The run's trigger args, as captured when the block deferred.
	 * @param array  $completed_action_ids Extra action ids to skip (loop scope: the entity's completed actions).
	 * @param array  $waiting_action_ids   The action ids the deferring block left waiting, as captured in its payload.
	 *
	 * @return int|null The status the waiting rows were settled to when the resume was refused or its path threw, null when the path ran.
	 * @throws \Throwable When the recipe tree cannot be read.
	 */
	public function resume_block( int $recipe_log_id, int $block_id, string $path_code, int $user_id, array $args, array $completed_action_ids = array(), array $waiting_action_ids = array() ): ?int {

		$row = $this->log_store->get_recipe_log_row( $recipe_log_id );
		if ( null === $row ) {
			return null;
		}
		$recipe_id = (int) $row->automator_recipe_id;

		try {
			$tree = $this->action_run->reader()->get_or_build( $recipe_id );
		} catch ( \Throwable $e ) {
			automator_log( 'resume_block: ' . $e->getMessage(), 'Recipe_Runner' );
			throw $e;
		}
		$node = null === $tree ? null : Tree_Walk::find_block( (array) ( $tree['content'] ?? array() ), $block_id );

		$action_log_ids = array();
		$completed      = $completed_action_ids;
		foreach ( $this->log_store->get_action_log_rows( $recipe_log_id ) as $action_row ) {
			$action_row                   = (array) $action_row;
			$action_id                    = (int) $action_row['automator_action_id'];
			$action_log_ids[ $action_id ] = (int) $action_row['ID'];
			if ( Automator_Status::finished( (int) $action_row['completed'] ) ) {
				$completed[ $action_id ] = true;
			}
		}

		if ( null === $node ) {
			$message = sprintf( 'Block %d was not found in recipe %d at resume; the actions waiting on it did not run.', $block_id, $recipe_id );
			$this->error_store->store_system_error(
				$recipe_log_id,
				new Action_Error(
					Error_Code::SYSTEM_ERROR,
					$message,
					array(
						'block_id'  => $block_id,
						'recipe_id' => $recipe_id,
					)
				)
			);
			$this->settle_waiting_rows( $waiting_action_ids, $completed, $recipe_log_id, Automator_Status::COMPLETED_WITH_ERRORS, $message );
			$this->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, $args );
			return Automator_Status::COMPLETED_WITH_ERRORS;
		}

		if ( ! $this->is_published( $tree ) ) {
			$this->settle_waiting_rows( Tree_Walk::action_ids( array( $node ) ), $completed, $recipe_log_id, Automator_Status::CANCELLED, sprintf( 'Recipe %d was not published when its delayed actions were due; they were cancelled.', $recipe_id ) );
			$this->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, $args );
			return Automator_Status::CANCELLED;
		}

		$context    = new Execution_Context( $recipe_id, $user_id, $recipe_log_id, $args, $action_log_ids, $completed, null, true );
		$children   = $this->children_runner();
		$walk_error = null;

		try {
			$children->run( $node, $path_code, $context );
		} catch ( \Throwable $e ) {
			$walk_error = $e->getMessage();
			$this->error_store->store_system_error(
				$recipe_log_id,
				new Action_Error(
					Error_Code::SYSTEM_ERROR,
					$e->getMessage(),
					array(
						'block_id'  => $block_id,
						'recipe_id' => $recipe_id,
						'exception' => get_class( $e ),
					)
				)
			);
		}

		// The walk passes over an action switched off while the block waited; left alone, its row would wait forever.
		$unfinished = array();
		foreach ( $this->log_store->get_action_log_rows( $recipe_log_id ) as $action_row ) {
			$action_row = (array) $action_row;
			if ( ! Automator_Status::finished( (int) $action_row['completed'] ) ) {
				$unfinished[ (int) $action_row['automator_action_id'] ] = true;
			}
		}
		foreach ( Tree_Walk::action_nodes( array( $node ) ) as $action_node ) {
			$action_id = (int) ( $action_node['id'] ?? 0 );
			if ( false === ( $action_node['is_item_on'] ?? true ) && isset( $unfinished[ $action_id ] ) ) {
				$this->complete_action( $action_id, $recipe_log_id, Automator_Status::COMPLETED_WITH_ERRORS, sprintf( 'Action %d was switched off when its delay was due; it did not run.', $action_id ) );
			}
		}

		$this->action_run->after_walk( $context );

		// The path threw before its walk ended: the rows it left waiting would show In progress until stuck-run recovery.
		if ( null !== $walk_error ) {
			$this->settle_waiting_rows( $waiting_action_ids, $this->with_finished_rows( $recipe_log_id, $completed ), $recipe_log_id, Automator_Status::COMPLETED_WITH_ERRORS, $walk_error );
			$this->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, $args );
			return Automator_Status::COMPLETED_WITH_ERRORS;
		}

		if ( ! $children->result()->has_async_work() ) {
			$this->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, $args );
		}

		return null;
	}

	/**
	 * Finish a background app action with its job's outcome: complete the awaiting row through the action's own
	 * process_job(), then settle every node held back on the run. The job is claimed first, so a second delivery of
	 * the same outcome loses the claim and does nothing, and it is marked finished only after the row and its
	 * dependents have settled; a re-driven job whose row already settled only releases the nodes still waiting on it,
	 * and a job re-driven MAX_REDRIVES times is given up as lost with the run closed on the error. Without the run's
	 * snapshot nothing can execute again, so the row fails and every held-back node is skipped.
	 *
	 * A recipe that is no longer published (any other status, or deleted) is refused before any of that: the job's
	 * outcome is not recorded, an awaiting row and every held-back node of the run are cancelled, nothing runs and
	 * the run is finalized.
	 *
	 * @param Background_Job        $job    The job record.
	 * @param Background_Job_Result $result The job's outcome.
	 *
	 * @return void
	 */
	public function complete_background_action( Background_Job $job, Background_Job_Result $result ): void {

		$recipe_log_id = $job->recipe_log_id();
		$action_id     = $job->action_id();

		// One completion path owns the job from here to mark_finished(). A callback
		// and the poll can both deliver the same outcome; the second loses the claim.
		// The poll releases an abandoned claim back to open before driving it again,
		// so every arrival, first or re-driven, claims the same way.
		if ( ! $this->job_store->claim( $job->id(), $result->source() ) ) {
			automator_log( sprintf( 'Background job %s for action %d of run %d is already claimed or finished; ignored.', $job->job_id(), $action_id, $recipe_log_id ), 'Recipe_Runner' );
			return;
		}

		$row = $this->log_store->get_recipe_log_row( $recipe_log_id );
		if ( null === $row ) {
			$this->job_store->mark_finished( $job->id(), $result->status(), $result->source() );
			return;
		}
		$recipe_id = (int) $row->automator_recipe_id;
		$user_id   = $job->user_id() > 0 ? $job->user_id() : (int) $row->user_id;

		$tree  = $this->action_run->reader()->get_or_build( $recipe_id );
		$items = is_array( $tree ) ? (array) ( $tree['content'] ?? array() ) : array();

		$snapshot = $this->snapshot_store->get( $recipe_log_id );
		$args     = null === $snapshot
			? array(
				'recipe_id'     => $recipe_id,
				'recipe_log_id' => $recipe_log_id,
			)
			: $this->run_args_from_snapshot( $snapshot, $recipe_log_id );

		// A recipe switched off does not continue: whatever the job reports, nothing in the run runs again.
		if ( ! $this->is_published( $tree ) ) {
			if ( Automator_Status::COMPLETED_AWAITING === $this->log_store->get_action_completion_status( $job->action_log_id() ) ) {
				$this->complete_action(
					$action_id,
					$recipe_log_id,
					Automator_Status::CANCELLED,
					'The recipe was switched off before this action\'s result came back; the result was not recorded and the steps waiting on it were cancelled.',
					array(
						'job_id'    => $job->job_id(),
						'action_id' => $action_id,
					)
				);
			}
			$this->abandon_run( $recipe_id, $user_id, $recipe_log_id, $args, $items, $job, Automator_Status::CANCELLED, 'The recipe was switched off before the step this one waits on finished; this step was cancelled.' );
			$this->job_store->mark_finished( $job->id(), $result->status(), $result->source() );
			return;
		}

		// A completion that died this many times would die again: the run cannot finish on its own, so it is
		// closed with the error and the job is given up instead of being driven every poll for good.
		if ( $job->redrives() >= Background_Dispatch_Policy::MAX_REDRIVES ) {
			$message = sprintf( 'Background job %s: the completion died %d times; the action failed and every step still waiting was skipped.', $job->job_id(), $job->redrives() );
			$context = array(
				'job_id'    => $job->job_id(),
				'action_id' => $action_id,
			);
			if ( Automator_Status::COMPLETED_AWAITING === $this->log_store->get_action_completion_status( $job->action_log_id() ) ) {
				$this->complete_action( $action_id, $recipe_log_id, Automator_Status::COMPLETED_WITH_ERRORS, $message, $context );
			} else {
				$this->error_store->store_system_error( $recipe_log_id, new Action_Error( Error_Code::SYSTEM_ERROR, $message, $context ) );
			}
			$this->abandon_run( $recipe_id, $user_id, $recipe_log_id, $args, $items, $job, Automator_Status::SKIPPED );
			$this->job_store->mark_finished( $job->id(), Background_Job::STATUS_LOST, $result->source() );
			return;
		}

		// A re-driven job whose row already settled (the earlier completion died between
		// settling the row and releasing the dependents) only needs the release.
		if ( Automator_Status::COMPLETED_AWAITING !== $this->log_store->get_action_completion_status( $job->action_log_id() ) ) {
			if ( array() !== Background_Wait_Set::from_run( $this->log_store, $recipe_log_id, $items )->dependents_of( $action_id ) ) {
				if ( null === $snapshot ) {
					$this->abandon_run( $recipe_id, $user_id, $recipe_log_id, $args, $items, $job, Automator_Status::SKIPPED );
				} else {
					$this->settle_dependents( $recipe_id, $user_id, $recipe_log_id, $args, $items, $job );
				}
			}
			$this->job_store->mark_finished( $job->id(), $result->status(), $result->source() );
			return;
		}

		if ( null === $snapshot ) {
			$message = sprintf( 'The run context for recipe log %d expired before background job %s reported; the action failed and every step still waiting was skipped.', $recipe_log_id, $job->job_id() );
			$this->complete_action(
				$action_id,
				$recipe_log_id,
				Automator_Status::COMPLETED_WITH_ERRORS,
				$message,
				array(
					'job_id'    => $job->job_id(),
					'action_id' => $action_id,
				)
			);
			$this->abandon_run( $recipe_id, $user_id, $recipe_log_id, $args, $items, $job, Automator_Status::SKIPPED );
			$this->job_store->mark_finished( $job->id(), $result->status(), $result->source() );
			return;
		}

		$resolved = $this->resolve_background_action( $job );
		if ( null === $resolved ) {
			$message = sprintf( 'Background job %s reported for action %d, which cannot complete a job; the action failed and the steps waiting on it were skipped.', $job->job_id(), $action_id );
			$this->complete_action(
				$action_id,
				$recipe_log_id,
				Automator_Status::COMPLETED_WITH_ERRORS,
				$message,
				array(
					'job_id'    => $job->job_id(),
					'action_id' => $action_id,
				)
			);
			$this->settle_dependents( $recipe_id, $user_id, $recipe_log_id, $args, $items, $job );
			$this->job_store->mark_finished( $job->id(), $result->status(), $result->source() );
			return;
		}

		$action_data         = $resolved['action_data'];
		$action_data['args'] = $args;

		$resolved['action']->complete_job( $user_id, $action_data, $recipe_id, $args, $job->parsed(), $job->remembered(), $result->to_envelope() );

		// complete_job() settles the row through the path a synchronous run uses. A row still awaiting afterwards is
		// the action's bug, not a wait; left alone, the run would finalize over nodes nothing will ever release.
		if ( Automator_Status::COMPLETED_AWAITING === $this->log_store->get_action_completion_status( $job->action_log_id() ) ) {
			$message = sprintf( 'Background job %s reported for action %d, but completing it left the row awaiting; the action failed and the steps waiting on it were skipped.', $job->job_id(), $action_id );
			$this->complete_action(
				$action_id,
				$recipe_log_id,
				Automator_Status::COMPLETED_WITH_ERRORS,
				$message,
				array(
					'job_id'    => $job->job_id(),
					'action_id' => $action_id,
				)
			);
		}

		$this->settle_dependents( $recipe_id, $user_id, $recipe_log_id, $args, $items, $job );
		$this->job_store->mark_finished( $job->id(), $result->status(), $result->source() );
	}

	/**
	 * The action a background job belongs to, with the action data its completion or its site retry runs on: the
	 * action post's data plus the run, the row and the job id. Null when the action's class cannot complete a job.
	 *
	 * @since 8.0.0
	 *
	 * @param Background_Job $job The job record.
	 *
	 * @return array{action: object, action_data: array<string, mixed>}|null
	 */
	public function resolve_background_action( Background_Job $job ): ?array {
		$action_data                             = $this->action_run->build_action_data( array( 'id' => $job->action_id() ) );
		$action_data['recipe_log_id']            = $job->recipe_log_id();
		$action_data['action_log_id']            = $job->action_log_id();
		$action_data['background_job_id']        = $job->job_id();
		$action_data['background_connection_id'] = $job->connection_id();

		$callable = $this->integration_registry->get_action_execution_function( (string) ( $action_data['meta']['code'] ?? '' ) );
		if ( ! is_array( $callable ) || ! is_object( $callable[0] ?? null ) || ! method_exists( $callable[0], 'complete_job' ) ) {
			return null;
		}

		return array(
			'action'      => $callable[0],
			'action_data' => $action_data,
		);
	}

	/**
	 * Whether the run's recipe is published. A run whose recipe has any other status, whose recipe no longer exists,
	 * or that has no run row does not continue: no background job of it runs or re-runs an action.
	 *
	 * @since 8.0.4
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return bool
	 */
	public function is_run_published( int $recipe_log_id ): bool {
		$row = $this->log_store->get_recipe_log_row( $recipe_log_id );
		if ( null === $row ) {
			return false;
		}

		return $this->is_published( $this->action_run->reader()->get_or_build( (int) $row->automator_recipe_id ) );
	}

	/**
	 * Whether a recipe tree is a published recipe's. A recipe that no longer exists has no tree.
	 *
	 * @since 8.0.4
	 *
	 * @param array|null $tree The recipe tree, or null when the recipe no longer exists.
	 *
	 * @return bool
	 */
	private function is_published( ?array $tree ): bool {
		return null !== $tree && Recipe_Status::PUBLISH === (string) ( $tree['status'] ?? '' );
	}

	/**
	 * After an awaited action's row settled: pass over the run until nothing more is due, skipping every held-back
	 * node with a failed blocker and running every one whose blockers all finished, then finalize unless a block
	 * deferred again. The rows decide, so a node waiting on two jobs is skipped as soon as one fails. Each pass
	 * re-reads the rows and the record and ends the loop when it claimed nothing; a node is claimed for this job
	 * before it runs or is skipped and its entry removed after, so two completions of the same run settling at once
	 * never run or skip the same node twice, and a completion that dies mid-node leaves a claim the next completion
	 * takes over once the poll has declared it dead.
	 *
	 * The walk is a continuation: a detach an action requests here is dropped, since a background leg has no visitor to release.
	 *
	 * @param int            $recipe_id     The recipe.
	 * @param int            $user_id       The user the run executes as.
	 * @param int            $recipe_log_id The run.
	 * @param array          $args          The run's trigger args.
	 * @param array          $items         The tree's `content`.
	 * @param Background_Job $job           The job this completion holds; it names the claims it takes.
	 *
	 * @return void
	 */
	private function settle_dependents( int $recipe_id, int $user_id, int $recipe_log_id, array $args, array $items, Background_Job $job ): void {

		$result = new Pipeline_Result();

		do {
			$wait           = Background_Wait_Set::from_run( $this->log_store, $recipe_log_id, $items );
			$action_log_ids = array();
			$completed      = array();
			$failed         = array();
			foreach ( $this->log_store->get_action_log_rows( $recipe_log_id ) as $row ) {
				$row       = (array) $row;
				$action_id = (int) $row['automator_action_id'];
				$status    = (int) $row['completed'];

				$action_log_ids[ $action_id ] = (int) $row['ID'];
				if ( Automator_Status::finished( $status ) ) {
					$completed[ $action_id ] = true;
				}
				if ( in_array( $status, array( Automator_Status::COMPLETED_WITH_ERRORS, Automator_Status::FAILED, Automator_Status::SKIPPED ), true ) ) {
					$failed[ $action_id ] = true;
				}
			}

			// A pass that claims nothing is the end: everything still due is held by a completion that is alive.
			$claimed = false;

			$doomed = $wait->doomed(
				static function ( int $id ) use ( $failed ): bool {
					return isset( $failed[ $id ] );
				}
			);
			foreach ( $doomed as $node_id ) {
				$claimed = $this->settle_blocked_node( $wait, $node_id, $recipe_id, $recipe_log_id, $job, Automator_Status::SKIPPED ) || $claimed;
			}

			$ready = array_diff(
				$wait->ready(
					static function ( int $id ) use ( $completed ): bool {
						return isset( $completed[ $id ] );
					}
				),
				$doomed
			);

			$context = new Execution_Context( $recipe_id, $user_id, $recipe_log_id, $args, $action_log_ids, $completed, $wait, true );
			foreach ( $ready as $node_id ) {
				if ( ! $this->claim_blocked_node( $wait, $node_id, $recipe_id, $recipe_log_id, $job ) ) {
					continue;
				}
				$claimed = true;
				// The record stays while the node runs: the walker reads it to know which records came before the
				// node, so a later sibling of the same integration cannot hold it back. The walker rewrites the record
				// when the node must wait on something else; then the rewritten record stays and the entry is kept.
				$before = $wait->record_for( $node_id );
				$node   = Tree_Walk::find_node( $items, $node_id );
				if ( null !== $node ) {
					$this->action_run->process_items( array( $node ), $context, $result, 0 );
				}
				if ( $wait->record_for( $node_id ) === $before ) {
					$wait->forget( $node_id );
					$this->log_store->merge_recipe_log_meta_entry( $recipe_id, $recipe_log_id, Execution_Log_Store::META_BLOCKED_NODES, (string) $node_id, null );
				}
				// Progress: the poll treats a claim as abandoned only after a poll interval without one.
				$this->job_store->touch_claim( $job->id() );
			}
		} while ( $claimed );

		$this->action_run->after_walk( $context );

		if ( ! $result->has_async_work() ) {
			$this->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, $args );
		}
	}

	/**
	 * Nothing in the run can execute again (its context is gone, its completion died too often, or its recipe is no
	 * longer published): settle every held-back node to one status, whatever it waited on, and finalize. With a
	 * message, each settled row also records it as its reason; without one, only the status is written.
	 *
	 * @param int            $recipe_id     The recipe.
	 * @param int            $user_id       The user the run executes as.
	 * @param int            $recipe_log_id The run.
	 * @param array          $args          The run's trigger args, or the two-key args a run without a snapshot still has.
	 * @param array          $items         The tree's `content`.
	 * @param Background_Job $job           The job this completion holds; it names the claims it takes.
	 * @param int            $status        Terminal Automator_Status for every held-back row: SKIPPED, or CANCELLED for a recipe that is no longer published.
	 * @param string         $message       Reason written to the error log for every settled row; empty writes the status only.
	 *
	 * @return void
	 */
	private function abandon_run( int $recipe_id, int $user_id, int $recipe_log_id, array $args, array $items, Background_Job $job, int $status, string $message = '' ): void {
		$wait = Background_Wait_Set::from_run( $this->log_store, $recipe_log_id, $items );
		foreach ( $wait->blocked_node_ids() as $node_id ) {
			$this->settle_blocked_node( $wait, $node_id, $recipe_id, $recipe_log_id, $job, $status, $message );
		}
		$this->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Settle a held-back node that will never run: its own row, or every row under a block, goes to the given status,
	 * with the message as each row's reason when one is given, then its entry is removed. A node another completion
	 * holds is left to it.
	 *
	 * @since 8.0.4
	 *
	 * @param Background_Wait_Set $wait          The run's wait state; the node is forgotten from it once claimed.
	 * @param int                 $node_id       The action or block.
	 * @param int                 $recipe_id     The recipe.
	 * @param int                 $recipe_log_id The run.
	 * @param Background_Job      $job           The job this completion holds.
	 * @param int                 $status        Terminal Automator_Status for the node's rows: SKIPPED, or CANCELLED for a recipe that is no longer published.
	 * @param string              $message       Reason written to the error log for each of the node's rows; empty writes the status only.
	 *
	 * @return bool Whether this completion settled it.
	 */
	private function settle_blocked_node( Background_Wait_Set $wait, int $node_id, int $recipe_id, int $recipe_log_id, Background_Job $job, int $status, string $message = '' ): bool {
		$record = $wait->record_for( $node_id );
		if ( null === $record || ! $this->claim_blocked_node( $wait, $node_id, $recipe_id, $recipe_log_id, $job ) ) {
			return false;
		}
		$wait->forget( $node_id );
		foreach ( 'action' === $record['kind'] ? array( $node_id ) : $record['covers'] as $action_id ) {
			$this->complete_action( (int) $action_id, $recipe_log_id, $status, $message );
		}
		$this->log_store->merge_recipe_log_meta_entry( $recipe_id, $recipe_log_id, Execution_Log_Store::META_BLOCKED_NODES, (string) $node_id, null );

		return true;
	}

	/**
	 * Take a held-back node for this completion by stamping its entry with the job id. A node another completion
	 * holds is left alone while that completion's job is still processing. A claim whose job is no longer
	 * processing, or that this job's own earlier attempt left behind, belongs to a request that died, so it is
	 * taken over and the node runs again: the poll decides when a completion died, by releasing the job claim
	 * after a poll interval, which is the same judgement that re-drives the job itself.
	 *
	 * @param Background_Wait_Set $wait          The run's wait state.
	 * @param int                 $node_id       The action or block.
	 * @param int                 $recipe_id     The recipe.
	 * @param int                 $recipe_log_id The run.
	 * @param Background_Job      $job           The job this completion holds.
	 *
	 * @return bool Whether this completion now holds the node.
	 */
	private function claim_blocked_node( Background_Wait_Set $wait, int $node_id, int $recipe_id, int $recipe_log_id, Background_Job $job ): bool {
		$record = $wait->record_for( $node_id );
		if ( null === $record ) {
			return false;
		}
		$holder = $record['claimed_by'] ?? null;
		if ( null !== $holder && $holder !== $job->job_id() ) {
			$other = $this->job_store->find_by_job_id( $holder );
			if ( null !== $other && Background_Job::STATUS_PROCESSING === $other->status() ) {
				return false;
			}
		}

		return $this->log_store->claim_recipe_log_meta_entry( $recipe_id, $recipe_log_id, Execution_Log_Store::META_BLOCKED_NODES, (string) $node_id, $job->job_id(), $holder );
	}

	/**
	 * The run's trigger args as the snapshot kept them, with the log ids the snapshot strips put back.
	 *
	 * @param Run_Snapshot $snapshot      The run snapshot.
	 * @param int          $recipe_log_id The run.
	 *
	 * @return array
	 */
	private function run_args_from_snapshot( Run_Snapshot $snapshot, int $recipe_log_id ): array {
		$args                  = (array) $snapshot->get( 'trigger_args', array() );
		$args['recipe_log_id'] = $recipe_log_id;

		$triggers   = (array) $snapshot->get( 'triggers', array() );
		$trigger_id = (int) ( $args['trigger_id'] ?? 0 );
		if ( isset( $triggers[ $trigger_id ]['trigger_log_id'] ) ) {
			$args['trigger_log_id'] = (int) $triggers[ $trigger_id ]['trigger_log_id'];
		}

		return $args;
	}

	/**
	 * Add the run's finished action rows to a map of finished action ids.
	 *
	 * @since 8.0.0
	 *
	 * @param int              $recipe_log_id The run.
	 * @param array<int, bool> $finished      Action ids already known to be finished, keyed by id.
	 *
	 * @return array<int, bool> The map with every action whose row has finished.
	 */
	private function with_finished_rows( int $recipe_log_id, array $finished ): array {
		foreach ( $this->log_store->get_action_log_rows( $recipe_log_id ) as $action_row ) {
			$action_row = (array) $action_row;
			if ( Automator_Status::finished( (int) $action_row['completed'] ) ) {
				$finished[ (int) $action_row['automator_action_id'] ] = true;
			}
		}

		return $finished;
	}

	/**
	 * Settle every listed row that has not finished to one terminal status with one message.
	 *
	 * @param int[]            $action_ids    Action ids to settle.
	 * @param array<int, bool> $finished      Action ids already finished, keyed by id.
	 * @param int              $recipe_log_id The run.
	 * @param int              $status        Terminal Automator_Status.
	 * @param string           $message       Written to the error log for each row.
	 *
	 * @return void
	 */
	private function settle_waiting_rows( array $action_ids, array $finished, int $recipe_log_id, int $status, string $message ): void {
		foreach ( array_unique( array_map( 'intval', $action_ids ) ) as $action_id ) {
			if ( $action_id > 0 && ! isset( $finished[ $action_id ] ) ) {
				$this->complete_action( $action_id, $recipe_log_id, $status, $message );
			}
		}
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

	/**
	 * Whether a user resolver is registered, so an Everyone recipe's run-as
	 * setting picks the user its actions run on.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function has_user_resolver(): bool {
		return null !== $this->user_resolver;
	}

	/**
	 * Accept and discard Pro 7.x's loop processor.
	 *
	 * Pro 7.x calls this beside set_user_resolver() at boot. The loop is a block
	 * now, dispatched by the processor Pro registers through
	 * register_block_processor(), so there is nothing to store and the walk never
	 * reads it. The method stays declared only so Pro 7.x does not fatal on a
	 * site whose Free half has already auto-updated.
	 *
	 * @deprecated 8.0.0 Remove in 8.1 with the Loop_Processor shim.
	 *
	 * @param Loop_Processor $processor Pro 7.x's processor. Discarded.
	 *
	 * @return void
	 */
	public function set_loop_processor( Loop_Processor $processor ): void {
	}
}
