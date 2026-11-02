<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Wire;
use Uncanny_Automator\App\Infrastructure\Http\Request_Headers;
use WP_Error;

/**
 * The site signature on the wire — the ONE place a platform request is signed
 * with the per-site signing secret, whichever transport sent it.
 *
 * Which requests: {@see Platform_Url_Matcher::is_site_signed()} owns that, and
 * is the only place that does — the platform plane (`/app/v1/*`) and the `/v3`
 * action surface (platform answers contract 3.1), on the configured platform
 * host only; `/v2` is never signed. The same rule is heard by
 * {@see Site_Credentials_Rejection_Observer} and {@see Clock_Skew_Monitor},
 * which read the answers to the requests signed here, and by {@see Client},
 * which waits out a short `Retry-After` on the same surfaces; the callers that
 * hold no matcher ask {@see self::is_site_signed_url()}.
 *
 * The register and completion routes are never signed here
 * ({@see Registration_Wire::is_registration_route()}, the rule
 * {@see Site_Credentials_Rejection_Observer} hears too). Each carries its own
 * proof, or deliberately none: a copy registers as a first registration, and
 * the platform reads `X-Automator-Timestamp` and `X-Automator-Signature` on the
 * register route as the M4 proof, so a signature made with the secret the copy
 * inherited would be judged as the source site's proof.
 *
 * Three hooks, one per moment of a send:
 *
 *  1. `http_request_args`, last ({@see self::maybe_sign()}): sign the request
 *     as it will be transmitted. Last means after the license headers
 *     ({@see Platform_Headers_Injector}, 9999) and after every body filter, so
 *     the signed bytes are the transmitted bytes.
 *  2. `pre_http_request`, last ({@see self::refuse_altered()}): a filter
 *     registered after this one at the same priority could still change the
 *     request. Rather than send bytes the signature does not cover — which
 *     the platform answers `VERIFICATION_ERROR`, and which would make this
 *     site re-register for nothing — such a request is refused here.
 *  3. `http_response`, last ({@see self::resend_if_replayed()}): a
 *     `REQUEST_REPLAYED` answer is answered by sending the same request once
 *     more; step 1 signs that send with a new timestamp and nonce.
 *
 * WHY a filter and not the callers: the filter runs once per
 * `wp_remote_request` call, so a Transient_Retry re-send gets a fresh timestamp
 * and nonce. Signing in the caller replays the nonce on retry, which the
 * platform's replay guard rejects.
 *
 * Wire contract (C2, byte-identical to the platform's ConnectionRequestSignature):
 * `v1:{ts}:{nonce}:{METHOD}:{canonical_target}:{sha256(raw_body)}`, HMAC-SHA256
 * over the base64-decoded secret → lowercase hex. No header is signed, so a
 * header added by a later filter ({@see Client_Timeout_Header_Injector})
 * cannot invalidate the signature.
 *
 * @since 8.0.0
 */
final class Platform_Request_Signature_Injector {

	/**
	 * The request argument a replay resend carries, so the resend's own
	 * answer is never sent again: at most one resend per request.
	 *
	 * @since 8.0.0
	 */
	public const RESENT_ARG = 'automator_platform_resent';

	/**
	 * The three headers this class adds to a site-signed request.
	 *
	 * @var array<int, string>
	 */
	private const SIGNATURE_HEADERS = array( 'x-automator-timestamp', 'x-automator-nonce', 'x-automator-signature' );

	/**
	 * @var Site_Credentials_Store
	 */
	private $credentials;

	/**
	 * @var Connection_Request_Signer
	 */
	private $signer;

	/**
	 * @var Platform_Url_Matcher
	 */
	private $urls;

	/**
	 * @var Registration_Wire
	 */
	private $wire;

	/**
	 * @param Site_Credentials_Store|null    $credentials Signing-secret store.
	 * @param Connection_Request_Signer|null $signer      Canonicalisation + HMAC.
	 * @param Platform_Url_Matcher|null      $urls        Which URLs are
	 *        site-signed; production gets the configured platform.
	 * @param Registration_Wire|null         $wire        Knows the registration
	 *        routes, which are never signed here.
	 */
	public function __construct( ?Site_Credentials_Store $credentials = null, ?Connection_Request_Signer $signer = null, ?Platform_Url_Matcher $urls = null, ?Registration_Wire $wire = null ) {
		$this->credentials = $credentials ?? new Site_Credentials_Store();
		$this->signer      = $signer ?? new Connection_Request_Signer();
		$this->urls        = $urls ?? Platform_Url_Matcher::from_constants();
		$this->wire        = $wire ?? new Registration_Wire();
	}

