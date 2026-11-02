<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Stages;

use Uncanny_Automator\App\Bridge\Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Definition_Bridge;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Builder\Action\Registry\Action_Meta_Structure_Converter;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Schema_Reader;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Violation_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Violation;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Children_Runner;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Processor;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Processor_Registry;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Refusal_Aware;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Tree_Walk;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Walk_Completion_Aware;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Pipeline_Exception;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Handler;
use Uncanny_Automator\App\Recipe_Runner\Services\Integration_Registry;
use Uncanny_Automator\App\Recipe_Runner\Services\Pro_Plugin_Presence;
use Uncanny_Automator\App\Recipe_Runner\Services\Recipe_Data_Provider;
use Uncanny_Automator\App\Recipe_Runner\Services\Run_Detach_Request;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Execution_Context;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\Recipe\Log_Properties;
use Uncanny_Automator\Utilities;

/**
 * Stage 3: Action Execution.
 *
 * Reads the canonical tree, creates one NOT_COMPLETED action-log row per action outside loop
 * bodies, then walks the tree: actions execute inline on their row, blocks dispatch to the
 * processor registered for their type. After the walk every Walk_Completion_Aware processor gets
 * one after_walk() call.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Stages
 */
class Action_Run_Stage implements Stage {

	use Log_Properties;

	/**
	 * @var Recipe_Complete_Stage|null
	 */
	private $recipe_complete;

	/**
	 * Block type to processor; Pro registers, Free registers nothing.
	 *
	 * @var Block_Processor_Registry
	 */
	private $processors;

	/**
	 * The one tree read seam.
	 *
	 * @var Recipe_Schema_Reader
	 */
	private $reader;

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
	 * The block configuration rule, read before every dispatch.
	 *
	 * @var Recipe_Violation_Service
	 */
	private $violations;

	/**
	 * Whether Pro is loaded, and which version, recorded when a block finds no processor.
	 *
	 * @var Pro_Plugin_Presence
	 */
	private $pro;

	/**
	 * The blocks each run's current walk deferred, by run and block type, until after_walk() hands them over.
	 *
	 * @var array<int, array<string, int[]>>
	 */
	private $deferred_blocks = array();

	/**
	 * The registered action definitions, read when an action's data is built.
	 *
	 * @since 8.0.0
	 *
	 * @var Action_Definition_Bridge
	 */
	private $action_definitions;

	/**
	 * Reads the fields an action definition declares.
	 *
	 * @since 8.0.0
	 *
	 * @var Action_Meta_Structure_Converter
	 */
	private $meta_structure;

	/**
	 * Wire the stage; every dependency is lazy-defaulted.
	 *
	 * @param Recipe_Complete_Stage|null    $recipe_complete Completion stage.
	 * @param Block_Processor_Registry|null $processors      Block processors.
	 * @param Execution_Log_Store|null      $log_store       Log store.
	 * @param Integration_Registry|null     $integrations    Integration registry.
	 * @param Recipe_Data_Provider|null     $data_provider   Data provider.
	 * @param Error_Handler|null            $error_handler   Error handler.
	 * @param Action_Error_Store|null       $error_store     uap_error_log writer for run-level failures.
	 * @param Recipe_Schema_Reader|null     $reader          Tree reader.
	 * @param Recipe_Violation_Service|null $violations      Block configuration rule.
	 * @param Action_Definition_Bridge|null $action_definitions Registered action definitions.
	 * @param Action_Meta_Structure_Converter|null $meta_structure Reads the fields a definition declares.
	 * @param Pro_Plugin_Presence|null $pro Whether Pro is loaded, and which version.
	 *
	 * @since 8.0.0 Accepts $action_definitions and $meta_structure.
	 */
	public function __construct( ?Recipe_Complete_Stage $recipe_complete = null, ?Block_Processor_Registry $processors = null, ?Execution_Log_Store $log_store = null, ?Integration_Registry $integrations = null, ?Recipe_Data_Provider $data_provider = null, ?Error_Handler $error_handler = null, ?Action_Error_Store $error_store = null, ?Recipe_Schema_Reader $reader = null, ?Recipe_Violation_Service $violations = null, ?Action_Definition_Bridge $action_definitions = null, ?Action_Meta_Structure_Converter $meta_structure = null, ?Pro_Plugin_Presence $pro = null ) {
		$this->recipe_complete = $recipe_complete;
		$this->processors      = $processors ?? new Block_Processor_Registry();
		$this->log_store       = $log_store ?? Database::get_execution_log_store();
		$this->integrations    = $integrations ?? new Integration_Registry();
		$this->data_provider   = $data_provider ?? new Recipe_Data_Provider();
		$this->error_handler   = $error_handler ?? new Error_Handler();
		$this->error_store     = $error_store ?? Database::get_action_error_store();
		$this->reader          = $reader ?? Recipe_Schema_Reader::instance();
		$this->violations      = $violations ?? Recipe_Violation_Service::instance();
		$this->pro             = $pro ?? new Pro_Plugin_Presence();

		$this->action_definitions = $action_definitions ?? new Automator_Action_Definition_Bridge();
		$this->meta_structure     = $meta_structure ?? new Action_Meta_Structure_Converter();
	}

