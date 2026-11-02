<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Platform_Inbox_Store;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;

/**
 * wpdb implementation of the durable ingress inbox (`uap_platform_inbox`).
 *
 * Claiming is atomic: one UPDATE stamps a per-call `claim_token` onto the
 * rows it wins, and the follow-up SELECT filters on that token. A drainer
 * can therefore only ever see rows its own statement claimed.
 *
 * This does NOT rely on the per-bucket drain transient for correctness. That
 * lock is a read-then-write and does not serialize concurrent drainers; it
 * stays only as a cheap way to avoid redundant work.
 *
 * @since 8.0.0
 */
class WP_Platform_Inbox_Store implements Platform_Inbox_Store {

	/**
	 * Rows deleted per statement by {@see self::cleanup_expired()}, so one
	 * expiry never holds a lock over an unbounded row count.
	 *
	 * @var int
	 */
	private const CLEANUP_BATCH_SIZE = 500;

	/**
	 * Delete statements per cleanup_expired() call. Bounds work per sweep;
	 * the remainder is collected on the next recovery tick.
	 *
	 * @var int
	 */
	private const CLEANUP_MAX_BATCHES = 20;

	/**
	 * WordPress database instance. A method that runs a prepared query reads it
	 * into a local `$wpdb` first: the PreparedSQL sniff recognises
	 * `$wpdb->prepare()` only under that name.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * @return string Prefixed table name.
	 */
	private function table(): string {
		return $this->wpdb->prefix . 'uap_platform_inbox';
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert_event( string $payload_type, int $recipe_log_id, string $event_key, array $payload, ?string $platform_received_at = null ): ?bool {

		$wpdb = $this->wpdb;

		if ( '' === $payload_type || '' === $event_key ) {
			return false;
		}

		// Resolve the drain-ordering key to a single non-null UTC DATETIME:
		// the platform's event time when supplied, else insert-time UTC. One
		// scale for every row means claim_pending can ORDER BY the plain
		// (indexed) column — no COALESCE filesort, no DATETIME/TIMESTAMP
		// timezone mixing against the local `received_at`.
		$source = null !== $platform_received_at ? $platform_received_at : gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$this->table()} (payload_type, recipe_log_id, job_id, payload, platform_received_at, status) VALUES (%s, %d, %s, %s, %s, 'pending')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$payload_type,
				$recipe_log_id,
				$event_key,
				(string) wp_json_encode( $payload ),
				$source
			)
		);

		// $wpdb->query() returns false only on a hard SQL failure (missing
		// table/column, connection loss). That is the one case the durable
		// write did NOT land — surface it loudly instead of letting the event
		// vanish. INSERT IGNORE returns 0 for a redelivery duplicate (the row
		// is already present) and 1 for a fresh insert; both are "durable".
		if ( false === $result ) {
			automator_log(
				sprintf( 'uap_platform_inbox insert failed (%s/%s): %s', $payload_type, $event_key, $wpdb->last_error ),
				'Platform_Inbox',
				false
			);
			return null;
		}

		return $result > 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function claim_pending( int $recipe_log_id, int $limit = 20 ): array {

		$wpdb = $this->wpdb;

		$limit = max( 1, $limit );
		$token = $this->mint_claim_token();

		// Ownership is established by the UPDATE itself, in one statement.
		//
		// The previous shape — SELECT the pending IDs, UPDATE them, SELECT
		// them back — was not atomic, and the per-bucket site transient in
		// front of it is a read-then-write that does not serialize either. Two
		// drainers could select the same IDs; the loser's UPDATE matched zero
		// rows (the `status = 'pending'` guard held), but its follow-up SELECT
		// filtered on `status = 'processing'` and so returned the winner's rows
		// anyway. Both then dispatched the same event and raced to write its
		// terminal state.
		//
		// Stamping a per-call token and reading back by that token means a
		// drainer can only ever see rows its own UPDATE won.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET status = 'processing', claim_token = %s, attempts = attempts + 1, last_attempt_at = NOW() WHERE recipe_log_id = %d AND status = 'pending' ORDER BY platform_received_at ASC, ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token,
				$recipe_log_id,
				$limit
			)
		);

		if ( false === $claimed || 0 === (int) $claimed ) {
			return array();
		}

		// Same causal order the claim applied, so the drain PROCESSES events in
		// the vendor's order rather than local receipt order.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, payload_type, recipe_log_id, job_id, payload, attempts FROM {$this->table()} WHERE claim_token = %s ORDER BY platform_received_at ASC, ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token
			),
			ARRAY_A
		);

		return array_map(
			static function ( array $row ): array {
				$decoded        = json_decode( (string) $row['payload'], true );
				$row['ID']      = (int) $row['ID'];
				$row['payload'] = is_array( $decoded ) ? $decoded : array();

				$row['recipe_log_id'] = (int) $row['recipe_log_id'];
				$row['attempts']      = (int) $row['attempts'];

				return $row;
			},
			$rows
		);
	}

	/**
	 * Mint a claim token unique to one claim_pending() call.
	 *
	 * Uniqueness is the whole contract — two concurrent drainers must never
	 * mint the same value, or the token stops proving ownership.
	 * `wp_generate_uuid4()` is seeded per call and is not derived from
	 * anything a second process shares.
	 *
	 * @return string
	 */
	private function mint_claim_token(): string {

		return wp_generate_uuid4();
	}
	/**
	 * {@inheritDoc}
	 */
	public function mark_done( int $id ): void {

		$wpdb = $this->wpdb;

		// error_message is cleared because a row that failed, was requeued by
		// the sweep and then succeeded must not keep advertising the old
		// exception — on an audit trail a stale error_message beside a
		// 'completed' status reads as a failure that wasn't.
		//
		// last_attempt_at uses SQL NOW(), NOT current_time( 'mysql', true ):
		// see the note on mark_failed().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET status = 'completed', error_message = NULL, last_attempt_at = NOW() WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function mark_failed( int $id, string $error_message ): void {

		$wpdb = $this->wpdb;

		// last_attempt_at MUST be stamped with SQL NOW(), on the same clock
		// `claim_pending()` writes and `requeue_stale()` compares against.
		//
		// `current_time( 'mysql', true )` returns a UTC wall-clock STRING,
		// which MySQL then interprets in the session time zone when writing
		// this TIMESTAMP column. On any site whose session tz is not UTC the
		// value lands offset by exactly that amount, and since requeue_stale()
		// gates on `last_attempt_at < DATE_SUB( NOW(), ... )` the 5-minute
		// stale window silently becomes the tz offset: hours of delayed
		// retries on a UTC-behind server, instant requeue-on-every-sweep on a
		// UTC-ahead one. Timezone-dependent, so it hides in UTC-configured
		// test environments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET status = 'failed', error_message = %s, last_attempt_at = NOW() WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$error_message,
				$id
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function requeue_stale( int $stale_after_seconds, int $max_attempts ): int {

		$wpdb = $this->wpdb;

		// A drain that dies without reaching mark_done() / mark_failed() — a
		// fatal, a timeout, exhausted memory, none of which the drainer's catch
		// sees — leaves its row `processing`. Retire such a row once it has used
		// its attempts. Without the cap it requeued on every sweep for as long as
		// it existed, re-firing its trigger each time and killing the recovery
		// cron that runs the sweep before it reached anything else.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET status = 'failed', claim_token = '', error_message = %s WHERE status = 'processing' AND attempts >= %d AND last_attempt_at < DATE_SUB( NOW(), INTERVAL %d SECOND )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'Retired: every attempt ended without a result (the drain crashed or timed out).',
				$max_attempts,
				$stale_after_seconds
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$result = $wpdb->query(
			$wpdb->prepare(
				// claim_token is cleared with the status: a requeued row is
				// unowned again, and leaving the crashed drainer's token on it
				// would let that drainer's stale SELECT pick it back up.
				"UPDATE {$this->table()} SET status = 'pending', claim_token = '' WHERE last_attempt_at < DATE_SUB( NOW(), INTERVAL %d SECOND ) AND status IN ( 'processing', 'failed' ) AND attempts < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$stale_after_seconds,
				$max_attempts
			)
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function buckets_with_pending( int $limit = 50 ): array {

		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT recipe_log_id FROM {$this->table()} WHERE status = 'pending' LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					max( 1, $limit )
				)
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanup_expired( int $ttl_seconds ): int {

		$wpdb = $this->wpdb;

		// Batched because rows now accumulate for the whole retention window
		// instead of being deleted on success. A single unbounded DELETE would
		// hold one transaction over every expired row — worst case when a site
		// shortens `automator_platform_inbox_retention_seconds` and days of
		// backlog expire at once. `retention_scan` makes finding them cheap;
		// it does nothing for how long the lock is held.
		//
		// The cap bounds work per sweep, not total: the sweep rides the
		// 15-minute recovery tick, so anything left over is collected shortly
		// after rather than deferred a day.
		$deleted = 0;

		for ( $batch = 0; $batch < self::CLEANUP_MAX_BATCHES; $batch++ ) {

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$this->table()} WHERE received_at < DATE_SUB( NOW(), INTERVAL %d SECOND ) LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$ttl_seconds,
					self::CLEANUP_BATCH_SIZE
				)
			);

			// false = SQL failure; 0 = nothing left expired. Either way stop.
			if ( false === $result || 0 === (int) $result ) {
				break;
			}

			$deleted += (int) $result;
		}

		return $deleted;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_processed_for_recipe( int $recipe_id, int $max_attempts ): int {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE i FROM {$this->table()} i INNER JOIN {$wpdb->prefix}uap_recipe_log r ON i.recipe_log_id = r.ID WHERE r.automator_recipe_id = %d AND ( i.status = 'completed' OR ( i.status = 'failed' AND i.attempts >= %d ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id,
				$max_attempts
			)
		);

		if ( false === $deleted ) {
			throw new Store_Exception( sprintf( 'The processed inbox rows of recipe %d could not be deleted: %s', absint( $recipe_id ), esc_html( $wpdb->last_error ) ) );
		}

		return (int) $deleted;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_processed_for_run( int $recipe_log_id, int $max_attempts ): int {

		if ( 0 >= $recipe_log_id ) {
			return 0;
		}

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table()} WHERE recipe_log_id = %d AND ( status = 'completed' OR ( status = 'failed' AND attempts >= %d ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id,
				$max_attempts
			)
		);

		if ( false === $deleted ) {
			throw new Store_Exception( sprintf( 'The processed inbox rows of run %d could not be deleted: %s', absint( $recipe_log_id ), esc_html( $wpdb->last_error ) ) );
		}

		return (int) $deleted;
	}
}
