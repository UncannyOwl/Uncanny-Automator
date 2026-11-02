<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Atomic_Option_Lock;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;

/**
 * Administrator-managed clients plus HTTPS CIMD resolution.
 */
final class OAuth_Client_Registry {

	private const OPTION_NAME = 'automator_mcp_oauth_clients_v1';

	private const REGISTRY_LOCK = 'automator_mcp_oauth_client_registry_lock';

	private Atomic_Option_Lock $lock;

	private const REVOKED_OPTION = 'automator_mcp_oauth_revoked_clients_v1';

	/** Anonymous registrations get a smaller redirect allowance than administrators. */
	public const DCR_MAX_REDIRECT_URIS = 5;

	public const DCR_MAX_REDIRECT_URI_LENGTH = 512;


	private const LOOPBACK_HOSTS = array( '127.0.0.1', '[::1]', '::1', 'localhost' );

	private OAuth_Cimd_Client_Fetcher $cimd_fetcher;

	/** Why the last resolve() returned null, for the authentication log. */
	private string $last_resolution_failure = '';

	private bool $mutation_busy = false;

	/**
	 * Constructor.
	 */
	public function __construct( ?OAuth_Cimd_Client_Fetcher $cimd_fetcher = null, ?Atomic_Option_Lock $lock = null ) {
		$this->lock         = $lock ?? new Atomic_Option_Lock();
		$this->cimd_fetcher = $cimd_fetcher ?? new OAuth_Cimd_Client_Fetcher();
	}

	/**
	 * Resolve an active pre-registered or CIMD client.
	 */
	public function resolve( string $client_id ): ?array {
		$this->last_resolution_failure = '';
		if ( $this->is_revoked( $client_id ) ) {
			$this->last_resolution_failure = 'revoked';
			return null;
		}

		$clients = $this->get_clients();
		if ( isset( $clients[ $client_id ] ) && is_array( $clients[ $client_id ] ) && ! empty( $clients[ $client_id ]['active'] ) ) {
			if ( 'cimd' !== ( $clients[ $client_id ]['source'] ?? '' ) ) {
				return $clients[ $client_id ];
			}

			// The URL remains the live metadata authority, including its bounded
			// cache and SSRF checks. Once an administrator has approved the client,
			// the snapshot they approved stands in when the live fetch fails, so a
			// blocked outbound request or a slow resolver does not drop a working
			// connection at its next token refresh.
			$live = $this->cimd_fetcher->fetch( $client_id );
			if ( is_array( $live ) ) {
				return $live;
			}
			if ( ! empty( $clients[ $client_id ]['approved_at'] ) ) {
				return $this->public_record( $clients[ $client_id ] );
			}

			$this->last_resolution_failure = 'cimd_fetch_failed:' . $this->cimd_fetcher->get_last_failure();
			return null;
		}

		$live = $this->cimd_fetcher->fetch( $client_id );
		if ( null === $live ) {
			$this->last_resolution_failure = 0 === strpos( $client_id, 'https://' )
				? 'cimd_fetch_failed:' . $this->cimd_fetcher->get_last_failure()
				: 'unknown_client';
		}

		return $live;
	}

	/**
	 * Why the most recent resolve() call returned null.
	 *
	 * Distinguishes a client this site never knew or revoked from a client whose
	 * metadata document could not be fetched, which is a hosting problem.
	 */
	public function get_last_resolution_failure(): string {
		return $this->last_resolution_failure;
	}

