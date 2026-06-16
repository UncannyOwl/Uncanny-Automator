<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Stages;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Pipeline_Exception;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Execution_Context;
use Uncanny_Automator\App\Recipe_Runner\Services\Loop_Processor;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Stages\Stage;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Handler;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\App\Recipe_Runner\Services\Integration_Registry;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Data_Provider;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\Recipe\Log_Properties;
use Uncanny_Automator\Services\Recipe\Structure;
use Uncanny_Automator\Utilities;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Stage 3: Action Execution.
 *
 * Executes all actions in a recipe — validates integration status,
 * creates action logs, parses custom values, and calls execution functions.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Stages
 * @since   7.2
 */
class Action_Run_Stage implements Stage {

	use Log_Properties;

	/**
	 * @var Recipe_Complete_Stage|null
	 */
	private $recipe_complete;

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
	 * @var Action_Error_Store
	 */
	private $error_store;

	/**
	 * @param Recipe_Complete_Stage|null $recipe_complete Optional injected stage for DI.
	 * @param Loop_Processor|null       $loop_processor  Optional loop processor (Pro provides this).
	 * @param Execution_Log_Store|null            $log_store       Optional log store instance.
	 * @param Integration_Registry|null $integrations    Optional integration registry.
	 * @param Recipe_Data_Provider|null $data_provider   Optional data provider instance.
	 * @param Error_Handler|null        $error_handler   Optional error handler instance.
	 * @param Action_Error_Store|null   $error_store     Optional uap_error_log writer for system-level failures (depth guard, pre-log exceptions).
	 */
	public function __construct( ?Recipe_Complete_Stage $recipe_complete = null, ?Loop_Processor $loop_processor = null, ?Execution_Log_Store $log_store = null, ?Integration_Registry $integrations = null, ?Recipe_Data_Provider $data_provider = null, ?Error_Handler $error_handler = null, ?Action_Error_Store $error_store = null ) {
		$this->recipe_complete = $recipe_complete;
		$this->loop_processor  = $loop_processor;
		$this->log_store       = $log_store ?? Database::get_execution_log_store();
		$this->integrations    = $integrations ?? new Integration_Registry();
		$this->data_provider   = $data_provider ?? new Recipe_Data_Provider();
		$this->error_handler   = $error_handler ?? new Error_Handler();
		$this->error_store     = $error_store ?? Database::get_action_error_store();
	}

	/**
	 * Set the loop processor for loop item execution.
	 *
	 * Called by Recipe_Runner::set_loop_processor() when Pro registers
	 * its processor after the stage is already constructed.
	 *
	 * @param Loop_Processor $processor The processor implementation.
	 *
	 * @return void
	 */
	public function set_loop_processor( Loop_Processor $processor ): void {
		$this->loop_processor = $processor;
	}

	/**
	 * Execute the action run stage.
	 *
	 * Reads execution state from Pipeline_Result (set by Stage 2).
	 * Builds Recipe\Structure, walks the tree, executes all actions.
	 * Does NOT run closures (Stage 5) or finalize recipe status (Stage 4).
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

		try {
			$this->complete_actions(
				$result->get_recipe_id(),
				$result->get_user_id(),
				$result->get_recipe_log_id(),
				$result->get_execution_args(),
				$result
			);
		} catch ( \Throwable $e ) {
			automator_log( 'complete_actions() failed: ' . $e->getMessage(), 'Action_Run_Stage' );
			$result->halt( 'Action stage exception: ' . $e->getMessage() );
		}

		return $result;
	}

	/**
	 * Get the Recipe_Complete_Stage instance.
	 *
	 * Falls back to the singleton's instance when none was injected,
	 * preserving backward compat for callers that don't use DI.
	 *
	 * @return Recipe_Complete_Stage
	 */
	protected function get_recipe_complete(): Recipe_Complete_Stage {
		if ( null === $this->recipe_complete ) {
			$this->recipe_complete = new Recipe_Complete_Stage();
		}
		return $this->recipe_complete;
	}

