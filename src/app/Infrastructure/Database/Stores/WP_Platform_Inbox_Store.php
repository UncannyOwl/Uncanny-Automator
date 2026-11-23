<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Platform_Inbox_Store;
use Uncanny_Automator\App\Recipe_Logs\App_Events\Value_Objects\App_Event_Query;
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
	 * The payload type of an event an app sent through the platform.
	 *
	 * @var string
	 */
	private const VENDOR_EVENT = 'vendor_event';

	/**
	 * How a vendor event's stored payload has always opened, up to its
	 * integration slug: Integrations_Webhook_Controller writes `integration`
	 * first. {@see self::fill_app_event_columns()} reads rows stored before
	 * the `integration` column by it.
	 *
	 * @var string
	 */
	private const INTEGRATION_PREFIX = '{"integration":"';

	/**
	 * Longest platform slug the `integration` column holds.
	 *
	 * @var int
	 */
	private const INTEGRATION_LENGTH = 64;

	/**
	 * Delete statements per cleanup_expired() call. Bounds work per sweep;
	 * the remainder is collected on the next recovery tick.
	 *
	 * @var int
	 */
	private const CLEANUP_MAX_BATCHES = 20;

	/**
	 * Run links written per INSERT by {@see self::add_started_runs()}, so one
	 * burst of runs never builds an unbounded statement.
	 *
	 * @var int
	 */
	private const RUN_LINK_BATCH_SIZE = 500;

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
	 * @return string Prefixed name of the table that links an app event to the runs it started.
	 */
	private function runs_table(): string {
		return $this->wpdb->prefix . 'uap_platform_inbox_runs';
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

		// A vendor event keeps its app's platform slug in a column of its
		// own, so the App events reads filter and group by app on an index,
		// and the text the tab's search matches: its values, never its keys.
		$is_app_event = self::VENDOR_EVENT === $payload_type;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$this->table()} (payload_type, integration, recipe_log_id, job_id, payload, search_text, platform_received_at, status) VALUES (%s, %s, %d, %s, %s, %s, %s, 'pending')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$payload_type,
				$is_app_event ? self::integration_of( $payload ) : '',
				$recipe_log_id,
				$event_key,
				(string) wp_json_encode( $payload ),
				$is_app_event ? self::search_text_of( $payload ) : '',
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
	public function cleanup_expired( int $ttl_seconds, int $app_event_ttl_seconds ): int {

		$wpdb = $this->wpdb;

		// Batched because rows now accumulate for the whole retention window
		// instead of being deleted on success. A single unbounded DELETE would
		// hold one transaction over every expired row — worst case when a site
		// shortens `automator_platform_inbox_retention_seconds` and days of
		// backlog expire at once. `app_events` and `retention_scan` make
		// finding them cheap; they do nothing for how long the lock is held.
		//
		// The cap bounds work per sweep, not total: the sweep rides the
		// 15-minute recovery tick, so anything left over is collected shortly
		// after rather than deferred a day. App events go first, then every
		// other row, out of one shared budget of statements.
		$passes = array(
			$wpdb->prepare( 'payload_type = %s AND received_at < DATE_SUB( NOW(), INTERVAL %d SECOND )', self::VENDOR_EVENT, $app_event_ttl_seconds ),
			$wpdb->prepare( 'payload_type <> %s AND received_at < DATE_SUB( NOW(), INTERVAL %d SECOND )', self::VENDOR_EVENT, $ttl_seconds ),
		);

		$deleted = 0;
		$batches = 0;

		foreach ( $passes as $where ) {
			while ( $batches < self::CLEANUP_MAX_BATCHES ) {
				++$batches;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table read; $where is prepared above.
				$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$this->table()} WHERE {$where} LIMIT %d", self::CLEANUP_BATCH_SIZE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result = $this->delete_rows( array_map( 'intval', (array) $ids ) );

				// false = SQL failure: stop the sweep. 0 = nothing left
				// expired in this pass: go on to the next.
				if ( false === $result ) {
					return $deleted;
				}

				$deleted += $result;

				if ( count( (array) $ids ) < self::CLEANUP_BATCH_SIZE ) {
					break;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Delete inbox rows and the run links they hold. The links go first: an
	 * expired row left without its links is still expired, while a link
	 * left without its row would never be read or removed.
	 *
	 * @param int[] $ids Inbox row IDs.
	 *
	 * @return int|false Rows deleted, or false when a delete failed.
	 */
	private function delete_rows( array $ids ) {

		$wpdb = $this->wpdb;
		$ids  = array_values( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) );

		if ( array() === $ids ) {
			return 0;
		}

		$in = implode( ',', $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table write; the ID list is cast to integers above.
		if ( false === $wpdb->query( "DELETE FROM {$this->runs_table()} WHERE inbox_id IN ( {$in} )" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table write; the ID list is cast to integers above.
		$deleted = $wpdb->query( "DELETE FROM {$this->table()} WHERE ID IN ( {$in} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return false === $deleted ? false : (int) $deleted;
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

	/**
	 * {@inheritDoc}
	 */
	public function add_started_runs( int $id, array $recipe_log_ids ): void {

		$wpdb = $this->wpdb;
		$ids  = array_values( array_unique( array_filter( array_map( 'intval', $recipe_log_ids ), static fn( int $run ): bool => $run > 0 ) ) );

		if ( 0 >= $id || array() === $ids ) {
			return;
		}

		foreach ( array_chunk( $ids, self::RUN_LINK_BATCH_SIZE ) as $chunk ) {
			$values = implode(
				', ',
				array_map(
					static fn( int $run ): string => sprintf( '(%d, %d)', $id, $run ),
					$chunk
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table write; every value is formatted as an integer above.
			if ( false === $wpdb->query( "INSERT IGNORE INTO {$this->runs_table()} ( inbox_id, recipe_log_id ) VALUES {$values}" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				throw new Store_Exception( sprintf( 'The runs app event %d started could not be linked to it: %s', absint( $id ), esc_html( $wpdb->last_error ) ) );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_started_runs( array $inbox_ids, int $limit ): array {

		$wpdb = $this->wpdb;
		$ids  = array_values( array_unique( array_filter( array_map( 'intval', $inbox_ids ), static fn( int $id ): bool => $id > 0 ) ) );

		if ( array() === $ids || 0 >= $limit ) {
			return array();
		}

		$in    = implode( ',', $ids );
		$found = array();

		foreach ( $ids as $id ) {
			$found[ $id ] = array(
				'run_ids' => array(),
				'total'   => 0,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table read; the ID list is cast to integers above.
		$totals = $wpdb->get_results( "SELECT l.inbox_id, COUNT(*) AS total FROM {$this->runs_table()} l INNER JOIN {$wpdb->prefix}uap_recipe_log r ON r.ID = l.recipe_log_id WHERE l.inbox_id IN ( {$in} ) GROUP BY l.inbox_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( (array) $totals as $row ) {
			$found[ (int) $row['inbox_id'] ]['total'] = (int) $row['total'];
		}

		$firsts = array();

		foreach ( $ids as $id ) {
			if ( 0 === $found[ $id ]['total'] ) {
				continue;
			}

			$firsts[] = $wpdb->prepare(
				"( SELECT l.inbox_id, l.recipe_log_id FROM {$this->runs_table()} l INNER JOIN {$wpdb->prefix}uap_recipe_log r ON r.ID = l.recipe_log_id WHERE l.inbox_id = %d ORDER BY l.recipe_log_id ASC LIMIT %d )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id,
				$limit
			);
		}

		if ( array() === $firsts ) {
			return $found;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table read; each part is prepared above.
		$rows = $wpdb->get_results( implode( ' UNION ALL ', $firsts ), ARRAY_A );

		foreach ( (array) $rows as $row ) {
			$found[ (int) $row['inbox_id'] ]['run_ids'][] = (int) $row['recipe_log_id'];
		}

		foreach ( $found as $id => $links ) {
			sort( $found[ $id ]['run_ids'] );
		}

		return $found;
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_app_events( App_Event_Query $query ): array {

		$wpdb  = $this->wpdb;
		$where = $this->app_event_where( $query );
		$order = 'asc' === $query->get_order() ? 'ASC' : 'DESC';
		$by    = 'status' === $query->get_orderby() ? $this->status_order_sql( $query ) . " {$order}, received_at DESC" : "received_at {$order}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, status, attempts, UNIX_TIMESTAMP( received_at ) AS received_at, error_message, payload FROM {$this->table()} WHERE {$where} ORDER BY {$by}, ID {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from prepared fragments; $by and $order are fixed strings.
				$query->get_per_page(),
				$query->get_offset()
			),
			ARRAY_A
		);

		$events = array();

		foreach ( (array) $rows as $row ) {
			$payload  = json_decode( (string) $row['payload'], true );
			$events[] = array(
				'ID'            => (int) $row['ID'],
				'status'        => (string) $row['status'],
				'attempts'      => (int) $row['attempts'],
				'received_at'   => (int) $row['received_at'],
				'error_message' => (string) ( $row['error_message'] ?? '' ),
				'payload'       => is_array( $payload ) ? $payload : array(),
			);
		}

		return $events;
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_app_events( App_Event_Query $query ): int {

		$wpdb  = $this->wpdb;
		$where = $this->app_event_where( $query );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table read; $where is built from prepared fragments.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * {@inheritDoc}
	 */
	public function has_app_events(): bool {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read.
		return '1' === $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$this->table()} WHERE payload_type = %s LIMIT 1", self::VENDOR_EVENT ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_app_event_integrations(): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read.
		$slugs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT integration FROM {$this->table()} WHERE payload_type = %s AND integration <> ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::VENDOR_EVENT
			)
		);

		$slugs = array_values( array_map( 'strval', (array) $slugs ) );
		sort( $slugs );

		return $slugs;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fill_app_event_columns( int $limit ): int {

		$wpdb  = $this->wpdb;
		$limit = max( 1, $limit );
		$start = strlen( self::INTEGRATION_PREFIX ) + 1;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$named = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET integration = LEFT( SUBSTRING( payload, %d, LOCATE( '\"', payload, %d ) - %d ), %d ) WHERE payload_type = %s AND integration = '' AND payload LIKE %s LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$start,
				$start,
				$start,
				self::INTEGRATION_LENGTH,
				self::VENDOR_EVENT,
				$wpdb->esc_like( self::INTEGRATION_PREFIX ) . '_%',
				$limit
			)
		);

		if ( false === $named ) {
			throw new Store_Exception( sprintf( 'The app events stored before their integration column could not be filled: %s', esc_html( $wpdb->last_error ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table read.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, payload FROM {$this->table()} WHERE payload_type = %s AND search_text IS NULL LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::VENDOR_EVENT,
				$limit
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$payload = json_decode( (string) $row['payload'], true );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
			$written = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->table()} SET search_text = %s WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					self::search_text_of( is_array( $payload ) ? $payload : array() ),
					(int) $row['ID']
				)
			);

			if ( false === $written ) {
				throw new Store_Exception( sprintf( 'The search text of app event %d could not be filled: %s', absint( $row['ID'] ), esc_html( $wpdb->last_error ) ) );
			}
		}

		return max( (int) $named, count( (array) $rows ) );
	}

	/**
	 * What the App events search matches for a vendor event: its event name
	 * and every value its data holds, as they arrived, one space apart.
	 * Keys are left out, so a search for "data" or "event" matches only an
	 * event that says it.
	 *
	 * @param array<string, mixed> $payload The event's payload.
	 *
	 * @return string
	 */
	private static function search_text_of( array $payload ): string {
		$values = array();

		if ( isset( $payload['event_name'] ) && is_scalar( $payload['event_name'] ) ) {
			$values[] = (string) $payload['event_name'];
		}

		$data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();

		array_walk_recursive(
			$data,
			static function ( $value ) use ( &$values ): void {
				if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
					$values[] = (string) $value;
				}
			}
		);

		return implode( ' ', array_filter( $values, static fn( string $value ): bool => '' !== trim( $value ) ) );
	}

	/**
	 * A vendor event's platform slug, as its payload names it.
	 *
	 * @param array<string, mixed> $payload The event's payload.
	 *
	 * @return string
	 */
	private static function integration_of( array $payload ): string {
		$slug = isset( $payload['integration'] ) && is_scalar( $payload['integration'] ) ? (string) $payload['integration'] : '';

		return substr( $slug, 0, self::INTEGRATION_LENGTH );
	}

	/**
	 * The rank of a row's status among the statuses in the order the tab's
	 * labels sort, so a sort by status reads A to Z by label, not by the
	 * stored word.
	 *
	 * @param App_Event_Query $query The read.
	 *
	 * @return string
	 */
	private function status_order_sql( App_Event_Query $query ): string {

		$wpdb     = $this->wpdb;
		$statuses = $query->get_status_order();

		if ( array() === $statuses ) {
			return 'status';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per status, built from the list's own length.
		return $wpdb->prepare( 'FIELD( status, ' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )', $statuses );
	}

	/**
	 * The WHERE clause of an App events read, every value prepared.
	 *
	 * @param App_Event_Query $query The read.
	 *
	 * @return string
	 */
	private function app_event_where( App_Event_Query $query ): string {

		$wpdb  = $this->wpdb;
		$parts = array( $wpdb->prepare( 'payload_type = %s', self::VENDOR_EVENT ) );

		if ( null !== $query->get_integration() ) {
			$parts[] = $wpdb->prepare( 'integration = %s', $query->get_integration() );
		}

		if ( null !== $query->get_inbox_status() ) {
			$parts[] = $wpdb->prepare( 'status = %s', $query->get_inbox_status() );
		}

		if ( null !== $query->get_received_from() ) {
			$parts[] = $wpdb->prepare( 'received_at >= FROM_UNIXTIME( %d )', $query->get_received_from() );
		}

		if ( null !== $query->get_received_to() ) {
			$parts[] = $wpdb->prepare( 'received_at <= FROM_UNIXTIME( %d )', $query->get_received_to() );
		}

		if ( null !== $query->get_search() ) {
			// The event's values as they arrived, never its keys or its JSON:
			// "data" matches a value that says data, and a path matches as typed.
			$parts[] = $wpdb->prepare( 'search_text LIKE %s', '%' . $wpdb->esc_like( $query->get_search() ) . '%' );
		}

		return implode( ' AND ', $parts );
	}
}
