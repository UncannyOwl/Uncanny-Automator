<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;

/**
 * Remove registrations nothing can use any more, without interrupting active approval.
 *
 * @since 8.0.0
 */
final class OAuth_Pending_Registration_Cleanup {

	public const APPROVAL_WINDOW       = 1800;
	public const HOOK                  = 'automator_mcp_pending_registration_cleanup';
	private const RETRY_DELAY          = 600;
	private const FOLLOWUP_DELAY       = 60;
	private const MAX_PER_RUN          = 5;
	private const CURSOR_OPTION        = 'automator_mcp_pending_registration_cursor';
	private const LEGACY_CURSOR_OPTION = 'automator_mcp_legacy_registration_cursor';
	private const PENDING              = 'pending';
	private const UNMARKED             = 'unmarked';
	private const ENDED                = 'ended';

	private OAuth_Client_Registry $clients;
	private OAuth_Artifact_Store $artifacts;
	private Token_Manager $tokens;

	/**
	 * @since 8.0.0
	 */
	public function __construct( ?OAuth_Client_Registry $clients = null, ?OAuth_Artifact_Store $artifacts = null, ?Token_Manager $tokens = null ) {
		$this->clients   = $clients ?? new OAuth_Client_Registry();
		$this->artifacts = $artifacts ?? new OAuth_Artifact_Store();
		$this->tokens    = $tokens ?? new Token_Manager();
	}

	/**
	 * Run an opportunistic or scheduled cleanup and arrange the next check.
	 *
	 * Removes, once nothing live uses them: an expired pending attempt, an
	 * attempt from before the pending marker that is older than the approval
	 * window, and an Uncanny Automator registration whose grants have all
	 * ended. Uncanny Automator registers again for every approval, so it never
	 * uses an ended registration of its own again.
	 *
	 * @since 8.0.0
	 * @param int $limit Maximum registrations to check in this pass.
	 * @param bool $include_approved Whether to inspect approved hosted grants; public registration skips them.
	 * @return int|null Removed registrations, or null if the registry is unavailable.
	 */
	public function run( int $limit = self::MAX_PER_RUN, bool $include_approved = true ): ?int {
		try {
			$limit   = max( 1, min( self::MAX_PER_RUN, $limit ) );
			$clients = $this->clients->confirmed_clients();
			if ( null === $clients ) {
				$this->schedule( time() + self::RETRY_DELAY );
				return null;
			}
			$now    = time();
			$next   = null;
			$due    = array();
			$hosted = array();
			foreach ( $clients as $id => $client ) {
				if ( $this->is_pending( $id, $client ) ) {
					$expiry = min( (int) $client['pending_expires_at'], (int) $client['created_at'] + self::APPROVAL_WINDOW );
					if ( $expiry > $now ) {
						$next = null === $next ? $expiry : min( $next, $expiry );
						continue;
					}
					$due[ $id ] = self::PENDING;
				} elseif ( $this->is_unmarked_attempt( $id, $client, $now ) ) {
					$due[ $id ] = self::UNMARKED;
				} elseif ( $include_approved && $this->is_hosted_approval( $id, $client ) ) {
					$hosted[] = $id;
				}
			}
			foreach ( $this->without_grants( $hosted ) as $id ) {
				$due[ $id ] = self::ENDED;
			}
			ksort( $due, SORT_STRING );
			$cursor    = (string) get_option( self::CURSOR_OPTION, '' );
			$due       = array_filter(
				$due,
				static function ( $id ) use ( $cursor ) {
					return strcmp( (string) $id, $cursor ) > 0; },
				ARRAY_FILTER_USE_KEY
			) + $due;
			$removed   = 0;
			$attempted = 0;
			$last_id   = '';
			foreach ( $due as $id => $kind ) {
				if ( $attempted >= $limit ) {
					$followup = $now + self::FOLLOWUP_DELAY;
					$next     = null === $next ? $followup : min( $next, $followup );
					break;
				}
				++$attempted;
				$last_id = (string) $id;
				$result  = self::ENDED === $kind ? $this->retire_if_unused( $last_id ) : $this->remove_if_unused( $last_id, self::PENDING === $kind, $now );
				if ( true === $result ) {
					++$removed;
					continue;
				}
				// A pending attempt in use waits for its approval to lapse; a failed check is retried.
				if ( null === $result || self::PENDING === $kind ) {
					$retry = $now + self::RETRY_DELAY;
					$next  = null === $next ? $retry : min( $next, $retry );
				}
			}
			if ( '' !== $last_id ) {
				update_option( self::CURSOR_OPTION, $last_id, false );
			}
			if ( null !== $next ) {
				$this->schedule( $next );
			}
			return $removed;
		} catch ( \Throwable $error ) {
			// A transient storage failure must not break a new connection.
			$this->schedule( time() + self::RETRY_DELAY );
			return null;
		}
	}