	/**
	 * The block processor registry, for registration and lookups.
	 *
	 * @return Block_Processor_Registry
	 */
	public function processors(): Block_Processor_Registry {
		return $this->processors;
	}

	/**
	 * The schema reader the walk and the resume both read through.
	 *
	 * @return Recipe_Schema_Reader
	 */
	public function reader(): Recipe_Schema_Reader {
		return $this->reader;
	}

	/**
	 * Execute the action run stage.
	 *
	 * Reads execution state from Pipeline_Result (set by Stage 2).
	 * Reads the canonical tree, walks it, executes all actions.
	 * Does NOT finalize recipe status (Stage 4).
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
			$this->error_store->store_system_error(
				$result->get_recipe_log_id(),
				new Action_Error( Error_Code::EXECUTION_FAILED, $e->getMessage() )
			);
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
	 * Execute the recipe's actions on the canonical tree.
	 *
	 * @param int                  $recipe_id     Recipe id.
	 * @param int                  $user_id       User id.
	 * @param int                  $recipe_log_id Recipe log id.
	 * @param array                $args          Trigger args.
	 * @param Pipeline_Result|null $result        The pipeline result deferred blocks mark async work on; the legacy facade passes none.
	 *
	 * @return bool False when the recipe has nothing to run or its legacy storage could not be migrated.
	 */
	public function complete_actions( int $recipe_id, int $user_id, int $recipe_log_id, array $args = array(), ?Pipeline_Result $result = null ): bool {

		$result = $result ?? new Pipeline_Result();

		try {
			$tree = $this->reader->get_or_build( $recipe_id );
		} catch ( Legacy_Storage_Migration_Exception $e ) {
			automator_log( $e->getMessage(), 'Action_Run_Stage' );
			$this->error_store->store_system_error(
				$recipe_log_id,
				new Action_Error( Error_Code::SYSTEM_ERROR, 'Legacy storage migration failed; the run did not execute. ' . $e->getMessage(), array( 'recipe_id' => $recipe_id ) )
			);
			return false;
		}

		$items = is_array( $tree ) ? (array) ( $tree['content'] ?? array() ) : array();

		if ( empty( $items ) ) {
			return $this->handle_no_actions( $recipe_id, $user_id, $recipe_log_id, $args );
		}

		$context = new Execution_Context( $recipe_id, $user_id, $recipe_log_id, $args, $this->create_run_rows( $items, $recipe_id, $user_id, $recipe_log_id, $args ) );

		// A tree with blocks keeps a chosen-paths and a block-config record from the start: a filter absent from the first was
		// never evaluated, a block absent from the second never reached, and only a 7.x run has neither.
		if ( in_array( 'block', array_column( $items, 'kind' ), true ) ) {
			$this->log_store->set_recipe_log_meta( $recipe_id, $recipe_log_id, Execution_Log_Store::META_BLOCK_CHOSEN_PATHS, '[]' );
			$this->log_store->set_recipe_log_meta( $recipe_id, $recipe_log_id, Execution_Log_Store::META_BLOCK_RUN_CONFIG, '[]' );
		}

		// In flight from here: a repeat trigger fire opens a new run, and a worker that finishes first is never reset.
		$this->log_store->mark_recipe_complete( $recipe_log_id, Automator_Status::IN_PROGRESS );

		apply_filters_deprecated(
			'automator_process_complete_actions',
			array( $items, $recipe_id, $user_id, $recipe_log_id, $args ),
			'7.3',
			'',
			'Action items are read from the canonical recipe tree. This filter no longer modifies the action list.'
		);

		Dispatcher::action( 'automator_before_process_complete_actions', $recipe_id, $user_id, $recipe_log_id, $items, $args );

		$this->process_items( $items, $context, $result, 0 );
		$this->after_walk( $context );

		Dispatcher::action( 'automator_recipe_action_walk_completed', $recipe_id, $user_id, $recipe_log_id, $args );
		/** @deprecated 8.0.0 Use automator_recipe_action_walk_completed; removed in 8.1. */
		Dispatcher::action_deprecated( 'automator_recipe_process_complete_complete_actions_before_closures', array( $recipe_id, $user_id, $recipe_log_id, $args ), '8.0.0', 'automator_recipe_action_walk_completed' );

		$this->log_store->update_recipe_count( $recipe_id );

		return true;
	}

	/**
	 * Create one NOT_COMPLETED row per switched-on action outside loop bodies, in tree order.
	 *
	 * @param array $items         Tree content.
	 * @param int   $recipe_id     Recipe id.
	 * @param int   $user_id       User id.
	 * @param int   $recipe_log_id Recipe log id.
	 * @param array $args          Trigger args.
	 *
	 * @return array<int, int> `action_id => action_log_id`.
	 */
	private function create_run_rows( array $items, int $recipe_id, int $user_id, int $recipe_log_id, array $args ): array {
		$map = array();
		foreach ( Tree_Walk::action_nodes( $items ) as $node ) {
			if ( false === ( $node['is_item_on'] ?? true ) ) {
				continue;
			}
			$action_id         = (int) $node['id'];
			$map[ $action_id ] = $this->create_action(
				$user_id,
				array(
					'ID'        => $action_id,
					'completed' => Automator_Status::NOT_COMPLETED,
				),
				$recipe_id,
				'',
				$recipe_log_id,
				$args
			);
		}

		return $map;
	}

