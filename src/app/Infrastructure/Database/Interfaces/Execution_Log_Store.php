<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/**
 * Contract for the execution log store.
 *
 * Owns CRUD across the execution log tables: uap_trigger_log,
 * uap_trigger_log_meta, uap_action_log, uap_action_log_meta,
 * uap_recipe_log, uap_recipe_count. Legacy DB handlers (Automator_DB_Handler_*)
 * delegate to the implementation. The recipe runner is the source of
 * truth for execution log writes.
 *
 * Defined as an open contract — the implementation is intentionally wide
 * because the legacy execution log surface is wide. Phase 5 of the
 * api-layer refactor moved it from `application/recipe_runner/services/`
 * to its correct home in `database/stores/` without altering the surface.
 *
 * Its Error_Message_Rewrite_Store methods rewrite uap_action_log.error_message.
 *
 * @since 8.0.0
 */
interface Execution_Log_Store extends Error_Message_Rewrite_Store {

	/**
	 * Recipe-log meta: `{ "<block_id>": { "chosen_path": "<path_code>", "conditions": { "<modifier_id>": { "met": bool } } } }`, written by
	 * the children runner for every multi-path block outside loop scope. `conditions` holds what each condition of a filter answered; a
	 * condition the filter decided without is absent, and an entry written before the record (or for a block without conditions) has none.
	 *
	 * @var string
	 */
	public const META_BLOCK_CHOSEN_PATHS = '_automator_block_chosen_paths';

	/**
	 * Recipe-log meta: `{ "<block_id>": { "block_type", "fields", "modifiers" } }`, the configuration each dispatched block executed with.
	 *
	 * @var string
	 */
	public const META_BLOCK_RUN_CONFIG = '_automator_block_run_config';

	/**
	 * Recipe-log meta: `{ "<node_id>": { "kind": "action"|"block", "waiting_on": [<action ids>], "integration_code": "<code, empty for a block>", "covers": [<action ids under a blocked block>] } }`,
	 * the nodes of this run waiting on an unfinished background app action. Written by the walker one entry at a time
	 * through merge_recipe_log_meta_entry(), cleared node by node as Recipe_Runner::complete_background_action() releases or skips them.
	 *
	 * @var string
	 */
	public const META_BLOCKED_NODES = '_automator_blocked_nodes';

	/**
	 * Recipe-log meta: `{ "<block_id>": { "reason": "<plain text>", "violations": [ { "code", "message", "target" } ] } }`,
	 * the blocks the walker refused because their configuration was incomplete. Written once per block per run through
	 * merge_recipe_log_meta_entry(); the run log reads it to show which modifier caused the refusal and why.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	public const META_BLOCK_REFUSALS = '_automator_block_refusals';

	/**
	 * Recipe-log meta key prefix for a run claim. `<prefix><claim>` holds the one holder of that claim for the run,
	 * written once through claim_recipe_log_meta(), so a node can act once for the whole run across the requests
	 * the run spans.
	 *
	 * @since 8.0.3
	 *
	 * @var string
	 */
	public const META_RUN_CLAIM_PREFIX = '_automator_run_claim_';

	/**
	 * Recipe-log meta: `{ "<loop_filter_id>": { "met": false, "message": "<plain text>" } }`, the loop filters of this
	 * run that matched no items for a reason the owner should read: the filter threw (the loop could not start), or
	 * the plugin it needs is not active. Written by Pro's loop, one entry per filter, through
	 * merge_recipe_log_meta_entry(); the run log shows such a filter as not met, with the message.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	public const META_LOOP_FILTER_OUTCOMES = '_automator_loop_filter_outcomes';

	/**
	 * Recipe-log meta: `{ "<action_id>": "{\"type\":\"delay|schedule|token\",\"time\":<utc epoch>}" }` for actions waiting under a delay.
	 *
	 * @var string
	 */
	public const META_ACTION_DELAYS = 'action_delays';

	/**
	 * Action-log meta: the Action Scheduler job id that will resume the row's delay block.
	 *
	 * @var string
	 */
	public const ACTION_META_ASYNC_JOB_ID = 'async_job_id';

