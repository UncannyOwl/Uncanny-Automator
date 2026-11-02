<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Atomic_Option_Lock;

/**
 * Hash-keyed, non-autoloaded storage for short-lived OAuth artifacts.
 */
final class OAuth_Artifact_Store {

	private const PREFIX = 'automator_mcp_oauth_v1_';

	private const CLEANUP_CURSOR_OPTION = 'automator_mcp_oauth_artifact_cleanup_cursor';
	private const CLEANUP_RETRY_OPTION  = 'automator_mcp_oauth_artifact_cleanup_retry';

	/**
	 * OAuth families keep replay state in one compact row. A bounded daily page
	 * comfortably covers the 100-client cap plus abandoned browser artifacts.
	 */
	private const DEFAULT_CLEANUP_LIMIT = 500;

	private const MAX_CLEANUP_LIMIT = 5000;

	private Atomic_Option_Lock $lock;
	private bool $cleanup_confirmed = false;

	/**
	 * Constructor.
	 */
	public function __construct( ?Atomic_Option_Lock $lock = null ) {
		$this->lock = $lock ?? new Atomic_Option_Lock();
	}

	/**
	 * Store a record under a one-way hash of its secret identifier.
	 */
	public function put( string $kind, string $identifier, array $record ): bool {
		$option              = $this->option_name( $kind, $identifier );
		$record['stored_at'] = time();

		return null === get_option( $option, null )
			? add_option( $option, $record, '', false )
			: update_option( $option, $record, false );
	}

	/**
	 * Read a record without consuming it.
	 */
	public function get( string $kind, string $identifier ): ?array {
		$record = get_option( $this->option_name( $kind, $identifier ), null );

		return is_array( $record ) ? $record : null;
	}

