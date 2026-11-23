<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Recipe_Logs\App_Events\Value_Objects\App_Event_Query;

/**
 * Durable ingress inbox for platform callback batches.
 *
 * Every event the platform delivers (async completions + vendor events)
 * is INSERTed here before any processing runs, then processed by the
 * drain — a mid-request fatal is retried by the recovery sweep instead
 * of lost. Plugin-side mirror of the platform's `callback_outbox`.
 *
 * Rows are NOT consumed: the drain moves each to a terminal status
 * (`completed` or `failed`) and leaves it in place, so the table doubles
 * as an audit trail of what arrived and whether it ran. Retention is
 * owned solely by {@see self::cleanup_expired()}.
 *
 * Status lifecycle:
 *
 *   pending ──claim_pending()──> processing ──┬──> completed  (terminal)
 *      ^                                      └──> failed
 *      └────────── requeue_stale() ───────────────────┘
 *                  (processing stale, or failed under max attempts)
 *
 * Row shape (see `uap_platform_inbox` in class-automator-db.php):
 * `job_id` holds the RESOLVED dedup key — the platform job id for
 * async completions, the event-id cascade result (event_id → job_id →
 * body hash) for vendor events. `recipe_log_id` is 0 for vendor events
 * and unresolvable jobs; those drain under the shared 0-bucket.
 *
 * @since 8.0.0
 */
interface Platform_Inbox_Store {

	/**
	 * Land one event. INSERT IGNORE against the UNIQUE
	 * (job_id, payload_type) key — a redelivered event while the row
	 * still exists is a silent no-op.
	 *
	 * @param string               $payload_type         'async_completion' or 'vendor_event'.
	 * @param int                  $recipe_log_id        Owning run, 0 when not resolvable.
	 * @param string               $event_key            Resolved dedup key (stored as job_id).
	 * @param array<string, mixed> $payload              Everything the drain needs to process the row.
	 * @param string|null          $platform_received_at When the PLATFORM received the vendor event
	 *                                                    (Contract J, UTC 'Y-m-d H:i:s') — distinct from
	 *                                                    the local `received_at` (when THIS site did).
	 *                                                    Drives causal drain order across retried
	 *                                                    batches; null resolves to insert-time UTC.
	 *
	 * @return bool|null True inserted, false on redelivery duplicate (both mean
	 *                   the event is durably present), NULL on a hard write
	 *                   failure (logged) — the durable write did NOT land.
	 */
	public function insert_event( string $payload_type, int $recipe_log_id, string $event_key, array $payload, ?string $platform_received_at = null ): ?bool;

	/**
	 * Claim up to $limit pending rows for one recipe bucket, oldest first.
	 * Claimed rows flip to status=processing with attempts+1 stamped, and
	 * are returned decoded. Callers serialize per bucket via the drain lock.
	 *
	 * @param int $recipe_log_id Bucket (0 = vendor events + unresolved jobs).
	 * @param int $limit         Max rows per claim.
	 *
	 * @return array<int, array<string, mixed>> Rows: ID, payload_type, recipe_log_id, job_id, payload (decoded array), attempts.
	 */
	public function claim_pending( int $recipe_log_id, int $limit = 20 ): array;

	/**
	 * Processing succeeded — the row is RETAINED with status `completed`
	 * (and its stale error_message cleared), so the inbox is a durable
	 * audit trail of everything the platform delivered AND whether it ran.
	 * Rows leave the table only via {@see self::cleanup_expired()}.
	 *
	 * Terminal: `claim_pending()` and `requeue_stale()` both filter on
	 * status, so a completed row is never re-claimed or re-fired. Its
	 * surviving UNIQUE (job_id, payload_type) key additionally keeps
	 * deduping redeliveries after the 24h dedup transient has expired —
	 * strictly stronger than the previous delete-on-success behavior,
	 * where an expired transient plus a deleted row meant a redelivery
	 * re-fired the trigger.
	 *
	 * @param int $id Row ID.
	 *
	 * @return void
	 */
	public function mark_done( int $id ): void;

	/**
	 * Processing threw — keep the row for the sweep, record the reason.
	 *
	 * @param int    $id            Row ID.
	 * @param string $error_message Trimmed exception message.
	 *
	 * @return void
	 */
	public function mark_failed( int $id, string $error_message ): void;

	/**
	 * Flip stale `processing` rows (crashed drains) and retryable `failed`
	 * rows back to `pending`, both only while attempts < $max_attempts. A
	 * stale `processing` row at or over the cap is retired to `failed`
	 * instead, so an event that crashes its drain cannot requeue forever.
	 *
	 * @param int $stale_after_seconds Age gate on last_attempt_at.
	 * @param int $max_attempts        Rows at/over this are never requeued.
	 *
	 * @return int Rows requeued.
	 */
	public function requeue_stale( int $stale_after_seconds, int $max_attempts ): int;