	/**
	 * Action-log meta: the row went to the background, where its worker is a request nothing tracks. Stuck recovery finds it by this.
	 *
	 * @var string
	 */
	public const ACTION_META_BACKGROUND_DISPATCH = 'background_dispatch';

	// Trigger logs.
	public function add_trigger( int $user_id, int $trigger_id, int $recipe_id, bool $is_completed, int $recipe_log_id ): int;

	/**
	 * Insert a trigger meta row.
	 *
	 * @return int|null Insert ID, or null when the meta payload fails validation
	 *                  (missing meta_key, non-numeric trigger_log_id, dedup hit on
	 *                  sentence_human_readable, etc.).
	 */
	public function add_trigger_meta( int $trigger_id, int $trigger_log_id, int $run_number, array $meta ): ?int;

	public function mark_trigger_complete( int $trigger_id, int $user_id, int $recipe_id, int $recipe_log_id, int $trigger_log_id ): void;
	public function is_trigger_completed( int $user_id, int $trigger_id, int $recipe_id, int $recipe_log_id, bool $process_recipe, array $args ): bool;
	public function get_triggers_by_recipe_log_id( int $user_id, int $recipe_id, int $recipe_log_id, $run_number = null ): array;
	public function get_trigger_meta_rows( int $trigger_log_id ): array;

	// Action logs.
	public function add_action( array $data ): int;
	public function add_action_meta( int $user_id, int $action_log_id, int $action_id, string $meta_key, string $meta_value ): void;

	/**
	 * Read a single meta value for an (action_log_id, meta_key) pair.
	 *
	 * Returns the most-recent row's `meta_value` when multiple rows
	 * share the key (e.g. duplicate writes), `null` when no row exists.
	 *
	 * Phase 2 upload-action body persistence (R6 idempotency replay)
	 * needs this to read back the JCS-canonical request bytes on retry —
	 * see {@see \Uncanny_Automator\Recipe\App_Action::read_persisted_upload_request()}.
	 *
	 * @param int    $action_log_id uap_action_log primary key.
	 * @param string $meta_key      Meta key to look up.
	 *
	 * @return string|null Stored meta value, or null when missing.
	 */
	public function get_action_meta_value( int $action_log_id, string $meta_key ): ?string;
	public function mark_action_complete( int $action_id, int $recipe_log_id, int $completed, string $error_message = '' ): void;

	/**
	 * Mark a single action_log row complete by primary key.
	 *
	 * Required for callers that need to target ONE iteration of a loop —
	 * `mark_action_complete()` updates every `uap_action_log` row matching
	 * `(action_id, recipe_log_id)`, which stomps sibling loop iterations.
	 * Webhook and async-completion paths that already hold the row's `ID`
	 * must use this instead.
	 *
	 * @param int    $action_log_id The uap_action_log primary key.
	 * @param int    $completed     Completion status constant.
	 * @param string $error_message Optional error message, routed to uap_error_log.
	 *
	 * @return void
	 */
	public function mark_action_complete_by_id( int $action_log_id, int $completed, string $error_message = '' ): void;

	/**
	 * Mark a single uap_action_log row complete only while it is still IN_PROGRESS.
	 *
	 * For a caller deciding from a row it read earlier: a worker that finished since keeps its result.
	 *
	 * @param int $action_log_id The uap_action_log primary key.
	 * @param int $completed     Completion status constant.
	 *
	 * @return bool True when this call changed the row.
	 */
	public function mark_action_complete_if_in_progress( int $action_log_id, int $completed ): bool;

