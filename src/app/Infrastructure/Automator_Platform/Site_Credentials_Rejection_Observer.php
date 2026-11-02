<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Wire;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Reverification_Ledger;

/**
 * Hears the platform reject this install's site credentials, whichever
 * transport made the request — the response-side twin of
 * {@see Platform_Request_Signature_Injector}.
 *
 * Wire contract W4: a response typed `SITE_NOT_REGISTERED` (any status), or a
 * site-signed request answered `401 VERIFICATION_ERROR`, means the platform
 * does not accept this install's site credentials. Holding a secret then
 * proves nothing, so the registration tick must re-register
 * ({@see Reverification_Ledger::request_reverification()}) instead of
 * short-circuiting as "already registered".
 *
 * Both are heard on the surfaces that know about the site's registration —
 * exactly the surfaces whose requests carry the site signature, which
 * {@see Platform_Url_Matcher::is_site_signed()} owns: the platform plane
 * (`/app/v1`) and the `/v3` action surface, on the configured platform host.
 * `/v2` knows nothing of site registration and is never read; an install with
 * no platform configured has no such surface. `CLOCK_SKEW` and `REQUEST_REPLAYED`
 * are signature answers too, but re-registering fixes neither: the clock is
 * {@see Clock_Skew_Monitor}'s, the replay the injector's own resend.
 *
 * WHY `http_response`: three transports reach the platform plane —
 * {@see Client} (connection routes), {@see Platform_Http} (registration,
 * heartbeat, capability discovery) and a raw `wp_remote_get` (the OAuth
 * start). WordPress runs this filter on every response any of them receives,
 * the same way `http_request_args` is the one place they are all signed.
 *
 * "Site-signed" means the request carried this install's C2 signature, which
 * only the injector adds and only it marks with `X-Automator-Nonce`. The
 * registration and completion routes put their own proof in
 * `X-Automator-Signature` with no nonce; their `401 VERIFICATION_ERROR` is
 * about that proof, and {@see Platform_Site_Registration} handles it. Those
 * two routes are never read at all: whatever they answer is the
 * re-verification, so it can never ask for another one.
 *
 * Only an error status (400 and up) is decoded; every other response returns
 * before its body is read.
 *
 * @since 8.0.0
 */
final class Site_Credentials_Rejection_Observer {

	/**
	 * @var Reverification_Ledger
	 */
	private $ledger;

	/**
	 * @var Platform_Url_Matcher
	 */
	private $urls;

	/**
	 * @var Registration_Wire
	 */
	private $wire;

	/**
	 * @param Reverification_Ledger|null $ledger Where the re-verification this
	 *        observer asks for is recorded; production gets the shared store's.
	 * @param Platform_Url_Matcher|null  $urls   Which URLs are the platform's
	 *        surfaces; production gets the configured platform.
	 * @param Registration_Wire|null     $wire   Knows the register and
	 *        completion routes.
	 */
	public function __construct( ?Reverification_Ledger $ledger = null, ?Platform_Url_Matcher $urls = null, ?Registration_Wire $wire = null ) {

		$this->ledger = $ledger ?? new Reverification_Ledger();
		$this->urls   = $urls ?? Platform_Url_Matcher::from_constants();
		$this->wire   = $wire ?? new Registration_Wire();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {

		add_filter( 'http_response', array( $this, 'observe' ), 10, 3 );
	}

	/**
	 * Filter callback. Never alters the response.
	 *
	 * @param mixed $response WP HTTP response.
	 * @param mixed $args     WP HTTP request args, after `http_request_args`.
	 * @param mixed $url      Request URL.
	 *
	 * @return mixed The response, unchanged.
	 */
	public function observe( $response, $args, $url ) {

		if ( ! is_array( $response ) || ! is_array( $args ) || ! is_string( $url ) || ! $this->can_reject_site_credentials( $response, $url ) ) {
			return $response;
		}

		$type = $this->rejection_type( Response::from_wp_response( $response, 0.0 ), Platform_Request_Signature_Injector::carries_site_signature( $args ) );
		if ( '' === $type ) {
			return $response;
		}

		$this->ledger->request_reverification( $type );

		return $response;
	}

	/**
	 * Hear the platform's answer to a site-signed request that reached this
	 * site another way than a WordPress HTTP response: a background job's
	 * outcome, whose write went out on the `/v3` action surface signed as this
	 * site. The same answers ask for the same re-verification.
	 *
	 * @since 8.0.0
	 *
	 * @param Response $response The platform's answer.
	 *
	 * @return void
	 */
	public function hear_site_signed_answer( Response $response ): void {

		$type = $this->rejection_type( $response, true );
		if ( '' !== $type ) {
			$this->ledger->request_reverification( $type );
		}
	}

	/**
	 * Whether the response can reject this install's site credentials,
	 * decided before its body is decoded (this runs for every response
	 * WordPress receives): an error status — nothing below 400 is a
	 * rejection — from a surface that knows about this site's registration,
	 * other than the register and completion routes themselves. Their answers
	 * ARE the re-verification (wire contract W4), so they never ask for
	 * another one.
	 *
	 * @param array<string, mixed> $response WP HTTP response.
	 * @param string               $url      Request URL.
	 *
	 * @return bool
	 */
	private function can_reject_site_credentials( array $response, string $url ): bool {

		return 400 <= (int) wp_remote_retrieve_response_code( $response )
			&& $this->urls->is_site_signed( $url )
			&& ! $this->wire->is_registration_route( $url );
	}

	/**
	 * The `error.type` when the response rejects this install's site
	 * credentials, '' otherwise.
	 *
	 * @param Response $response    Parsed platform response.
	 * @param bool     $site_signed Whether the request carried this site's signature.
	 *
	 * @return string
	 */
	private function rejection_type( Response $response, bool $site_signed ): string {

		$type = $response->error_type();

		if ( 'SITE_NOT_REGISTERED' === $type ) {
			return $type;
		}

		$is_signature_rejected = 401 === $response->status_code()
			&& 'VERIFICATION_ERROR' === $type
			&& $site_signed;

		return $is_signature_rejected ? $type : '';
	}
}