	/**
	 * Register one client and return its one-time secret when confidential.
	 *
	 * @param string[] $contacts Registrant contacts (RFC 7591). Only an Uncanny
	 *                           Automator registration keeps one, for display.
	 *
	 * @return array|false
	 */
	public function register( string $name, string $type, array $redirect_uris, string $source = 'pre_registered', array $contacts = array() ) {
		$this->mutation_busy = false;
		$owner               = $this->lock->acquire( self::REGISTRY_LOCK, 30, 0.25 );
		if ( false === $owner ) {
			$this->mutation_busy = true;
			return false;
		}

		try {
			if ( ! in_array( $type, array( 'public', 'confidential' ), true ) ) {
				return false;
			}
			if ( ! in_array( $source, array( 'pre_registered', 'dcr' ), true ) ) {
				return false;
			}
			// Dynamic registrants declare their redirects; only an administrator
			// may defer them for first-use binding.
			if ( 'dcr' === $source && array() === $redirect_uris ) {
				return false;
			}

			$provided      = count( $redirect_uris );
			$redirect_uris = $this->normalize_redirect_uris( $redirect_uris );
			// Invalid entries reject the registration. A public client may register
			// with no redirect at all: the address is then learned, once, from the
			// first sign-in an administrator approves.
			if ( empty( $redirect_uris ) && ( 0 < $provided || 'confidential' === $type ) ) {
				return false;
			}
			if ( 'dcr' === $source && ! self::dcr_redirect_uris_within_limits( $redirect_uris ) ) {
				return false;
			}

			// The identifier names its own authentication model, so an administrator
			// pasting it into an app knows whether a secret exists at all.
			$client_id      = ( 'confidential' === $type ? 'automator_secret_' : 'automator_pkce_' )
				. str_replace( '-', '', wp_generate_uuid4() );
			$secret         = 'confidential' === $type ? wp_generate_password( 48, false, false ) : '';
			$created_at     = time();
			$record         = array(
				'client_id'     => $client_id,
				'name'          => $this->storable_name( $name ),
				'type'          => $type,
				'redirect_uris' => $redirect_uris,
				'source'        => $source,
				'active'        => true,
				'created_at'    => $created_at,
			);
			$record['name'] = Hosted_Agent_Access::client_name( $record );
			// Any registrant can claim a contact. Only the trusted callback can
			// finish an approval, so only its registrations name an account.
			$contact = 'dcr' === $source && Hosted_Agent_Access::allows_client( $record ) ? $this->storable_contact( $contacts ) : '';
			if ( '' !== $contact ) {
				$record['contact'] = $contact;
			}
			if ( 'dcr' === $source ) {
				$record['pending_expires_at'] = $created_at + OAuth_Pending_Registration_Cleanup::APPROVAL_WINDOW;
			}
			if ( '' !== $secret ) {
				$record['secret_hash'] = password_hash( $secret, PASSWORD_DEFAULT );
			}

			$clients               = $this->get_clients( true );
			$clients[ $client_id ] = $record;
			if ( ! update_option( self::OPTION_NAME, $clients, false ) ) {
				return false;
			}

			$response = $this->public_record( $record );
			if ( '' !== $secret ) {
				$response['client_secret'] = $secret;
			}

			return $response;

		} finally {
			$this->lock->release( self::REGISTRY_LOCK, $owner );
		}
	}

	/**
	 * Preserve a dynamically registered client after administrator approval.
	 */
	public function mark_dynamic_client_approved( string $client_id ): bool {
		$this->mutation_busy = false;
		$owner               = $this->lock->acquire( self::REGISTRY_LOCK, 30, 0.25 );
		if ( false === $owner ) {
			$this->mutation_busy = true;
			return false;
		}

		try {
			$clients = $this->get_clients( true );
			if (
				! isset( $clients[ $client_id ] )
				|| ! is_array( $clients[ $client_id ] )
				|| 'dcr' !== ( $clients[ $client_id ]['source'] ?? '' )
				|| empty( $clients[ $client_id ]['active'] )
			) {
				return false;
			}

			if ( ! empty( $clients[ $client_id ]['approved_at'] ) ) {
				return true;
			}

			$clients[ $client_id ]['approved_at'] = time();
			unset( $clients[ $client_id ]['pending_expires_at'] );

			return update_option( self::OPTION_NAME, $clients, false );

		} finally {
			$this->lock->release( self::REGISTRY_LOCK, $owner );
		}
	}

	/**
	 * Remember an administrator-approved CIMD client for later disconnection.
	 *
	 * CIMD remains the source of metadata until approval. Persisting the exact
	 * approved snapshot gives the Connection screen a local revocation handle and
	 * ensures client revocation also blocks existing OAuth access tokens.
	 */
	public function remember_approved_cimd_client( string $client_id, string $name, array $redirect_uris ): bool {
		$this->mutation_busy = false;
		$owner               = $this->lock->acquire( self::REGISTRY_LOCK, 30, 0.25 );
		if ( false === $owner ) {
			$this->mutation_busy = true;
			return false;
		}

		try {
			if ( $this->is_revoked( $client_id, true ) || strlen( $client_id ) > 2048 ) {
				return false;
			}
			$parts = wp_parse_url( $client_id );
			if (
				! is_array( $parts )
				|| 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) )
				|| empty( $parts['host'] )
				|| ! wp_http_validate_url( $client_id )
			) {
				return false;
			}

			$name          = $this->storable_name( $name );
			$redirect_uris = $this->normalize_redirect_uris( $redirect_uris );
			if ( '' === $name || self::character_length( $name ) > 100 || array() === $redirect_uris ) {
				return false;
			}