	/**
	 * Mark a deferred (background/async-dispatched) action's row IN_PROGRESS and
	 * stamp its scheduled run time — but only while the row is non-terminal. A
	 * pending (NOT_COMPLETED) row transitions to IN_PROGRESS and dispatches the
	 * status-change events; a row already IN_PROGRESS (Pro's async postpone got
	 * there first) has only its date corrected, with no status events; a terminal
	 * row is left untouched.
	 *
	 * The background/async worker request fires inside the before-action
	 * filter (non-blocking POST); on fast servers the worker completes the
	 * action — terminal status, recipe finalized — BEFORE the dispatching
	 * request finalizes the deferral. An unconditional IN_PROGRESS write
	 * would downgrade that finished row and strand the run "In progress"
	 * forever. The WHERE guard makes the transition atomic.
	 *
	 * @param int         $action_id     The action post ID.
	 * @param int         $recipe_log_id The recipe log ID.
	 * @param string|null $date_time     Scheduled run time to record (Y-m-d H:i:s);
	 *                                   falls back to the current time when null.
	 *
	 * @return void
	 */
	public function mark_action_scheduled( int $action_id, int $recipe_log_id, ?string $date_time = null ): void;
	public function get_action_error_messages( int $recipe_log_id ): array;
	public function get_action_completion_status( int $action_log_id ): ?int;

	/**
	 * Look up the action log ID for a (action, recipe_log) pair.
	 *
	 * Returns `0` (not `null`) when no row exists. The 0 sentinel is preserved
	 * for backward compatibility with the legacy `Automator_DB_Handler_Action::get_id()`
	 * surface — many callers compare to `0` directly.
	 */
	public function get_action_log_id_by_action_and_recipe_log( int $action_id, int $recipe_log_id ): int;

	/**
	 * Fetch the raw uap_action_log row for a given primary key.
	 *
	 * Returns the row's `user_id`, `automator_action_id`, `automator_recipe_id`,
	 * and `automator_recipe_log_id` — enough for callers that need to rebuild
	 * an action_data shape (resume / replay / dispatch paths).
	 *
	 * @return object|null wpdb row object, or null when no row matches.
	 */
	public function get_action_log_row( int $action_log_id ): ?object;

	public function get_all_action_statuses( int $recipe_log_id ): array;

	// Recipe logs.
	public function add_recipe_log( int $user_id, int $recipe_id, int $completed, int $run_number ): ?int;
	public function get_recipe_run_number( int $recipe_log_id ): ?int;
	public function mark_recipe_complete( int $recipe_log_id, int $completed ): void;
	public function mark_recipe_complete_with_error( int $recipe_id, int $recipe_log_id, int $error_status ): void;
	public function get_scheduled_actions_count( int $recipe_log_id, array $args ): int;
	public function recipe_log_pre_exists( int $recipe_id, int $user_id ): ?int;
	public function mark_recipe_incomplete( int $recipe_id, int $recipe_log_id ): void;
	public function update_recipe_count( int $recipe_id ): void;

	/**
	 * Fetch the raw recipe-log row for a given recipe_log_id.
	 *
	 * @return object|null wpdb row object, or null when no row matches.
	 */
	public function get_recipe_log_row( int $recipe_log_id ): ?object;

	public function get_recipe_status( int $recipe_log_id ): ?int;

	/**
	 * The newest recipe-log row for a recipe, or null when it has never run.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return object|null Row with { ID, run_number, completed }, or null when none.
	 */
	public function get_latest_recipe_log( int $recipe_id ): ?object;

	// Trigger log lookups for run-number sync.

	/**
	 * Get all trigger log IDs for a recipe log.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return int[] Array of trigger log IDs.
	 */
	public function get_trigger_log_ids_by_recipe_log( int $recipe_log_id ): array;

	/**
	 * Correct run_number on trigger log meta rows — replaces stale values
	 * written during Stage 1 with the authoritative value from the recipe log.
	 *
	 * @param int[] $trigger_log_ids Trigger log IDs to update.
	 * @param int   $stale_run_number  The run number to replace.
	 * @param int   $correct_run_number The authoritative run number.
	 *
	 * @return void
	 */
	public function sync_trigger_meta_run_numbers( array $trigger_log_ids, int $stale_run_number, int $correct_run_number ): void;

	// Recipe count tracking.

	/**
	 * Insert a recipe count row (atomic, idempotent via INSERT IGNORE).
	 *
	 * Seeds the initial count from completed recipe log entries.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return void
	 */
	public function insert_recipe_count( int $recipe_id ): void;