	/**
	 * Distinct recipe buckets that currently have pending rows — the
	 * sweep re-fires the drain for each.
	 *
	 * @param int $limit Max buckets per sweep.
	 *
	 * @return int[] recipe_log_id values (0 included when vendor rows pend).
	 */
	public function buckets_with_pending( int $limit = 50 ): array;

	/**
	 * Drop every row older than its TTL regardless of status, with the runs
	 * an app event linked. This is the ONLY path that removes rows, so the
	 * TTL is the audit-retention window for completed and terminal-failed
	 * rows alike — not just a dead-letter cleanup. An app event (a
	 * `vendor_event` row) has a TTL of its own, since the Logs page's App
	 * events tab lists them.
	 *
	 * @since 8.0.0
	 * @since 8.1.0 Takes an app event's TTL, and deletes its run links.
	 *
	 * @param int $ttl_seconds           Age gate on received_at for every row but an app event.
	 * @param int $app_event_ttl_seconds Age gate on received_at for an app event.
	 *
	 * @return int Rows deleted.
	 */
	public function cleanup_expired( int $ttl_seconds, int $app_event_ttl_seconds ): int;

	/**
	 * Delete the processed rows of a recipe's runs, found through `uap_recipe_log`, so call it before the runs'
	 * log rows go. Processed means `completed`, or `failed` with attempts at or over $max_attempts, which
	 * requeue_stale() no longer requeues. A pending, processing or still-retryable failed row stays, so its
	 * completion still applies.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id    The recipe whose runs' rows go.
	 * @param int $max_attempts The drain's attempt cap, as passed to requeue_stale().
	 *
	 * @return int Rows deleted.
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the delete fails.
	 */
	public function delete_processed_for_recipe( int $recipe_id, int $max_attempts ): int;

	/**
	 * Delete the processed rows of one run (see delete_processed_for_recipe()). Run 0, the bucket of vendor
	 * events and unresolved jobs, is no run: nothing is deleted for it.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The run.
	 * @param int $max_attempts  The drain's attempt cap, as passed to requeue_stale().
	 *
	 * @return int Rows deleted.
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the delete fails.
	 */
	public function delete_processed_for_run( int $recipe_log_id, int $max_attempts ): int;

	/**
	 * Link an app event to the runs it started while it was handled, in
	 * `uap_platform_inbox_runs`. Adds to the runs an earlier attempt of the
	 * same row linked; a run already linked stays linked once.
	 *
	 * @since 8.1.0
	 *
	 * @param int   $id             Inbox row ID.
	 * @param int[] $recipe_log_ids Run IDs.
	 *
	 * @return void
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When a link could not be written.
	 */
	public function add_started_runs( int $id, array $recipe_log_ids ): void;

	/**
	 * The runs each app event started that still exist: the first ones by
	 * run ID, and how many there are in all.
	 *
	 * @since 8.1.0
	 *
	 * @param int[] $inbox_ids Inbox row IDs.
	 * @param int   $limit     Most run IDs kept per event.
	 *
	 * @return array<int, array{run_ids: int[], total: int}> Per inbox row ID.
	 */
	public function find_started_runs( array $inbox_ids, int $limit ): array;

	/**
	 * One page of the app events the site received (`vendor_event` rows),
	 * for the Logs page's App events tab.
	 *
	 * Each row: `ID`, `status`, `attempts`, `received_at` (Unix seconds),
	 * `error_message` and `payload` (decoded: `integration`, `event_name`,
	 * `data`, and `connection_id` on rows stored since <version>). The runs
	 * an event started are read with {@see self::find_started_runs()}.
	 *
	 * @since 8.1.0
	 *
	 * @param App_Event_Query $query The read.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function find_app_events( App_Event_Query $query ): array;

	/**
	 * How many app events match the query across all pages.
	 *
	 * @since 8.1.0
	 *
	 * @param App_Event_Query $query The read.
	 *
	 * @return int
	 */
	public function count_app_events( App_Event_Query $query ): int;

	/**
	 * Fill the App events columns of app events stored before them, up to
	 * $limit rows of each: `integration`, read from the payload, which has
	 * always named the app first, and `search_text`, the event's values.
	 *
	 * @since 8.1.0
	 *
	 * @param int $limit Most rows filled.
	 *
	 * @return int Rows filled; fewer than $limit when none are left.
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the rows could not be filled.
	 */
	public function fill_app_event_columns( int $limit ): int;

	/**
	 * Whether the inbox holds any app event (a `vendor_event` row).
	 *
	 * @since 8.1.0
	 *
	 * @return bool
	 */
	public function has_app_events(): bool;

	/**
	 * The integration slugs of the app events the inbox holds.
	 *
	 * @since 8.1.0
	 *
	 * @return string[]
	 */
	public function get_app_event_integrations(): array;
}