	/**
	 * Complete all actions in a recipe.
	 *
	 * @param int                 $recipe_id     The recipe ID.
	 * @param int                 $user_id       The user ID.
	 * @param int                 $recipe_log_id The recipe log ID.
	 * @param array               $args          Trigger args.
	 * @param Pipeline_Result|null $result       Current pipeline result — required for loops to signal
	 *                                           async work. Null when called via the legacy facade; in
	 *                                           that case loops cannot defer Stage 4 (facade has no
	 *                                           Stage 4 to defer), which is correct for that caller.
	 *
	 * @return bool
	 */
	public function complete_actions( int $recipe_id, int $user_id, int $recipe_log_id, array $args = array(), ?Pipeline_Result $result = null ): bool {

		try {
			$structure = new Structure(
				$recipe_id,
				array(
					'fields'       => array( 'show_original_field_resolver_structure' => true ),
					'publish_only' => true,
				)
			);

			$actions_object = $structure->retrieve()->get( 'actions' );
			$items          = null !== $actions_object ? $actions_object->get( 'items' ) : null;
		} catch ( \Throwable $e ) {
			automator_log( 'Structure retrieval failed for recipe_id=' . $recipe_id . ': ' . $e->getMessage(), 'Action_Run_Stage' );
			$items = null;
		}

		if ( empty( $items ) || ! is_iterable( $items ) ) {
			return $this->handle_no_actions( $recipe_id, $user_id, $recipe_log_id, $args );
		}

		$context = new Execution_Context( $recipe_id, $user_id, $recipe_log_id, $args );

		// Deprecated: previously allowed callers to reorder/filter the flat actions array.
		// Now fires as notification-only — return value is ignored since items come from Recipe\Structure.
		apply_filters_deprecated(
			'automator_process_complete_actions',
			array( $items, $recipe_id, $user_id, $recipe_log_id, $args ),
			'7.3',
			'',
			'Action items are now read from Recipe\\Structure. This filter no longer modifies the action list.'
		);

		Dispatcher::action( 'automator_before_process_complete_actions', $recipe_id, $user_id, $recipe_log_id, $items, $args );

		$this->process_items( $items, $context, $structure, 0, $result );

		// All loops are now queued. Dispatch the first one.
		// This happens AFTER the tree walk so all loops are in the queue
		// before any starts processing.
		if ( null !== $this->loop_processor ) {
			$this->loop_processor->dispatch_all();
		}

		Dispatcher::action( 'automator_recipe_process_complete_complete_actions_before_closures', $recipe_id, $user_id, $recipe_log_id, $args );

		$this->log_store->update_recipe_count( $recipe_id );

		// Closures moved to Closure_Stage (Stage 5). Recipe finalization moved to
		// Recipe_Complete_Stage (Stage 4). This method only executes actions.

		return true;
	}

