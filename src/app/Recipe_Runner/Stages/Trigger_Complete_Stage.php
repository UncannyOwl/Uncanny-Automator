<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Stages;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Stages\Stage;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\User_Resolution;
use Uncanny_Automator\App\Recipe_Runner\Services\User_Resolver;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Handler;
use Uncanny_Automator\App\Recipe_Runner\Services\Integration_Registry;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Data_Provider;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Log_Manager;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Run_Snapshot_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Trigger_Diagnostics;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Stage 2: Trigger Completion.
 *
 * Marks triggers complete, checks if all/any triggers are done,
 * and enriches args with trigger IDs for token parsing.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Stages
 * @since   7.2
 */
class Trigger_Complete_Stage implements Stage {

	/**
	 * @var Action_Run_Stage
	 */
	private $action_run;

	/**
	 * @var Recipe_Log_Manager
	 */
	private $log_manager;

	/**
	 * @var User_Resolver|null Pro injects this for anonymous recipe user resolution.
	 */
	private $user_resolver;

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
	 * @var Error_Handler
	 */
	private $error_handler;

	/**
	 * @var Run_Snapshot_Store
	 */
	private $snapshot_store;

	/**
	 * @var Trigger_Diagnostics
	 */
	private $diagnostics;

	/**
	 * Build the stage with optional dependencies.
	 *
	 * @param Action_Run_Stage|null      $action_run      Optional custom action run stage.
	 * @param Recipe_Log_Manager|null    $log_manager     Optional log manager instance.
	 * @param User_Resolver|null         $user_resolver   Optional user resolver (Pro provides this).
	 * @param Execution_Log_Store|null             $log_store       Optional log store instance.
	 * @param Integration_Registry|null  $integrations    Optional integration registry.
	 * @param Recipe_Data_Provider|null  $data_provider   Optional data provider instance.
	 * @param Error_Handler|null         $error_handler   Optional error handler instance.
	 * @param Run_Snapshot_Store|null    $snapshot_store  Optional snapshot store instance.
	 */
	public function __construct( ?Action_Run_Stage $action_run = null, ?Recipe_Log_Manager $log_manager = null, ?User_Resolver $user_resolver = null, ?Execution_Log_Store $log_store = null, ?Integration_Registry $integrations = null, ?Recipe_Data_Provider $data_provider = null, ?Error_Handler $error_handler = null, ?Run_Snapshot_Store $snapshot_store = null ) {
		$this->action_run     = $action_run ?? new Action_Run_Stage();
		$this->log_manager    = $log_manager ?? new Recipe_Log_Manager();
		$this->user_resolver  = $user_resolver;
		$this->log_store      = $log_store ?? Database::get_execution_log_store();
		$this->integrations   = $integrations ?? new Integration_Registry();
		$this->data_provider  = $data_provider ?? new Recipe_Data_Provider();
		$this->error_handler  = $error_handler ?? new Error_Handler();
		$this->snapshot_store = $snapshot_store ?? Database::get_run_snapshot_store();
		$this->diagnostics   = new Trigger_Diagnostics();
	}

	/**
	 * Set the user resolver for anonymous recipe processing.
	 *
	 * Called by Recipe_Runner::set_user_resolver() when Pro registers
	 * its resolver after the stage is already constructed.
	 *
	 * @param User_Resolver $resolver The resolver implementation.
	 *
	 * @return void
	 */
	public function set_user_resolver( User_Resolver $resolver ): void {
		$this->user_resolver = $resolver;
	}