	/**
	 * One after_walk() call per registered Walk_Completion_Aware processor; a throw is a system error on the run.
	 *
	 * The error names the blocks of that type the walk deferred (`block_ids`): the work the processor was handing
	 * over, such as the loops it was to dispatch, which the failure leaves undone.
	 *
	 * @param Execution_Context $context The run.
	 *
	 * @return void
	 */
	public function after_walk( Execution_Context $context ): void {
		$deferred = $this->deferred_blocks[ $context->get_recipe_log_id() ] ?? array();
		unset( $this->deferred_blocks[ $context->get_recipe_log_id() ] );

		foreach ( $this->processors->all() as $block_type => $processor ) {
			if ( ! $processor instanceof Walk_Completion_Aware ) {
				continue;
			}
			try {
				$processor->after_walk( $context );
			} catch ( \Throwable $e ) {
				automator_log( 'after_walk failed for ' . $block_type . ': ' . $e->getMessage(), 'Action_Run_Stage' );
				$this->error_store->store_system_error(
					$context->get_recipe_log_id(),
					new Action_Error(
						Error_Code::SYSTEM_ERROR,
						$e->getMessage(),
						array(
							'block_type' => $block_type,
							'block_ids'  => $deferred[ $block_type ] ?? array(),
							'recipe_id'  => $context->get_recipe_id(),
							'exception'  => get_class( $e ),
						)
					)
				);
			}
		}
	}

	/**
	 * Walk tree items: actions execute inline, blocks dispatch to their processor.
	 *
	 * One bad item never stops its siblings; the failure is stored on the run.
	 *
	 * @param array             $items   Tree items (`content[]` or a path's `items[]`).
	 * @param Execution_Context $context The run.
	 * @param Pipeline_Result   $result  The walk's result.
	 * @param int               $depth   Recursion depth.
	 *
	 * @return void
	 */
	public function process_items( array $items, Execution_Context $context, Pipeline_Result $result, int $depth = 0 ): void {

		if ( $depth > 50 ) {
			automator_log( 'Recursion depth exceeded (50) in process_items', 'Action_Run_Stage' );
			$this->error_store->store_system_error(
				$context->get_recipe_log_id(),
				new Action_Error(
					Error_Code::RECIPE_RECURSION,
					'Recipe item tree exceeded 50 levels; remaining items were not executed',
					array(
						'depth'     => $depth,
						'recipe_id' => $context->get_recipe_id(),
					)
				)
			);
			return;
		}

		$items = array_values( $items );
		foreach ( $items as $index => $item ) {
			try {
				switch ( (string) ( $item['kind'] ?? '' ) ) {
					case 'action':
						if ( $this->waits_on_background( $item, $context ) ) {
							break;
						}
						$this->process_action_node( $item, $context );
						$this->track_background_dispatch( $item, $context );
						break;
					case 'block':
						if ( $this->waits_on_background( $item, $context ) ) {
							break;
						}
						$this->process_block( $item, $context, $result, $depth );
						break;
					default:
						break;
				}
			} catch ( \Throwable $e ) {
				automator_log(
					'Item processing failed: ' . $e->getMessage() . ' (kind=' . ( $item['kind'] ?? 'unknown' ) . ', id=' . ( $item['id'] ?? '?' ) . ')',
					'Action_Run_Stage'
				);
				$this->error_store->store_system_error(
					$context->get_recipe_log_id(),
					new Action_Error(
						Error_Code::SYSTEM_ERROR,
						$e->getMessage(),
						array(
							'item_kind' => $item['kind'] ?? 'unknown',
							'item_id'   => $item['id'] ?? null,
							'recipe_id' => $context->get_recipe_id(),
							'exception' => get_class( $e ),
						)
					)
				);
			}
			// Outside the catch above on purpose: a failure while recording the remainder must never fall through to
			// the next sibling, which would run on this leg after the run detached.
			if ( $this->take_detach( $item, $context ) || $this->walk_detached( $context ) ) {
				Run_Detach_Request::note_recorded(
					$context->get_recipe_log_id(),
					$this->record_detached_remainder( array_slice( $items, $index + 1 ), $context )
				);
				return;
			}
		}
	}

	/**
	 * Execute one action node on its run-start row; switched-off and already-completed actions are skipped.
	 *
	 * @param array             $node    Action node.
	 * @param Execution_Context $context The run.
	 *
	 * @return void
	 */
	private function process_action_node( array $node, Execution_Context $context ): void {
		if ( false === ( $node['is_item_on'] ?? true ) ) {
			return;
		}

		$action_id = (int) $node['id'];
		if ( $context->is_completed( $action_id ) ) {
			return;
		}

		$this->execute_single_action( $this->scoped_action_data( $node, $context ), $context );
	}

