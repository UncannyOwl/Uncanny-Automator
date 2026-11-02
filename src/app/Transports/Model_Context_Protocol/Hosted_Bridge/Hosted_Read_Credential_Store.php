<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge;

use Uncanny_Automator\App\Transient\Application\Hosted_Read_Cache;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Hosted_Read_Credential_Option_Store;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Atomic_Option_Lock;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Internal_Token_Configuration;

/**
 * The read credentials the hosted Uncanny Automator service delivered to this site.
 *
 * One entry per hosted sign-in, keyed by the WordPress user and refresh family
 * whose token delivered it, never by an identifier the caller supplies. The
 * secret is encrypted with strong wp-config.php key material, and the
 * encryption binds it to this blog, this site address, its family and its
 * version, so a copy moved anywhere else reads as no credential.
 */
final class Hosted_Read_Credential_Store {

	public const SAVED = 'saved';

	public const UNCHANGED = 'unchanged';

	public const CONFLICT = 'conflict';

	public const BUSY = 'busy';

	public const UNAVAILABLE = 'unavailable';

	public const REVOKED = 'revoked';

	private const OPTION = 'automator_mcp_hosted_read_credentials_v1';

	/** A little longer than the hosted 30-day lifetime, after which the hosted service refuses it anyway. */
	private const RETENTION = 31 * DAY_IN_SECONDS;

	private Hosted_Secret_Box $box;

	private Atomic_Option_Lock $lock;

	private Hosted_Read_Cache $cache;

	private Hosted_Read_Credential_Option_Store $option_store;

	/**
	 * Constructor.
	 */
	public function __construct( ?Internal_Token_Configuration $keys = null, ?Atomic_Option_Lock $lock = null, ?Hosted_Read_Cache $cache = null, ?Hosted_Read_Credential_Option_Store $option_store = null ) {
		$this->box          = new Hosted_Secret_Box( 'uncanny-automator:mcp:hosted-read-credential:v1', $keys );
		$this->lock         = $lock ?? new Atomic_Option_Lock();
		$this->cache        = $cache ?? new Hosted_Read_Cache();
		$this->option_store = $option_store ?? Database::get_hosted_read_credential_option_store();
	}

	/**
	 * Whether strong wp-config.php key material is available to protect a secret.
	 */
	public function is_available(): bool {
		return $this->box->is_available();
	}

	/**
	 * Save one delivery. Versions only move forward; repeating the stored
	 * version with the same secret is a completed delivery, not an error.
	 *
	 * @param callable|null $still_live Checked again under the lock, so a sign-in
	 *                                  revoked while the delivery waited stores nothing.
	 *
	 * @return array{status:string,stored_version:int}
	 */
	public function save( int $user_id, string $family_id, string $client_id, int $version, string $secret, ?callable $still_live = null ): array {
		if ( ! $this->box->is_available() ) {
			return $this->result( self::UNAVAILABLE, 0 );
		}

		$owner = $this->lock->acquire( self::OPTION . '_lock', 15, 2 );
		if ( false === $owner ) {
			return $this->result( self::BUSY, 0 );
		}

		try {
			if ( null !== $still_live && ! $still_live() ) {
				return $this->result( self::REVOKED, 0 );
			}

			$entries  = $this->read_entries();
			$entry_id = $this->entry_id( $user_id, $family_id );
			$existing = $entries[ $entry_id ] ?? null;
			$hash     = hash( 'sha256', $secret );

			if ( is_array( $existing ) ) {
				$stored_version = (int) $existing['version'];
				if ( $version < $stored_version || ( $version === $stored_version && ! hash_equals( (string) $existing['secret_hash'], $hash ) ) ) {
					return $this->result( self::CONFLICT, $stored_version );
				}
				if ( $version === $stored_version && null !== $this->decrypt( $existing ) ) {
					// A repeated delivery is a reconnect: the Dashboard must show it at once.
					$this->cache->clear();
					return $this->result( self::UNCHANGED, $stored_version );
				}
			}

			$envelope = $this->box->seal( $secret, array( $user_id, $family_id, $client_id, $version ) );
			if ( null === $envelope ) {
				return $this->result( self::UNAVAILABLE, 0 );
			}

			$entries[ $entry_id ] = array(
				'user_id'     => $user_id,
				'family_id'   => $family_id,
				'client_id'   => $client_id,
				'version'     => $version,
				'secret_hash' => $hash,
				'envelope'    => $envelope,
				'expires_at'  => time() + self::RETENTION,
			);
			if ( ! $this->write_entries( $entries ) ) {
				return $this->result( self::BUSY, 0 );
			}

			// A new connection shows on the Dashboard at once, not after the cached reads expire.
			$this->cache->clear();

			return $this->result( self::SAVED, $version );
		} finally {
			$this->lock->release( self::OPTION . '_lock', $owner );
		}
	}

