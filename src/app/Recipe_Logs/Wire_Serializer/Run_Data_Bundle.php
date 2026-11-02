<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;

/**
 * Immutable holder for all execution data of one recipe run, prefetched once
 * and indexed by node id so the serializer walk never issues per-node
 * queries. Projection input only — not a domain aggregate.
 */
final class Run_Data_Bundle {

	/**
	 * Run-level errors stored against a block, indexed by block id, in the order stored.
	 *
	 * @var array<int,array<int,array{item_type: string, code: string, message: string, context: array<string,mixed>}>>
	 */
	private $block_errors = array();


	/**
	 * Raw uap_recipe_log row for this run.
	 *
	 * @var array<string,mixed>
	 */
	private $recipe_log_row;

	/**
	 * Action rows indexed by automator_action_id.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $action_rows = array();

	/**
	 * Trigger rows indexed by automator_trigger_id.
	 *
	 * @var array<int,array<int,array<string,mixed>>>
	 */
	private $trigger_rows = array();

	/**
	 * Retries indexed by item_log_id.
	 *
	 * @var array<int,array<int,array<string,mixed>>>
	 */
	private $api_retries;

	/**
	 * Action-log ids that have an API log row, keyed by id.
	 *
	 * @var array<int,bool>
	 */
	private $api_logged = array();

	/**
	 * Run token map, keyed by braced token identifier.
	 *
	 * @var array<string,string>
	 */
	private $tokens_map;

	/**
	 * Action-log meta bag, indexed by action_log_id then meta key.
	 *
	 * @var array<int,array<string,string>>
	 */
	private $action_meta_bag;

	/**
	 * Trigger-log meta bag, indexed by trigger_log_id then meta key.
	 *
	 * @var array<int,array<string,string>>
	 */
	private $trigger_meta_bag;

	/**
	 * Every stored `properties` value per trigger log, oldest row first, indexed by trigger_log_id.
	 *
	 * @since 8.0.0
	 *
	 * @var array<int,array<int,string>>
	 */
	private $trigger_properties;

	/**
	 * Recipe-log meta bag, indexed by meta key.
	 *
	 * @var array<string,string>
	 */
	private $recipe_meta_bag;

	/**
	 * Error message indexed by action_log_id.
	 *
	 * @var array<int,string>
	 */
	private $error_by_action_log = array();

	/**
	 * Error rows recorded against a block, in row order, indexed by block id.
	 *
	 * @var array<int,array<int,array{code:string,message:string}>>
	 */
	private $errors_by_block = array();

	/**
	 * Error rows recorded against the run itself (`item_type='system'`), in row order.
	 *
	 * @var array<int,array{code:string,message:string}>
	 */
	private $run_errors = array();

	/**
	 * Loop entries indexed by loop_id.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $loop_entries;

	/**
	 * Per-status breakdown indexed by action_id.
	 *
	 * @var array<int,array<string,int>>
	 */
	private $loop_breakdowns;

	/**
	 * Site-local creation datetime (post_date) indexed by tree node post id.
	 *
	 * @var array<int,string>
	 */
	private $node_created_map;

