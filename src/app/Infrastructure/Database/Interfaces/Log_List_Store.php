<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Recipe_Logs\Lists\Value_Objects\Log_List_Query;

/**
 * Reads for the Logs page lists: one filtered, sorted page of recipe runs,
 * trigger logs, action logs or failed actions, its total, the batch reads
 * that complete a page, and the options its filters offer.
 *
 * Read-only. Every list leaves out runs stored as -1 (the legacy Logs
 * tables never showed them) and rows whose run or recipe no longer exists,
 * because an entry always opens its run.
 *
 * @since 8.1.0
 */
interface Log_List_Store {

	/**
	 * One page of a list's rows, in the query's order.
	 *
	 * Every row carries: entry_id, recipe_log_id, recipe_id, run_number,
	 * recipe_title, recipe_completed, completed, date_time (site time),
	 * user_id, user_found (1 when the user still exists), display_name,
	 * user_email. Trigger and action rows add item_id, item_title (the item
	 * post's title). Action rows add error_message (the frozen legacy
	 * column). Failed rows add api_log_id, endpoint, api_status, response,
	 * params and resent_count.
	 *
	 * @param Log_List_Query $query The read.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_rows( Log_List_Query $query ): array;

	/**
	 * How many rows match the query across all pages.
	 *
	 * @param Log_List_Query $query The read.
	 *
	 * @return int
	 */
	public function count_rows( Log_List_Query $query ): int;

	/**
	 * What ran in each run, in the order it ran: its triggers, then its
	 * actions, loops and the actions inside them by the time each first ran.
	 *
	 * @param int[] $recipe_log_ids Run ids.
	 *
	 * @return array<int, array<int, array{kind: string, id: int}>> Per run id: kind `item` (a trigger or action post id) or `loop` (a loop block id).
	 */
	public function get_run_steps( array $recipe_log_ids ): array;

	/**
	 * One post meta value for each of a set of posts.
	 *
	 * @param int[]  $post_ids Post ids.
	 * @param string $meta_key The meta key.
	 *
	 * @return array<int, string> Per post id that has the meta.
	 */
	public function get_post_meta_values( array $post_ids, string $meta_key ): array;

	/**
	 * The trigger sentence each trigger log row stored when it ran.
	 *
	 * @param int[] $trigger_log_ids Trigger log ids.
	 *
	 * @return array<int, string> Per trigger log id.
	 */
	public function get_trigger_sentences( array $trigger_log_ids ): array;

	/**
	 * The action sentence each action log row stored when it ran: its HTML
	 * form when it stored one, else its bracket form.
	 *
	 * @param int[] $action_log_ids Action log ids.
	 *
	 * @return array<int, array{text: string, html: bool}> Per action log id.
	 */
	public function get_action_sentences( array $action_log_ids ): array;

	/**
	 * One recipe-log meta value for each of a set of runs.
	 *
	 * @param int[]  $recipe_log_ids Run ids.
	 * @param string $meta_key       The meta key.
	 *
	 * @return array<int, string> Per run id that has the meta.
	 */
	public function get_recipe_log_meta_values( array $recipe_log_ids, string $meta_key ): array;

	/**
	 * The runs among a set that still exist, with what deleting one needs.
	 *
	 * @param int[] $recipe_log_ids Run ids.
	 *
	 * @return array<int, array{recipe_id: int, run_number: int}> Per run id.
	 */
	public function get_runs( array $recipe_log_ids ): array;

	/**
	 * The recipes that have runs, by title.
	 *
	 * @return array<int, array{id: int, title: string}>
	 */
	public function get_logged_recipes(): array;

	/**
	 * The users who have runs, by name. User 0 stands for guests.
	 *
	 * @return array<int, array{id: int, found: bool, display_name: string, user_email: string}>
	 */
	public function get_logged_users(): array;

	/**
	 * The stored status integers runs, triggers and actions carry.
	 *
	 * @return int[]
	 */
	public function get_logged_statuses(): array;

	/**
	 * The integration codes of the triggers and actions that have run, and
	 * whether any run holds a loop.
	 *
	 * @return array{codes: string[], has_loops: bool}
	 */
	public function get_logged_integrations(): array;

	/**
	 * The codes of the triggers or actions that have run.
	 *
	 * @param string $item_type `trigger` or `action`.
	 *
	 * @return string[]
	 */
	public function get_logged_item_codes( string $item_type ): array;

	/**
	 * The API endpoints the failed actions called.
	 *
	 * @return string[]
	 */
	public function get_failed_endpoints(): array;
}