	/**
	 * Preserve the preexisting cap-only cleanup for older unmarked clients.
	 *
	 * @since 8.0.0
	 * @return int Number removed.
	 */
	public function prune_legacy_at_cap( int $cap ): int {
		$clients = $this->clients->confirmed_clients();
		if ( null === $clients ) {
			return 0;
		}
		$active = 0;
		$legacy = array();
		foreach ( $clients as $id => $client ) {
			if ( ! is_string( $id ) || ! is_array( $client ) || 'dcr' !== ( $client['source'] ?? '' ) || empty( $client['active'] ) ) {
				continue;
			}
			++$active;
			if ( empty( $client['approved_at'] ) && ! array_key_exists( 'pending_expires_at', $client ) ) {
				$legacy[ $id ] = (int) ( $client['created_at'] ?? 0 );
			}
		}
		$excess = $active - max( 1, $cap ) + 1;
		asort( $legacy, SORT_NUMERIC );
		$ids          = array_keys( $legacy );
		$cursor_index = array_search( (string) get_option( self::LEGACY_CURSOR_OPTION, '' ), $ids, true );
		if ( false !== $cursor_index ) {
			$ids = array_merge( array_slice( $ids, $cursor_index + 1 ), array_slice( $ids, 0, $cursor_index + 1 ) );
		}
		$removed   = 0;
		$attempted = 0;
		$last_id   = '';
		foreach ( $ids as $id ) {
			if ( $removed >= $excess || $attempted >= self::MAX_PER_RUN ) {
				break;
			}
			++$attempted;
			$last_id = $id;
			if ( true === $this->remove_if_unused( $id, false, time() ) ) {
				++$removed;
			}
		}
		if ( '' !== $last_id ) {
			update_option( self::LEGACY_CURSOR_OPTION, $last_id, false );
		}
		return $removed;
	}

	/**
	 * Arrange a single follow-up without postponing an earlier scheduled check.
	 *
	 * @since 8.0.0
	 */
	public function schedule( int $timestamp ): void {
		try {
			$scheduled = wp_next_scheduled( self::HOOK );
			if ( false !== $scheduled && $scheduled <= $timestamp ) {
				return;
			}
			if ( false !== $scheduled ) {
				wp_unschedule_event( $scheduled, self::HOOK );
			}
			wp_schedule_single_event( max( time() + 1, $timestamp ), self::HOOK );
		} catch ( \Throwable $error ) {
			// A broken scheduler must not make registration fail; later visits retry.
			unset( $error );
		}
	}

	/**
	 * Check the marker that distinguishes known pending from legacy records.
	 *
	 * @since 8.0.0
	 * @param mixed $id Registry key.
	 * @param mixed $client Registry record.
	 */
	private function is_pending( $id, $client ): bool {
		return $this->is_active_dynamic( $id, $client )
			&& empty( $client['approved_at'] ) && isset( $client['pending_expires_at'] )
			&& is_numeric( $client['pending_expires_at'] ) && (int) $client['pending_expires_at'] > 0
			&& isset( $client['created_at'] ) && is_numeric( $client['created_at'] ) && (int) $client['created_at'] > 0;
	}