	/**
	 * Builds the bundle from prefetched raw store output, indexing rows by id.
	 *
	 * @param array<string,mixed> $parts Prefetched raw structures. Keys:
	 *   recipe_log_row, trigger_rows, action_rows, api_retries, api_logged, tokens_map,
	 *   action_meta_bag, trigger_meta_bag, trigger_properties, recipe_meta_bag, error_rows,
	 *   loop_entries, loop_breakdowns, node_created_map.
	 */
	public function __construct( array $parts ) {
		$this->recipe_log_row     = is_array( $parts['recipe_log_row'] ?? null ) ? $parts['recipe_log_row'] : array();
		$this->api_retries        = is_array( $parts['api_retries'] ?? null ) ? $parts['api_retries'] : array();
		$this->tokens_map         = is_array( $parts['tokens_map'] ?? null ) ? $parts['tokens_map'] : array();
		$this->action_meta_bag    = is_array( $parts['action_meta_bag'] ?? null ) ? $parts['action_meta_bag'] : array();
		$this->trigger_meta_bag   = is_array( $parts['trigger_meta_bag'] ?? null ) ? $parts['trigger_meta_bag'] : array();
		$this->trigger_properties = is_array( $parts['trigger_properties'] ?? null ) ? $parts['trigger_properties'] : array();
		$this->recipe_meta_bag    = is_array( $parts['recipe_meta_bag'] ?? null ) ? $parts['recipe_meta_bag'] : array();
		$this->loop_entries       = is_array( $parts['loop_entries'] ?? null ) ? $parts['loop_entries'] : array();
		$this->loop_breakdowns    = is_array( $parts['loop_breakdowns'] ?? null ) ? $parts['loop_breakdowns'] : array();
		$this->node_created_map   = is_array( $parts['node_created_map'] ?? null ) ? $parts['node_created_map'] : array();

		foreach ( (array) ( $parts['api_logged'] ?? array() ) as $action_log_id ) {
			$this->api_logged[ (int) $action_log_id ] = true;
		}

		foreach ( (array) ( $parts['action_rows'] ?? array() ) as $row ) {
			$this->action_rows[ (int) $row['automator_action_id'] ] = $row;
		}
		foreach ( (array) ( $parts['trigger_rows'] ?? array() ) as $row ) {
			$this->trigger_rows[ (int) $row['automator_trigger_id'] ][] = $row;
		}
		foreach ( (array) ( $parts['error_rows'] ?? array() ) as $row ) {
			$this->index_block_error( (array) $row );
		}

		foreach ( (array) ( $parts['error_rows'] ?? array() ) as $err ) {
			$this->index_error_row( (array) $err );
		}
	}

	/**
	 * File one `uap_error_log` row under what it was recorded against: the run itself
	 * (`system`), a block (`block`, by `error_context.block_id`) or an action-log row.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,mixed> $err The error row.
	 * @return void
	 */
	private function index_error_row( array $err ): void {
		$item_type = (string) ( $err['item_type'] ?? '' );
		$entry     = array(
			'code'    => (string) ( $err['error_code'] ?? '' ),
			'message' => (string) ( $err['error_message'] ?? '' ),
		);

		if ( 'system' === $item_type ) {
			$this->run_errors[] = $entry;
			return;
		}

		if ( 'block' === $item_type ) {
			$context = json_decode( (string) ( $err['error_context'] ?? '' ), true );

			$this->errors_by_block[ (int) ( $context['block_id'] ?? 0 ) ][] = $entry;
			return;
		}

		if ( isset( $err['action_log_id'] ) ) {
			$this->error_by_action_log[ (int) $err['action_log_id'] ] = $entry['message'];
		}
	}

	/**
	 * Index a stored system or block error under every block its context names.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,mixed> $err The uap_error_log row.
	 * @return void
	 */
	private function index_block_error( array $err ): void {
		if ( ! in_array( (string) ( $err['item_type'] ?? '' ), array( 'system', 'block' ), true ) ) {
			return;
		}

		$context = json_decode( (string) ( $err['error_context'] ?? '' ), true );
		$context = is_array( $context ) ? $context : array();
		$error   = array(
			'item_type' => (string) $err['item_type'],
			'code'      => (string) ( $err['error_code'] ?? '' ),
			'message'   => (string) ( $err['error_message'] ?? '' ),
			'context'   => $context,
		);

		foreach ( self::blocks_named_by( $context ) as $block_id ) {
			$this->block_errors[ $block_id ][] = $error;
		}
	}

	/**
	 * The blocks a run-level error's context names: `block_id` (a block with no processor, a processor's outcome,
	 * a resume), `item_id` when `item_kind` is `block` (a block whose processing threw), and every id in
	 * `block_ids` (the blocks a walk deferred to a processor whose after_walk() failed, such as the loops it was
	 * to dispatch). Empty when it names none.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,mixed> $context The error's decoded context.
	 * @return int[] Block post ids, each once.
	 */
	private static function blocks_named_by( array $context ): array {
		$ids = array_map( 'intval', is_array( $context['block_ids'] ?? null ) ? $context['block_ids'] : array() );
		if ( isset( $context['block_id'] ) ) {
			$ids[] = (int) $context['block_id'];
		} elseif ( 'block' === ( $context['item_kind'] ?? '' ) ) {
			$ids[] = (int) ( $context['item_id'] ?? 0 );
		}

		return array_values(
			array_unique(
				array_filter(
					$ids,
					static function ( int $id ): bool {
						return 0 < $id;
					}
				)
			)
		);
	}