	/**
	 * Walk the recipe structure tree and dispatch each item by type.
	 *
	 * Actions execute inline. Filters recurse into children.
	 * Loops delegate to the injected Loop_Processor (Pro provides this).
	 * Unknown types fire a hook for third-party extensibility.
	 *
	 * @param iterable             $items     Items from Recipe\Structure.
	 * @param Execution_Context    $context   Current execution state.
	 * @param Structure            $structure Recipe\Structure instance.
	 * @param int                  $depth     Recursion depth counter.
	 * @param Pipeline_Result|null $result    Current pipeline result (threaded through
	 *                                        to process_loop so it can mark async work
	 *                                        dispatched). Null tolerated for legacy callers.
	 *
	 * @return void
	 */
	public function process_items( $items, Execution_Context $context, Structure $structure, int $depth = 0, ?Pipeline_Result $result = null ): void {

		// Recursion guard — prevents stack overflow from circular filter references.
		// Silent drop would lie to the resolver (no action_log rows, recipe reports
		// COMPLETED) — record a system error so Stage 4 surfaces the failure.
		if ( $depth > 50 ) {
			automator_log( 'Recursion depth exceeded (50) in process_items — possible circular filter reference', 'Action_Run_Stage' );
			$this->error_store->store_system_error(
				$context->get_recipe_log_id(),
				new Action_Error(
					Error_Code::RECIPE_RECURSION,
					'Recipe item tree exceeded 50 levels — possible circular filter reference; remaining items were not executed',
					array(
						'depth'     => $depth,
						'recipe_id' => $context->get_recipe_id(),
					)
				)
			);
			return;
		}

		foreach ( $items as $item ) {

			try {

				$type = $item['type'] ?? 'action';

				switch ( $type ) {

					case 'action':
						$action_data = $this->build_action_data( $item );
						$this->execute_single_action(
							$action_data,
							$context->get_recipe_id(),
							$context->get_user_id(),
							$context->get_recipe_log_id(),
							$context->get_args()
						);
						break;

					case 'filter':
						$this->process_filter( $item, $context, $structure, $depth, $result );
						break;

					case 'loop':
						$this->process_loop( $item, $context, $structure, $result );
						break;

					case 'delay_schedule':
						$this->process_delay( $item, $context, $structure );
						break;

					default:
						Dispatcher::action( 'automator_process_unknown_item_type', $type, $item, $context );
						break;
				}
			} catch ( \Throwable $e ) {
				// Log and continue — one bad item must not kill the remaining items.
				//
				// Also persist to uap_error_log so Stage 4's resolver sees the
				// failure. Pre-log exceptions (thrown before create_action() writes
				// the action_log row) would otherwise leave no trace and the
				// recipe would finalize as COMPLETED — silently hiding the failure.
				automator_log(
					'Item processing failed: ' . $e->getMessage() . ' (type=' . ( $item['type'] ?? 'unknown' ) . ', id=' . ( $item['id'] ?? '?' ) . ')',
					'Action_Run_Stage'
				);
				$this->error_store->store_system_error(
					$context->get_recipe_log_id(),
					new Action_Error(
						Error_Code::SYSTEM_ERROR,
						$e->getMessage(),
						array(
							'item_type' => $item['type'] ?? 'unknown',
							'item_id'   => $item['id'] ?? null,
							'recipe_id' => $context->get_recipe_id(),
							'exception' => get_class( $e ),
						)
					)
				);
				continue;
			}
		}
	}

	/**
	 * Process a filter item — evaluate conditions and recurse into children.
	 *
	 * Current model: children in $item['items']. Condition evaluation
	 * happens per-action via automator_before_action_executed filter
	 * (Actions_Conditions at priority 5).
	 *
	 * Phase 8 blocks: paths with IF/ELSE branches via $item['paths'].
	 *
	 * @param array                $item      Filter item from structure.
	 * @param Execution_Context    $context   Execution state.
	 * @param Structure            $structure Recipe structure.
	 * @param int                  $depth     Recursion depth counter.
	 * @param Pipeline_Result|null $result    Current pipeline result (threaded through).
	 *
	 * @return void
	 */
	protected function process_filter( array $item, Execution_Context $context, Structure $structure, int $depth = 0, ?Pipeline_Result $result = null ): void {

		// Unified blocks (uo-block post type): children live in paths keyed by path ID.
		// Each path is an ordered array of actions and/or nested blocks.
		// Legacy (actions_conditions meta): children live in flat items array.
		if ( isset( $item['paths'] ) && is_array( $item['paths'] ) ) {
			foreach ( $item['paths'] as $path_children ) {
				if ( ! empty( $path_children ) ) {
					$this->process_items( $path_children, $context, $structure, $depth + 1, $result );
				}
			}
			return;
		}

		$children = $item['items'] ?? array();

		if ( ! empty( $children ) ) {
			$this->process_items( $children, $context, $structure, $depth + 1, $result );
		}
	}