	/**
	 * An attempt from before the pending marker existed, older than the approval window.
	 *
	 * @since 8.0.0
	 * @param mixed $id Registry key.
	 * @param mixed $client Registry record.
	 */
	private function is_unmarked_attempt( $id, $client, int $now ): bool {
		return $this->is_active_dynamic( $id, $client )
			&& empty( $client['approved_at'] ) && ! array_key_exists( 'pending_expires_at', $client )
			&& isset( $client['created_at'] ) && is_numeric( $client['created_at'] ) && (int) $client['created_at'] > 0
			&& (int) $client['created_at'] + self::APPROVAL_WINDOW <= $now;
	}

	/**
	 * An approved registration that only the trusted Uncanny Automator callback can use.
	 *
	 * @since 8.0.0
	 * @param mixed $id Registry key.
	 * @param mixed $client Registry record.
	 */
	private function is_hosted_approval( $id, $client ): bool {
		return $this->is_active_dynamic( $id, $client ) && ! empty( $client['approved_at'] ) && Hosted_Agent_Access::allows_client( $client );
	}

	/**
	 * An active dynamic registration stored under its own client ID.
	 *
	 * @since 8.0.0
	 * @param mixed $id Registry key.
	 * @param mixed $client Registry record.
	 */
	private function is_active_dynamic( $id, $client ): bool {
		return is_string( $id ) && '' !== $id && is_array( $client )
			&& isset( $client['client_id'] ) && is_string( $client['client_id'] ) && hash_equals( $id, $client['client_id'] )
			&& 'dcr' === ( $client['source'] ?? '' ) && ! empty( $client['active'] );
	}

	/**
	 * The registrations no user holds a grant through, from one read of each
	 * grant store. An unproven read selects none.
	 *
	 * @since 8.0.0
	 * @param string[] $ids Registration IDs.
	 * @return string[]
	 */
	private function without_grants( array $ids ): array {
		if ( array() === $ids ) {
			return array();
		}
		$renewal = $this->artifacts->get_active_client_users( $ids );
		$access  = null === $renewal ? null : $this->tokens->get_active_client_users( $ids );
		if ( null === $renewal || null === $access ) {
			return array();
		}

		return array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $renewal, $access ) {
					return array() === ( $renewal[ $id ] ?? null ) && array() === ( $access[ $id ] ?? null );
				}
			)
		);
	}

	/**
	 * Lock the client before checking live consent, codes, grants and tokens.
	 *
	 * @since 8.0.0
	 * @return bool|null True if removed, false if in use or ineligible, null on failure.
	 */
	private function remove_if_unused( string $id, bool $require_expired, int $now ): ?bool {
		return $this->artifacts->with_lock(
			'client',
			$id,
			function ( OAuth_Artifact_Store $store, string $lock_name, string $owner ) use ( $id, $require_expired, $now ): ?bool {
				$in_use = $this->in_use( $store, $id );
				if ( false !== $in_use ) {
					return null === $in_use ? null : false;
				}
				return $this->clients->remove_unapproved_dynamic_client( $id, $require_expired, $now, $lock_name, $owner );
			},
			true,
			0.0
		);
	}

	/**
	 * Lock the client, confirm nothing live uses it, then revoke and delete it
	 * as the token endpoint's retirement does. The revocation marker stays.
	 *
	 * @since 8.0.0
	 * @return bool|null True if retired, false if in use, null on failure.
	 */
	private function retire_if_unused( string $id ): ?bool {
		return $this->artifacts->with_lock(
			'client',
			$id,
			function ( OAuth_Artifact_Store $store ) use ( $id ): ?bool {
				$in_use = $this->in_use( $store, $id );
				if ( false !== $in_use ) {
					return null === $in_use ? null : false;
				}
				return $this->clients->revoke( $id ) && $this->clients->delete( $id ) ? true : null;
			},
			false,
			0.0
		);
	}

	/**
	 * Whether live consent, a code, a grant or an access token uses the client.
	 * Call while holding its client lock.
	 *
	 * @since 8.0.0
	 * @return bool|null Null when the scan could not prove either.
	 */
	private function in_use( OAuth_Artifact_Store $store, string $id ): ?bool {
		$artifacts = $store->has_live_client_artifacts( $id );
		if ( false !== $artifacts ) {
			return $artifacts;
		}

		return $this->tokens->has_live_client_tokens( $id );
	}
}