	/**
	 * The raw uap_recipe_log row for this run.
	 *
	 * @return array<string,mixed> The recipe-log row.
	 */
	public function recipe_log_row(): array {
		return $this->recipe_log_row;
	}

	/**
	 * Site-local creation datetime (post_date) for a tree node post, or
	 * null when unknown (post gone, or the map wasn't prefetched — unknown
	 * never hides a node).
	 *
	 * @param int $post_id Node post id.
	 * @return string|null The creation datetime, or null.
	 */
	public function node_created_at( int $post_id ): ?string {
		return isset( $this->node_created_map[ $post_id ] ) ? (string) $this->node_created_map[ $post_id ] : null;
	}

	/**
	 * All action ids referenced in the prefetched action rows.
	 *
	 * @return array<int,int> Action post ids.
	 */
	public function all_action_ids(): array {
		return array_map( 'intval', array_keys( $this->action_rows ) );
	}

	/**
	 * Looks up the prefetched action-log row for an action.
	 *
	 * @param int $action_id Action post id.
	 * @return array<string,mixed>|null The action-log row, or null when absent.
	 */
	public function action_row( int $action_id ): ?array {
		return $this->action_rows[ $action_id ] ?? null;
	}

	/**
	 * All trigger ids referenced in the prefetched trigger rows.
	 *
	 * @return array<int,int> Trigger post ids.
	 */
	public function all_trigger_ids(): array {
		return array_map( 'intval', array_keys( $this->trigger_rows ) );
	}

	/**
	 * Looks up the prefetched trigger-log rows for a trigger.
	 *
	 * @param int $trigger_id Trigger post id.
	 * @return array<int,array<string,mixed>> The trigger's log rows, empty when none.
	 */
	public function trigger_rows_for( int $trigger_id ): array {
		return $this->trigger_rows[ $trigger_id ] ?? array();
	}

	/**
	 * Looks up the prefetched api-response retries for an action-log row.
	 *
	 * @param int $item_log_id The action_log row ID.
	 * @return array<int,array<string,mixed>> Retry rows, empty when none.
	 */
	public function retries_for( int $item_log_id ): array {
		return $this->api_retries[ $item_log_id ] ?? array();
	}

	/**
	 * Whether the action-log row has an API log row a resend can replay.
	 *
	 * @param int $action_log_id Action-log row id.
	 * @return bool
	 */
	public function has_api_log( int $action_log_id ): bool {
		return isset( $this->api_logged[ $action_log_id ] );
	}

	/**
	 * The run's token map.
	 *
	 * @return array<string,string> Token map keyed by braced token identifier.
	 */
	public function tokens_map(): array {
		return $this->tokens_map;
	}

	/**
	 * The run's token map keyed by bare token code (`{{admin_email}}` to `admin_email`), which is how the
	 * FE's sentence and property components look a pill up. Interpolation keeps using tokens_map().
	 *
	 * @return array<string,string>
	 */
	public function wire_tokens(): array {
		$out = array();
		foreach ( $this->tokens_map as $key => $value ) {
			$out[ (string) preg_replace( '/^\{\{|\}\}$/', '', (string) $key ) ] = $value;
		}

		return $out;
	}

	/**
	 * Looks up a single action-log meta value by key.
	 *
	 * @param int    $action_log_id The action_log row ID.
	 * @param string $key           The meta key.
	 * @return string|null The stored value, or null when the key is absent.
	 */
	public function action_meta( int $action_log_id, string $key ): ?string {
		return $this->action_meta_bag[ $action_log_id ][ $key ] ?? null;
	}

	/**
	 * Looks up a single trigger-log meta value by key.
	 *
	 * @param int    $trigger_log_id The trigger_log row ID.
	 * @param string $key            The meta key.
	 * @return string|null The stored value, or null when the key is absent.
	 */
	public function trigger_meta( int $trigger_log_id, string $key ): ?string {
		return $this->trigger_meta_bag[ $trigger_log_id ][ $key ] ?? null;
	}