	/**
	 * Process a loop item — delegate to the injected Loop_Processor.
	 *
	 * @param array                $item      Loop item from structure.
	 * @param Execution_Context    $context   Execution state.
	 * @param Structure            $structure Recipe structure.
	 * @param Pipeline_Result|null $result    Current pipeline result — passed explicitly
	 *                                        so re-entrant calls (Pro "Run a recipe" action
	 *                                        synchronously re-invoking the pipeline) mark
	 *                                        async work on the correct result, not on a
	 *                                        shared instance field.
	 *
	 * @return void
	 */
	protected function process_loop( array $item, Execution_Context $context, Structure $structure, ?Pipeline_Result $result = null ): void {

		if ( null === $this->loop_processor ) {
			Dispatcher::action( 'automator_missing_processor', 'loop', $item, $context );
			return;
		}

		$this->loop_processor->process( $item, $context->to_array(), $structure );

		// Loop was dispatched to background — Stage 4 must skip finalize_recipe().
		// Pro's loop completion callback will finalize when all iterations are done.
		if ( null !== $result ) {
			$result->mark_async_work_dispatched();
		}
	}

	/**
	 * Process a delay item — delegate to the injected Delay_Processor.
	 *
	 * Placeholder for Phase 8. Currently a no-op.
	 *
	 * @param array             $item      Delay item from structure.
	 * @param Execution_Context $context   Execution state.
	 * @param Structure         $structure Recipe structure.
	 *
	 * @return void
	 */
	protected function process_delay( array $item, Execution_Context $context, Structure $structure ): void {
		Dispatcher::action( 'automator_missing_processor', 'delay', $item, $context );
	}

	/**
	 * Convert a Recipe\Structure action item to action_data format.
	 *
	 * Structure items use: id, code, integration_code, is_item_on.
	 * execute_single_action() expects: ID, post_status, meta (with meta.code, meta.integration).
	 *
	 * @param array $item Action item from Recipe\Structure (json_decoded associative array).
	 *
	 * @return array Action data compatible with execute_single_action().
	 */
	protected function build_action_data( array $item ): array {

		$action_id = $item['id'] ?? 0;

		// Structure items don't carry flat meta — build it from post meta.
		$meta = \Uncanny_Automator\Utilities::flatten_post_meta( (array) get_post_meta( absint( $action_id ) ) );

		// Ensure code and integration are present — Structure hydrates these as top-level props.
		if ( ! isset( $meta['code'] ) && isset( $item['code'] ) ) {
			$meta['code'] = $item['code'];
		}

		if ( ! isset( $meta['integration'] ) && isset( $item['integration_code'] ) ) {
			$meta['integration'] = $item['integration_code'];
		}

		return array(
			'ID'          => $action_id,
			'post_status' => ( isset( $item['is_item_on'] ) && true === $item['is_item_on'] ) ? 'publish' : 'draft',
			'meta'        => $meta,
		);
	}

	/**
	 * Complete a single action.
	 *
	 * @param array $action_data   The action data.
	 * @param int   $recipe_id     The recipe ID.
	 * @param int   $user_id       The user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Trigger args.
	 *
	 * @return bool
	 */
	public function complete_action( array $action_data, int $recipe_id, int $user_id, int $recipe_log_id, array $args = array() ): bool {

		if ( 'draft' === (string) $action_data['post_status'] ) {
			return false;
		}

		$action_data['recipe_log_id'] = $recipe_log_id;

		try {
			$this->validate_action_prerequisites( $action_data );
			$this->run_action_callback( $action_data, $recipe_id, $user_id, $recipe_log_id, $args );
		} catch ( \Throwable $e ) {
			$this->create_error_property( $e->getTraceAsString() );
			$this->complete_with_error( $user_id, $action_data, $recipe_id, $e->getMessage(), $recipe_log_id, $args, $e );
		}

		return true;
	}

	/**
	 * Verify that an action execution function exists.
	 *
	 * @param mixed $action_execution_function The function reference.
	 *
	 * @return void
	 * @throws \Exception When function doesn't exist.
	 */
	public function verify_execution_function( $action_execution_function ): void {

		$error = $this->error_handler->get_error_message( 'action-function-not-exist' );

		if ( null === $action_execution_function ) {
			throw new Pipeline_Exception( esc_html( $error ) );
		}

		if ( is_array( $action_execution_function ) && ! method_exists( $action_execution_function[0], $action_execution_function[1] ) ) {
			throw new Pipeline_Exception( esc_html( $error ) );
		}

		if ( is_string( $action_execution_function ) && ! function_exists( $action_execution_function ) ) {
			throw new Pipeline_Exception( esc_html( $error ) );
		}
	}

