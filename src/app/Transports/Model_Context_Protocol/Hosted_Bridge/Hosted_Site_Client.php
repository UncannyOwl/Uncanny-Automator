<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge;

use Uncanny_Automator\App\Transient\Application\Hosted_Read_Cache;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Atomic_Option_Lock;
use WP_Error;

/**
 * Reads one connection's apps, activity and usage from the hosted service.
 *
 * Each read uses a short-lived access token from the client credentials grant
 * (client_secret_basic). The token is cached encrypted for at most four
 * minutes and fetched under a lock, so the Dashboard's parallel reads share
 * one token request. The apps and the usage are kept for five minutes
 * (`Hosted_Read_Cache`), so a Dashboard refresh makes no call for them;
 * connecting the site clears them, and the setup wizard replaces the apps
 * with a fresh read. Activity is never kept, because WordPress
 * stores no text an AI wrote. After a refusal or a failure the client backs
 * off and makes no call until the window ends. Errors never carry the secret, a token
 * or the hosted reply.
 */
final class Hosted_Site_Client {

	public const UNAVAILABLE = 'hosted_unavailable';

	public const REFUSED = 'hosted_refused';

	public const INVALID_CLIENT = 'hosted_invalid_client';

	public const USAGE_UNAVAILABLE = 'hosted_usage_unavailable';

	public const INVALID = 'hosted_invalid_response';

	public const APPS_LIMIT = 'hosted_apps_limit';

	// Internal signal that a prior hosted version does not support app paging.
	private const LEGACY_APPS_PAGE = 'hosted_legacy_apps_page';

	// Fits a page of maximum-length UTF-8 names under the transport limit.
	private const APPS_PAGE_SIZE = 200;

	/** The apps read, as the cache knows it. */
	private const APPS_READ = '/api/sites/v1/apps?limit=' . self::APPS_PAGE_SIZE;

	/** The kept answer for an account without a usage plan. */
	private const NO_USAGE_PLAN = 'no_usage_plan';

	private const TOKEN_CACHE_SECONDS = 240;

	/** A token is cached only while at least this much of its life remains. */
	private const TOKEN_MARGIN = 30;

	private const MAX_TOKEN_LIFETIME = 300;

	private const LOCK_WAIT = 5;

	private const REFUSED_BACKOFF = 60;

	private const FAILURE_BACKOFF = 30;

	private const MAX_BACKOFF = 300;

	private Hosted_Http_Transport $transport;

	private Hosted_Read_Schema $schema;

	private Hosted_Secret_Box $box;

	private Atomic_Option_Lock $lock;

	private Hosted_Read_Cache $cache;

	/**
	 * Constructor.
	 */
	public function __construct(
		?Hosted_Http_Transport $transport = null,
		?Hosted_Secret_Box $box = null,
		?Atomic_Option_Lock $lock = null,
		?Hosted_Read_Cache $cache = null
	) {
		$this->transport = $transport ?? new Hosted_Http_Transport();
		$this->schema    = new Hosted_Read_Schema();
		$this->box       = $box ?? new Hosted_Secret_Box( 'uncanny-automator:mcp:hosted-access-token:v1' );
		$this->lock      = $lock ?? new Atomic_Option_Lock();
		$this->cache     = $cache ?? new Hosted_Read_Cache();
	}

	/**
	 * The AI apps bound to the credential's connection.
	 *
	 * @param array{user_id:int,family_id:string,client_id:string,version:int,secret:string} $credential Stored credential.
	 *
	 * @return list<array{id:string,name:string,vendor:string,last_used_at:?string}>|WP_Error
	 */
	public function apps( array $credential ) {
		return $this->cache->remember(
			$this->cache_scope( $credential ),
			self::APPS_READ,
			fn() => $this->read_apps( $credential )
		);
	}