	/**
	 * Every `properties` value a trigger log stored, oldest row first: one row per `set_trigger_log_properties()`
	 * call, plus the runner's own (execution engine, and the backtrace in debug mode).
	 *
	 * @since 8.0.0
	 *
	 * @param int $trigger_log_id The trigger_log row ID.
	 * @return array<int,string> The stored (serialized) values; empty when the run recorded none.
	 */
	public function trigger_properties( int $trigger_log_id ): array {
		return $this->trigger_properties[ $trigger_log_id ] ?? array();
	}

	/**
	 * Looks up a single recipe-log meta value by key.
	 *
	 * @param string $key The meta key.
	 * @return string|null The stored value, or null when the key is absent.
	 */
	public function recipe_meta( string $key ): ?string {
		return $this->recipe_meta_bag[ $key ] ?? null;
	}

	/**
	 * Whether the runner kept a chosen-paths record for this run. Absent on runs older than the record.
	 *
	 * @return bool
	 */
	public function chosen_paths_recorded(): bool {
		return is_array( json_decode( (string) $this->recipe_meta( Execution_Log_Store::META_BLOCK_CHOSEN_PATHS ), true ) );
	}

	/**
	 * Resolves which path a multi-path block took from the chosen-paths recipe meta.
	 *
	 * @param int $block_id The block's post ID.
	 * @return string|null The chosen path_code, or null when no decision was recorded.
	 */
	public function chosen_path_for_block( int $block_id ): ?string {
		$decoded = json_decode( (string) $this->recipe_meta( Execution_Log_Store::META_BLOCK_CHOSEN_PATHS ), true );
		if ( ! is_array( $decoded ) || ! isset( $decoded[ $block_id ]['chosen_path'] ) ) {
			return null;
		}
		return (string) $decoded[ $block_id ]['chosen_path'];
	}

	/**
	 * Whether the run records the blocks it reached: every 8.0.0 run with a block opens the record at its start; a 7.x run has none.
	 *
	 * @return bool
	 */
	public function has_block_run_config_record(): bool {
		return '' !== (string) $this->recipe_meta( Execution_Log_Store::META_BLOCK_RUN_CONFIG );
	}

	/**
	 * The configuration a block executed with in this run: `{ block_type, fields, modifiers, started_at }`, or null when the run did not reach the block or predates the record.
	 *
	 * @param int $block_id Block post id.
	 * @return array<string, mixed>|null
	 */
	public function block_run_config( int $block_id ): ?array {
		$decoded = json_decode( (string) $this->recipe_meta( Execution_Log_Store::META_BLOCK_RUN_CONFIG ), true );
		if ( ! is_array( $decoded ) || ! is_array( $decoded[ $block_id ] ?? null ) ) {
			return null;
		}

		return $decoded[ $block_id ];
	}

	/**
	 * Why the walker refused a block on this run: `{ reason, violations: [ { code, message, target } ] }`, or null
	 * when the block was not refused (or the run predates the record).
	 *
	 * @since 8.0.0
	 *
	 * @param int $block_id Block post id.
	 * @return array<string, mixed>|null
	 */
	public function block_refusal( int $block_id ): ?array {
		$decoded = json_decode( (string) $this->recipe_meta( Execution_Log_Store::META_BLOCK_REFUSALS ), true );
		if ( ! is_array( $decoded ) || ! is_array( $decoded[ $block_id ] ?? null ) ) {
			return null;
		}

		return $decoded[ $block_id ];
	}

	/**
	 * What Pro's loop recorded for a loop filter that matched no items for a reason the owner should read (it threw,
	 * or the plugin it needs is not active): `{ met: false, message }`, or null when nothing was recorded.
	 *
	 * @since 8.0.0
	 *
	 * @param int $modifier_id The loop filter's (block modifier's) id.
	 * @return array{met: bool, message: string}|null
	 */
	public function loop_filter_outcome( int $modifier_id ): ?array {
		$decoded = json_decode( (string) $this->recipe_meta( Execution_Log_Store::META_LOOP_FILTER_OUTCOMES ), true );
		if ( ! is_array( $decoded ) || ! is_array( $decoded[ $modifier_id ] ?? null ) ) {
			return null;
		}

		return array(
			'met'     => ! empty( $decoded[ $modifier_id ]['met'] ),
			'message' => (string) ( $decoded[ $modifier_id ]['message'] ?? '' ),
		);
	}