	/**
	 * Read an artifact from the database, distinguishing absence from an
	 * unavailable or malformed record. A cached negative is not proof that a
	 * credential has ended.
	 *
	 * @since 8.0.0
	 *
	 * @return array{exists:bool,record?:array}|null Null means the read was unproven.
	 */
	public function get_confirmed( string $kind, string $identifier ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A destructive cleanup decision needs an authoritative read, not an option cache hit.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->option_name( $kind, $identifier ) ),
			ARRAY_A
		);
		if ( '' !== $wpdb->last_error ) {
			return null;
		}
		if ( null === $row ) {
			return array( 'exists' => false );
		}
		if ( ! is_array( $row ) || ! array_key_exists( 'option_value', $row ) ) {
			return null;
		}

		$record = automator_safe_unserialize( $row['option_value'] );
		return is_array( $record ) ? array(
			'exists' => true,
			'record' => $record,
		) : null;
	}

	/**
	 * Atomically consume a record once.
	 */
	public function consume( string $kind, string $identifier ): ?array {
		$option = $this->option_name( $kind, $identifier );
		$owner  = $this->lock->acquire( $option . '_lock', 10, 1 );
		if ( false === $owner ) {
			return null;
		}

		try {
			// Single-use hinges on delete_option() issuing a direct DB
			// DELETE: even with a stale object cache, only one concurrent
			// consumer gets a truthy delete. Never replace this with a
			// cache-backed existence check.
			$record = get_option( $option, null );
			if ( ! is_array( $record ) || ! delete_option( $option ) ) {
				return null;
			}

			return $record;
		} finally {
			$this->lock->release( $option . '_lock', $owner );
		}
	}

	/**
	 * Run an atomic mutation while holding a named artifact lock.
	 *
	 * @param callable $callback Receives this store and returns a result.
	 * @param bool $pass_lease Also pass the lock option name and owner to the callback.
	 * @param float $wait_seconds Maximum time to wait for the lock.
	 * @return mixed|null Callback result or null on lock contention.
	 */
	public function with_lock( string $kind, string $identifier, callable $callback, bool $pass_lease = false, float $wait_seconds = 1 ) {
		$lock_name = $this->option_name( $kind, $identifier ) . '_lock';
		$owner     = $this->lock->acquire( $lock_name, 'client' === $kind ? 60 : 15, $wait_seconds );
		if ( false === $owner ) {
			return null;
		}

		try {
			// Another PHP request may have rotated or revoked this record while
			// we waited for the lock. Discard our earlier get_option() view so
			// the callback reads current state and update_option() compares against
			// it. The lock alone does not refresh WordPress's per-request cache.
			wp_cache_delete( $this->option_name( $kind, $identifier ), 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			return $pass_lease ? $callback( $this, $lock_name, $owner ) : $callback( $this );
		} finally {
			$this->lock->release( $lock_name, $owner );
		}
	}

	/**
	 * Find remaining grants or pending authorization for a client.
	 * Call while holding its client lifecycle lock. An incomplete scan is not
	 * evidence that a registration is unused.
	 *
	 * @return bool|null True when busy, false when unused, null on scan failure.
	 */
	public function has_live_client_artifacts( string $client_id ): ?bool {
		$records = $this->read_live_client_artifacts( array( $client_id ) );
		return null === $records ? null : array() !== $records;
	}

	/**
	 * Cancel uncompleted approvals without banning the app from reconnecting.
	 * The caller holds this client's lifecycle lock, shared by consent and token
	 * exchange, so a pending code cannot turn into a family during this cleanup.
	 *
	 * @since 8.0.0
	 */
	public function delete_pending_client_grants( string $client_id ): bool {
		$records = $this->read_live_client_artifacts( array( $client_id ) );
		if ( null === $records ) {
			return false;
		}
		foreach ( $records as $artifact ) {
			if ( 'pending' === $artifact['kind'] && ! delete_option( $artifact['option_name'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Return live refresh-family owners for every requested client in one scan.
	 * Pending consent/code artifacts cannot identify a connected user.
	 *
	 * @param string[] $client_ids Requested client identifiers.
	 * @return array<string,int[]>|null Grant owners, or null on unproven reads.
	 */
	public function get_active_client_users( array $client_ids ): ?array {
		$records = $this->read_live_client_artifacts( $client_ids );
		if ( null === $records ) {
			return null;
		}
		$users = array_fill_keys( $client_ids, array() );
		foreach ( $records as $artifact ) {
			if ( 'family' !== $artifact['kind'] ) {
				continue;
			}
			$record = $artifact['record'];
			if ( ! isset( $record['user_id'] ) || ! is_numeric( $record['user_id'] ) || (int) $record['user_id'] <= 0 ) {
				return null;
			}
			$user_id                                   = (int) $record['user_id'];
			$users[ $record['client_id'] ][ $user_id ] = $user_id;
		}
		return array_map( 'array_values', $users );
	}

	/**
	 * Read bounded, fresh live artifacts once for cancellation or display.
	 *
	 * @param string[] $client_ids Requested client identifiers.
	 * @return array|null Matching records, or null when absence is unproven.
	 */
	private function read_live_client_artifacts( array $client_ids ): ?array {
		global $wpdb;

		foreach ( $client_ids as $client_id ) {
			if ( ! is_string( $client_id ) || '' === $client_id || strlen( $client_id ) > 2048 ) {
				return null;
			}
		}
		if ( array() === $client_ids ) {
			return array();
		}
		$requested = array_fill_keys( $client_ids, true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix scan of the options table, which WordPress has no API for; live OAuth state, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE (option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s) AND option_name NOT LIKE %s LIMIT 5001",
				$wpdb->esc_like( self::PREFIX . 'family_' ) . '%',
				$wpdb->esc_like( self::PREFIX . 'consent_' ) . '%',
				$wpdb->esc_like( self::PREFIX . 'code_' ) . '%',
				'%' . $wpdb->esc_like( '_lock' )
			)
		);
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error || count( $rows ) > 5000 ) {
			return null;
		}

		$live = array();
		foreach ( $rows as $row ) {
			$record = automator_safe_unserialize( (string) $row->option_value );
			if ( ! is_array( $record ) || ! isset( $record['client_id'], $record['expires_at'] )
				|| ! is_string( $record['client_id'] ) || ! is_numeric( $record['expires_at'] ) ) {
				return null;
			}
			if ( ! isset( $requested[ $record['client_id'] ] ) || (int) $record['expires_at'] <= time() ) {
				continue;
			}
			$is_family = 0 === strpos( (string) $row->option_name, self::PREFIX . 'family_' );
			if ( $is_family && ! empty( $record['revoked_at'] ) ) {
				continue;
			}
			$live[] = array(
				'kind'        => $is_family ? 'family' : 'pending',
				'record'      => $record,
				'option_name' => (string) $row->option_name,
			);
		}
		return $live;
	}

	/**
	 * Whether a refresh family the user could still use is stored on this site.
	 *
	 * Reads the database without taking a lock. A family row that cannot be
	 * attributed to a user leaves the answer unproven.
	 *
	 * @since 8.0.0
	 *
	 * @return bool|null Null when the read failed or a family row is unreadable.
	 */
	public function has_live_families_for_user( int $user_id ): ?bool {
		return $this->has_live_rows_for_user( 'family', $user_id );
	}

	/**
	 * Whether an authorization code the user could still exchange is stored on
	 * this site. It becomes a token without the user doing anything more, so a
	 * change that ends their authority has to end it too.
	 *
	 * Reads the database without taking a lock. A code row that cannot be
	 * attributed to a user leaves the answer unproven.
	 *
	 * @since 8.0.0
	 *
	 * @return bool|null Null when the read failed or a code row is unreadable.
	 */
	public function has_pending_codes_for_user( int $user_id ): ?bool {
		return $this->has_live_rows_for_user( 'code', $user_id );
	}

	/**
	 * Whether a row of this kind that is not revoked or expired belongs to the user.
	 *
	 * @param string $kind    Artifact kind.
	 * @param int    $user_id User.
	 * @return bool|null Null when the read failed or a row is unreadable.
	 */
	private function has_live_rows_for_user( string $kind, int $user_id ): ?bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix scan of the options table, which WordPress has no API for; live OAuth state, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
				$wpdb->esc_like( self::PREFIX . $kind . '_' ) . '%',
				'%' . $wpdb->esc_like( '_lock' )
			)
		);
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			return null;
		}

		$now = time();
		foreach ( $rows as $row ) {
			$record = automator_safe_unserialize( (string) $row->option_value );
			if ( ! is_array( $record ) || ! isset( $record['user_id'] ) || ! is_numeric( $record['user_id'] ) ) {
				return null;
			}
			if ( (int) $record['user_id'] !== $user_id || ! empty( $record['revoked_at'] ) ) {
				continue;
			}
			// Only an expiry in the past proves a row unusable.
			if ( ! is_numeric( $record['expires_at'] ?? null ) || (int) $record['expires_at'] > $now ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mark every live refresh family belonging to a user as revoked.
	 *
	 * Each family is rewritten under its own lock so an in-flight rotation
	 * cannot overwrite the revocation with a stale copy of the row.
	 *
	 * @return int Number of families revoked.
	 */
	public function revoke_families_for_user( int $user_id ): int {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix scan of the options table, which WordPress has no API for; live OAuth state, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
				$wpdb->esc_like( self::PREFIX . 'family_' ) . '%',
				'%' . $wpdb->esc_like( '_lock' )
			)
		);

		$revoked = 0;
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$record = automator_safe_unserialize( (string) $row->option_value );
			if (
				! is_array( $record )
				|| (int) ( $record['user_id'] ?? 0 ) !== $user_id
				|| ! empty( $record['revoked_at'] )
				|| ! isset( $record['family_id'] )
				|| ! is_string( $record['family_id'] )
			) {
				continue;
			}

			$family_id = $record['family_id'];
			$result    = $this->with_lock(
				'family',
				$family_id,
				static function ( OAuth_Artifact_Store $store ) use ( $family_id ): bool {
					$family = $store->get( 'family', $family_id );
					if ( ! is_array( $family ) || ! empty( $family['revoked_at'] ) ) {
						return false;
					}
					$family['revoked_at'] = time();

					return $store->put( 'family', $family_id, $family );
				}
			);
			if ( true === $result ) {
				++$revoked;
			}
		}

		return $revoked;
	}

	/**
	 * Delete a record if it exists.
	 */
	public function delete( string $kind, string $identifier ): bool {
		$option = $this->option_name( $kind, $identifier );

		return null === get_option( $option, null ) || delete_option( $option );
	}

	/**
	 * Remove a bounded batch of expired site-local artifacts.
	 *
	 * WordPress options have no native expiry, so scheduled maintenance must
	 * eventually remove consumed-family history and abandoned browser state.
	 */
	public function cleanup_expired( int $limit = self::DEFAULT_CLEANUP_LIMIT ): int {
		global $wpdb;
		$this->cleanup_confirmed = false;

		// The scan walks the id order in pages across runs: a fixed
		// ORDER BY option_id LIMIT window would park forever on the oldest
		// unexpired rows and never reach expired rows behind them.
		wp_cache_delete( self::CLEANUP_CURSOR_OPTION, 'options' );
		wp_cache_delete( self::CLEANUP_RETRY_OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$limit  = max( 1, min( self::MAX_CLEANUP_LIMIT, $limit ) );
		$cursor = (int) get_option( self::CLEANUP_CURSOR_OPTION, 0 );
		$retry  = $cursor > 0 && (bool) get_option( self::CLEANUP_RETRY_OPTION, false );
		$like   = $wpdb->esc_like( self::PREFIX ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix scan of the options table, which WordPress has no API for; live OAuth state, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id > %d ORDER BY option_id ASC LIMIT %d",
				$like,
				$cursor,
				$limit
			)
		);
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			$this->cleanup_confirmed = $this->write_cleanup_option( self::CLEANUP_RETRY_OPTION, 1 );
			return 0;
		}

		$removed   = 0;
		$processed = 0;
		$deadline  = microtime( true ) + 1.0;
		foreach ( $rows as $row ) {
			if ( 0 < $processed && microtime( true ) >= $deadline ) {
				break;
			}
			$cursor = (int) $row->option_id;
			++$processed;
			$name         = (string) $row->option_name;
			$stored_value = (string) $row->option_value;
			// Read the value from the bounded page itself. Calling get_option() for
			// every row would turn the larger safety margin into thousands of
			// individual database/cache lookups during WP-Cron.
			$record = automator_safe_unserialize( $stored_value );
			if (
				is_array( $record )
				&& isset( $record['expires_at'] )
				&& (int) $record['expires_at'] < time()
			) {
				if ( $this->delete_if_unchanged( $name, $stored_value, (string) $row->autoload ) ) {
					++$removed;
				} else {
					$retry = true;
				}
			}
		}

		// A short page means the walk reached the end; start over next run.
		$finished    = count( $rows ) === $processed && count( $rows ) < $limit;
		$next_cursor = $finished ? 0 : $cursor;
		if ( $retry && ! $this->write_cleanup_option( self::CLEANUP_RETRY_OPTION, 1 ) ) {
			return $removed;
		}
		if ( ! $this->write_cleanup_option( self::CLEANUP_CURSOR_OPTION, $next_cursor ) ) {
			$this->cleanup_confirmed = $this->write_cleanup_option( self::CLEANUP_RETRY_OPTION, 1 );
			return $removed;
		}
		$this->cleanup_confirmed = $retry || $this->write_cleanup_option( self::CLEANUP_RETRY_OPTION, 0 );

		return $removed;
	}

	/**
	 * Report whether the last cleanup page or its retry was durably recorded.
	 *
	 * @since 8.0.0
	 */
	public function cleanup_was_confirmed(): bool {
		return $this->cleanup_confirmed;
	}

	/**
	 * Distinguish an unchanged option from a failed write.
	 *
	 * @since 8.0.0
	 * @param string $name Site-local option name.
	 * @param mixed $value Expected option value.
	 */
	private function write_cleanup_option( string $name, $value ): bool {
		if ( update_option( $name, $value, false ) ) {
			return true;
		}
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return (string) maybe_serialize( get_option( $name, null ) ) === (string) maybe_serialize( $value );
	}

	/**
	 * Delete only the exact option value inspected by the cleanup page.
	 *
	 * An artifact or lock can be replaced after the SELECT and before cleanup
	 * reaches its row. Matching the serialized value keeps a newly written live
	 * record intact instead of deleting it based on a stale expiry decision.
	 */
	private function delete_if_unchanged( string $name, string $stored_value, string $autoload ): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = %s AND BINARY autoload = %s",
			$name,
			$stored_value,
			$autoload
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Cleanup requires a value-conditional delete that the Options API cannot express.
		$deleted = 1 === (int) $wpdb->query( $query );
		if ( $deleted ) {
			wp_cache_delete( $name, 'options' );
			// WordPress 6.6 added more autoload values. Older versions use 'yes'.
			$autoloaded_values = function_exists( 'wp_autoload_values_to_autoload' )
				? wp_autoload_values_to_autoload()
				: array( 'yes' );
			if ( in_array( $autoload, $autoloaded_values, true ) ) {
				wp_cache_delete( 'alloptions', 'options' );
			}
			wp_cache_delete( 'notoptions', 'options' );
		}

		return $deleted;
	}

	/**
	 * Build a bounded option name from a kind and secret identifier.
	 */
	private function option_name( string $kind, string $identifier ): string {
		$kind = preg_replace( '/[^a-z0-9_]/', '', strtolower( $kind ) );
		if ( ! is_string( $kind ) || '' === $kind ) {
			throw new \InvalidArgumentException( 'Invalid OAuth artifact kind.' );
		}

		return self::PREFIX . $kind . '_' . hash( 'sha256', $identifier );
	}
}