	/**
	 * Create an action log entry.
	 *
	 * @param int    $user_id       The user ID.
	 * @param array  $action_data   The action data.
	 * @param int    $recipe_id     The recipe ID.
	 * @param string $error_message Error message.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param array  $args          Trigger args.
	 *
	 * @return int The action log ID.
	 */
	public function create_action( int $user_id, array $action_data, int $recipe_id, $error_message = '', $recipe_log_id = null, array $args = array() ): int {

		$action_id = (int) $action_data['ID'];
		$completed = (int) $action_data['completed'];
		$date_time = Dispatcher::filter( 'automator_action_log_date_time', null, $action_data );

		$action_log_id = $this->log_store->add_action(
			array(
				'user_id'       => $user_id,
				'action_id'     => $action_id,
				'recipe_id'     => $recipe_id,
				'recipe_log_id' => $recipe_log_id,
				'completed'     => $completed,
				'error_message' => $error_message,
				'date_time'     => $date_time,
			)
		);

		$this->store_action_sentences( $user_id, $action_log_id, $action_id );

		return $action_log_id;
	}

	/**
	 * Parse custom value fields before action execution.
	 *
	 * @param array $action_data The action data.
	 * @param int   $user_id     The user ID.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Trigger args.
	 *
	 * @return array Modified action data.
	 */
	public function parse_custom_value( array $action_data, int $user_id, int $recipe_id, array $args ): array {

		if ( ! isset( $action_data['meta'] ) ) {
			return $action_data;
		}

		$custom_keys = preg_grep( '/(automator_custom_value)/', $action_data['meta'] );

		if ( ! $custom_keys ) {
			return $action_data;
		}

		$updated_values = array();

		foreach ( $custom_keys as $action_meta => $custom_value ) {
			$custom_key = "{$action_meta}_custom";

			if ( ! array_key_exists( $custom_key, $action_data['meta'] ) ) {
				continue;
			}

			$parsed = $this->data_provider->parse_text( $action_data['meta'][ $custom_key ], $recipe_id, $user_id, $args );

			if ( $parsed ) {
				$action_data['meta'][ $action_meta ] = $parsed;
				$updated_values[ $action_meta ]      = $parsed;
			}
		}

		$this->persist_custom_values( $updated_values, $user_id, $args );

		return $action_data;
	}

	/**
	 * Complete an action with an error.
	 *
	 * @param int    $user_id       The user ID.
	 * @param array  $action_data   The action data.
	 * @param int    $recipe_id     The recipe ID.
	 * @param string $error_message The error message.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param array  $args          Trigger args.
	 *
	 * @return void
	 */
	public function complete_with_error( int $user_id, array $action_data, int $recipe_id, string $error_message, int $recipe_log_id, array $args, ?\Throwable $exception = null ): void {

		$action_data['complete_with_errors'] = true;
		$this->error_handler->add_error( 'complete_action', $error_message, array( $action_data, $this ) );

		// Build structured error with pipeline context so uap_error_log gets a useful error_code and context.
		$inferred   = Error_Code::infer_from_message( $error_message );
		$error_code = Error_Code::UNKNOWN === $inferred ? Error_Code::EXECUTION_FAILED : $inferred;
		$context    = array(
			'action_code'      => $action_data['meta']['code'] ?? '',
			'integration_code' => $action_data['meta']['integration'] ?? '',
			'action_id'        => $action_data['ID'] ?? 0,
		);

		if ( null !== $exception ) {
			$context['exception_class'] = get_class( $exception );
			$context['exception_file']  = $exception->getFile() . ':' . $exception->getLine();
		}

		$action_data['structured_errors'] = array(
			new Action_Error( $error_code, $error_message, $context ),
		);

		$this->get_recipe_complete()->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
	}

