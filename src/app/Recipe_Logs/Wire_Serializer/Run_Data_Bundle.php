<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Filter_Block;
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
	 * How many levels of tokens inside token values are filled in, the limit the
	 * action token parser applies at run time.
	 *
	 * @var int
	 */
	private const MAX_NESTED_TOKEN_DEPTH = 5;

	/** How long after a step's time its token record can still be written. */
	private const TOKEN_RECORD_GRACE_SECONDS = 2;

	/**
	 * Run token map, keyed by braced token identifier.
	 *
	 * @var array<string,string>
	 */
	private $tokens_map;

	/**
	 * The run's token records, oldest first: array{added:int, tokens:array<string,string>}.
	 *
	 * @var array<int,array{added:int,tokens:array<string,string>}>
	 */
	private $tokens_records = array();

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
	 * Every stored `action_tokens` value per action log, oldest row first, indexed by action_log_id.
	 *
	 * @since 8.1.0
	 *
	 * @var array<int,array<int,string>>
	 */
	private $action_tokens;

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
	 * Error message indexed by action post id, for an action that failed before it had an
	 * action-log row of its own (action_log_id 0), such as an integration that is not connected.
	 *
	 * @var array<int,string>
	 */
	private $error_by_action = array();

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
	 * What each migrated condition of a filter answered on a run recorded under 7.x, indexed by filter block id then
	 * modifier id: `{ asked, met, message }`. Inside a Loop, the answers span the loop's items and carry no message.
	 * Empty for a run recorded under 8.x.
	 *
	 * @since 8.0.3
	 *
	 * @var array<int,array<int|string,array{asked:bool,met:bool,message:string}>>
	 */
	private $legacy_filter_conditions;

	/**
	 * Builds the bundle from prefetched raw store output, indexing rows by id.
	 *
	 * @param array<string,mixed> $parts Prefetched raw structures. Keys:
	 *   recipe_log_row, trigger_rows, action_rows, api_retries, api_logged, tokens_map,
	 *   action_meta_bag, action_tokens, trigger_meta_bag, trigger_properties, recipe_meta_bag, error_rows,
	 *   loop_entries, loop_breakdowns, node_created_map, legacy_filter_conditions.
	 */
	public function __construct( array $parts ) {
		$this->recipe_log_row = is_array( $parts['recipe_log_row'] ?? null ) ? $parts['recipe_log_row'] : array();
		$this->api_retries    = is_array( $parts['api_retries'] ?? null ) ? $parts['api_retries'] : array();
		$this->tokens_map     = $this->with_nested_tokens_filled_in( is_array( $parts['tokens_map'] ?? null ) ? $parts['tokens_map'] : array() );
		foreach ( (array) ( $parts['tokens_records'] ?? array() ) as $record ) {
			if ( is_array( $record ) && is_array( $record['tokens'] ?? null ) ) {
				$this->tokens_records[] = array(
					'id'     => (int) ( $record['id'] ?? 0 ),
					'added'  => (int) ( $record['added'] ?? 0 ),
					'tokens' => $record['tokens'],
				);
			}
		}
		$this->action_meta_bag    = is_array( $parts['action_meta_bag'] ?? null ) ? $parts['action_meta_bag'] : array();
		$this->trigger_meta_bag   = is_array( $parts['trigger_meta_bag'] ?? null ) ? $parts['trigger_meta_bag'] : array();
		$this->trigger_properties = is_array( $parts['trigger_properties'] ?? null ) ? $parts['trigger_properties'] : array();
		$this->action_tokens      = is_array( $parts['action_tokens'] ?? null ) ? $parts['action_tokens'] : array();
		$this->recipe_meta_bag    = is_array( $parts['recipe_meta_bag'] ?? null ) ? $parts['recipe_meta_bag'] : array();
		$this->loop_entries       = is_array( $parts['loop_entries'] ?? null ) ? $parts['loop_entries'] : array();
		$this->loop_breakdowns    = is_array( $parts['loop_breakdowns'] ?? null ) ? $parts['loop_breakdowns'] : array();
		$this->node_created_map   = is_array( $parts['node_created_map'] ?? null ) ? $parts['node_created_map'] : array();

		$this->legacy_filter_conditions = is_array( $parts['legacy_filter_conditions'] ?? null ) ? $parts['legacy_filter_conditions'] : array();

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

		if ( (int) ( $err['action_log_id'] ?? 0 ) > 0 ) {
			$this->error_by_action_log[ (int) $err['action_log_id'] ] = $entry['message'];
			return;
		}

		// No action-log row: show it on the action's step when that step reports a run,
		// and otherwise against the run, so a recorded error is never dropped.
		$context   = json_decode( (string) ( $err['error_context'] ?? '' ), true );
		$action_id = is_array( $context ) ? (int) ( $context['action_id'] ?? 0 ) : 0;
		$row       = $action_id > 0 ? ( $this->action_rows[ $action_id ] ?? null ) : null;
		if ( null !== $row && \Uncanny_Automator\Automator_Status::NOT_COMPLETED !== (int) ( $row['completed'] ?? \Uncanny_Automator\Automator_Status::NOT_COMPLETED ) ) {
			$this->error_by_action[ $action_id ] = $entry['message'];
			return;
		}
		// An informational note (is_actionable 0), such as "A user found matching", is not a run error.
		if ( isset( $err['is_actionable'] ) && 0 === (int) $err['is_actionable'] ) {
			return;
		}
		// The same failure recorded more than once reads once.
		if ( ! in_array( $entry, $this->run_errors, true ) ) {
			$this->run_errors[] = $entry;
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
	 * When the run started, as a raw MySQL datetime, or '' when unknown.
	 *
	 * The run row's date_time is not it once the run finishes: mark_recipe_complete()
	 * overwrites it with the completion time. A finished run's start is then its
	 * earliest trigger or action row, and never later than date_time: a step row can
	 * be rewritten after the run finished, by a resend for one. Read this, not
	 * date_time, wherever a step or block needs the run's start.
	 *
	 * @since 8.1.0
	 *
	 * @return string
	 */
	public function run_started_at(): string {
		$date_time = (string) ( $this->recipe_log_row['date_time'] ?? '' );
		if ( ! \Uncanny_Automator\Automator_Status::finished( (int) ( $this->recipe_log_row['completed'] ?? \Uncanny_Automator\Automator_Status::NOT_COMPLETED ) ) ) {
			return $date_time;
		}

		$dates = '' === $date_time ? array() : array( $date_time );
		foreach ( $this->action_rows as $row ) {
			if ( is_array( $row ) && '' !== (string) ( $row['date_time'] ?? '' ) ) {
				$dates[] = (string) $row['date_time'];
			}
		}
		foreach ( $this->trigger_rows as $rows ) {
			foreach ( (array) $rows as $row ) {
				if ( is_array( $row ) && '' !== (string) ( $row['date_time'] ?? '' ) ) {
					$dates[] = (string) $row['date_time'];
				}
			}
		}

		return empty( $dates ) ? '' : (string) min( $dates );
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
	 * Fills in the tokens held inside the recorded token values.
	 *
	 * An action field token is recorded with the field's saved text, before the
	 * run's parser resolves the tokens in that text. Those tokens are recorded as
	 * well, so the log can show what the run used.
	 *
	 * @since 8.0.2
	 *
	 * @param array<string,string> $tokens The token map as recorded.
	 * @return array<string,string> The token map with final values.
	 */
	private function with_nested_tokens_filled_in( array $tokens ): array {
		foreach ( $tokens as $key => $value ) {
			if ( false === strpos( (string) $value, '{{' ) ) {
				continue;
			}

			for ( $depth = 0; $depth < self::MAX_NESTED_TOKEN_DEPTH; ++$depth ) {
				$filled_in = strtr( (string) $value, $tokens );
				if ( $filled_in === $value ) {
					break;
				}
				$value = $filled_in;
			}

			$tokens[ $key ] = $value;
		}

		return $tokens;
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
		return $this->bare_token_keys( $this->tokens_map );
	}

	/**
	 * The token values a step that ran at this time used.
	 *
	 * tokens_map() merges every record of the run, the latest winning, which shows an early
	 * step a value only recorded days later: a user deleted between a welcome email and a
	 * delayed follow-up made the welcome email read as sent to nobody. This merges only the
	 * records written up to the step's time. Without records, or a readable time, it is
	 * tokens_map().
	 *
	 * @since 8.1.0
	 *
	 * An action that names where it stands among the run's records gets exactly the records up
	 * to there, which holds even when two actions finish in the same second (QA D103). At 0
	 * the run had no record yet, so the action shows none. Runs logged before actions named
	 * their place use the step's time.
	 *
	 * @param string   $date_time The step's site-local MySQL datetime.
	 * @param int|null $record_id The action's place among the run's token records, when it named one.
	 * @return array<string,string> Token map keyed by braced token identifier.
	 */
	public function tokens_map_at( string $date_time, ?int $record_id = null ): array {
		if ( 0 === $record_id ) {
			return array();
		}
		if ( null !== $record_id && $record_id > 0 && in_array( $record_id, array_column( $this->tokens_records, 'id' ), true ) ) {
			$merged = array();
			foreach ( $this->tokens_records as $record ) {
				if ( $record['id'] <= $record_id ) {
					$merged = array_merge( $merged, $record['tokens'] );
				}
			}
			return $this->with_nested_tokens_filled_in( $merged );
		}
		if ( empty( $this->tokens_records ) || '' === $date_time ) {
			return $this->tokens_map;
		}
		$moment = date_create_immutable( $date_time, wp_timezone() );
		if ( false === $moment ) {
			return $this->tokens_map;
		}
		// A step's row and its token record are written in the same second or close to it,
		// but never as late as the next step: that record holds the next step's values (QA D103).
		$until = $moment->getTimestamp() + self::TOKEN_RECORD_GRACE_SECONDS;
		$next  = $this->next_step_time( $moment->getTimestamp() );
		if ( null !== $next ) {
			$until = min( $until, $next - 1 );
		}
		$merged = array();
		foreach ( $this->tokens_records as $record ) {
			if ( $record['added'] <= $until ) {
				$merged = array_merge( $merged, $record['tokens'] );
			}
		}
		if ( empty( $merged ) ) {
			// A step dated before every record still ran with the run's first values.
			$merged = $this->tokens_records[0]['tokens'];
		}

		return $this->with_nested_tokens_filled_in( $merged );
	}

	/**
	 * Where an action stands among the run's token records, or null for a run logged before
	 * actions noted their place.
	 *
	 * An action notes its place when it runs or when the filter skips it. In a run with
	 * recorded places, an unmarked action has no known boundary. Row IDs reflect initial
	 * allocation order, so only recorded places can associate tokens with those actions.
	 *
	 * @since 8.1.0
	 *
	 * @param int $action_log_id The action's log row.
	 * @return int|null
	 */
	public function token_place_for( int $action_log_id ): ?int {
		$own = $this->action_meta( $action_log_id, \Uncanny_Automator\Logger\Tokens_Logger::RECORD_META_KEY );
		if ( null !== $own ) {
			return (int) $own;
		}
		foreach ( $this->action_rows as $row ) {
			if ( null !== $this->action_meta( (int) $row['ID'], \Uncanny_Automator\Logger\Tokens_Logger::RECORD_META_KEY ) ) {
				return 0;
			}
		}
		return null;
	}

	/**
	 * The time of the run's first trigger or action step after a moment, or null.
	 *
	 * @param int $after Unix time of the step being shown.
	 * @return int|null
	 */
	private function next_step_time( int $after ): ?int {
		$next = null;
		$rows = array_values( $this->action_rows );
		foreach ( $this->trigger_rows as $trigger_rows ) {
			$rows = array_merge( $rows, $trigger_rows );
		}
		foreach ( $rows as $row ) {
			$time = date_create_immutable( (string) ( $row['date_time'] ?? '' ), wp_timezone() );
			if ( false === $time || '' === (string) ( $row['date_time'] ?? '' ) ) {
				continue;
			}
			$at = $time->getTimestamp();
			if ( $at > $after && ( null === $next || $at < $next ) ) {
				$next = $at;
			}
		}
		return $next;
	}

	/**
	 * tokens_map_at(), keyed by bare token code as wire_tokens() is.
	 *
	 * @since 8.1.0
	 *
	 * @param string   $date_time The step's site-local MySQL datetime.
	 * @param int|null $record_id The token record the action wrote, when it named one.
	 * @return array<string,string>
	 */
	public function wire_tokens_at( string $date_time, ?int $record_id = null ): array {
		return $this->bare_token_keys( $this->tokens_map_at( $date_time, $record_id ) );
	}

	/**
	 * @param array<string,string> $tokens Token map keyed by braced identifier.
	 * @return array<string,string> The same map keyed by bare token code.
	 */
	private function bare_token_keys( array $tokens ): array {
		$out = array();
		foreach ( $tokens as $key => $value ) {
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
	 * Every `action_tokens` value an action log stored, oldest row first: the action's own (`hydrate_tokens()`), and
	 * the runner's completion status.
	 *
	 * @since 8.1.0
	 *
	 * @param int $action_log_id The action_log row ID.
	 * @return array<int,string> The stored JSON values; empty when the run stored none.
	 */
	public function action_tokens( int $action_log_id ): array {
		return $this->action_tokens[ $action_log_id ] ?? array();
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
	 * What each condition of a filter answered when the filter chose its path: modifier id => met. A condition the
	 * filter decided without is absent. A run recorded under 7.x answers from the 7.x results its migrated conditions
	 * map to.
	 *
	 * @since 8.0.3
	 *
	 * @param int $block_id The filter block's post ID.
	 * @return array<string, bool>|null Null when the run kept no answers for the block.
	 */
	public function filter_condition_results( int $block_id ): ?array {
		$decoded = json_decode( (string) $this->recipe_meta( Execution_Log_Store::META_BLOCK_CHOSEN_PATHS ), true );
		if ( is_array( $decoded ) && is_array( $decoded[ $block_id ]['conditions'] ?? null ) ) {
			$results = array();
			foreach ( $decoded[ $block_id ]['conditions'] as $modifier_id => $answer ) {
				$results[ (string) $modifier_id ] = is_array( $answer ) && ! empty( $answer['met'] );
			}

			return $results;
		}

		return $this->legacy_filter_condition_results( $block_id );
	}

	/**
	 * What each migrated condition of a filter answered on a run recorded under 7.x: modifier id => met. A condition
	 * 7.x never asked is absent. Inside a Loop, a condition was asked when any loop item asked it, and met when it
	 * passed for any item.
	 *
	 * @since 8.0.3
	 *
	 * @param int $block_id The filter block's post ID.
	 * @return array<string, bool>|null Null when the run kept no 7.x results for the block.
	 */
	public function legacy_filter_condition_results( int $block_id ): ?array {
		if ( ! isset( $this->legacy_filter_conditions[ $block_id ] ) ) {
			return null;
		}

		$results = array();
		foreach ( $this->legacy_filter_conditions[ $block_id ] as $modifier_id => $condition ) {
			if ( ! empty( $condition['asked'] ) ) {
				$results[ (string) $modifier_id ] = ! empty( $condition['met'] );
			}
		}

		return $results;
	}

	/**
	 * The message 7.x recorded for a migrated condition the filter asked and that did not pass.
	 *
	 * @since 8.0.3
	 *
	 * @param int        $block_id    The filter block's post ID.
	 * @param int|string $modifier_id The condition's modifier id.
	 * @return string|null Null for an 8.x run, a met condition, one never asked, or one without a message.
	 */
	public function legacy_condition_message( int $block_id, $modifier_id ): ?string {
		$condition = $this->legacy_filter_conditions[ $block_id ][ (string) $modifier_id ] ?? null;
		if ( ! is_array( $condition ) || empty( $condition['asked'] ) || ! empty( $condition['met'] ) || '' === (string) ( $condition['message'] ?? '' ) ) {
			return null;
		}

		return (string) $condition['message'];
	}

	/**
	 * Whether a migrated filter's conditions were met on a run recorded under 7.x, from the results 7.x kept, under the
	 * filter's match mode: any condition met for `any`, every condition met for `all`. A condition never asked counts as
	 * not met, as in 7.x.
	 *
	 * @since 8.0.3
	 *
	 * @param int    $block_id   The filter block's post ID.
	 * @param string $match_mode The filter's match mode (`any` or `all`).
	 * @return bool|null Null when the run kept no 7.x results for the filter.
	 */
	public function legacy_filter_met( int $block_id, string $match_mode ): ?bool {
		if ( ! isset( $this->legacy_filter_conditions[ $block_id ] ) ) {
			return null;
		}

		$met = array();
		foreach ( $this->legacy_filter_conditions[ $block_id ] as $condition ) {
			$met[] = ! empty( $condition['asked'] ) && ! empty( $condition['met'] );
		}

		return Filter_Block::MATCH_MODE_ALL === $match_mode ? ! in_array( false, $met, true ) : in_array( true, $met, true );
	}

	/**
	 * Whether a modifier is a migrated condition whose filter's 7.x result this run kept.
	 *
	 * @since 8.0.3
	 *
	 * @param int|string $modifier_id The modifier id.
	 * @return bool
	 */
	public function kept_legacy_condition( $modifier_id ): bool {
		foreach ( $this->legacy_filter_conditions as $conditions ) {
			if ( isset( $conditions[ (string) $modifier_id ] ) ) {
				return true;
			}
		}

		return false;
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
	 * What Pro's loop recorded for a loop filter that matched no items, with why (the reasons are listed on
	 * Execution_Log_Store::META_LOOP_FILTER_OUTCOMES): `{ met: false, message }`, or null when nothing was recorded.
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
		return self::decode_delay_config( (string) $this->recipe_meta( Execution_Log_Store::META_ACTION_DELAYS ), $action_id );
	}

	/**
	 * One action's record in a run's stored action_delays meta.
	 *
	 * @since 8.1.0
	 *
	 * @param string $stored    The run's action_delays meta value (JSON).
	 * @param int    $action_id Action post id.
	 * @return array|null The record, or null when the action has none.
	 */
	public static function decode_delay_config( string $stored, int $action_id ): ?array {
		$decoded = json_decode( $stored, true );
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
	 * Looks up an error recorded against an action before it had an action-log row.
	 *
	 * @since 8.0.5
	 *
	 * @param int $action_id The action post ID.
	 * @return string|null The error message, or null when none was recorded that way.
	 */
	public function error_message_for_action( int $action_id ): ?string {
		return $this->error_by_action[ $action_id ] ?? null;
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