	/**
	 * The AI apps bound to the credential's connection, read now and kept in
	 * place of any earlier list.
	 *
	 * The hosted service refuses a new token for a moment about every five
	 * minutes, while it checks the site again, so a kept list answers while it
	 * is unavailable.
	 *
	 * @param array{user_id:int,family_id:string,client_id:string,version:int,secret:string} $credential Stored credential.
	 *
	 * @return list<array{id:string,name:string,vendor:string,last_used_at:?string}>|WP_Error
	 */
	public function fresh_apps( array $credential ) {
		$scope = $this->cache_scope( $credential );
		$fresh = $this->cache->refresh( $scope, self::APPS_READ, fn() => $this->read_apps( $credential ) );

		if ( $fresh instanceof WP_Error && self::UNAVAILABLE === $fresh->get_error_code() ) {
			return $this->cache->remember( $scope, self::APPS_READ, fn() => $fresh );
		}

		return $fresh;
	}

	/**
	 * Read complete, ordered pages. Never cache or return part of one account.
	 *
	 * @since 8.0.0
	 *
	 * @return list<array<string,mixed>>|WP_Error
	 */
	private function read_apps( array $credential ) {
		$apps  = array();
		$after = null;

		while ( true ) {
			$query = array( 'limit' => self::APPS_PAGE_SIZE );
			if ( null !== $after ) {
				$query['after'] = $after;
			}
			$reply = $this->read( $credential, '/api/sites/v1/apps', $query, null === $after );
			if ( $reply instanceof WP_Error ) {
				if ( null === $after && self::LEGACY_APPS_PAGE === $reply->get_error_code() ) {
					// The old hosted version rejects every query parameter on this route.
					$legacy = $this->checked( $this->read( $credential, '/api/sites/v1/apps' ), array( $this->schema, 'apps' ) );
					// The old version silently capped its answer, so exactly 500 is not proof of completeness.
					return is_array( $legacy ) && count( $legacy ) === Hosted_Read_Schema::MAX_APPS ? $this->error( self::APPS_LIMIT ) : $legacy;
				}
				return $reply;
			}

			$page   = $this->schema->apps( $reply );
			$cursor = $reply['next_cursor'] ?? null;
			if ( null === $page || ! array_key_exists( 'next_cursor', $reply ) || count( $page ) > self::APPS_PAGE_SIZE ) {
				return $this->error( self::INVALID );
			}
			$last = $after;
			foreach ( $page as $app ) {
				if ( null !== $last && strcmp( $app['id'], $last ) <= 0 ) {
					return $this->error( self::INVALID );
				}
				$last = $app['id'];
			}
			if (
				null !== $cursor
				&& ( ! is_string( $cursor ) || count( $page ) !== self::APPS_PAGE_SIZE || $cursor !== $last )
			) {
				return $this->error( self::INVALID );
			}
			if ( count( $apps ) + count( $page ) > Hosted_Read_Schema::MAX_APPS ) {
				return $this->error( self::APPS_LIMIT );
			}
			$apps = array_merge( $apps, $page );
			if ( null === $cursor ) {
				return $apps;
			}
			if ( count( $apps ) === Hosted_Read_Schema::MAX_APPS ) {
				return $this->error( self::APPS_LIMIT );
			}
			$after = $cursor;
		}
	}

	/**
	 * One page of the connection's activity. Never kept: the summaries are text
	 * an AI wrote, which WordPress does not store.
	 *
	 * @param array<string,int|string> $query Parameters already checked against the contract.
	 *
	 * @return array{entries:list<array<string,mixed>>,total:int}|WP_Error
	 */
	public function activity( array $credential, array $query ) {
		return $this->checked( $this->read( $credential, '/api/sites/v1/activity', $query ), array( $this->schema, 'activity' ) );
	}

	/**
	 * The account's AI tool use allowance.
	 *
	 * @return array{plan:string,used:int,limit:?int,resets_at:?string}|WP_Error
	 */
	public function usage( array $credential ) {
		$usage = $this->cache->remember(
			$this->cache_scope( $credential ),
			'/api/sites/v1/usage',
			function () use ( $credential ) {
				$usage = $this->checked( $this->read( $credential, '/api/sites/v1/usage' ), array( $this->schema, 'usage' ) );

				// An account without a usage plan is a definite answer, so it is kept like a read.
				return is_wp_error( $usage ) && self::USAGE_UNAVAILABLE === $usage->get_error_code() ? array( self::NO_USAGE_PLAN => true ) : $usage;
			}
		);

		return is_array( $usage ) && isset( $usage[ self::NO_USAGE_PLAN ] ) ? $this->error( self::USAGE_UNAVAILABLE ) : $usage;
	}

