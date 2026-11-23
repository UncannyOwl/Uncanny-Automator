<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Async_Jobs;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Platform_Inbox_Store;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Integration_Catalog\App_Events\App_Event_Sources;

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
	 * Fired when a run starts; the drain listens while it hands an app event over.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const RUN_STARTED_HOOK = 'automator_recipe_run_started';

	/**
	 * Runs the queued triggers now (Trigger_Queue::DRAIN_HOOK): an app's trigger only queues on the event,
	 * and its run starts when the queue drains.
	 *
	 * @since 8.1.0
	 */
	const TRIGGER_QUEUE_DRAIN_HOOK = 'automator_drain_trigger_queue';

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
	 * An app event (a `vendor_event` row) keeps {@see self::APP_EVENT_RETENTION_SECONDS} instead.
	 *
	 * @var int
	 */
	const RETENTION_SECONDS = 7 * DAY_IN_SECONDS;

	/**
	 * Retention window of an app event (a `vendor_event` row): fifteen days,
	 * what the Logs page's App events tab lists. Every other row keeps
	 * {@see self::RETENTION_SECONDS}. Tune via the
	 * `automator_platform_inbox_app_event_retention_seconds` filter.
	 *
	 * @since 8.1.0
	 *
	 * @var int
	 */
	const APP_EVENT_RETENTION_SECONDS = 15 * DAY_IN_SECONDS;

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
	 * The apps that send events, by the platform's slug.
	 *
	 * @var App_Event_Sources
	 */
	private $sources;

	/**
	 * @param Platform_Inbox_Store|null $store   Optional override for testing.
	 * @param App_Event_Sources|null    $sources Optional override for testing.
	 */
	public function __construct( ?Platform_Inbox_Store $store = null, ?App_Event_Sources $sources = null ) {
		$this->store   = $store ?? Database::get_platform_inbox_store();
		$this->sources = $sources ?? automator_app_event_sources();
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

		$this->store->cleanup_expired(
			$this->retention_seconds( 'automator_platform_inbox_retention_seconds', self::RETENTION_SECONDS ),
			$this->retention_seconds( 'automator_platform_inbox_app_event_retention_seconds', self::APP_EVENT_RETENTION_SECONDS )
		);

		return $requeued;
	}

	/**
	 * Resolve an audit-retention window through its filter.
	 *
	 * Floored at one hour so a bad filter value (0, negative, a string that
	 * casts to 0) can never turn the daily sweep into "delete everything,
	 * including rows still pending".
	 *
	 * @since 8.0.0
	 * @since 8.1.0 Resolves either window: every row's, or an app event's.
	 *
	 * @param string $filter   The window's filter.
	 * @param int    $fallback The window when no filter changes it.
	 *
	 * @return int Seconds.
	 */
	private function retention_seconds( string $filter, int $fallback ): int {

		$seconds = (int) Dispatcher::filter( $filter, $fallback );

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

			$this->dispatch_vendor_event( (int) $row['ID'], $integration, $data, $event_name );
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

	/**
	 * Hand a vendor event to its integration, and record the runs it started.
	 *
	 * A run counts when its trigger belongs to the event's own integration:
	 * the trigger's `integration` is the code the app's source states for the
	 * platform's slug. A run started further down a chain (a Dropbox run's
	 * action firing a WordPress trigger) stays with the event that really
	 * started it. An app with no source on this request names no
	 * integration, so its event records no runs. The runs are recorded even
	 * when a listener throws, because they ran.
	 *
	 * A link that could not be written is logged and the event still counts
	 * as handled: failing it would run its recipes again on the retry.
	 *
	 * @since 8.1.0
	 *
	 * @param int                  $row_id      Inbox row ID.
	 * @param string               $integration The platform's slug for the app.
	 * @param array<string, mixed> $data        The event's data block.
	 * @param string               $event_name  The event's name.
	 *
	 * @return void
	 */
	private function dispatch_vendor_event( int $row_id, string $integration, array $data, string $event_name ): void {

		$source = $this->sources->find( $integration );

		if ( null === $source ) {
			Dispatcher::action( "automator_{$integration}_event_received", $data, $event_name );
			return;
		}

		$code     = $source->get_integration_code();
		$started  = array();
		$listener = static function ( $recipe_id, $user_id, $recipe_log_id, $args ) use ( $code, &$started ): void {
			$trigger_id = absint( is_array( $args ) ? ( $args['trigger_id'] ?? 0 ) : 0 );

			if ( 0 === $trigger_id || (string) get_post_meta( $trigger_id, 'integration', true ) !== $code ) {
				return;
			}

			$started[] = absint( $recipe_log_id );
		};

		add_action( self::RUN_STARTED_HOOK, $listener, 10, 4 );

		try {
			// Per MVP E.7 — same hook + args the pre-inbox controller fired.
			Dispatcher::action( "automator_{$integration}_event_received", $data, $event_name );
			$this->run_queued_triggers();
		} finally {
			remove_action( self::RUN_STARTED_HOOK, $listener, 10 );

			$this->link_runs( $row_id, $started );
		}
	}

	/**
	 * Run the triggers the event queued while the listener still hears their runs start: left to the queue's
	 * shutdown pass, they start after it is gone, and no delivery is ever linked to its run. A recipe that fails
	 * there is its own run's error, never the event's: the event was received and handled, and failing its row
	 * would run its recipes again on the retry.
	 *
	 * @since 8.1.0
	 *
	 * @return void
	 */
	private function run_queued_triggers(): void {

		try {
			Dispatcher::action( self::TRIGGER_QUEUE_DRAIN_HOOK );
		} catch ( \Throwable $failed ) {
			automator_log( 'A recipe failed while its app event was handled: ' . $failed->getMessage(), 'Inbox_Drainer run links', true );
		}
	}

	/**
	 * Link an app event to the runs it started. A write that fails is logged,
	 * forced, and never thrown: the event was handled and its runs ran.
	 *
	 * @since 8.1.0
	 *
	 * @param int   $row_id  Inbox row ID.
	 * @param int[] $started Run IDs.
	 *
	 * @return void
	 */
	private function link_runs( int $row_id, array $started ): void {

		if ( array() === $started ) {
			return;
		}

		try {
			$this->store->add_started_runs( $row_id, $started );
		} catch ( Store_Exception $refused ) {
			automator_log( $refused->getMessage(), 'Inbox_Drainer run links', true );
		}
	}
}