	/**
	 * Handle the case when a recipe has no actions.
	 *
	 * @param int   $recipe_id     The recipe ID.
	 * @param int   $user_id       The user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Trigger args.
	 *
	 * @return bool
	 */
	protected function handle_no_actions( int $recipe_id, int $user_id, int $recipe_log_id, array $args ): bool {

		// Don't write status or run closures — Stage 4 (finalize_recipe) and
		// Stage 5 (Closure_Stage) own those responsibilities.
		// The resolver handles zero action log entries correctly:
		// falls through to flag-based resolution → COMPLETED or DID_NOTHING.
		return false;
	}

	/**
	 * Validate that an action's integration is active and connected.
	 *
	 * @param array $action_data The action data.
	 *
	 * @return void
	 * @throws \Exception When prerequisites are not met.
	 */
	protected function validate_action_prerequisites( array $action_data ): void {

		$action_code        = $action_data['meta']['code'];
		$action_integration = $this->integrations->get_action_integration( $action_code );

		if ( ! $this->integrations->is_plugin_active( $action_integration ) ) {
			throw new Pipeline_Exception( esc_html( $this->error_handler->get_error_message( 'action-not-active' ) ) );
		}

		if ( ! $this->integrations->is_app_connected( $action_integration ) ) {
			throw new Pipeline_Exception( esc_html( $this->error_handler->get_error_message( 'app-not-connected' ) ) );
		}
	}

	/**
	 * Prepare and execute the action callback function.
	 *
	 * @param array $action_data   The action data.
	 * @param int   $recipe_id     The recipe ID.
	 * @param int   $user_id       The user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Trigger args.
	 *
	 * @return void
	 * @throws \Exception When the action is skipped by a filter.
	 */
	protected function run_action_callback( array &$action_data, int $recipe_id, int $user_id, int $recipe_log_id, array $args ): void {

		$action_code               = $action_data['meta']['code'];
		$action_execution_function = $this->integrations->get_action_execution_function( $action_code );

		$this->verify_execution_function( $action_execution_function );

		$action_data['completed'] = Automator_Status::NOT_COMPLETED;

		if ( ! isset( $action_data['loop'] ) ) {
			$action_data['action_log_id'] = $this->create_action( $user_id, $action_data, $recipe_id, '', $recipe_log_id, $args );
		}

		$action_data['args'] = $args;
		$action_data         = $this->parse_custom_value( $action_data, $user_id, $recipe_id, $args );

		$action_args                = $args;
		$action_args['action_meta'] = $action_data['meta'] ?? array();

		$action = Dispatcher::filter(
			'automator_before_action_executed',
			array(
				'user_id'     => $user_id,
				'action_data' => $action_data,
				'recipe_id'   => $recipe_id,
				'args'        => $action_args,
			),
			$args
		);

		if ( $this->is_action_skipped( $action, $args ) ) {
			// Finalize the log row so it doesn't stay orphaned at NOT_COMPLETED.
			//
			// Deferred async dispatches (Background_Actions, and any future
			// integration that sets process_further_reason = 'background_dispatch'
			// / 'async_scheduled') write IN_PROGRESS so Recipe_Status_Resolver
			// treats the row as has_scheduled and Stage 4 defers finalize_recipe()
			// until the worker lands. True skips (condition filters, policy
			// blocks) stay at SKIPPED — terminal, resolver treats as done.
			if ( isset( $action_data['action_log_id'] ) ) {
				$deferred_reasons     = array( 'background_dispatch', 'async_scheduled' );
				$process_reason       = $action['process_further_reason'] ?? '';
				$is_deferred_dispatch = in_array( $process_reason, $deferred_reasons, true )
					// Pro <= 7.3.0.1 Async_Actions postpones delayed/scheduled
					// actions without declaring a reason — recognize the deferral
					// by the Action Scheduler job it just attached. A SKIPPED row
					// here makes Pro's run_with_hash() resume guard refuse the
					// execution ("already has completed status"), so the AS job
					// completes while the action never runs.
					|| ! empty( $action['action_data']['async']['job_id'] );
				if ( $is_deferred_dispatch ) {
					// Conditional NOT_COMPLETED → IN_PROGRESS: the worker fires
					// inside the filter (non-blocking POST) and on fast servers
					// completes the action before this finalize runs — never
					// downgrade a finished row back to IN_PROGRESS.
					$this->log_store->mark_action_scheduled( (int) $action_data['ID'], $recipe_log_id, Dispatcher::filter( 'automator_action_log_date_time', null, $action['action_data'] ) );
				} else {
					$this->log_store->mark_action_complete( (int) $action_data['ID'], $recipe_log_id, Automator_Status::SKIPPED );
				}
			}
			return;
		}

		call_user_func_array( $action_execution_function, $action );
	}