	/**
	 * An action node's data for this run: in loop scope it carries the
	 * entity's `loop` args, so its completion lands on the entity's item.
	 *
	 * @param array             $node    Action node.
	 * @param Execution_Context $context The run.
	 *
	 * @return array
	 */
	private function scoped_action_data( array $node, Execution_Context $context ): array {
		$action_data = $this->build_action_data( $node );

		if ( $context->is_loop_scoped() ) {
			$loop                = $context->get_args()['loop'];
			$loop['action_id']   = (int) $node['id'];
			$action_data['loop'] = $loop;
		}

		return $action_data;
	}

	/**
	 * Snapshot the block's configuration, refuse it when that configuration is incomplete, else hand it to its processor.
	 *
	 * @param array             $node    Block node.
	 * @param Execution_Context $context The run.
	 * @param Pipeline_Result   $result  The walk's result.
	 * @param int               $depth   Depth of this block.
	 *
	 * @return void
	 */
	private function process_block( array $node, Execution_Context $context, Pipeline_Result $result, int $depth ): void {
		$block_type = (string) ( $node['block_type'] ?? '' );
		$block_id   = (int) ( $node['id'] ?? 0 );

		$this->snapshot_block_config( $node, $context );

		$processor = $this->processors->processor_for( $block_type );
		if ( null === $processor ) {
			$this->settle_not_run(
				$node,
				$context,
				Action_Error::without_escalation(
					Error_Code::BLOCK_NOT_RUN,
					esc_html_x( 'This block did not run because Uncanny Automator Pro is not active.', 'Recipe run log', 'uncanny-automator' ),
					array(
						'block_type'  => $block_type,
						'block_id'    => $block_id,
						'recipe_id'   => $context->get_recipe_id(),
						// Pro 7.x loads on an 8.0 Free but registers no processor: the log tells "not active" from "too old".
						'pro_active'  => $this->pro->is_active(),
						'pro_version' => $this->pro->version(),
					)
				)
			);
			return;
		}

		$violations = $this->violations->evaluate_block_configuration( $node );
		if ( Recipe_Violation_Service::has_violations( $violations ) ) {
			$this->refuse_block( $node, $processor, $violations, $context );
			return;
		}

		try {
			$outcome = $processor->process( $node, $context, new Block_Children_Runner( $this, $result, $this->log_store, $depth ) );
		} catch ( \Throwable $e ) {
			// Entity items in a loop body are Pro's; the walk's catch stores the throw on the run as before.
			if ( $context->is_loop_scoped() ) {
				throw $e;
			}
			automator_log( 'Block processor failed: ' . $e->getMessage() . ' (block_type=' . $block_type . ', id=' . $block_id . ')', 'Action_Run_Stage' );
			$this->settle_not_run(
				$node,
				$context,
				new Action_Error(
					Error_Code::BLOCK_NOT_RUN,
					sprintf(
						// translators: %s is the error the block stopped with.
						esc_html_x( 'This block stopped with an error, so the rest of its actions did not run: %s', 'Recipe run log', 'uncanny-automator' ),
						esc_html( $e->getMessage() )
					),
					array(
						'block_type' => $block_type,
						'recipe_id'  => $context->get_recipe_id(),
						'exception'  => get_class( $e ),
					)
				)
			);
			return;
		}

		if ( $outcome->is_not_run() ) {
			$this->settle_not_run( $node, $context, $outcome->error() );
			return;
		}

		if ( $outcome->is_deferred() ) {
			$result->mark_async_work_dispatched();
			$this->deferred_blocks[ $context->get_recipe_log_id() ][ $block_type ][] = $block_id;
		}

		if ( null !== $outcome->error() ) {
			$this->record_block_error( $node, $context, $outcome->error() );
		}
	}

	/**
	 * Settle a block that ran none of its actions, or stopped part-way: record why on the block, then skip every
	 * switched-on action under it that has not run, creating the row for one added after the run began.
	 * Nothing under the block is dispatched. In a loop body the entity's items are the processor's to settle.
	 *
	 * @since 8.0.0
	 *
	 * @param array             $node    Block node.
	 * @param Execution_Context $context The run.
	 * @param Action_Error      $error   Why the block did not run.
	 *
	 * @return void
	 */
	private function settle_not_run( array $node, Execution_Context $context, Action_Error $error ): void {
		$this->record_block_error( $node, $context, $error );

		if ( $context->is_loop_scoped() ) {
			return;
		}

		foreach ( Tree_Walk::action_nodes( array( $node ) ) as $action_node ) {
			$action_id = (int) $action_node['id'];
			if ( false === ( $action_node['is_item_on'] ?? true ) || $context->is_completed( $action_id ) ) {
				continue;
			}

			$row_id = $context->action_log_id_for( $action_id );
			if ( null === $row_id ) {
				$this->create_action(
					$context->get_user_id(),
					array(
						'ID'        => $action_id,
						'completed' => Automator_Status::SKIPPED,
					),
					$context->get_recipe_id(),
					'',
					$context->get_recipe_log_id(),
					$context->get_args()
				);
				continue;
			}

			// The context's completed set is fixed when the leg starts, so read the row: an action that ran earlier in
			// this walk (finished, or its background job is out) keeps its row. Anything else never ran, including a
			// row an enclosing delay marked scheduled before this leg resumed it.
			$status = $this->log_store->get_action_completion_status( $row_id );
			if ( null !== $status && ! Automator_Status::finished( $status ) && Automator_Status::COMPLETED_AWAITING !== $status ) {
				$this->log_store->mark_action_complete_by_id( $row_id, Automator_Status::SKIPPED );
			}
		}
	}