	/**
	 * Ask the hosted service to revoke the credential, and forget its token.
	 *
	 * @return bool Whether the hosted service confirmed revocation.
	 */
	public function revoke( array $credential ): bool {
		delete_transient( $this->transient( 'token', $credential ) );
		$reply = $this->transport->request( 'POST', '/api/sites/v1/revoke', $this->basic_headers( $credential ), '', 2 );

		if ( null !== $reply && $this->is_invalid_client( $reply ) ) {
			return true;
		}
		if ( null === $reply || 200 !== $reply['status'] || 'application/json' !== strtolower( trim( explode( ';', $reply['headers']['content-type'] ?? '', 2 )[0] ) ) ) {
			return false;
		}
		$body = json_decode( $reply['body'], false, 32 );
		return $body instanceof \stdClass && array() === get_object_vars( $body );
	}

	/**
	 * A reply that passed its schema check, else the read's error.
	 *
	 * @param array|WP_Error         $reply The decoded reply.
	 * @param callable(array):?array $check The reply's schema check.
	 *
	 * @return array|WP_Error
	 */
	private function checked( $reply, callable $check ) {
		return is_wp_error( $reply ) ? $reply : ( $check( $reply ) ?? $this->error( self::INVALID ) );
	}

	/**
	 * The cache scope of one credential version at this site address, read the
	 * way the credential's own binding reads it (the stored option, not the
	 * filtered home_url()), so a database copied to another address never shows
	 * this site's kept reads.
	 */
	private function cache_scope( array $credential ): string {
		return implode( "\0", array( get_current_blog_id(), untrailingslashit( (string) get_option( 'home' ) ), (int) $credential['user_id'], (string) $credential['family_id'], (string) $credential['client_id'], (int) $credential['version'] ) );
	}

