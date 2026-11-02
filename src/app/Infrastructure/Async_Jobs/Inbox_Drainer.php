<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Async_Jobs;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Platform_Inbox_Store;

/**
 * Consumes `uap_platform_inbox` rows and fires the exact dispatches the
 * webhook controller used to fire inline — `automator_platform_async_completed`
 * for async completions (consumed by Background_Job_Completion_Subscriber), and
 * `automator_{integration}_event_received` for vendor events (consumed by
 * the per-integration fanout listeners).
 *
 * Listens on `automator_platform_inbox_drain( int $recipe_log_id )`, fired
 * by Integrations_Webhook_Controller after landing a batch and by the
 * Stuck_Recipe_Recovery sweep for crash-recovered rows.
 *
 * The drain runs SYNCHRONOUSLY inside the firing request — `Dispatcher::
 * action()` is a straight `do_action()` passthrough, with no shutdown
 * deferral anywhere in the path. That ordering is load-bearing: the row
 * only reaches a terminal status AFTER every listener on the dispatched
 * hook has returned, so a throwing listener leaves it retryable.
 *
 * Rows are never deleted on success — {@see Platform_Inbox_Store::mark_done()}
 * moves them to `completed` and leaves them in place, making the table an
 * audit trail of what the platform delivered and whether it ran. The only
 * removal path is the retention sweep below.
 *
 * Buckets: async completions drain under their `recipe_log_id`; vendor
 * events and unresolvable jobs drain under the shared 0 bucket. A
 * per-bucket site transient serializes concurrent drains; rows a lost
 * race leaves behind are re-fired by the sweep.
 *
 * @since 8.0.0
 */
class Inbox_Drainer {

	/**
	 * Drain hook fired per bucket.
	 *
	 * @var string
	 */
	const DRAIN_HOOK = 'automator_platform_inbox_drain';

	/**
	 * A failed or crashed row is retried by the sweep until it reaches this
	 * many claim attempts, then it stays `failed` until the retention cleanup.
	 *
	 * @var int
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * A `processing` row older than this is considered a crashed drain
	 * and requeued by the sweep, up to {@see self::MAX_ATTEMPTS}.
	 *
	 * @var int
	 */
	const STALE_AFTER_SECONDS = 5 * MINUTE_IN_SECONDS;

	/**
	 * Audit-retention window. Rows are dropped past this age regardless of
	 * status, and since success no longer deletes, this is the ONLY thing
	 * bounding the table's growth.
	 *
	 * Deliberately longer than the 24h dedup-transient TTL it used to
	 * match: once rows survive processing they are the audit trail, and a
	 * one-day trail is not one. Seven days keeps a useful support window
	 * while bounding a busy site's row count. Tune via the
	 * `automator_platform_inbox_retention_seconds` filter.
	 *
	 * @var int
	 */
	const RETENTION_SECONDS = 7 * DAY_IN_SECONDS;

	/**
	 * Rows claimed per loop iteration.
	 *
	 * @var int
	 */
	const CLAIM_BATCH = 20;

	/**
	 * Safety bound on claim loops per drain call.
	 *
	 * @var int
	 */
	const MAX_LOOPS = 10;

	/**
	 * The inbox store.
	 *
	 * @var Platform_Inbox_Store
	 */
	private $store;

	/**
	 * @param Platform_Inbox_Store|null $store Optional override for testing.
	 */
	public function __construct( ?Platform_Inbox_Store $store = null ) {
		$this->store = $store ?? Database::get_platform_inbox_store();
	}