			$clients = $this->get_clients( true );
			if ( isset( $clients[ $client_id ] ) ) {
				if ( 'cimd' !== ( $clients[ $client_id ]['source'] ?? '' ) || empty( $clients[ $client_id ]['active'] ) ) {
					return false;
				}

				$clients[ $client_id ]['name']          = $name;
				$clients[ $client_id ]['redirect_uris'] = $redirect_uris;
				$clients[ $client_id ]['approved_at']   = time();

				return update_option( self::OPTION_NAME, $clients, false )
					|| $clients === $this->get_clients( true );
			}

			$clients[ $client_id ] = array(
				'client_id'     => $client_id,
				'name'          => $name,
				'type'          => 'public',
				'redirect_uris' => $redirect_uris,
				'source'        => 'cimd',
				'active'        => true,
				'created_at'    => time(),
				'approved_at'   => time(),
			);

			return update_option( self::OPTION_NAME, $clients, false );

		} finally {
			$this->lock->release( self::REGISTRY_LOCK, $owner );
		}
	}

	/**
	 * Whether an exact client is present in the administrator-managed registry.
	 */
	public function has_stored_client( string $client_id ): bool {
		$clients = $this->get_clients();

		return isset( $clients[ $client_id ] ) && is_array( $clients[ $client_id ] );
	}

	/**
	 * Make room under the dynamic-client cap by dropping old unmarked registrations.
	 *
	 * Older records lack a reliable pending marker. Preserve their existing
	 * cap-only behavior, but never remove one while it has live consent or grants.
	 * New registrations keep their full approval window even when the cap is full.
	 *
	 * @param int $cap Maximum active dynamic clients.
	 * @return int Number of pending registrations removed.
	 */
	public function prune_pending_dynamic_clients( int $cap ): int {
		try {
			$cleanup = new OAuth_Pending_Registration_Cleanup( $this, new OAuth_Artifact_Store(), new Token_Manager() );
			return $cleanup->prune_legacy_at_cap( $cap );
		} catch ( \Throwable $error ) {
			return 0;
		}
	}

	/**
	 * Read the registry directly so cleanup never trusts an incomplete cache read.
	 *
	 * @since 8.0.0
	 * @return array|null Registry map, or null when the read is unproven.
	 */
	public function confirmed_clients(): ?array {
		$row = $this->confirmed_registry_row();
		return null === $row ? null : $row['clients'];
	}

	/**
	 * Remove one unapproved dynamic record while holding its lifecycle lock.
	 *
	 * @since 8.0.0
	 * @return bool|null True if removed, false if ineligible, null on failure.
	 */
	public function remove_unapproved_dynamic_client( string $client_id, bool $require_expired, int $now, string $lifecycle_lock_name, string $lifecycle_lock_owner ): ?bool {
		$expected_lock = 'automator_mcp_oauth_v1_client_' . hash( 'sha256', $client_id ) . '_lock';
		if ( ! hash_equals( $expected_lock, $lifecycle_lock_name ) || '' === $lifecycle_lock_owner ) {
			return null;
		}
		$owner = $this->lock->acquire( self::REGISTRY_LOCK, 30, 0.25 );
		if ( false === $owner ) {
			return null;
		}
		try {
			$row = $this->confirmed_registry_row();
			if ( null === $row ) {
				return null;
			}
			$clients = $row['clients'];
			$client  = $clients[ $client_id ] ?? null;
			if ( ! is_array( $client ) || ! isset( $client['client_id'] ) || ! is_string( $client['client_id'] ) || ! hash_equals( $client_id, $client['client_id'] ) || 'dcr' !== ( $client['source'] ?? '' ) || empty( $client['active'] ) || ! empty( $client['approved_at'] ) ) {
				return false;
			}
			$expiry     = $client['pending_expires_at'] ?? null;
			$created_at = $client['created_at'] ?? null;
			if ( $require_expired ) {
				if ( ! is_numeric( $expiry ) || (int) $expiry <= 0 || ! is_numeric( $created_at ) || (int) $created_at <= 0 ) {
					return false;
				}
				if ( min( (int) $expiry, (int) $created_at + OAuth_Pending_Registration_Cleanup::APPROVAL_WINDOW ) > $now ) {
					return false;
				}
			} elseif ( null !== $expiry ) {
				return false;
			}
			unset( $clients[ $client_id ] );
			global $wpdb;
			// The lifecycle lease can expire while a liveness scan is running.
			// Compare its exact current value in the same update as the registry.
			// A successor cannot create consent until it replaces this lease.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lease ownership must be read from the database.
			$lease_value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $lifecycle_lock_name ) );
			if ( '' !== $wpdb->last_error || ! is_string( $lease_value ) ) {
				return null;
			}
			$lease = automator_safe_unserialize( $lease_value );
			if ( ! is_array( $lease ) || ! isset( $lease['owner'] ) || ! is_string( $lease['owner'] ) || ! hash_equals( $lifecycle_lock_owner, $lease['owner'] ) ) {
				return null;
			}
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"UPDATE {$wpdb->options} AS registry INNER JOIN {$wpdb->options} AS lifecycle ON lifecycle.option_name = %s SET registry.option_value = %s WHERE registry.option_name = %s AND BINARY registry.option_value = %s AND BINARY lifecycle.option_value = %s",
				$lifecycle_lock_name,
				maybe_serialize( $clients ),
				self::OPTION_NAME,
				$row['raw'],
				$lease_value
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- A value-conditional update preserves concurrent registry mutations.
			if ( 1 !== $wpdb->query( $query ) ) {
				return null;
			}
			wp_cache_delete( self::OPTION_NAME, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$alloptions = wp_cache_get( 'alloptions', 'options' );
			if ( is_array( $alloptions ) && isset( $alloptions[ self::OPTION_NAME ] ) ) {
				wp_cache_delete( 'alloptions', 'options' );
			}
			return true;
		} finally {
			$this->lock->release( self::REGISTRY_LOCK, $owner );
		}
	}

	/**
	 * Read the exact stored registry value for a safe conditional update.
	 *
	 * @since 8.0.0
	 * @return array|null Null means the read failed or was malformed.
	 */
	private function confirmed_registry_row(): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A destructive decision needs the database value, not an option cache hit.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION_NAME ), ARRAY_A );
		if ( '' !== $wpdb->last_error ) {
			return null;
		}
		if ( null === $row ) {
			return array(
				'clients' => array(),
				'raw'     => '',
			);
		}
		if ( ! is_array( $row ) || ! isset( $row['option_value'] ) || ! is_string( $row['option_value'] ) ) {
			return null;
		}
		$clients = automator_safe_unserialize( $row['option_value'] );
		return is_array( $clients ) ? array(
			'clients' => $clients,
			'raw'     => $row['option_value'],
		) : null;
	}

	/**
	 * Revoke a client locally.
	 */
	public function revoke( string $client_id ): bool {
		$this->mutation_busy = false;
		$owner               = $this->lock->acquire( self::REGISTRY_LOCK, 30, 0.25 );
		if ( false === $owner ) {
			$this->mutation_busy = true;
			return false;
		}

		try {
			$revoked               = $this->read_registry_option( self::REVOKED_OPTION, true );
			$revoked               = is_array( $revoked ) ? $revoked : array();
			$revoked[ $client_id ] = time();
			$stored                = update_option( self::REVOKED_OPTION, $revoked, false ) || $this->is_revoked( $client_id, true );
			if ( ! $stored ) {
				return false;
			}

			$clients = $this->get_clients( true );
			if ( isset( $clients[ $client_id ] ) ) {
				$clients[ $client_id ]['active']     = false;
				$clients[ $client_id ]['revoked_at'] = time();
				update_option( self::OPTION_NAME, $clients, false );
			}

			return true;

		} finally {
			$this->lock->release( self::REGISTRY_LOCK, $owner );
		}
	}

	/**
	 * Delete a revoked client record.
	 *
	 * Only revoked clients can be deleted, and the revocation entry itself is
	 * kept: token validation checks is_revoked(), so removing the marker
	 * would quietly revive any still-living token issued to the client.
	 */
	public function delete( string $client_id ): bool {
		$this->mutation_busy = false;
		$owner               = $this->lock->acquire( self::REGISTRY_LOCK, 30, 0.25 );
		if ( false === $owner ) {
			$this->mutation_busy = true;
			return false;
		}

		try {
			if ( ! $this->is_revoked( $client_id, true ) ) {
				return false;
			}

			$clients = $this->get_clients( true );
			if ( ! isset( $clients[ $client_id ] ) ) {
				return true;
			}

			unset( $clients[ $client_id ] );

			return update_option( self::OPTION_NAME, $clients, false );

		} finally {
			$this->lock->release( self::REGISTRY_LOCK, $owner );
		}
	}

	/**
	 * Whether a redirect would be accepted at registration.
	 */
	public function is_acceptable_redirect( string $redirect_uri ): bool {
		return array() !== $this->normalize_redirect_uris( array( $redirect_uri ) );
	}

	/**
	 * Bind the first approved redirect to a client registered without one.
	 *
	 * Idempotent for the same address, and refuses a different one: the
	 * binding is trust-on-first-use, sealed by the administrator approving
	 * the consent screen that named the destination.
	 */
	public function bind_redirect( string $client_id, string $redirect_uri ): bool {
		$this->mutation_busy = false;
		$owner               = $this->lock->acquire( self::REGISTRY_LOCK, 30, 0.25 );
		if ( false === $owner ) {
			$this->mutation_busy = true;
			return false;
		}

		try {
			$clients = $this->get_clients( true );
			if ( ! isset( $clients[ $client_id ] ) || empty( $clients[ $client_id ]['active'] ) ) {
				return false;
			}

			$normalized = $this->normalize_redirect_uris( array( $redirect_uri ) );
			if ( array() === $normalized ) {
				return false;
			}

			$existing = (array) ( $clients[ $client_id ]['redirect_uris'] ?? array() );
			if ( array() !== $existing ) {
				return in_array( $normalized[0], $existing, true );
			}

			$clients[ $client_id ]['redirect_uris']     = $normalized;
			$clients[ $client_id ]['redirect_bound_at'] = time();

			return update_option( self::OPTION_NAME, $clients, false );

		} finally {
			$this->lock->release( self::REGISTRY_LOCK, $owner );
		}
	}

	/**
	 * Whether a client has been explicitly revoked by this site.
	 */
	public function is_revoked( string $client_id, bool $fresh = false ): bool {
		$revoked = $this->read_registry_option( self::REVOKED_OPTION, $fresh );

		return is_array( $revoked ) && isset( $revoked[ $client_id ] );
	}

	/**
	 * Check the revocation list without treating a failed database read as an
	 * empty list or relying on a stale negative cache entry.
	 *
	 * @since 8.0.0
	 *
	 * @return bool|null Null means the list could not be verified.
	 */
	public function is_revoked_confirmed( string $client_id ): ?bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The credential liveness check must distinguish an absent list from a failed read.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::REVOKED_OPTION ),
			ARRAY_A
		);
		if ( '' !== $wpdb->last_error ) {
			return null;
		}
		if ( null === $row ) {
			return false;
		}
		if ( ! is_array( $row ) || ! array_key_exists( 'option_value', $row ) ) {
			return null;
		}

		$revoked = automator_safe_unserialize( $row['option_value'] );
		return is_array( $revoked ) ? isset( $revoked[ $client_id ] ) : null;
	}

	/**
	 * List public client registration details.
	 */
	public function list_clients(): array {
		return array_values( array_map( array( $this, 'public_record' ), $this->get_clients() ) );
	}

	/**
	 * Authenticate a client at the token endpoint.
	 */
	public function authenticate( array $client, string $provided_client_id, ?string $secret ): bool {
		if ( ! isset( $client['client_id'] ) || ! is_string( $client['client_id'] ) || ! hash_equals( $client['client_id'], $provided_client_id ) ) {
			return false;
		}

		if ( 'confidential' !== ( $client['type'] ?? null ) ) {
			// null means no Basic header was sent at all, which is correct for a
			// public client. An empty string is a real (empty) secret and fails:
			// the empty FORM secret some clients send is tolerated earlier, at
			// the token endpoint, never here.
			return null === $secret;
		}

		return is_string( $secret )
			&& isset( $client['secret_hash'] )
			&& is_string( $client['secret_hash'] )
			&& password_verify( $secret, $client['secret_hash'] );
	}

	/**
	 * Whether one redirect URI is acceptable for any client.
	 *
	 * Accepts https anywhere and http only on loopback, as the MCP
	 * authorization specification requires. A wildcard, fragment or userinfo
	 * anywhere rejects the address.
	 */
	public static function is_valid_redirect_uri( string $uri ): bool {
		if ( '' === $uri || strlen( $uri ) > 2048 || 1 === preg_match( '/[\x00-\x20\x7F]/', $uri ) || false !== strpos( $uri, '*' ) ) {
			return false;
		}
		$parts = wp_parse_url( $uri );
		if (
			! is_array( $parts )
			|| ! isset( $parts['scheme'] )
			|| isset( $parts['fragment'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
		) {
			return false;
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( 'https' === $scheme ) {
			return '' !== $host;
		}
		if ( 'http' === $scheme ) {
			return in_array( $host, self::LOOPBACK_HOSTS, true );
		}

		return false;
	}

	/**
	 * Whether an anonymous registration's redirect list stays within its allowance.
	 *
	 * @param string[] $redirect_uris Normalized redirect list.
	 */
	public static function dcr_redirect_uris_within_limits( array $redirect_uris ): bool {
		if ( count( $redirect_uris ) > self::DCR_MAX_REDIRECT_URIS ) {
			return false;
		}
		foreach ( $redirect_uris as $uri ) {
			if ( ! is_string( $uri ) || strlen( $uri ) > self::DCR_MAX_REDIRECT_URI_LENGTH ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate and normalize redirect URI lists.
	 *
	 * @return string[]
	 */
	private function normalize_redirect_uris( array $redirect_uris ): array {
		if ( count( $redirect_uris ) > 20 ) {
			return array();
		}

		$valid = array();
		foreach ( $redirect_uris as $uri ) {
			if ( ! is_string( $uri ) || ! self::is_valid_redirect_uri( $uri ) ) {
				return array();
			}
			$valid[] = $uri;
		}

		return array_values( array_unique( $valid ) );
	}

	/**
	 * The first contact that is a plain email address, or an empty string.
	 *
	 * @since 8.0.0
	 *
	 * @param array $contacts Registrant contacts.
	 */
	private function storable_contact( array $contacts ): string {
		foreach ( array_slice( $contacts, 0, 5 ) as $contact ) {
			if ( is_string( $contact ) && strlen( $contact ) <= 254 && sanitize_email( $contact ) === $contact && false !== is_email( $contact ) ) {
				return $contact;
			}
		}

		return '';
	}

	/**
	 * Prepare a client-supplied name for a serialized option.
	 *
	 * Sites still on a three-byte utf8 options table silently drop four-byte
	 * characters on write, which corrupts the serialized registry and hides
	 * every client. Emoji are stored as entities there and untouched on utf8mb4.
	 */
	private function storable_name( string $name ): string {
		return (string) wp_encode_emoji( sanitize_text_field( $name ) );
	}

	/**
	 * Character length that does not count bytes when multibyte support exists.
	 */
	private static function character_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Read current registry state, including on persistent object-cache sites.
	 *
	 * A request can have cached the map before waiting for its mutation lock.
	 * Legacy autoloaded rows must also be refreshed, but unrelated alloptions
	 * stays warm when these registry rows are not in it.
	 */
	private function read_registry_option( string $name, bool $fresh = false ): array {
		if ( $fresh ) {
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$alloptions = wp_cache_get( 'alloptions', 'options' );
			if ( is_array( $alloptions ) && isset( $alloptions[ $name ] ) ) {
				wp_cache_delete( 'alloptions', 'options' );
			}
		}
		$value = get_option( $name, array() );

		return is_array( $value ) ? $value : array();
	}

	/** Whether the preceding registry edit could not acquire its lock. */
	public function mutation_was_busy(): bool {
		return $this->mutation_busy;
	}

	/** Recheck local authorization under the client lifecycle lock, without network I/O. */
	public function is_authenticated_client_current( array $client ): bool {
		$client_id = (string) ( $client['client_id'] ?? '' );
		if ( $this->is_revoked( $client_id, true ) ) {
			return false;
		}
		$clients = $this->get_clients( true );
		if ( 'cimd' === ( $client['source'] ?? '' ) ) {
			return ! isset( $clients[ $client_id ] ) || ! empty( $clients[ $client_id ]['active'] );
		}
		return isset( $clients[ $client_id ] ) && $clients[ $client_id ] === $client;
	}

	/**
	 * Read the pre-registration store.
	 */
	private function get_clients( bool $fresh = false ): array {
		$clients = $this->read_registry_option( self::OPTION_NAME, $fresh );

		return is_array( $clients ) ? $clients : array();
	}

	/**
	 * Remove credential hashes from an administrative response.
	 */
	private function public_record( array $record ): array {
		unset( $record['secret_hash'] );
		$record['name'] = Hosted_Agent_Access::client_name( $record );
		if ( isset( $record['client_id'] ) && is_string( $record['client_id'] ) && $this->is_revoked( $record['client_id'] ) ) {
			// The permanent marker is the authorization source of truth. Overlay
			// presentation state so a failed secondary row update cannot show a
			// disconnected app as active.
			$record['active'] = false;
		}

		return $record;
	}
}