	/**
	 * Attach the three hooks, each as the last callback of its filter.
	 *
	 * The resend is a static callback: WordPress keeps one registration of it
	 * however many instances register, so a replayed send is sent again once.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'http_request_args', array( $this, 'maybe_sign' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_http_request', array( $this, 'refuse_altered' ), PHP_INT_MAX, 3 );
		add_filter( 'http_response', array( self::class, 'resend_if_replayed' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Sign a site-signed request as it will be transmitted.
	 *
	 * @param mixed $args WP HTTP args.
	 * @param mixed $url  Request URL.
	 *
	 * @return mixed
	 */
	public function maybe_sign( $args, $url ) {

		if ( ! is_array( $args ) || ! is_string( $url ) || ! $this->urls->is_site_signed( $url ) ) {
			return $args;
		}

		// register and register/complete carry their own proof, or
		// deliberately none (a copy's first registration): never this one.
		if ( $this->wire->is_registration_route( $url ) ) {
			return $args;
		}

		$headers = Request_Headers::from_args( $args );

		// A request already signed — its own proof-of-possession, or an
		// earlier instance of this filter — is left untouched.
		if ( Request_Headers::has( $headers, 'X-Automator-Signature' ) ) {
			return $args;
		}

		$secret = (string) $this->credentials->signing_secret();
		if ( '' === $secret ) {
			return $args; // Not registered yet — nothing to sign with.
		}

		$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );

		if ( ! self::is_query_method( $method ) ) {
			// Transmit a string body so what is signed is what is sent. An
			// array becomes the encoding its surface has always received.
			$args['body'] = $this->wire_body( $args['body'] ?? '', $url, $headers );
		}