	/**
	 * GET a site-read endpoint and decode its JSON reply.
	 *
	 * @return array|WP_Error
	 */
	private function read( array $credential, string $path, array $query = array(), bool $legacy_apps_fallback = false ) {
		$scope = 'backoff_' . basename( $path );
		$wait  = max( $this->backoff_remaining( $credential ), $this->backoff_remaining( $credential, $scope ) );
		if ( $wait > 0 ) {
			return $this->error( self::UNAVAILABLE, $wait );
		}

		$token = $this->access_token( $credential );
		if ( $token instanceof WP_Error ) {
			return $token;
		}

		$reply = $this->transport->request(
			'GET',
			array() === $query ? $path : $path . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ),
			array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			)
		);
		if ( null === $reply ) {
			return $this->back_off( $credential, self::FAILURE_BACKOFF );
		}

		if ( 200 === $reply['status'] ) {
			return $this->decode( $reply ) ?? $this->error( self::INVALID );
		}
		if ( 401 === $reply['status'] ) {
			// The token ended early or its credential changed; the next read gets a new one.
			delete_transient( $this->transient( 'token', $credential ) );
			return $this->error( self::UNAVAILABLE, 1 );
		}
		if ( $legacy_apps_fallback && 400 === $reply['status'] && 'invalid_request' === ( $this->decode( $reply )['error'] ?? null ) ) {
			return new WP_Error( self::LEGACY_APPS_PAGE );
		}
		if ( 409 === $reply['status'] && 'usage_unavailable' === ( $this->decode( $reply )['error'] ?? null ) ) {
			return $this->error( self::USAGE_UNAVAILABLE );
		}

		// A failing endpoint backs off alone: a usage outage must not hold back apps or activity.
		return $this->failed( $credential, $reply, $scope );
	}

	/**
	 * A cached access token, or a new one fetched by one request at a time.
	 *
	 * @return string|WP_Error
	 */
	private function access_token( array $credential ) {
		$token = $this->cached_token( $credential );
		if ( null !== $token ) {
			return $token;
		}

		$lock  = $this->transient( 'token_lock', $credential );
		$owner = $this->lock->acquire( $lock, 15, self::LOCK_WAIT );
		if ( false === $owner ) {
			return $this->error( self::UNAVAILABLE, 1 );
		}

		try {
			// Another request may have fetched a token, or hit a refusal, while this one waited.
			$token = $this->cached_token( $credential );
			if ( null !== $token ) {
				return $token;
			}
			$wait = $this->backoff_remaining( $credential );

			return $wait > 0 ? $this->error( self::UNAVAILABLE, $wait ) : $this->request_token( $credential );
		} finally {
			$this->lock->release( $lock, $owner );
		}
	}

	/**
	 * Request a token with the client credentials grant.
	 *
	 * @return string|WP_Error
	 */
	private function request_token( array $credential ) {
		$reply = $this->transport->request( 'POST', '/api/sites/v1/token', $this->basic_headers( $credential ), 'grant_type=client_credentials' );
		if ( null === $reply ) {
			return $this->back_off( $credential, self::FAILURE_BACKOFF );
		}
		if ( $this->is_invalid_client( $reply ) ) {
			return $this->error( self::INVALID_CLIENT );
		}
		if ( 200 !== $reply['status'] ) {
			return $this->failed( $credential, $reply );
		}

		$data       = $this->decode( $reply );
		$token      = $data['access_token'] ?? null;
		$expires_in = $data['expires_in'] ?? null;
		if (
			! is_string( $token ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{43}$/D', $token )
			|| ! is_string( $data['token_type'] ?? null ) || 0 !== strcasecmp( 'Bearer', $data['token_type'] )
			|| ! is_int( $expires_in ) || $expires_in < 1 || $expires_in > self::MAX_TOKEN_LIFETIME
		) {
			return $this->error( self::INVALID );
		}

		$this->cache_token( $credential, $token, $expires_in );

		return $token;
	}

	/**
	 * Turn a refusal or failure into an error and start the back-off window.
	 *
	 * @param array{status:int,headers:array<string,string>,body:string} $reply Hosted reply.
	 * @param string                                                     $scope Back-off window to start.
	 */
	private function failed( array $credential, array $reply, string $scope = 'backoff' ): WP_Error {
		$status = $reply['status'];
		if ( 401 === $status || 403 === $status ) {
			$this->back_off( $credential, self::REFUSED_BACKOFF, $scope );
			return $this->error( self::REFUSED );
		}
		if ( $status >= 400 && $status < 500 && 429 !== $status ) {
			return $this->error( self::INVALID );
		}

		$retry_after = $reply['headers']['retry-after'] ?? '';

		return $this->back_off(
			$credential,
			in_array( $status, array( 429, 503 ), true ) && ctype_digit( $retry_after ) ? (int) $retry_after : self::FAILURE_BACKOFF,
			$scope
		);
	}

	/**
	 * Make no call in this window for the given seconds. The `backoff` window
	 * covers the credential (token requests); `backoff_{endpoint}` one read.
	 */
	private function back_off( array $credential, int $seconds, string $scope = 'backoff' ): WP_Error {
		$seconds = max( 1, min( self::MAX_BACKOFF, $seconds ) );
		set_transient( $this->transient( $scope, $credential ), time() + $seconds, $seconds );

		return $this->error( self::UNAVAILABLE, $seconds );
	}

	/**
	 * Seconds left in a back-off window.
	 */
	private function backoff_remaining( array $credential, string $scope = 'backoff' ): int {
		$until = get_transient( $this->transient( $scope, $credential ) );

		return is_int( $until ) ? max( 0, $until - time() ) : 0;
	}

	/**
	 * The cached token, or null when none is usable here.
	 */
	private function cached_token( array $credential ): ?string {
		$cached = get_transient( $this->transient( 'token', $credential ) );
		if ( ! is_array( $cached ) || ! is_int( $cached['expires_at'] ?? null ) || $cached['expires_at'] <= time() || ! is_string( $cached['envelope'] ?? null ) ) {
			return null;
		}

		return $this->box->open( $cached['envelope'], $this->token_binding( $credential, $cached['expires_at'] ) );
	}

	/**
	 * Cache a token encrypted, for at most four minutes and never past its last 30 seconds.
	 */
	private function cache_token( array $credential, string $token, int $expires_in ): void {
		$ttl = min( self::TOKEN_CACHE_SECONDS, $expires_in - self::TOKEN_MARGIN );
		if ( $ttl < 1 ) {
			return;
		}

		$expires_at = time() + $ttl;
		$envelope   = $this->box->seal( $token, $this->token_binding( $credential, $expires_at ) );
		if ( null !== $envelope ) {
			set_transient(
				$this->transient( 'token', $credential ),
				array(
					'expires_at' => $expires_at,
					'envelope'   => $envelope,
				),
				$ttl
			);
		}
	}

	/**
	 * What a cached token is bound to.
	 *
	 * @return array<int,int|string>
	 */
	private function token_binding( array $credential, int $expires_at ): array {
		return array( (int) $credential['user_id'], (string) $credential['family_id'], (string) $credential['client_id'], (int) $credential['version'], $expires_at );
	}

	/**
	 * Transient or lock name for one credential version.
	 */
	private function transient( string $kind, array $credential ): string {
		return 'automator_mcp_hosted_' . $kind . '_' . substr( hash( 'sha256', $credential['family_id'] . "\0" . $credential['client_id'] . "\0" . $credential['version'] ), 0, 32 );
	}

	/**
	 * client_secret_basic headers. Client IDs and secrets use only URL-safe
	 * characters (checked on delivery), so form-encoding them changes nothing.
	 *
	 * @return array<string,string>
	 */
	private function basic_headers( array $credential ): array {
		return array(
			'Authorization' => 'Basic ' . base64_encode( $credential['client_id'] . ':' . $credential['secret'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 6749 client_secret_basic.
			'Content-Type'  => 'application/x-www-form-urlencoded',
			'Accept'        => 'application/json',
		);
	}

	/**
	 * Recognize the hosted Basic-auth refusal, not a proxy or an expired bearer token.
	 * Call only for the token and revocation endpoints.
	 *
	 * @since 8.0.0
	 * @param array $reply Hosted response.
	 */
	private function is_invalid_client( array $reply ): bool {
		return 401 === $reply['status']
			&& 'application/json' === strtolower( trim( explode( ';', $reply['headers']['content-type'] ?? '', 2 )[0] ) )
			&& 'Basic realm="uncanny-automator-sites"' === trim( $reply['headers']['www-authenticate'] ?? '' )
			&& array( 'error' => 'invalid_client' ) === $this->decode( $reply );
	}

	/**
	 * Decode a JSON object reply, or null.
	 *
	 * @param array{status:int,headers:array<string,string>,body:string} $reply Hosted reply.
	 */
	private function decode( array $reply ): ?array {
		if ( 0 !== stripos( $reply['headers']['content-type'] ?? '', 'application/json' ) ) {
			return null;
		}

		$data = json_decode( $reply['body'], true, 32 );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * An error with nothing from the request or reply in it.
	 */
	private function error( string $code, int $retry_after = 0 ): WP_Error {
		$messages = array(
			self::UNAVAILABLE       => 'The Uncanny Automator service is not available right now.',
			self::INVALID_CLIENT    => esc_html__( 'This site credential is no longer accepted by the Uncanny Automator service.', 'uncanny-automator' ),
			self::REFUSED           => 'The Uncanny Automator service did not accept this site’s credential.',
			self::USAGE_UNAVAILABLE => 'This account has no AI usage plan.',
			self::INVALID           => 'The Uncanny Automator service sent a reply this site could not use.',
			self::APPS_LIMIT        => esc_html__( 'This connection has 500 or more AI apps and cannot be fully checked. Ask the Uncanny Automator account owner to remove unused AI apps, then refresh this page.', 'uncanny-automator' ),
		);

		return new WP_Error( $code, $messages[ $code ], array( 'retry_after' => $retry_after ) );
	}
}