	/**
	 * Store a processor's error on its block, where the run log shows it.
	 *
	 * @since 8.0.0
	 *
	 * @param array             $node    Block node.
	 * @param Execution_Context $context The run.
	 * @param Action_Error      $error   The error.
	 *
	 * @return void
	 */
	private function record_block_error( array $node, Execution_Context $context, Action_Error $error ): void {
		$this->error_store->store_block_error( $context->get_recipe_log_id(), (int) ( $node['id'] ?? 0 ), $error );
	}

	/**
	 * Settle a block that cannot run as configured: every switched-on action
	 * under it that has not completed is completed with the reason, through
	 * the same failure path a throwing action takes, and a processor that
	 * records its own refusals is told. Nothing under the block is dispatched.
	 *
	 * @param array              $node       Block node.
	 * @param Block_Processor    $processor  The block's processor.
	 * @param Recipe_Violation[] $violations Why the block cannot run.
	 * @param Execution_Context  $context    The run.
	 *
	 * @return void
	 */
	private function refuse_block( array $node, Block_Processor $processor, array $violations, Execution_Context $context ): void {
		$reason = sprintf(
			// translators: %s is why the block's configuration is incomplete.
			esc_html_x( 'Not run: %s', 'Recipe run log', 'uncanny-automator' ),
			implode(
				' ',
				array_map(
					static function ( Recipe_Violation $violation ): string {
						return $violation->to_wire()['message'];
					},
					$violations
				)
			)
		);

		$this->record_refusal( $node, $reason, $violations, $context );

		foreach ( Tree_Walk::action_nodes( array( $node ) ) as $action_node ) {
			$action_id = (int) $action_node['id'];
			if ( false === ( $action_node['is_item_on'] ?? true ) || $context->is_completed( $action_id ) ) {
				continue;
			}

			$action_data                  = $this->scoped_action_data( $action_node, $context );
			$action_data['recipe_log_id'] = $context->get_recipe_log_id();
			if ( ! isset( $action_data['loop'] ) ) {
				$action_data['action_log_id'] = $context->action_log_id_for( $action_id )
					?? $this->create_action( $context->get_user_id(), $action_data, $context->get_recipe_id(), '', $context->get_recipe_log_id(), $context->get_args() );
			}

			$this->complete_with_error(
				$context->get_user_id(),
				$action_data,
				$context->get_recipe_id(),
				$reason,
				$context->get_recipe_log_id(),
				$context->get_args(),
				new Action_Error_Exception( $reason, Error_Code::VALIDATION_FAILED )
			);
		}

		if ( $processor instanceof Block_Refusal_Aware ) {
			$processor->refused( $node, $context, $reason );
		}
	}

	/**
	 * Record the refusal on the run so the log can show which modifier caused it and why. Once per block per run:
	 * a block in a loop body is refused once per entity, and every entity carries the same reason.
	 *
	 * @param array              $node       Block node.
	 * @param string             $reason     Plain-text reason.
	 * @param Recipe_Violation[] $violations Why the block cannot run.
	 * @param Execution_Context  $context    The run.
	 *
	 * @return void
	 */
	private function record_refusal( array $node, string $reason, array $violations, Execution_Context $context ): void {
		$block_id = (string) (int) ( $node['id'] ?? 0 );
		$recorded = json_decode( (string) $this->log_store->get_recipe_log_meta( $context->get_recipe_log_id(), Execution_Log_Store::META_BLOCK_REFUSALS ), true );
		if ( is_array( $recorded ) && isset( $recorded[ $block_id ] ) ) {
			return;
		}

		$this->log_store->merge_recipe_log_meta_entry(
			$context->get_recipe_id(),
			$context->get_recipe_log_id(),
			Execution_Log_Store::META_BLOCK_REFUSALS,
			$block_id,
			array(
				'reason'     => $reason,
				'violations' => array_map(
					static function ( Recipe_Violation $violation ): array {
						return $violation->to_wire();
					},
					$violations
				),
			)
		);
	}

