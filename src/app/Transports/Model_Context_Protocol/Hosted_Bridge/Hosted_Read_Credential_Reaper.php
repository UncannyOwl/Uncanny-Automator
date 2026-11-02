<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Atomic_Option_Lock;

/**
 * Ends a stored read credential once its hosted sign-in no longer stands.
 *
 * A confirmed ended credential is retained until the hosted service confirms
 * it has been revoked. The hosted service also confirms the sign-in at least
 * every five minutes and stops issuing tokens when WordPress refuses.
 *
 * WordPress revokes sign-ins on several paths (the admin screen, the OAuth
 * revocation endpoint, refresh-token reuse, password and role changes, user
 * deletion). They all write a revoked family, a user revocation marker or the
 * revoked-client list, so the reaper watches those writes and sweeps once at
 * shutdown. Anything that ends a sign-in without such a write, such as a
 * capability removed from a role, is caught the next time credentials are used.
 */
final class Hosted_Read_Credential_Reaper {

	private const FAMILY_PREFIX = 'automator_mcp_oauth_v1_family_';

	private const MARKER_PREFIX = 'automator_mcp_oauth_v1_user_revocation_';

	private const REVOKED_CLIENTS = 'automator_mcp_oauth_revoked_clients_v1';

	private const CURSOR = 'automator_mcp_hosted_reap_cursor_v1';

	private const LOCK = 'automator_mcp_hosted_reap_cursor_lock_v1';

	private const SIGNAL = 'automator_mcp_hosted_reap_signal_v1';

	private const ACKNOWLEDGED = 'automator_mcp_hosted_reap_acknowledged_v1';

	private const IDLE_CHECKED = 'automator_mcp_hosted_reap_idle_checked_v1';

	private const IDLE_CHECK_INTERVAL = 300;

	private const RETRY_BACKOFF = 60;

	private const RETRY_PREFIX = 'automator_mcp_hosted_reap_retry_';

	private static ?self $instance = null;

	private Hosted_Read_Credential_Store $store;

	private Hosted_Site_Client $client;

	private Hosted_Sign_In_Liveness $liveness;

	private Atomic_Option_Lock $lock;

	private bool $has_unconfirmed_credentials = false;

	private bool $scheduled = false;

	private bool $attempted = false;

	/**
	 * Share the request's cleanup budget between dashboard reads and shutdown.
	 *
	 * @since 8.0.0
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Constructor.
	 */
	public function __construct(
		?Hosted_Read_Credential_Store $store = null,
		?Hosted_Site_Client $client = null,
		?Hosted_Sign_In_Liveness $liveness = null,
		?Atomic_Option_Lock $lock = null
	) {
		$this->store    = $store ?? new Hosted_Read_Credential_Store();
		$this->client   = $client ?? new Hosted_Site_Client();
		$this->liveness = $liveness ?? new Hosted_Sign_In_Liveness();
		$this->lock     = $lock ?? new Atomic_Option_Lock();
	}

	/**
	 * Watch the writes that end a sign-in.
	 */
	public function register_hooks(): void {
		add_action( 'added_option', array( $this, 'on_option_added' ), 10, 1 );
		add_action( 'updated_option', array( $this, 'on_option_updated' ), 10, 3 );
		add_action( 'deleted_option', array( $this, 'on_option_deleted' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'continue_reaping' ) );
	}

	/**
	 * An admin visit continues pending work even when WP-Cron is disabled.
	 *
	 * @since 8.0.0
	 */
	public function continue_reaping(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$signal = automator_get_option( self::SIGNAL, '', true );
		if ( is_string( $signal ) && '' !== $signal && automator_get_option( self::ACKNOWLEDGED, '', true ) !== $signal ) {
			$this->schedule( false );
			return;
		}
		$this->schedule_idle_check();
	}

