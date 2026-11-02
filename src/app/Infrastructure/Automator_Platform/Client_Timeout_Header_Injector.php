<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Infrastructure\Http\Request_Headers;

/**
 * Declares, on every request to the Automator platform, how long this client
 * will wait for the answer — so the platform can refuse work its client has
 * already abandoned.
 *
 * WHY: the platform queues a request when all of its workers are busy, and a
 * queued request runs whenever a worker frees up — even after the client's
 * timeout has passed. The vendor write lands and the credit is charged behind
 * a plugin that logged the call as failed (and may run it again). The platform
 * refuses a request, before any side effect, once its queue wait exceeds the
 * deadline the client declared. Only the client knows that deadline: `/v2`
 * waits 30s before 7.6.0 and 60s from 7.6.0 (`automator_api_timeout`), the
 * platform plane 15s ({@see Platform_Http::TIMEOUT_SECONDS}), Facebook Lead
 * Ads' `get_lead` 30s, and a site can filter any of them. Guessing from
 * `plugin_ver` sees none of that, and installs live for years — the control
 * has to ship in the client.
 *
 * Wire contract (the platform's queue-deadline middleware reads exactly this):
 *  - `X-Automator-Client-Timeout: <seconds>` — the request's effective
 *    `timeout` arg rounded UP to whole seconds, as a decimal string, so any
 *    positive timeout declares at least 1 (a value past PHP_INT_MAX, or INF,
 *    caps at PHP_INT_MAX; the platform clamps what it enforces).
 *  - On every request under `AUTOMATOR_API_URL` — `/v2`, `/v3`, `/app/v1` —
 *    licensed or not. Never on another host ({@see Platform_Url_Matcher::is_platform()}).
 *  - A caller that set the header itself (any case) wins; it is left alone.
 *  - A `timeout` that sets no deadline declares nothing, and the platform
 *    falls back to what it can infer:
 *     - 0 or below: to cURL that means "no timeout", so the caller waits
 *       indefinitely. Declaring 1 second would let the platform refuse a
 *       request its caller is still waiting for.
 *     - not a number (absent, null, NAN, a non-numeric string): WP_Http
 *       always supplies one, so this only happens when another filter broke
 *       the args.
 *
 * WHY `http_request_args` and not the callers: four transports reach the
 * platform (Api_Server, {@see Client}, {@see Platform_Http}, raw
 * `wp_remote_*`), and each `wp_remote_request()` call runs the filter — so
 * every Transient_Retry attempt declares it, from the args as that attempt
 * sends them.
 *
 * WHY priority PHP_INT_MAX: the declared value must be the timeout the
 * transport will use. WP_Http resolves the default (`http_request_timeout`)
 * BEFORE `http_request_args`, so only a later `http_request_args` callback can
 * still change it; running last reads the final value. Order against
 * {@see Platform_Request_Signature_Injector} (10000) does not matter: its C2
 * signature covers timestamp, nonce, method, canonical target and body hash —
 * no header — so a header added after signing cannot invalidate it.
 *
 * @since 8.0.0
 */
final class Client_Timeout_Header_Injector {

	/**
	 * The header the platform reads the client's deadline from.
	 *
	 * @var string
	 */
	const HEADER = 'X-Automator-Client-Timeout';

	/**
	 * @var Platform_Url_Matcher
	 */
	private Platform_Url_Matcher $urls;

	/**
	 * @param Platform_Url_Matcher|null $urls Which URLs are the platform.
	 *        Production passes null and gets the configured platform.
	 */
	public function __construct( ?Platform_Url_Matcher $urls = null ) {

		$this->urls = $urls ?? Platform_Url_Matcher::from_constants();
	}

	/**
	 * Register the `http_request_args` filter last (PHP_INT_MAX), after every
	 * callback that could still change the timeout.
	 *
	 * @return void
	 */
	public function register_hooks(): void {

		add_filter( 'http_request_args', array( $this, 'maybe_inject' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Filter callback: add the declared timeout to a platform request that
	 * does not carry one yet.
	 *
	 * Defensive about input shape: any non-array `$args` or non-string `$url`
	 * is returned as-is so foreign callers don't trigger type errors.
	 *
	 * @param array<string, mixed>|mixed $args WP HTTP request args.
	 * @param string|mixed               $url  Outbound request URL.
	 *
	 * @return array<string, mixed>|mixed Modified args, or the input verbatim.
	 */
	public function maybe_inject( $args, $url ) {

		if ( ! is_array( $args ) || ! is_string( $url ) || ! $this->urls->is_platform( $url ) ) {
			return $args;
		}

		$headers = Request_Headers::from_args( $args );
		if ( Request_Headers::has( $headers, self::HEADER ) ) {
			return $args;
		}

		$seconds = self::declared_seconds( $args['timeout'] ?? null );
		if ( null === $seconds ) {
			return $args;
		}

		$headers[ self::HEADER ] = (string) $seconds;
		$args['headers']         = $headers;

		return $args;
	}

	/**
	 * The whole seconds to declare for a `timeout` arg: rounded up, at most
	 * PHP_INT_MAX, or null when the timeout sets no deadline.
	 *
	 * An int is used as-is so PHP_INT_MAX never takes a lossy trip through a
	 * float. A positive float rounds up to at least 1, and is capped BEFORE the
	 * int cast: casting one past the int range (INF, 1e30) is undefined in PHP.
	 *
	 * @param mixed $timeout The `timeout` arg as WP_Http will use it.
	 *
	 * @return int|null Null when the timeout sets no deadline: not a number,
	 *                  or 0 or below (cURL: wait indefinitely).
	 */
	private static function declared_seconds( $timeout ): ?int {

		if ( is_int( $timeout ) ) {
			return 0 < $timeout ? $timeout : null;
		}

		if ( ! is_numeric( $timeout ) ) {
			return null;
		}

		$timeout = (float) $timeout;

		if ( is_nan( $timeout ) || 0.0 >= $timeout ) {
			return null;
		}

		$seconds = ceil( $timeout );

		if ( (float) PHP_INT_MAX <= $seconds ) {
			return PHP_INT_MAX;
		}

		return (int) $seconds;
	}
}