	/**
	 * Hold a node back when it depends on unfinished background work: an action's row flips to BLOCKED_ON_DEPENDENCY,
	 * a block is simply not dispatched, and either is recorded on the run. Loop entities never wait.
	 *
	 * @param array             $node    Action or block node.
	 * @param Execution_Context $context The run.
	 *
	 * @return bool True when the node was held back.
	 */
	private function waits_on_background( array $node, Execution_Context $context ): bool {
		if ( $context->is_loop_scoped() ) {
			return false;
		}

		$blockers = $context->wait_set()->blockers_for( $node );
		if ( array() === $blockers ) {
			return false;
		}

		$node_id = (int) ( $node['id'] ?? 0 );
		$kind    = 'block' === (string) ( $node['kind'] ?? '' ) ? 'block' : 'action';
		$covers  = 'block' === $kind ? Tree_Walk::action_ids( array( $node ) ) : array();

		if ( 'action' === $kind && null !== $context->action_log_id_for( $node_id ) ) {
			$this->log_store->mark_action_complete( $node_id, $context->get_recipe_log_id(), Automator_Status::BLOCKED_ON_DEPENDENCY );
		}

		$context->wait_set()->add_blocked( $node_id, $kind, $blockers, 'action' === $kind ? (string) ( $node['integration_code'] ?? '' ) : '', $covers );
		$this->log_store->merge_recipe_log_meta_entry( $context->get_recipe_id(), $context->get_recipe_log_id(), Execution_Log_Store::META_BLOCKED_NODES, (string) $node_id, $context->wait_set()->record_for( $node_id ) );

		return true;
	}

	/**
	 * After an action ran, note it in the wait set when its row went to COMPLETED_AWAITING.
	 *
	 * @param array             $node    Action node.
	 * @param Execution_Context $context The run.
	 *
	 * @return void
	 */
	private function track_background_dispatch( array $node, Execution_Context $context ): void {
		if ( $context->is_loop_scoped() ) {
			return;
		}

		$action_id     = (int) ( $node['id'] ?? 0 );
		$action_log_id = $context->action_log_id_for( $action_id );
		if ( null === $action_log_id ) {
			return;
		}

		if ( Automator_Status::COMPLETED_AWAITING === $this->log_store->get_action_completion_status( $action_log_id ) ) {
			$context->wait_set()->add_awaiting( $action_id, (string) ( $node['integration_code'] ?? '' ) );
		}
	}

	/**
	 * Consume a detach request the node just made and decide whether to honour it: only a synchronous,
	 * non-loop walk detaches, and only after an action.
	 *
	 * @param array             $node    The node that just ran.
	 * @param Execution_Context $context The run.
	 *
	 * @return bool True when the walk detaches after this node.
	 */
	private function take_detach( array $node, Execution_Context $context ): bool {
		$recipe_log_id = $context->get_recipe_log_id();
		if ( ! Run_Detach_Request::take( $recipe_log_id ) ) {
			return false;
		}
		if ( $context->is_loop_scoped() || $context->is_continuation() || 'action' !== (string) ( $node['kind'] ?? '' ) ) {
			return false;
		}
		Run_Detach_Request::mark_detached( $recipe_log_id, (int) ( $node['id'] ?? 0 ) );
		return true;
	}

	/**
	 * Record the remaining siblings of a detached level as nodes waiting on the anchor, in walk order. A node that
	 * cannot be recorded, and every node after it, is skipped with a system error: nothing after the anchor may run
	 * on this leg, and a row left neither recorded nor settled would keep the run open for good.
	 *
	 * @param array<int, array<string, mixed>> $items   The siblings after the node that detached, at one level.
	 * @param Execution_Context                $context The walk.
	 *
	 * @return int Nodes recorded.
	 */
	private function record_detached_remainder( array $items, Execution_Context $context ): int {
		$recipe_log_id = $context->get_recipe_log_id();
		$anchor        = (int) Run_Detach_Request::anchor( $recipe_log_id );
		$items         = array_values( $items );
		$recorded      = 0;
		foreach ( $items as $index => $item ) {
			$kind    = (string) ( $item['kind'] ?? '' );
			$node_id = (int) ( $item['id'] ?? 0 );
			if ( $node_id <= 0 || ! in_array( $kind, array( 'action', 'block' ), true ) ) {
				continue;
			}
			if ( 'action' === $kind && false === ( $item['is_item_on'] ?? true ) ) {
				continue;
			}
			try {
				$this->record_blocked_node( $item, $anchor, $context );
			} catch ( \Throwable $e ) {
				$this->skip_unrecorded_remainder( array_slice( $items, $index ), $anchor, $context, $e );
				break;
			}
			++$recorded;
		}
		return $recorded;
	}

	/**
	 * Record one node as waiting on the anchor: the row goes BLOCKED_ON_DEPENDENCY, the wait set and the run meta
	 * gain its entry. Protected so a test can make the recording fail.
	 *
	 * @param array<string, mixed> $item    The node.
	 * @param int                  $anchor  The action the node waits on.
	 * @param Execution_Context    $context The walk.
	 *
	 * @return void
	 */
	protected function record_blocked_node( array $item, int $anchor, Execution_Context $context ): void {
		$recipe_log_id = $context->get_recipe_log_id();
		$kind          = (string) ( $item['kind'] ?? '' );
		$node_id       = (int) ( $item['id'] ?? 0 );
		$covers        = 'block' === $kind ? Tree_Walk::action_ids( array( $item ) ) : array();
		if ( 'action' === $kind && null !== $context->action_log_id_for( $node_id ) ) {
			$this->log_store->mark_action_complete( $node_id, $recipe_log_id, Automator_Status::BLOCKED_ON_DEPENDENCY );
		}
		$context->wait_set()->add_blocked( $node_id, $kind, array( $anchor ), 'action' === $kind ? (string) ( $item['integration_code'] ?? '' ) : '', $covers );
		$this->log_store->merge_recipe_log_meta_entry( $context->get_recipe_id(), $recipe_log_id, Execution_Log_Store::META_BLOCKED_NODES, (string) $node_id, $context->wait_set()->record_for( $node_id ) );
	}