	/**
	 * The nodes of this run waiting on a background app action: `node_id => { kind, waiting_on, integration_code, covers }`.
	 *
	 * @return array<string, array{kind: string, waiting_on: int[], integration_code: string, covers: int[]}>
	 */
	public function blocked_nodes(): array {
		$decoded = json_decode( (string) $this->recipe_meta( Execution_Log_Store::META_BLOCKED_NODES ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Whether a node is recorded as waiting on a background app action.
	 *
	 * @param int $node_id Action or block post id.
	 * @return bool
	 */
	public function is_node_blocked( int $node_id ): bool {
		return isset( $this->blocked_nodes()[ (string) $node_id ] );
	}

	/**
	 * Delay/schedule config for an action from the action_delays meta.
	 * The meta is double-encoded: the outer map's values are JSON strings.
	 *
	 * @param int $action_id Action post id.
	 * @return array<string,mixed>|null The decoded config, or null when absent.
	 */
	public function delay_config_for_action( int $action_id ): ?array {
		$decoded = json_decode( (string) $this->recipe_meta( Execution_Log_Store::META_ACTION_DELAYS ), true );
		if ( ! is_array( $decoded ) || ! isset( $decoded[ $action_id ] ) ) {
			return null;
		}
		$entry = $decoded[ $action_id ];
		if ( is_string( $entry ) ) {
			$entry = json_decode( $entry, true );
		}
		return is_array( $entry ) ? $entry : null;
	}

	/**
	 * Looks up the prefetched loop-run entry for a loop block.
	 *
	 * @param int $loop_id Loop block post id.
	 * @return array<string,mixed>|null The loop entry row, or null when absent.
	 */
	public function loop_entry( int $loop_id ): ?array {
		return $this->loop_entries[ $loop_id ] ?? null;
	}

	/**
	 * Looks up the prefetched per-status breakdown for a loop action.
	 *
	 * @param int $action_id Action post id.
	 * @return array<string,int> Status code => count, empty when none.
	 */
	public function loop_breakdown_for_action( int $action_id ): array {
		return $this->loop_breakdowns[ $action_id ] ?? array();
	}

	/**
	 * The run-level errors stored against a block on this run, in the order stored: `[ { code, message, context } ]`,
	 * empty when none. The message is as stored (not yet wire text).
	 *
	 * @since 8.0.0
	 *
	 * @param int $block_id Block post id.
	 * @return array<int,array{item_type: string, code: string, message: string, context: array<string,mixed>}>
	 */
	public function block_errors( int $block_id ): array {
		return $this->block_errors[ $block_id ] ?? array();
	}

	/**
	 * Looks up the recorded error message for an action-log row.
	 *
	 * @param int $action_log_id The action_log row ID.
	 * @return string|null The error message, or null when the action never errored.
	 */
	public function error_message_for( int $action_log_id ): ?string {
		return $this->error_by_action_log[ $action_log_id ] ?? null;
	}

	/**
	 * The errors recorded against the run itself rather than one of its steps (a trigger
	 * that failed after the run opened, a stage that threw, a run that stuck-run recovery
	 * closed), oldest first.
	 *
	 * @since 8.0.0
	 *
	 * @return array<int,array{code:string,message:string}> Each row's error code and stored message.
	 */
	public function run_errors(): array {
		return $this->run_errors;
	}

	/**
	 * The error recorded against a block: why it did not run when it did not, otherwise its first note.
	 *
	 * @since 8.0.0
	 *
	 * @param int $block_id Block post id.
	 * @return array{message:string, not_run:bool}|null The stored message and whether the block did not run, or null.
	 */
	public function block_error( int $block_id ): ?array {
		$rows = $this->errors_by_block[ $block_id ] ?? array();
		if ( empty( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( Error_Code::BLOCK_NOT_RUN === $row['code'] ) {
				return array(
					'message' => $row['message'],
					'not_run' => true,
				);
			}
		}

		return array(
			'message' => $rows[0]['message'],
			'not_run' => false,
		);
	}
}