	/**
	 * Execute the trigger completion stage.
	 *
	 * @param Pipeline_Context $context Immutable pipeline context.
	 * @param Pipeline_Result  $result  Accumulated results from trigger entry stage.
	 *
	 * @return Pipeline_Result
	 */
	public function execute( Pipeline_Context $context, Pipeline_Result $result ): Pipeline_Result {

		if ( ! $context->should_mark_trigger_complete() ) {
			return $result;
		}

		$entries = $result->get_trigger_entries();

		if ( empty( $entries ) ) {
			return $result->halt( 'No trigger entries to complete.' );
		}

		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['result'] ) && isset( $entry['args'] ) ) {
				$this->complete_trigger_internal( $entry['args'], $result );
			}
		}

		return $result;
	}

	/**
	 * Complete a single trigger.
	 *
	 * Validates IDs, checks integration status, marks trigger complete,
	 * fires hooks, and runs actions when all triggers are satisfied.
	 *
	 * @param array $args Trigger args with recipe_id, trigger_id, user_id, log IDs, etc.
	 *
	 * @return bool|null True on success, null on validation failure.
	 */
	/**
	 * Complete a single trigger (public entry point for direct calls).
	 *
	 * Called by Recipe_Runner::complete_trigger() outside the pipeline loop.
	 * Returns a Pipeline_Result with execution_ready set if triggers are satisfied.
	 * The caller (Recipe_Runner) runs stages 3-5 via execute_stages_from().
	 *
	 * @param array $args Trigger args with recipe_id, trigger_id, user_id, log IDs, etc.
	 *
	 * @return Pipeline_Result Result with execution_ready set if actions should run.
	 */
	public function complete_trigger( array $args ): Pipeline_Result {

		$result = new Pipeline_Result();
		$this->complete_trigger_internal( $args, $result );
		return $result;
	}

	/**
	 * Internal trigger completion — shared by execute() and complete_trigger().
	 *
	 * @param array           $args   Trigger args.
	 * @param Pipeline_Result $result Pipeline result to set execution_ready on.
	 *
	 * @return void
	 */
	private function complete_trigger_internal( array $args, Pipeline_Result $result ): void {

		$ids = $this->extract_ids( $args );

		if ( null === $ids ) {
			return;
		}

		if ( ! $this->is_integration_active( $ids['trigger_id'] ) ) {
			return;
		}

		$this->fire_pre_completion_hooks( $ids, $args );

		$this->log_store->mark_trigger_complete(
			$ids['trigger_id'],
			$ids['user_id'],
			$ids['recipe_id'],
			$ids['recipe_log_id'],
			$ids['trigger_log_id']
		);

		$process = $this->build_filtered_process( $ids, $args );

		$this->fire_post_completion_hooks( $process );

		$this->maybe_run_actions( $process, $result );

		$this->diagnostics->record( $process['args'] ?? $args );

		if ( $result->is_execution_ready() ) {
			$this->capture_run_snapshot( $result );
		}
	}

	// ── Snapshot Capture ──────────────────────────────────────────────

	/**
	 * Correct trigger_log_meta run_number to match the recipe log.
	 *
	 * Trigger_Numtimes writes meta during Stage 1 with a run_number computed
	 * before the recipe log exists. If the recipe log got a different run_number,
	 * the meta rows drift. This corrects them so get_trigger_log_meta() queries
	 * that join on run_number find the right rows.
	 *
	 * @param int $recipe_log_id    The recipe log ID.
	 * @param int $stale_run_number The run_number written during Stage 1.
	 * @param int $correct_run_number The authoritative run_number from recipe_log.
	 *
	 * @return void
	 */
	private function sync_trigger_meta_run_number( int $recipe_log_id, int $stale_run_number, int $correct_run_number ): void {

		$trigger_log_ids = $this->log_store->get_trigger_log_ids_by_recipe_log( $recipe_log_id );

		if ( empty( $trigger_log_ids ) ) {
			return;
		}

		$this->log_store->sync_trigger_meta_run_numbers( $trigger_log_ids, $stale_run_number, $correct_run_number );
	}

	/**
	 * Capture a run snapshot for replay.
	 *
	 * Reads trigger_log_meta for all triggers in this run and stores
	 * the compressed snapshot. Runs BEFORE Stage 3 (actions) so the
	 * snapshot exists even if actions fail.
	 *
	 * @param Pipeline_Result $result The pipeline result with execution args.
	 *
	 * @return void
	 */
	private function capture_run_snapshot( Pipeline_Result $result ): void {

		$recipe_log_id = $result->get_recipe_log_id();
		$recipe_id     = $result->get_recipe_id();
		$user_id       = $result->get_user_id();
		$args          = $result->get_execution_args();

		// Collect trigger log meta for all triggers in this run.
		$triggers = array();

		$recipe_triggers = $args['recipe_triggers'] ?? array();

		if ( ! empty( $recipe_triggers ) ) {
			foreach ( $recipe_triggers as $trigger_id => $trigger_info ) {
				$trigger_log_id = absint( $trigger_info['trigger_log_id'] ?? 0 );
				$meta_rows      = $this->get_trigger_meta_rows( $trigger_log_id );

				$triggers[ $trigger_id ] = array(
					'trigger_id'     => absint( $trigger_id ),
					'trigger_log_id' => $trigger_log_id,
					'meta_rows'      => $meta_rows,
				);
			}
		} else {
			// Single-trigger recipe -- use args directly.
			$trigger_id     = absint( $args['trigger_id'] ?? 0 );
			$trigger_log_id = absint( $args['trigger_log_id'] ?? 0 );

			if ( 0 !== $trigger_id && 0 !== $trigger_log_id ) {
				$meta_rows = $this->get_trigger_meta_rows( $trigger_log_id );

				$triggers[ $trigger_id ] = array(
					'trigger_id'     => $trigger_id,
					'trigger_log_id' => $trigger_log_id,
					'meta_rows'      => $meta_rows,
				);
			}
		}

		// Strip log IDs from args -- these are run-specific, recreated on replay.
		$trigger_args = $args;
		unset( $trigger_args['recipe_log_id'], $trigger_args['trigger_log_id'] );

		$snapshot = array(
			'recipe_id'    => $recipe_id,
			'user_id'      => $user_id,
			'run_number'   => absint( $args['run_number'] ?? 1 ),
			'trigger_args' => $trigger_args,
			'triggers'     => $triggers,
		);

		$this->snapshot_store->capture( $recipe_log_id, $recipe_id, $user_id, $snapshot );
	}

	/**
	 * Retrieve all trigger_log_meta rows for a given trigger log.
	 *
	 * @param int $trigger_log_id The trigger log ID.
	 *
	 * @return array Array of associative arrays with meta_key and meta_value.
	 */
	private function get_trigger_meta_rows( int $trigger_log_id ): array {
		return $this->log_store->get_trigger_meta_rows( $trigger_log_id );
	}

	// ── Trigger Completion Helpers ──────────────────────────────────────

	/**
	 * Extract and validate required IDs from trigger args.
	 *
	 * @param array $args Raw trigger args.
	 *
	 * @return array{user_id: int, trigger_id: int, recipe_id: int, trigger_log_id: int, recipe_log_id: int}|null
	 */
	private function extract_ids( array $args ): ?array {

		// user_id=0 is valid for anonymous ("Everyone") recipes — do NOT
		// override it. Only fall back to get_current_user_id() when the key
		// is entirely missing from $args (legacy callers that forgot to pass it).
		// The legacy code (class-automator-recipe-process-complete.php:66) had
		// `if ( is_null( $user_id ) )` after `absint()` — which was dead code
		// (absint never returns null). This preserves that no-op behavior.
		$user_id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : absint( get_current_user_id() );

		$trigger_id = absint( $args['trigger_id'] ?? 0 );

		if ( 0 === $trigger_id ) {
			$this->error_handler->add_error(
				'complete_trigger',
				'ERROR: You are trying to complete a trigger without providing a trigger_id.',
				$this
			);
			return null;
		}

		$recipe_id = absint( $args['recipe_id'] ?? 0 );

		if ( 0 === $recipe_id ) {
			$this->error_handler->add_error(
				'complete_trigger',
				'ERROR: You are trying to complete a trigger without providing a recipe_id.',
				$this
			);
			return null;
		}

		$trigger_log_id = absint( $args['trigger_log_id'] ?? 0 );
		$recipe_log_id  = absint( $args['recipe_log_id'] ?? 0 );

		if ( 0 === $trigger_log_id || 0 === $recipe_log_id ) {
			$this->error_handler->add_error(
				'complete_trigger',
				'ERROR: trigger_log_id or recipe_log_id is 0 — broken Stage 1 output.',
				$this
			);
			return null;
		}

		return array(
			'user_id'        => $user_id,
			'trigger_id'     => $trigger_id,
			'recipe_id'      => $recipe_id,
			'trigger_log_id' => $trigger_log_id,
			'recipe_log_id'  => $recipe_log_id,
		);
	}

	/**
	 * Check whether the trigger's integration plugin is active.
	 *
	 * @param int $trigger_id The trigger post ID.
	 *
	 * @return bool
	 */
	private function is_integration_active( int $trigger_id ): bool {

		$trigger_code        = $this->data_provider->get_trigger_code( $trigger_id );
		$trigger_integration = $this->integrations->get_trigger_integration( $trigger_code );

		if ( ! $this->integrations->is_plugin_active( $trigger_integration ) ) {
			$this->error_handler->add_error(
				'complete_trigger',
				'ERROR: You are trying to complete ' . $trigger_code . ' and the plugin ' . $trigger_integration . ' is not active.',
				$this
			);
			return false;
		}

		return true;
	}

	/**
	 * Fire hooks before trigger is marked complete.
	 *
	 * @param array $ids  Extracted trigger/recipe IDs.
	 * @param array $args Raw trigger args.
	 *
	 * @return void
	 */
	private function fire_pre_completion_hooks( array $ids, array $args ): void {

		do_action_deprecated(
			'uap_before_trigger_completed',
			array( $ids['user_id'], $ids['trigger_id'], $ids['recipe_id'], $ids['trigger_log_id'], $args ),
			'3.0',
			'automator_before_trigger_is_completed'
		);

		Dispatcher::action(
			'automator_before_trigger_is_completed',
			$ids['user_id'],
			$ids['trigger_id'],
			$ids['recipe_id'],
			$ids['trigger_log_id'],
			$args
		);
	}

	/**
	 * Build the process context and apply continuation filters.
	 *
	 * Pro hooks into `automator_maybe_continue_recipe_process` to control
	 * whether the recipe should proceed to actions (e.g. Everyone recipes).
	 *
	 * @param array $ids  Extracted trigger/recipe IDs.
	 * @param array $args Raw trigger args.
	 *
	 * @return array Filtered process context.
	 */
	private function build_filtered_process( array $ids, array $args ): array {

		$original_user_id = $ids['user_id'];

		$process = array(
			'maybe_continue_recipe_process' => true,
			'recipe_id'                     => $ids['recipe_id'],
			'user_id'                       => $ids['user_id'],
			'recipe_log_id'                 => $ids['recipe_log_id'],
			'trigger_log_id'                => $ids['trigger_log_id'],
			'trigger_id'                    => $ids['trigger_id'],
			'args'                          => $args,
		);

		// Resolve anonymous users via injected resolver (Pro provides this).
		if ( null !== $this->user_resolver ) {
			$process = $this->resolve_anonymous_user( $process );
		}

		// Deprecated filter chain — kept for backward compat with third-party code.
		// Pro's resolver no longer hooks here; this is notification-only.
		$process = apply_filters_deprecated(
			'uap_maybe_continue_recipe_process',
			array( $process ),
			'3.0',
			'automator_maybe_continue_recipe_process'
		);

		$process = (array) Dispatcher::filter( 'automator_maybe_continue_recipe_process', $process );

		$this->maybe_sync_user_id_to_logs( $process, $original_user_id );

		return $process;
	}

	/**
	 * Resolve an anonymous user via the injected User_Resolver.
	 *
	 * Only runs for anonymous (Everyone) recipes that have user selector config.
	 * Modifies the $process array with the resolved user_id, parsed_data, and
	 * status flags (do-nothing, complete_with_errors).
	 *
	 * Flag propagation: do-nothing and error flags are set on $process['args']
	 * so that downstream status determination picks them up. Additionally,
	 * Action_Failure_Handler (hooked on automator_before_action_executed at
	 * priority 1) reads the resolution result to skip individual actions when
	 * user resolution failed — that hook-based coupling is separate from the
	 * flag propagation here.
	 *
	 * @param array $process The process context array.
	 *
	 * @return array Modified process with resolved user.
	 */
	private function resolve_anonymous_user( array $process ): array {

		$recipe_id   = absint( $process['recipe_id'] );
		$recipe_type = (string) $this->data_provider->get_recipe_type( $recipe_id );

		if ( 'anonymous' !== $recipe_type ) {
			return $process;
		}

		// Skip if no user selector is configured — recipe runs as anonymous without user resolution.
		$fields      = $this->data_provider->get_recipe_user_selector_fields( $recipe_id );
		$user_action = $this->data_provider->get_recipe_user_selector_source( $recipe_id );

		if ( empty( $fields ) && empty( $user_action ) ) {
			return $process;
		}

		$resolution = $this->user_resolver->resolve( $process );

		// Always continue the recipe flow — action skipping is handled
		// per-action by Action_Failure_Handler, not by halting the pipeline.
		$process['maybe_continue_recipe_process'] = true;

		if ( $resolution->has_user() ) {
			$process['user_id'] = $resolution->get_user_id();

			if ( isset( $process['args'] ) ) {
				$process['args']['user_id'] = $resolution->get_user_id();
			}
		} else {
			// Legacy parity (Pro <= 7.2.x: "May be trigger $args has user, use
			// that"). Resolution yielded no user, but the raw trigger args carry
			// one — e.g. a form plugin registered and logged the visitor in
			// before the trigger fired. Recover ATTRIBUTION from the args so the
			// run's logs point at that user; the do-nothing flag below still
			// skips the actions, exactly as legacy did.
			$args_user_id = absint( $process['args']['user_id'] ?? 0 );

			if ( 0 === absint( $process['user_id'] ?? 0 ) && $args_user_id > 0 ) {
				$process['user_id'] = $args_user_id;
			}
		}

		// Propagate flags so determine_recipe_status() / resolve_action_status() pick them up.
		if ( $resolution->is_do_nothing() && isset( $process['args'] ) ) {
			$process['args']['do-nothing'] = true;
		}

		if ( $resolution->should_complete_with_errors() && isset( $process['args'] ) ) {
			$process['args']['complete_with_errors'] = true;
		}

		$parsed_data = $resolution->get_parsed_data();
		if ( ! empty( $parsed_data ) && isset( $process['args'] ) ) {
			$process['args']['parsed_data'] = $parsed_data;
		}

		if ( '' !== $resolution->get_error_message() && isset( $process['args'] ) ) {
			$process['args']['user_action_message'] = $resolution->get_error_message();
		}

		return $process;
	}

	/**
	 * Sync user ID to log tables when a filter changes it.
	 *
	 * Pro's user selector resolves anonymous users via the continuation filter.
	 * When user_id changes, persist it across trigger_log, recipe_log, and trigger_log_meta.
	 *
	 * @param array $process          Filtered process context.
	 * @param int   $original_user_id The user ID before filters ran.
	 *
	 * @return void
	 */
	private function maybe_sync_user_id_to_logs( array $process, int $original_user_id ): void {

		$resolved_user_id = absint( $process['user_id'] ?? 0 );

		if ( 0 === $resolved_user_id || $resolved_user_id === $original_user_id ) {
			return;
		}

		$parsed_data = $process['args']['parsed_data'] ?? array();

		$this->log_manager->update_logs_user_id(
			$resolved_user_id,
			absint( $process['recipe_log_id'] ?? 0 ),
			absint( $process['trigger_id'] ?? 0 ),
			absint( $process['trigger_log_id'] ?? 0 ),
			$parsed_data
		);
	}

	/**
	 * Fire hooks after trigger is marked complete.
	 *
	 * @param array $process Filtered process context.
	 *
	 * @return void
	 */
	private function fire_post_completion_hooks( array $process ): void {

		do_action_deprecated( 'uap_trigger_completed', array( $process ), '3.0', 'automator_trigger_completed' );
		Dispatcher::action( 'automator_trigger_completed', $process );
	}

	/**
	 * Run actions if all/any triggers are satisfied.
	 *
	 * Reads recipe_id, user_id, recipe_log_id, and args from the filtered
	 * process context — Pro filters can override these values.
	 *
	 * @param array $process Filtered process context from build_filtered_process().
	 *
	 * @return void
	 */
	private function maybe_run_actions( array $process, Pipeline_Result $result ): void {

		if ( empty( $process['maybe_continue_recipe_process'] ) ) {
			return;
		}

		$recipe_id     = absint( $process['recipe_id'] ?? 0 );
		$user_id       = absint( $process['user_id'] ?? 0 );
		$recipe_log_id = absint( $process['recipe_log_id'] ?? 0 );
		$args          = $process['args'] ?? array();

		if ( ! $this->triggers_completed( $recipe_id, $user_id, $recipe_log_id, $args ) ) {
			return;
		}

		$this->add_recipe_count( $recipe_id );

		$args = $this->maybe_get_triggers_of_a_recipe( $args );

		// Read the authoritative run_number from the recipe log row.
		// $args['run_number'] may be stale (computed by Trigger_Numtimes before
		// the recipe log was created). The DB row is the single source of truth.
		$db_run_number    = $this->log_store->get_recipe_run_number( $recipe_log_id );
		$stale_run_number = absint( $args['run_number'] ?? 0 );

		if ( null !== $db_run_number ) {
			$args['run_number'] = $db_run_number;

			// Correct trigger_log_meta rows that were written with the stale
			// run_number during Stage 1 (before the recipe log existed).
			if ( $stale_run_number !== $db_run_number && 0 !== $stale_run_number ) {
				$this->sync_trigger_meta_run_number( $recipe_log_id, $stale_run_number, $db_run_number );
			}
		}

		// Signal stages 3-5. set_execution_ready() enforces consistency —
		// it overwrites recipe_id, user_id, recipe_log_id in $args with
		// the authoritative values from Pipeline_Result.
		$result->set_execution_ready( $recipe_id, $user_id, $recipe_log_id, $args );

		// Deprecated hooks — kept for backward compat with old Pro versions
		// whose license expired and still hook automator_actions_completed_run_flow.
		$flow_type = apply_filters_deprecated(
			'automator_triggers_completed_run_flow_type',
			array( 'linear', $recipe_id, $user_id, $recipe_log_id, $args, $this ),
			'7.3',
			'',
			'Loops now execute inside the unified pipeline. This filter no longer controls flow.'
		);

		do_action_deprecated(
			'automator_actions_completed_run_flow',
			array( $flow_type, $recipe_id, $user_id, $recipe_log_id, $args ),
			'7.3',
			'',
			'Loops now execute inside Action_Run_Stage::process_items(). This hook is notification-only.'
		);
	}

	// ── Trigger Status Helpers ─────────────────────────────────────────

	/**
	 * Check if all triggers in a recipe are completed.
	 *
	 * @param int   $recipe_id     The recipe ID.
	 * @param int   $user_id       The user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Trigger args.
	 *
	 * @return bool
	 */
	public function triggers_completed( int $recipe_id, int $user_id, int $recipe_log_id, array $args = array() ): bool {

		if ( 0 === $recipe_id ) {
			return false;
		}

		$trigger_statuses = $this->collect_trigger_statuses( $recipe_id, $user_id, $recipe_log_id, $args );

		if ( empty( $trigger_statuses ) ) {
			return false;
		}

		if ( true === $this->is_any_trigger_option_set( $recipe_id ) ) {
			return $this->is_any_recipe_trigger_completed( $trigger_statuses );
		}

		return $this->are_all_recipe_triggers_completed( $trigger_statuses );
	}

	/**
	 * Check if "Any" option is selected for triggers.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return bool
	 */
	public function is_any_trigger_option_set( int $recipe_id ): bool {
		$value  = $this->data_provider->get_trigger_logic( $recipe_id );
		$is_any = '' !== $value && 'any' === $value;

		return Dispatcher::filter( 'automator_recipe_any_trigger_complete', $is_any, $recipe_id );
	}

	/**
	 * Check if all recipe triggers are completed.
	 *
	 * @param array $statuses Map of trigger_id => completed status.
	 *
	 * @return bool
	 */
	public function are_all_recipe_triggers_completed( array $statuses ): bool {
		return ! in_array( false, $statuses, true );
	}

	/**
	 * Check if any recipe trigger is completed.
	 *
	 * @param array $statuses Map of trigger_id => completed status.
	 *
	 * @return bool
	 */
	public function is_any_recipe_trigger_completed( array $statuses ): bool {
		return in_array( true, $statuses, true );
	}

	// ── Trigger Enrichment ─────────────────────────────────────────────

	/**
	 * Enrich $args with all trigger IDs from a recipe for token parsing.
	 *
	 * @param array $args Trigger args.
	 *
	 * @return array Enriched args with recipe_triggers key.
	 */
	public function maybe_get_triggers_of_a_recipe( array $args = array() ): array {

		if ( empty( $args ) ) {
			return $args;
		}

		$user_id       = $args['user_id'] ?? null;
		$recipe_id     = $args['recipe_id'] ?? null;
		$recipe_log_id = $args['recipe_log_id'] ?? null;
		$run_number    = $args['run_number'] ?? null;

		if ( null === $user_id || null === $recipe_id || null === $recipe_log_id ) {
			return $args;
		}

		$recipe_triggers = $this->log_store->get_triggers_by_recipe_log_id( $user_id, $recipe_id, $recipe_log_id, $run_number );

		if ( empty( $recipe_triggers ) ) {
			return $args;
		}

		$meta = $args['meta'] ?? '';
		$code = $args['code'] ?? '';

		foreach ( $recipe_triggers as $recipe_trigger ) {
			$trigger_id                             = $recipe_trigger->automator_trigger_id;
			$args['recipe_triggers'][ $trigger_id ] = array(
				'recipe_id'      => $recipe_id,
				'recipe_log_id'  => $recipe_log_id,
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $recipe_trigger->trigger_log_id,
				'user_id'        => $user_id,
				'run_number'     => (int) ( $run_number ?? 0 ),
				'meta'           => $meta,
				'code'           => $code,
			);
		}

		return $args;
	}

	// ── Diagnostics ────────────────────────────────────────────────────

	/**
	 * Add backtrace property to trigger log.
	 *
	 * @deprecated 7.3 Use Trigger_Diagnostics::add_backtrace_property() directly.
	 *
	 * @param array $args Trigger args.
	 *
	 * @return void
	 */
	public function add_backtrace_property( array $args ): void {
		$this->diagnostics->add_backtrace_property( $args );
	}

	/**
	 * Add engine identifier property to trigger log.
	 *
	 * @deprecated 7.3 Use Trigger_Diagnostics::add_engine_property() directly.
	 *
	 * @param array $args Trigger args — checks for 'engine' key.
	 *
	 * @return void
	 */
	public function add_engine_property( array $args ): void {
		$this->diagnostics->add_engine_property( $args );
	}

	/**
	 * Store historical recipe count.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return void
	 */
	public function add_recipe_count( int $recipe_id ): void {
		$this->log_store->insert_recipe_count( $recipe_id );
	}

	/**
	 * Collect completion statuses for all published triggers in a recipe.
	 *
	 * @param int   $recipe_id     The recipe ID.
	 * @param int   $user_id       The user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Trigger args.
	 *
	 * @return array Map of trigger_id => bool.
	 */
	protected function collect_trigger_statuses( int $recipe_id, int $user_id, int $recipe_log_id, array $args ): array {

		$recipe_triggers  = $this->data_provider->get_recipe_data( AUTOMATOR_POST_TYPE_TRIGGER, $recipe_id );
		$trigger_statuses = array();

		foreach ( $recipe_triggers as $recipe_trigger ) {

			if ( 'publish' !== (string) $recipe_trigger['post_status'] ) {
				continue;
			}

			$trigger_integration = $recipe_trigger['meta']['integration'];

			if ( ! $this->integrations->is_plugin_active( $trigger_integration ) ) {
				$this->error_handler->add_error(
					'complete_trigger',
					'ERROR: You are trying to complete ' . $recipe_trigger['meta']['code'] . ' and the plugin ' . $trigger_integration . ' is not active. @recipe_id ' . $recipe_id,
					$this
				);
				continue;
			}

			$trigger_statuses[ $recipe_trigger['ID'] ] = $this->log_store->is_trigger_completed(
				(int) $user_id,
				(int) $recipe_trigger['ID'],
				(int) $recipe_id,
				(int) $recipe_log_id,
				true,
				(array) $args
			);
		}

		return $trigger_statuses;
	}
}