	/**
	 * The part of the remainder that could not be recorded: every row under it is skipped, the wait set forgets
	 * what may have been half-recorded, and the run carries one system error naming the cause. Other levels of the
	 * walk keep recording their own later siblings: those nodes are independent, they run in the background, and
	 * the run finishes with the error naming the skipped steps. A failure inside this bookkeeping is logged and
	 * the walk still stops.
	 *
	 * @param array<int, array<string, mixed>> $items   The node that failed and everything after it.
	 * @param int                              $anchor  The action the run detached after.
	 * @param Execution_Context                $context The walk.
	 * @param \Throwable                       $cause   What the recording threw.
	 *
	 * @return void
	 */
	private function skip_unrecorded_remainder( array $items, int $anchor, Execution_Context $context, \Throwable $cause ): void {
		$recipe_log_id = $context->get_recipe_log_id();
		$skipped       = array();
		$message       = sprintf( 'The run handed off after action %d, but the steps after it could not be recorded for the background leg and were skipped: %s', $anchor, $cause->getMessage() );
		automator_log( $message, 'Action_Run_Stage' );
		try {
			foreach ( $items as $item ) {
				$context->wait_set()->forget( (int) ( $item['id'] ?? 0 ) );
			}
			foreach ( Tree_Walk::action_ids( $items ) as $action_id ) {
				if ( null === $context->action_log_id_for( $action_id ) ) {
					continue;
				}
				$this->log_store->mark_action_complete( $action_id, $recipe_log_id, Automator_Status::SKIPPED );
				$skipped[] = $action_id;
			}
			$this->error_store->store_system_error(
				$recipe_log_id,
				new Action_Error(
					Error_Code::SYSTEM_ERROR,
					$message,
					array(
						'anchor_action_id'   => $anchor,
						'skipped_action_ids' => $skipped,
						'recipe_id'          => $context->get_recipe_id(),
						'exception'          => get_class( $cause ),
					)
				)
			);
		} catch ( \Throwable $e ) {
			automator_log( 'Skipping the unrecorded remainder failed as well: ' . $e->getMessage(), 'Action_Run_Stage' );
		}
	}

	/**
	 * Whether this walk already detached at an enclosing level, so this level records its own remainder while the
	 * walk unwinds. Loop and continuation walks never detach.
	 *
	 * @param Execution_Context $context The walk.
	 *
	 * @return bool
	 */
	private function walk_detached( Execution_Context $context ): bool {
		return ! $context->is_loop_scoped() && ! $context->is_continuation() && Run_Detach_Request::is_detached( $context->get_recipe_log_id() );
	}

	/**
	 * Record the block's fields and modifiers as executed, and when the run reached it, once per block per run.
	 *
	 * @param array             $node    Block node.
	 * @param Execution_Context $context The run.
	 *
	 * @return void
	 */
	private function snapshot_block_config( array $node, Execution_Context $context ): void {
		$recipe_log_id = $context->get_recipe_log_id();
		$block_id      = (string) ( $node['id'] ?? 0 );

		// Two sibling blocks dispatched by two schedulers read-merge-write the same row; unlocked, the later write drops the earlier entry.
		$lock = 'automator_run_meta_' . $recipe_log_id;
		if ( ! $this->log_store->acquire_lock( $lock, 5 ) ) {
			automator_log( sprintf( 'Recipe log %d: run-meta lock not acquired; entry for block %s written unlocked.', $recipe_log_id, $block_id ), 'Recipe_Runner' );
		}

		try {
			$existing = json_decode( (string) $this->log_store->get_recipe_log_meta( $recipe_log_id, Execution_Log_Store::META_BLOCK_RUN_CONFIG ), true );
			$existing = is_array( $existing ) ? $existing : array();

			if ( isset( $existing[ $block_id ] ) ) {
				return;
			}

			$existing[ $block_id ] = array(
				'block_type' => (string) ( $node['block_type'] ?? '' ),
				'fields'     => is_array( $node['fields'] ?? null ) ? $node['fields'] : array(),
				'modifiers'  => is_array( $node['modifiers'] ?? null ) ? $node['modifiers'] : array(),
				'started_at' => current_time( 'mysql' ),
			);

			$this->log_store->set_recipe_log_meta( $context->get_recipe_id(), $recipe_log_id, Execution_Log_Store::META_BLOCK_RUN_CONFIG, (string) wp_json_encode( $existing ) );
		} finally {
			$this->log_store->release_lock( $lock );
		}
	}