	/**
	 * Wire the drain listener. Called from Application_Bootstrap.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::DRAIN_HOOK, array( $this, 'drain' ), 10, 1 );
	}

	/**
	 * Drain one bucket: claim pending rows oldest-first and process each
	 * in isolation — one throwing row is marked failed (kept for the
	 * sweep) and never aborts the rest.
	 *
	 * @param int|string $recipe_log_id Bucket to drain (0 = vendor/unresolved).
	 *
	 * @return void
	 */
	public function drain( $recipe_log_id ): void {

		$recipe_log_id = (int) $recipe_log_id;
		$lock_key      = 'automator_inbox_drain_lock_' . $recipe_log_id;

		// Best-effort serialization; a lost race is safe (claim flips
		// status, layer-2 dedup guards the resume path) — the sweep
		// re-fires anything left pending.
		if ( false !== get_site_transient( $lock_key ) ) {
			return;
		}
		set_site_transient( $lock_key, 1, MINUTE_IN_SECONDS );

		try {
			for ( $i = 0; $i < self::MAX_LOOPS; $i++ ) {
				$rows = $this->store->claim_pending( $recipe_log_id, self::CLAIM_BATCH );
				if ( array() === $rows ) {
					break;
				}

				foreach ( $rows as $row ) {
					try {
						$this->process_row( $row );
						$this->store->mark_done( (int) $row['ID'] );
					} catch ( \Throwable $e ) {
						$this->store->mark_failed( (int) $row['ID'], substr( $e->getMessage(), 0, 1000 ) );
						automator_log( $e->getMessage(), 'Inbox_Drainer row ' . (int) $row['ID'], false );
					}
				}
			}
		} finally {
			delete_site_transient( $lock_key );
		}
	}

	/**
	 * Recovery sweep — cron-driven (Stuck_Recipe_Recovery, 15-min tick).
	 * Requeues crashed/retryable rows, re-fires the drain per pending
	 * bucket, then drops everything past the TTL.
	 *
	 * @return int Rows requeued.
	 */
	public function sweep(): int {

		$requeued = $this->store->requeue_stale( self::STALE_AFTER_SECONDS, self::MAX_ATTEMPTS );

		foreach ( $this->store->buckets_with_pending() as $bucket ) {
			Dispatcher::action( self::DRAIN_HOOK, $bucket );
		}

		$this->store->cleanup_expired( $this->retention_seconds() );

		return $requeued;
	}

	/**
	 * Resolve the audit-retention window.
	 *
	 * Floored at one hour so a bad filter value (0, negative, a string that
	 * casts to 0) can never turn the daily sweep into "delete everything,
	 * including rows still pending".
	 *
	 * @return int Seconds.
	 */
	private function retention_seconds(): int {

		$seconds = (int) Dispatcher::filter(
			'automator_platform_inbox_retention_seconds',
			self::RETENTION_SECONDS
		);

		return max( HOUR_IN_SECONDS, $seconds );
	}

	/**
	 * Re-fire the dispatch a row's payload carries. Payload shapes are
	 * written by Integrations_Webhook_Controller at ingest time.
	 *
	 * @param array<string, mixed> $row Claimed row (payload already decoded).
	 *
	 * @return void
	 */
	private function process_row( array $row ): void {

		$payload = is_array( $row['payload'] ) ? $row['payload'] : array();

		if ( 'vendor_event' === (string) $row['payload_type'] ) {
			$integration = (string) ( $payload['integration'] ?? '' );
			if ( '' === $integration ) {
				return;
			}

			$data       = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();
			$event_name = (string) ( $payload['event_name'] ?? '' );

			// Per MVP E.7 — same hook + args the pre-inbox controller fired.
			Dispatcher::action( "automator_{$integration}_event_received", $data, $event_name );
			return;
		}

		Dispatcher::action(
			'automator_platform_async_completed',
			(string) ( $payload['job_id'] ?? $row['job_id'] ),
			(string) ( $payload['status'] ?? '' ),
			isset( $payload['result'] ) && is_array( $payload['result'] ) ? $payload['result'] : array(),
			isset( $payload['error'] ) && is_array( $payload['error'] ) ? $payload['error'] : array(),
			isset( $payload['credits'] ) && is_array( $payload['credits'] ) ? $payload['credits'] : array(),
			(string) ( $payload['request_id'] ?? '' )
		);
	}
}