	// Recipe log lookups.

	/**
	 * @param int   $recipe_id        The recipe ID.
	 * @param int   $user_id          The user ID.
	 * @param int[] $terminal_statuses Statuses that count as terminal.
	 *
	 * @return int|null
	 */
	public function find_pending_recipe_log( int $recipe_id, int $user_id, array $terminal_statuses ): ?int;

	/**
	 * Runs whose actions stopped progressing: candidates for stuck recovery.
	 *
	 * @param int $threshold_seconds Idle time, measured from the run's newest action row.
	 * @param int $batch_size        Maximum rows.
	 *
	 * @return object[] Rows with ID, automator_recipe_id, user_id, date_time, last_action_activity.
	 */
	public function find_stuck_recipe_logs( int $threshold_seconds, int $batch_size ): array;

	/**
	 * Action rows still waiting on a background worker past the threshold: candidates for failing as lost.
	 *
	 * @param int $threshold_seconds Time since the dispatch.
	 * @param int $batch_size        Maximum rows.
	 *
	 * @return object[] Rows with ID, automator_recipe_log_id, automator_recipe_id, user_id, date_time.
	 */
	public function find_lost_background_dispatches( int $threshold_seconds, int $batch_size ): array;

	/**
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int
	 */
	public function get_user_completed_recipe_count( int $recipe_id, int $user_id ): int;

	/**
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return int
	 */
	public function get_max_recipe_log_number( int $recipe_id ): int;

	/**
	 * The published or draft recipes that ran the most, most runs first and
	 * the newer recipe first on a tie. Recipes that never ran are left out.
	 *
	 * @param int $limit The most recipes to return.
	 *
	 * @return array<int,array{id:int,title:string,runs:int}> Raw post titles.
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the query fails.
	 */
	public function get_most_active_recipes( int $limit ): array;

	/**
	 * @param int      $recipe_id  The recipe ID.
	 * @param int      $user_id    The user ID.
	 * @param int      $run_number The run number.
	 * @param int|null $log_number The sequential log number.
	 *
	 * @return int
	 */
	public function insert_recipe_log_row( int $recipe_id, int $user_id, int $run_number, ?int $log_number ): int;

	/** @return bool */
	public function acquire_lock( string $lock_name, int $timeout = 10 ): bool;

	/** @return void */
	public function release_lock( string $lock_name ): void;

	/**
	 * @param string $table_name Unprefixed table name.
	 *
	 * @return int|null
	 */
	public function get_next_auto_increment( string $table_name ): ?int;

	/**
	 * @param int $user_id        The resolved user ID.
	 * @param int $recipe_log_id  The recipe log ID.
	 * @param int $trigger_log_id The trigger log ID.
	 *
	 * @return void
	 */
	public function update_logs_user_id( int $user_id, int $recipe_log_id, int $trigger_log_id ): void;

	/**
	 * @param int $trigger_log_id The trigger log ID.
	 * @param int $user_id        The user ID.
	 *
	 * @return int|null
	 */
	public function get_trigger_meta_run_number( int $trigger_log_id, int $user_id ): ?int;

	// Completion counts.

	/**
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return int
	 */
	public function get_global_completion_count( int $recipe_id ): int;

	/**
	 * @param int[] $recipe_ids Recipe IDs.
	 *
	 * @return array
	 */
	public function batch_get_global_completion_counts( array $recipe_ids ): array;

	/**
	 * @param int   $user_id    The user ID.
	 * @param int[] $recipe_ids Recipe IDs.
	 *
	 * @return array
	 */
	public function batch_get_user_completion_counts( int $user_id, array $recipe_ids ): array;

	// Run number lookups.

	/**
	 * Get the max run_number for a recipe+user, excluding certain statuses.
	 *
	 * @param int   $recipe_id         The recipe ID.
	 * @param int   $user_id           The user ID.
	 * @param int[] $excluded_statuses Statuses to exclude.
	 *
	 * @return int|null Null when no matching rows.
	 */
	public function get_max_run_number( int $recipe_id, int $user_id, array $excluded_statuses ): ?int;