	/**
	 * Periodically recover a missed signal, including a failed option write.
	 *
	 * @since 8.0.0
	 */
	private function schedule_idle_check(): void {
		$now  = time();
		$last = (int) automator_get_option( self::IDLE_CHECKED, 0, true );
		if ( $last > 0 && $last <= $now && $now - $last < self::IDLE_CHECK_INTERVAL ) {
			return;
		}
		$owner = $this->lock->acquire( self::LOCK, 15, 0 );
		if ( false === $owner ) {
			return;
		}
		try {
			$last = (int) automator_get_option( self::IDLE_CHECKED, 0, true );
			if ( $last > 0 && $last <= $now && $now - $last < self::IDLE_CHECK_INTERVAL ) {
				return;
			}
			if ( automator_update_option( self::IDLE_CHECKED, $now, false ) && $this->store->has_retained_entries() ) {
				$this->schedule( false );
			}
		} finally {
			$this->lock->release( self::LOCK, $owner );
		}
	}

	/**
	 * A new marker or revoked-client list.
	 *
	 * @param string $option Option name.
	 */
	public function on_option_added( $option ): void {
		if ( $this->is_revocation_record( (string) $option ) ) {
			$this->schedule();
		}
	}

	/**
	 * A changed marker or revoked-client list, or a family that was just revoked.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 */
	public function on_option_updated( $option, $old_value, $value ): void {
		$option = (string) $option;
		if (
			$this->is_revocation_record( $option )
			|| ( 0 === strpos( $option, self::FAMILY_PREFIX ) && ! empty( $value['revoked_at'] ) && empty( $old_value['revoked_at'] ) )
		) {
			$this->schedule();
		}
	}

	/**
	 * A family that was removed.
	 *
	 * @param string $option Option name.
	 */
	public function on_option_deleted( $option ): void {
		if ( 0 === strpos( (string) $option, self::FAMILY_PREFIX ) ) {
			$this->schedule();
		}
	}

	/**
	 * Sweep after the request's revocations are written.
	 */
	public function reap(): void {
		$this->scheduled = false;
		$this->live_credentials();
	}

	/**
	 * The stored credentials whose sign-in stands. A confirmed ended sign-in is
	 * removed only after the hosted service confirms revocation.
	 *
	 * @return list<array{user_id:int,family_id:string,client_id:string,version:int,secret:string}>
	 */
	public function live_credentials(): array {
		$observed                          = automator_get_option( self::SIGNAL, '', true );
		$credentials                       = $this->store->readable();
		$live                              = array();
		$ended                             = array();
		$this->has_unconfirmed_credentials = false;
		foreach ( $credentials as $credential ) {
			$status = $this->liveness->status( $credential['user_id'], $credential['family_id'] );
			if ( true === $status ) {
				$live[] = $credential;
				continue;
			}
			if ( null === $status ) {
				$this->has_unconfirmed_credentials = true;
				continue;
			}
			$ended[ hash( 'sha256', $credential['user_id'] . "\0" . $credential['family_id'] ) ] = $credential;
		}

		if ( array() !== $ended ) {
			// Also records pending work if the request's attempt was already spent.
			$this->mark_pending();
			if ( ! $this->attempted ) {
				$this->revoke_one( $ended );
			}
		} elseif ( ! $this->has_unconfirmed_credentials ) {
			$this->acknowledge_empty_scan( $observed, $credentials );
		}

		return $live;
	}