	/**
	 * The version stored for one sign-in, or 0 when none can be read here.
	 */
	public function stored_version( int $user_id, string $family_id ): int {
		$entry = $this->read_entries()[ $this->entry_id( $user_id, $family_id ) ] ?? null;

		return is_array( $entry ) && null !== $this->decrypt( $entry ) ? (int) $entry['version'] : 0;
	}

	/**
	 * Remove one sign-in's credential.
	 *
	 * @return bool|null Whether a credential was removed, or null when the store was busy.
	 */
	public function delete( int $user_id, string $family_id ): ?bool {
		$owner = $this->lock->acquire( self::OPTION . '_lock', 15, 2 );
		if ( false === $owner ) {
			return null;
		}

		try {
			$entries  = $this->read_entries();
			$entry_id = $this->entry_id( $user_id, $family_id );
			if ( ! isset( $entries[ $entry_id ] ) ) {
				return false;
			}
			unset( $entries[ $entry_id ] );
			if ( ! $this->write_entries( $entries ) ) {
				return false;
			}

			$this->cache->clear();

			return true;
		} finally {
			$this->lock->release( self::OPTION . '_lock', $owner );
		}
	}

	/**
	 * Remove only the credential the hosted service confirmed is no longer usable.
	 * A delivery during the HTTP request must not lose its newer version.
	 *
	 * @since 8.0.0
	 * @param array $credential Credential sent to the hosted service.
	 * @return bool Whether that exact credential is confirmed absent.
	 */
	public function delete_if_matches( array $credential ): bool {
		$owner = $this->lock->acquire( self::OPTION . '_lock', 15, 2 );
		if ( false === $owner ) {
			return false;
		}
		try {
			$entries = $this->option_store->read_confirmed();
			if ( null === $entries ) {
				return false;
			}
			$id = $this->entry_id( $credential['user_id'], $credential['family_id'] );
			if ( ! array_key_exists( $id, $entries ) ) {
				return true;
			}
			$entry = $entries[ $id ];
			if ( ! is_array( $entry ) || ( $entry['client_id'] ?? null ) !== $credential['client_id']
				|| ( $entry['version'] ?? null ) !== $credential['version']
				|| ( $entry['secret_hash'] ?? null ) !== hash( 'sha256', $credential['secret'] ) ) {
				return false;
			}
			unset( $entries[ $id ] );
			if ( ! $this->write_entries( $entries ) ) {
				return false;
			}
			$this->cache->clear();
			return true;
		} finally {
			$this->lock->release( self::OPTION . '_lock', $owner );
		}
	}

	/**
	 * Every credential that can be read here, for the outbound client.
	 *
	 * @return list<array{user_id:int,family_id:string,client_id:string,version:int,secret:string}>
	 */
	public function readable(): array {
		$credentials = array();
		foreach ( $this->read_entries() as $entry ) {
			$credential = $this->credential( $entry );
			if ( null !== $credential ) {
				$credentials[] = $credential;
			}
		}

		return $credentials;
	}

	/**
	 * Whether any retained credential is available to inspect later.
	 *
	 * @since 8.0.0
	 */
	public function has_retained_entries(): bool {
		return array() !== $this->read_entries();
	}