	// Recipe log meta.

	/**
	 * Insert a single recipe log meta row.
	 *
	 * @param int    $recipe_id     The recipe ID.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 * @param string $meta_value    The meta value.
	 *
	 * @return void
	 */
	public function add_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key, string $meta_value ): void;

	/**
	 * Copy specific recipe log meta rows from one log to another.
	 *
	 * Used by replay to duplicate structure metadata (actions_flow, triggers_logic)
	 * from the original run to the replayed run.
	 *
	 * @param int      $source_log_id The source recipe log ID.
	 * @param int      $target_log_id The target recipe log ID.
	 * @param int      $recipe_id     The recipe ID.
	 * @param string[] $keys          Meta keys to copy.
	 *
	 * @return void
	 */
	public function copy_recipe_log_meta( int $source_log_id, int $target_log_id, int $recipe_id, array $keys ): void;

	/**
	 * Run-identity row for the (recipe, run, log) triple.
	 *
	 * @param int $recipe_id  Recipe post id.
	 * @param int $run_number 1-indexed run number.
	 * @param int $log_id     Recipe-log row id.
	 * @return array<string,mixed>|null
	 */
	public function get_recipe_log_row_by_ids( int $recipe_id, int $run_number, int $log_id ): ?array;

	/**
	 * All trigger-log rows for a recipe-log, ordered by date_time ASC.
	 *
	 * @param int $recipe_log_id Recipe-log row id.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_trigger_log_rows( int $recipe_log_id ): array;

	/**
	 * All action-log rows for a recipe-log, ordered by date_time ASC.
	 *
	 * @param int $recipe_log_id Recipe-log row id.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_action_log_rows( int $recipe_log_id ): array;

	/**
	 * Flattened TOKEN-code => resolved-value map for a run.
	 *
	 * @param int $recipe_id     Recipe post id.
	 * @param int $recipe_log_id Recipe-log row id.
	 * @param int $run_number    1-indexed run number.
	 * @return array<string,string>
	 */
	public function get_tokens_record_map( int $recipe_id, int $recipe_log_id, int $run_number ): array;

	/**
	 * Action-log meta indexed [action_log_id][meta_key] => value.
	 *
	 * @param array<int,int> $action_log_ids Action-log row ids.
	 * @return array<int,array<string,string>>
	 */
	public function get_action_log_meta_bag( array $action_log_ids ): array;

	/**
	 * Every value one meta key holds per trigger log, oldest row first, indexed [trigger_log_id] => values. A trigger
	 * log can hold several rows under one key: each `set_trigger_log_properties()` call stores its own `properties`
	 * row, which get_trigger_log_meta_bag() would collapse into one.
	 *
	 * @since 8.0.0
	 *
	 * @param array<int,int> $trigger_log_ids Trigger-log row ids.
	 * @param string         $meta_key        Meta key.
	 * @return array<int,array<int,string>>
	 */
	public function get_trigger_log_meta_values( array $trigger_log_ids, string $meta_key ): array;

	/**
	 * Trigger-log meta indexed [trigger_log_id][meta_key] => value.
	 *
	 * @param array<int,int> $trigger_log_ids Trigger-log row ids.
	 * @return array<int,array<string,string>>
	 */
	public function get_trigger_log_meta_bag( array $trigger_log_ids ): array;

	/**
	 * Recipe-log meta for the fixed log keys (`triggers_logic`, `META_ACTION_DELAYS`, `META_BLOCK_CHOSEN_PATHS`, `META_BLOCK_RUN_CONFIG`, `META_BLOCKED_NODES`, `META_BLOCK_REFUSALS`, `META_LOOP_FILTER_OUTCOMES`) plus any extra keys, indexed [meta_key] => value.
	 *
	 * @param int            $recipe_log_id Recipe-log row id.
	 * @param array<int,string> $extra_keys Additional meta keys to fetch (e.g. condition group hashes).
	 * @return array<string,string>
	 */
	public function get_recipe_log_meta_bag( int $recipe_log_id, array $extra_keys = array() ): array;

	/**
	 * Every value each of the given recipe-log meta keys holds for a run, oldest row first. A run can hold several rows
	 * under one key, which get_recipe_log_meta_bag() would collapse into one.
	 *
	 * @since 8.0.3
	 *
	 * @param int           $recipe_log_id Recipe-log row id.
	 * @param array<string> $meta_keys     Meta keys.
	 * @return array<string,array<int,string>> Meta key => its values; a key with no rows is absent.
	 */
	public function get_recipe_log_meta_values( int $recipe_log_id, array $meta_keys ): array;

	/**
	 * Loop-run rows for a recipe-log/run, indexed by loop_id. Empty when the Pro loop table is absent.
	 *
	 * @param int $recipe_log_id Recipe-log row id.
	 * @param int $run_number    1-indexed run number.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_loop_entries( int $recipe_log_id, int $run_number ): array;

	/**
	 * Whether a loop of the run ended Completed with errors: a uap_loop_entries row of the run whose status is
	 * `completed-with-errors`, which Pro writes when a loop could not start (its filter or token failed, the
	 * walker refused it). The loops in `$except_loop_ids` are left out. False when the Pro loop table is absent.
	 *
	 * @since 8.0.0
	 *
	 * @param int   $recipe_log_id   Recipe-log row id.
	 * @param int[] $except_loop_ids Loop block ids whose entries do not count.
	 *
	 * @return bool
	 */
	public function has_failed_loop_entries( int $recipe_log_id, array $except_loop_ids = array() ): bool;

	/**
	 * Per-action per-status loop-item counts, indexed [action_id][status] => count. Empty when the Pro table is absent.
	 *
	 * @param int $recipe_log_id Recipe-log row id.
	 * @param int $run_number    1-indexed run number.
	 * @return array<int,array<string,int>>
	 */
	public function get_loop_item_status_breakdown( int $recipe_log_id, int $run_number ): array;

	/**
	 * Paged per-iteration rows for one loop-body action in one run (ITEM arm
	 * of the iterations sub-resource). Kind-aware search joins users/posts by
	 * entity_id when $loop_type warrants. A token loop's entity_id is a position
	 * and its labels are not joinable, so it matches $matched_entity_ids instead
	 * of the entity_id text. All kinds match error_message. Empty array when the
	 * Pro loop tables are absent.
	 *
	 * @param int    $recipe_id  Recipe post id.
	 * @param int    $recipe_log_id Recipe-log row id.
	 * @param int    $run_number Run number.
	 * @param int    $action_id  Loop-body action post id.
	 * @param int    $per_page   Page size.
	 * @param int    $offset     Row offset.
	 * @param string $search     Raw search term; '' disables the search filter.
	 * @param string $loop_type  Loop kind: users|posts|token|'' (drives the search JOIN).
	 * @param array<int,int> $matched_entity_ids Token loops: entity ids whose label contains $search.
	 * @return array<int,array<string,mixed>> Iteration rows ordered by ID ASC.
	 */
	public function get_action_iteration_rows( int $recipe_id, int $recipe_log_id, int $run_number, int $action_id, int $per_page, int $offset, string $search, string $loop_type, array $matched_entity_ids = array() ): array;

	/**
	 * Total per-iteration row count for one loop-body action in one run,
	 * honouring the same kind-aware search as get_action_iteration_rows().
	 * Zero when the Pro loop tables are absent.
	 *
	 * @param int    $recipe_id  Recipe post id.
	 * @param int    $recipe_log_id Recipe-log row id.
	 * @param int    $run_number Run number.
	 * @param int    $action_id  Loop-body action post id.
	 * @param string $search     Raw search term; '' disables the search filter.
	 * @param string $loop_type  Loop kind: users|posts|token|''.
	 * @param array<int,int> $matched_entity_ids Token loops: entity ids whose label contains $search.
	 * @return int The matching row count.
	 */
	public function count_action_iteration_rows( int $recipe_id, int $recipe_log_id, int $run_number, int $action_id, string $search, string $loop_type, array $matched_entity_ids = array() ): int;

	/**
	 * Read a single recipe-log-meta value, or null when missing.
	 *
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 *
	 * @return string|null
	 */
	public function get_recipe_log_meta( int $recipe_log_id, string $meta_key ): ?string;

	/**
	 * Whether a run has a recipe-log meta row for this key, whoever it was written for.
	 *
	 * The run owns its recipe-log meta: the user_id column is not consulted.
	 *
	 * @since 8.0.1
	 *
	 * @param int    $recipe_id     The recipe ID.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 *
	 * @return bool
	 */
	public function has_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key ): bool;

	/**
	 * Upsert: insert if no row, otherwise update meta_value in place.
	 *
	 * @param int    $recipe_id     The recipe ID.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 * @param string $meta_value    The meta value.
	 *
	 * @return void
	 */
	public function set_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key, string $meta_value ): void;

	/**
	 * Delete the row for this (recipe_log_id, meta_key) pair. No-op if missing.
	 *
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 *
	 * @return void
	 */
	public function delete_recipe_log_meta( int $recipe_log_id, string $meta_key ): void;

	/**
	 * Set or remove one entry of a JSON-object recipe-log meta under the run-meta advisory lock, so two walks
	 * of the same run never lose each other's entries. Removing an entry that is already gone writes nothing and
	 * returns false.
	 *
	 * @param int        $recipe_id     The recipe.
	 * @param int        $recipe_log_id The run.
	 * @param string     $meta_key      The meta key holding a JSON object.
	 * @param string     $entry_key     The entry to set or remove.
	 * @param array|null $entry         The entry, or null to remove it.
	 *
	 * @return bool Whether the record changed.
	 */
	public function merge_recipe_log_meta_entry( int $recipe_id, int $recipe_log_id, string $meta_key, string $entry_key, ?array $entry ): bool;

	/**
	 * Stamp one entry of a JSON-object recipe-log meta with its holder, under the run-meta advisory lock, only when
	 * its current holder is the one expected (null for an unclaimed entry). Of two walks claiming the same entry
	 * with the same expectation, exactly one gets true.
	 *
	 * @param int         $recipe_id       The recipe.
	 * @param int         $recipe_log_id   The run.
	 * @param string      $meta_key        The meta key holding a JSON object.
	 * @param string      $entry_key       The entry to claim.
	 * @param string      $holder          Who claims it; written to the entry's `claimed_by`.
	 * @param string|null $previous_holder The holder the entry must currently name, or null for none.
	 *
	 * @return bool Whether the entry was claimed.
	 */
	public function claim_recipe_log_meta_entry( int $recipe_id, int $recipe_log_id, string $meta_key, string $entry_key, string $holder, ?string $previous_holder ): bool;

	/**
	 * Read a run's recipe-log meta value through the table's recipe_id index (there is none on recipe_log_id).
	 *
	 * @since 8.0.3
	 *
	 * @param int    $recipe_id     The recipe the run belongs to.
	 * @param int    $recipe_log_id The run.
	 * @param string $meta_key      The meta key.
	 *
	 * @return string|null Null when the run has no row for the key.
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the read fails.
	 */
	public function find_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key ): ?string;

	/**
	 * Write $holder under a run's recipe-log meta key unless the run already holds a value there. The read and the
	 * insert happen under the run-meta advisory lock; the table has no unique key, so without the lock the claim is
	 * refused rather than risk a second winner.
	 *
	 * @since 8.0.3
	 *
	 * @param int    $recipe_id     The recipe the run belongs to.
	 * @param int    $recipe_log_id The run.
	 * @param string $meta_key      The meta key.
	 * @param string $holder        Who claims it.
	 *
	 * @return string The holder the run keeps: $holder when it was first, the earlier holder otherwise.
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the lock is not acquired, or the read or the insert fails; nothing is written.
	 */
	public function claim_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key, string $holder ): string;
}