		$timestamp = (string) time();
		$nonce     = rtrim( strtr( base64_encode( random_bytes( 16 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding a random nonce, not obfuscation.

		$headers['X-Automator-Timestamp'] = $timestamp;
		$headers['X-Automator-Nonce']     = $nonce;
		$headers['X-Automator-Signature'] = 'v1=' . $this->signature( $secret, $args, $url, $timestamp, $nonce );

		$args['headers'] = $headers;

		return $args;
	}

	/**
	 * Refuse to send a site-signed request whose final method, target or body
	 * no longer match its signature.
	 *
	 * Runs last on `pre_http_request`, after every `http_request_args` filter.
	 * A request another filter answered already, and one this class did not
	 * sign, pass through untouched.
	 *
	 * @param mixed $preempt A response another filter already chose, or false.
	 * @param mixed $args    Final WP HTTP args.
	 * @param mixed $url     Request URL.
	 *
	 * @return mixed The preempt unchanged, or a WP_Error that stops the send.
	 */
	public function refuse_altered( $preempt, $args, $url ) {

		if ( false !== $preempt || ! is_array( $args ) || ! is_string( $url ) || ! $this->urls->is_site_signed( $url ) ) {
			return $preempt;
		}

		$headers = array_change_key_case( Request_Headers::from_args( $args ), CASE_LOWER );
		if ( ! isset( $headers['x-automator-nonce'], $headers['x-automator-timestamp'], $headers['x-automator-signature'] ) ) {
			return $preempt;
		}

		$secret   = (string) $this->credentials->signing_secret();
		$expected = 'v1=' . $this->signature( $secret, $args, $url, (string) $headers['x-automator-timestamp'], (string) $headers['x-automator-nonce'] );

		if ( hash_equals( $expected, (string) $headers['x-automator-signature'] ) ) {
			return $preempt;
		}

		return new WP_Error(
			'automator_platform_signature_mismatch',
			esc_html__( 'Uncanny Automator did not send this request to the Automator App Platform because another plugin changed it after it was signed.', 'uncanny-automator' )
		);
	}

	/**
	 * Send a site-signed request once more when the platform answers
	 * `REQUEST_REPLAYED` (platform answers contract 4.2).
	 *
	 * The resend carries {@see self::RESENT_ARG}, so its own answer — a second
	 * `REQUEST_REPLAYED` included — is returned, never sent again. Only an
	 * error status is decoded; every other response returns before its body
	 * is read.
	 *
	 * @param mixed $response WP HTTP response.
	 * @param mixed $args     WP HTTP args as sent (signed).
	 * @param mixed $url      Request URL.
	 *
	 * @return mixed The response, or the resend's response.
	 */
	public static function resend_if_replayed( $response, $args, $url ) {

		if ( ! is_array( $response ) || ! is_array( $args ) || ! is_string( $url ) || ! empty( $args[ self::RESENT_ARG ] ) ) {
			return $response;
		}

		if ( 400 > (int) wp_remote_retrieve_response_code( $response ) || ! self::is_site_signed_url( $url ) || ! self::carries_site_signature( $args ) ) {
			return $response;
		}

		if ( 'REQUEST_REPLAYED' !== Response::from_wp_response( $response, 0.0 )->error_type() ) {
			return $response;
		}

		$resend                     = $args;
		$resend['headers']          = self::without_signature( Request_Headers::from_args( $args ) );
		$resend[ self::RESENT_ARG ] = true;

		return wp_remote_request( $url, $resend );
	}

	/**
	 * Whether a request to this URL is signed with this site's secret, on the
	 * configured platform ({@see Platform_Url_Matcher::is_site_signed()}, the
	 * one implementation of the rule).
	 *
	 * For the callers that hold no matcher: the replay resend (a static
	 * callback, registered once however many instances register),
	 * {@see Clock_Skew_Monitor} and the client's `Retry-After` wait.
	 *
	 * @param string $url Request URL.
	 *
	 * @return bool
	 */
	public static function is_site_signed_url( string $url ): bool {

		return Platform_Url_Matcher::from_constants()->is_site_signed( $url );
	}

	/**
	 * Whether these request args carry the site signature this class adds.
	 * Only this class sets `X-Automator-Nonce`; the registration and completion
	 * routes carry their own proof in `X-Automator-Signature` with no nonce.
	 *
	 * @param array<string, mixed> $args WP HTTP args.
	 *
	 * @return bool
	 */
	public static function carries_site_signature( array $args ): bool {

		return Request_Headers::has( Request_Headers::from_args( $args ), 'X-Automator-Nonce' );
	}

	/**
	 * The signature over the request as WordPress will transmit it.
	 *
	 * @param string               $secret    Base64 site signing secret.
	 * @param array<string, mixed> $args      WP HTTP args, body already final.
	 * @param string               $url       Request URL.
	 * @param string               $timestamp Unix seconds.
	 * @param string               $nonce     base64url nonce.
	 *
	 * @return string Lowercase hex HMAC.
	 */
	private function signature( string $secret, array $args, string $url, string $timestamp, string $nonce ): string {

		$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$parts  = wp_parse_url( $url );
		$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$query  = isset( $parts['query'] ) ? (string) $parts['query'] : '';
		$body   = $args['body'] ?? '';

		if ( self::is_query_method( $method ) ) {
			// WordPress folds an array body into the query string after these
			// filters and transmits no body, so that query is what is signed.
			// The body must STAY an array: handed a string, Requests passes it
			// to http_build_query() — a TypeError on PHP 8. The canonical
			// target sorts and re-encodes pairs, so pair order and encoding
			// style are not part of the contract.
			if ( is_array( $body ) && array() !== $body ) {
				$query = ltrim( $query . '&' . http_build_query( $body, '', '&' ), '&' );
			}
			$body = '';
		}

		return $this->signer->sign(
			$secret,
			$method,
			$this->signer->canonical_target( $path, $query ),
			self::transmitted_body( $body ),
			$timestamp,
			$nonce
		);
	}

	/**
	 * The bytes WordPress transmits for a non-GET body: a string as it is, an
	 * array form-encoded the way Requests encodes it. After {@see maybe_sign()}
	 * the body is always a string; an array here means a later filter put one
	 * back, and the guard must compare what will really be sent.
	 *
	 * @param mixed $body The request body.
	 *
	 * @return string
	 */
	private static function transmitted_body( $body ): string {

		if ( is_string( $body ) ) {
			return $body;
		}

		return is_array( $body ) ? http_build_query( $body, '', '&' ) : '';
	}

	/**
	 * The body bytes a non-GET request is transmitted with.
	 *
	 * A string body is final. An array keeps the encoding its surface has
	 * always received, so signing changes nothing else about the request
	 * (platform answers contract 3.1): JSON on the platform plane, and on `/v3`
	 * the form encoding WordPress itself sends an array body with.
	 *
	 * @param mixed                $body    The request body.
	 * @param string               $url     Request URL.
	 * @param array<string, mixed> $headers Request headers; JSON adds its content type.
	 *
	 * @return string
	 */
	private function wire_body( $body, string $url, array &$headers ): string {

		if ( is_string( $body ) ) {
			return $body;
		}

		$body = is_array( $body ) ? $body : array();

		if ( $this->urls->is_action_surface( $url ) ) {
			return self::transmitted_body( $body );
		}

		$headers['Content-Type'] = 'application/json';

		return (string) wp_json_encode( $body );
	}

	/**
	 * Whether WordPress sends this method's array body as a query string.
	 *
	 * @param string $method Upper-case HTTP method.
	 *
	 * @return bool
	 */
	private static function is_query_method( string $method ): bool {
		return 'GET' === $method || 'HEAD' === $method;
	}

	/**
	 * The headers without this class's three signature headers.
	 *
	 * @param array<string, mixed> $headers Request headers.
	 *
	 * @return array<string, mixed>
	 */
	private static function without_signature( array $headers ): array {

		foreach ( array_keys( $headers ) as $name ) {
			if ( in_array( strtolower( (string) $name ), self::SIGNATURE_HEADERS, true ) ) {
				unset( $headers[ $name ] );
			}
		}

		return $headers;
	}
}