	/**
	 * Confirm that a scan covered every retained, readable credential in the
	 * current database value. A failed, changed or malformed read is unproven.
	 *
	 * @since 8.0.0
	 *
	 * @param list<array{user_id:int,family_id:string,client_id:string,version:int,secret:string}> $credentials Previously scanned credentials.
	 */
	public function scan_is_confirmed( array $credentials ): bool {
		$stored = $this->option_store->read_confirmed();
		if ( null === $stored ) {
			return false;
		}
		$fresh = array();
		foreach ( $stored as $entry ) {
			if ( ! is_array( $entry ) || ! is_numeric( $entry['expires_at'] ?? null ) ) {
				return false;
			}
			if ( (int) $entry['expires_at'] <= time() ) {
				continue;
			}
			if (
				! is_int( $entry['user_id'] ?? null )
				|| ! is_string( $entry['family_id'] ?? null ) || ! is_string( $entry['client_id'] ?? null )
				|| ! is_int( $entry['version'] ?? null ) || ! is_string( $entry['secret_hash'] ?? null )
				|| ! is_string( $entry['envelope'] ?? null )
			) {
				return false;
			}
			$credential = $this->credential( $entry );
			if ( null === $credential ) {
				return false;
			}
			$fresh[] = $credential;
		}

		return $fresh === $credentials;
	}

	/**
	 * Open the credential bound to one retained entry.
	 *
	 * @since 8.0.0
	 *
	 * @return array{user_id:int,family_id:string,client_id:string,version:int,secret:string}|null
	 */
	private function credential( array $entry ): ?array {
		$secret = $this->decrypt( $entry );
		if ( null === $secret ) {
			return null;
		}

		return array(
			'user_id'   => (int) $entry['user_id'],
			'family_id' => (string) $entry['family_id'],
			'client_id' => (string) $entry['client_id'],
			'version'   => (int) $entry['version'],
			'secret'    => $secret,
		);
	}

	/**
	 * Read entries that are still within retention and well formed.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function read_entries(): array {
		wp_cache_delete( self::OPTION, 'options' );
		// A failed read can leave a cached absence; allow a later retry to see the option.
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ self::OPTION ] ) ) {
			unset( $notoptions[ self::OPTION ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
		$stored  = get_option( self::OPTION, array() );
		$entries = array();
		foreach ( is_array( $stored ) ? $stored : array() as $entry_id => $entry ) {
			if (
				is_array( $entry )
				&& is_int( $entry['user_id'] ?? null )
				&& is_string( $entry['family_id'] ?? null )
				&& is_string( $entry['client_id'] ?? null )
				&& is_int( $entry['version'] ?? null )
				&& is_string( $entry['secret_hash'] ?? null )
				&& is_string( $entry['envelope'] ?? null )
				&& (int) ( $entry['expires_at'] ?? 0 ) > time()
			) {
				$entries[ (string) $entry_id ] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Write every entry, creating the option without autoload when it is absent.
	 */
	private function write_entries( array $entries ): bool {
		if ( null === get_option( self::OPTION, null ) ) {
			return add_option( self::OPTION, $entries, '', false );
		}

		return update_option( self::OPTION, $entries, false ) || get_option( self::OPTION ) === $entries;
	}

	/**
	 * The storage key for one sign-in.
	 */
	private function entry_id( int $user_id, string $family_id ): string {
		return hash( 'sha256', $user_id . "\0" . $family_id );
	}

	/**
	 * Decrypt an entry, or null when it was moved, edited or has no key here.
	 */
	private function decrypt( array $entry ): ?string {
		$secret = $this->box->open( (string) $entry['envelope'], array( (int) $entry['user_id'], (string) $entry['family_id'], (string) $entry['client_id'], (int) $entry['version'] ) );

		return null !== $secret && hash_equals( (string) $entry['secret_hash'], hash( 'sha256', $secret ) ) ? $secret : null;
	}

	/**
	 * @return array{status:string,stored_version:int}
	 */
	private function result( string $status, int $stored_version ): array {
		return array(
			'status'         => $status,
			'stored_version' => $stored_version,
		);
	}
}