	/**
	 * Check if an action was skipped by the before-action filter.
	 *
	 * @param array $action The filtered action array.
	 * @param array $args   Original trigger args.
	 *
	 * @return bool
	 */
	protected function is_action_skipped( array &$action, array $args ): bool {

		if ( ! isset( $action['process_further'] ) ) {
			return false;
		}

		Dispatcher::action( 'automator_action_been_process_further', $action );

		if ( false === $action['process_further'] ) {
			$action = Dispatcher::filter( 'automator_action_complete_action_skipped', $action, $args );
			$this->error_handler->add_error( 'complete_action', 'Action was skipped by uap_before_action_executed filter' );
			return true;
		}

		unset( $action['process_further'] );

		return false;
	}

	/**
	 * Execute a single action, logging failures.
	 *
	 * @param array $action_data   The action data.
	 * @param int   $recipe_id     The recipe ID.
	 * @param int   $user_id       The user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Trigger args.
	 *
	 * @return void
	 */
	protected function execute_single_action( array $action_data, int $recipe_id, int $user_id, int $recipe_log_id, array $args ): void {

		$completed = $this->complete_action( $action_data, $recipe_id, $user_id, $recipe_log_id, $args );

		if ( false === $completed ) {
			Utilities::log(
				$this->error_handler->get_messages( 'complete_action' ),
				'Method complete_action has returned false',
				defined( 'AUTOMATOR_DEBUG_MODE' ) && AUTOMATOR_DEBUG_MODE,
				'complete_actions'
			);
		}
	}

	/**
	 * Store action sentence meta for logging.
	 *
	 * @param int $user_id       The user ID.
	 * @param int $action_log_id The action log ID.
	 * @param int $action_id     The action ID.
	 *
	 * @return void
	 */
	protected function store_action_sentences( int $user_id, int $action_log_id, int $action_id ): void {

		$sentences = $this->data_provider->get_action_sentence( $action_id );

		if ( empty( $sentences ) ) {
			return;
		}

		foreach ( $sentences as $meta_key => $meta_value ) {
			if ( ! empty( $meta_value ) ) {
				$this->log_store->add_action_meta( $user_id, $action_log_id, $action_id, $meta_key, maybe_serialize( $meta_value ) );
			}
		}
	}

	/**
	 * Persist parsed custom values to trigger meta.
	 *
	 * @param array $updated_values Map of meta_key => parsed_value.
	 * @param int   $user_id        The user ID.
	 * @param array $args           Trigger args.
	 *
	 * @return void
	 */
	protected function persist_custom_values( array $updated_values, int $user_id, array $args ): void {

		if ( empty( $updated_values ) ) {
			return;
		}

		foreach ( $updated_values as $meta_key => $meta_value ) {
			$this->log_store->add_trigger_meta(
				$args['trigger_id'],
				$args['trigger_log_id'],
				$args['run_number'],
				array(
					'user_id'        => $user_id,
					'trigger_id'     => $args['trigger_id'],
					'meta_key'       => $meta_key,
					'meta_value'     => $meta_value,
					'run_number'     => $args['run_number'],
					'trigger_log_id' => $args['trigger_log_id'],
				)
			);
		}
	}

	/**
	 * Create error property for stack trace logging.
	 *
	 * @param string $stack_trace The stack trace string.
	 *
	 * @return void
	 */
	protected function create_error_property( string $stack_trace ): void {
		$this->set_log_properties(
			array(
				'type'       => 'code',
				'label'      => esc_html_x( 'Stacktrace', 'Uncanny Automator', 'uncanny-automator' ),
				'value'      => $stack_trace,
				'attributes' => array(
					'code_language' => 'json',
				),
			)
		);
	}
}