	/**
	 * Attempt one credential, advancing before the call so a failure cannot
	 * starve the rest. Only a hash of the local identity is persisted.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,array<string,mixed>> $ended Confirmed ended credentials.
	 */
	private function revoke_one( array $ended ): void {
		$owner = $this->lock->acquire( self::LOCK, 15, 0 );
		if ( false === $owner ) {
			return;
		}

		try {
			ksort( $ended, SORT_STRING );
			$cursor = automator_get_option( self::CURSOR, '', true );
			$cursor = is_string( $cursor ) ? $cursor : '';
			$keys   = array_keys( $ended );
			$order  = array();
			foreach ( $keys as $key ) {
				if ( strcmp( $key, $cursor ) > 0 ) {
					$order[] = $key;
				}
			}
			foreach ( $keys as $key ) {
				if ( strcmp( $key, $cursor ) <= 0 ) {
					$order[] = $key;
				}
			}
			$next = null;
			$now  = time();
			foreach ( $order as $key ) {
				$retry_at = get_transient( self::RETRY_PREFIX . $key );
				if ( is_numeric( $retry_at ) && (int) $retry_at > $now && (int) $retry_at <= $now + self::RETRY_BACKOFF ) {
					continue;
				}
				$next = $key;
				break;
			}
			if ( null === $next ) {
				return;
			}

			$credential = $ended[ $next ];
			$status     = $this->liveness->status( $credential['user_id'], $credential['family_id'] );
			if ( false !== $status ) {
				$this->has_unconfirmed_credentials = $this->has_unconfirmed_credentials || null === $status;
				return;
			}
			if ( ! automator_update_option( self::CURSOR, $next, false ) ) {
				return;
			}

			$this->attempted = true;
			if ( $this->client->revoke( $credential ) && $this->store->delete_if_matches( $credential ) ) {
				delete_transient( self::RETRY_PREFIX . $next );
			} else {
				set_transient( self::RETRY_PREFIX . $next, time() + self::RETRY_BACKOFF, self::RETRY_BACKOFF );
			}
		} finally {
			$this->lock->release( self::LOCK, $owner );
		}
	}

	/**
	 * Acknowledge only the signal seen before this scan. A concurrent revocation
	 * publishes a different signal and therefore remains pending.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $observed Signal read before scanning.
	 * @param array $credentials Credentials read during the scan.
	 */
	private function acknowledge_empty_scan( $observed, array $credentials ): void {
		if (
			! is_string( $observed ) || '' === $observed
			|| automator_get_option( self::ACKNOWLEDGED, '', true ) === $observed
			|| ! $this->store->scan_is_confirmed( $credentials )
		) {
			return;
		}
		automator_update_option( self::ACKNOWLEDGED, $observed, false );
	}

	/**
	 * Publish a new signal without storing credential identifiers or secrets.
	 *
	 * @since 8.0.0
	 */
	private function mark_pending(): void {
		automator_update_option( self::SIGNAL, wp_generate_uuid4(), false );
	}

	/**
	 * Retire a credential after the hosted token endpoint confirms invalid_client.
	 *
	 * @since 8.0.0
	 * @param array $credential The exact refused credential.
	 */
	public function forget_invalid_credential( array $credential ): bool {
		return $this->store->delete_if_matches( $credential );
	}

	/**
	 * Whether the most recent credential scan could not verify a sign-in.
	 *
	 * @since 8.0.0
	 */
	public function has_unconfirmed_credentials(): bool {
		return $this->has_unconfirmed_credentials;
	}

	/**
	 * The live credentials in the order their admins connected, first first.
	 *
	 * @return list<array{user_id:int,family_id:string,client_id:string,version:int,secret:string}>
	 */
	public function live_credentials_in_connection_order(): array {
		$live = $this->live_credentials();
		usort(
			$live,
			fn( array $a, array $b ): int => array( $this->liveness->connected_at( $a['family_id'] ), $a['user_id'] )
				<=> array( $this->liveness->connected_at( $b['family_id'] ), $b['user_id'] )
		);

		return $live;
	}

	/**
	 * Whether the option records a user revocation or a revoked client.
	 */
	private function is_revocation_record( string $option ): bool {
		return self::REVOKED_CLIENTS === $option || 0 === strpos( $option, self::MARKER_PREFIX );
	}

	/**
	 * Run one sweep at shutdown.
	 */
	private function schedule( bool $mark_pending = true ): void {
		if ( $mark_pending ) {
			$this->mark_pending();
		}
		if ( ! $this->scheduled ) {
			$this->scheduled = true;
			add_action( 'shutdown', array( $this, 'reap' ) );
		}
	}
}