	/**
	 * Convert a tree action node to the `action_data` shape the execution path expects.
	 *
	 * The store keeps only the fields the owner saved. An action reads each field its definition declares from
	 * `meta`, so a declared field with no saved value reads as an empty value, as it did when the store wrote one.
	 *
	 * @since 8.0.0 A declared field with no saved value reads as an empty value.
	 *
	 * @param array $node Action node with `id`, `code`, `integration_code`.
	 *
	 * @return array{ID: int, post_status: string, meta: array}
	 */
	public function build_action_data( array $node ): array {

		$action_id = (int) ( $node['id'] ?? 0 );

		$meta = Utilities::flatten_post_meta( (array) get_post_meta( $action_id ) );

		if ( ! isset( $meta['code'] ) && isset( $node['code'] ) ) {
			$meta['code'] = $node['code'];
		}

		if ( ! isset( $meta['integration'] ) && isset( $node['integration_code'] ) ) {
			$meta['integration'] = $node['integration_code'];
		}

		$meta += array_fill_keys( $this->declared_field_codes( (string) ( $meta['code'] ?? '' ) ), '' );

		return array(
			'ID'          => $action_id,
			'post_status' => ( false === ( $node['is_item_on'] ?? true ) ) ? 'draft' : 'publish',
			'meta'        => $meta,
		);
	}

	/**
	 * The field codes an action's definition declares.
	 *
	 * @since 8.0.0
	 *
	 * @param string $action_code The action code.
	 *
	 * @return string[] None for an action that is not registered: it does not run.
	 */
	private function declared_field_codes( string $action_code ): array {
		$definition = '' !== $action_code ? $this->action_definitions->get_action_definition( $action_code ) : null;

		if ( null === $definition ) {
			return array();
		}

		return array_map( 'strval', array_keys( $this->meta_structure->from_definition( $definition ) ) );
	}

	/**
	 * Complete a single action.
	 *
	 * @param array $action_data   The action data.
	 * @param int   $recipe_id     The recipe ID.
	 * @param int   $user_id       The user ID.
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array                  $args          Trigger args.
	 * @param Execution_Context|null $context       The run; carries the run-start row for this action. Legacy callers pass none.
	 *
	 * @return bool
	 */
	public function complete_action( array $action_data, int $recipe_id, int $user_id, int $recipe_log_id, array $args = array(), ?Execution_Context $context = null ): bool {

		if ( 'draft' === (string) $action_data['post_status'] ) {
			return false;
		}

		$action_data['recipe_log_id'] = $recipe_log_id;

		try {
			$this->validate_action_prerequisites( $action_data );
			$this->run_action_callback( $action_data, $recipe_id, $user_id, $recipe_log_id, $args, $context );
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
	 * @param int             $user_id       The user ID.
	 * @param array           $action_data   The action data.
	 * @param int             $recipe_id     The recipe ID.
	 * @param string          $error_message The error message.
	 * @param int             $recipe_log_id The recipe log ID.
	 * @param array           $args          Trigger args.
	 * @param \Throwable|null $exception     What failed; an Action_Error_Exception supplies its own error code.
	 *
	 * @return void
	 */
	public function complete_with_error( int $user_id, array $action_data, int $recipe_id, string $error_message, int $recipe_log_id, array $args, ?\Throwable $exception = null ): void {

		$action_data['complete_with_errors'] = true;
		$this->error_handler->add_error( 'complete_action', $error_message, array( $action_data, $this ) );

		// Build structured error with pipeline context so uap_error_log gets a useful error_code and context.
		if ( $exception instanceof Action_Error_Exception ) {
			$error_code = $exception->get_error_code();
		} else {
			$inferred   = Error_Code::infer_from_message( $error_message );
			$error_code = Error_Code::UNKNOWN === $inferred ? Error_Code::EXECUTION_FAILED : $inferred;
		}
		$context = array(
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

		// Don't write status — Stage 4 (finalize_recipe) owns that.
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
	protected function run_action_callback( array &$action_data, int $recipe_id, int $user_id, int $recipe_log_id, array $args, ?Execution_Context $context = null ): void {

		$action_code               = $action_data['meta']['code'];
		$action_execution_function = $this->integrations->get_action_execution_function( $action_code );

		$this->verify_execution_function( $action_execution_function );

		$action_data['completed'] = Automator_Status::NOT_COMPLETED;

		if ( ! isset( $action_data['loop'] ) ) {
			$action_log_id                = null !== $context ? $context->action_log_id_for( (int) $action_data['ID'] ) : null;
			$action_data['action_log_id'] = $action_log_id ?? $this->create_action( $user_id, $action_data, $recipe_id, '', $recipe_log_id, $args );
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
			// Deferred dispatches go IN_PROGRESS so Stage 4 waits; true skips are terminal.
			if ( isset( $action_data['action_log_id'] ) ) {
				$deferred_reasons = array( 'background_dispatch', 'async_scheduled' );
				$process_reason   = $action['process_further_reason'] ?? '';

				// Pro <= 7.3.0.1 postpones without a reason; the attached job id is the tell.
				$is_deferred_dispatch = in_array( $process_reason, $deferred_reasons, true )
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
	 * Execute a single action on the run, logging a false return.
	 *
	 * @param array             $action_data The action data.
	 * @param Execution_Context $context     The run.
	 *
	 * @return void
	 */
	protected function execute_single_action( array $action_data, Execution_Context $context ): void {

		$completed = $this->complete_action( $action_data, $context->get_recipe_id(), $context->get_user_id(), $context->get_recipe_log_id(), $context->get_args(), $context );

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
