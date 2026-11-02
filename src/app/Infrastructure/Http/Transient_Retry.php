<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Http;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Response;

/**
 * Retry a bounded set of connection-stage failures reported by WordPress.
 *
 * Wraps wp_remote_request() with a small, bounded retry for transient
 * transport failures that PROVE the request never reached the Automator
 * Platform application: connection-class WP_Error only — DNS resolution
 * failure, refused/failed TCP connect, connect-phase timeout, SSL connect
 * failure. No bytes left the site, so nothing executed server-side, which is
 * what makes this safe to apply to every platform call, POSTs included.
 *
 * HTTP responses, including Cloudflare's 52x statuses, do not establish
 * whether an action ran, so none is replayed automatically. Read/operation
 * timeouts (cURL 28 "Operation timed out ...") are not retried either: the
 * request was sent and the response never arrived. Site owners may still
 * override the retry policy through the existing filter.
 *
 * Retry-After, when the caller asks for it: a surface that refuses a request
 * BEFORE acting on it and says when to come back — `429` (a throttle) or
 * `503` (a dependency down) with a numeric `Retry-After` — is waited out once,
 * when the wait is short ({@see self::RETRY_AFTER_MAX_WAIT_SECONDS}). A longer
 * wait is not held in the request: the refusal goes back to the caller. Only
 * the platform's site-signed surfaces ask (`/v3`, `/app/v1`; platform answers
 * contract 4.1); every `/v2` call keeps the transport-only policy.
 *
 * @since 7.4.1
 * @package Uncanny_Automator\App\Infrastructure\Http
 */
class Transient_Retry {

	/**
	 * No HTTP status alone proves that an action was not executed.
	 *
	 * @var int[]
	 */
	const RETRYABLE_STATUS_CODES = array();

	/**
	 * Statuses that refuse a request before it acts and say, in Retry-After,
	 * when to come back.
	 *
	 * @var int[]
	 */
	const RETRY_AFTER_STATUS_CODES = array( 429, 503 );

	/**
	 * The longest Retry-After waited out inside the request, in seconds. It
	 * stays well inside the client's 60-second timeout and PHP's usual
	 * 30-second limit, so a short throttle costs one wait, not the step.
	 *
	 * @var int
	 */
	const RETRY_AFTER_MAX_WAIT_SECONDS = 10;

	/**
	 * URL fragments that are never retried. v2/credits fires multiple times
	 * per recipe evaluation — during a platform outage, retrying it would
	 * hold a PHP worker through the full schedule PER CHECK and stack across
	 * every concurrent recipe on the site, unlike an action call which waits
	 * at most once per action. It also has its own client-side fallback, so
	 * a failed check self-heals on the next one: fail fast, retry nothing.
	 *
	 * @var string[]
	 */
	const NON_RETRYABLE_URL_FRAGMENTS = array( 'v2/credits' );

	/**
	 * Lower-cased WP_Error message fragments that identify connection-class
	 * transport failures (request never sent). "Connection timed out after"
	 * is cURL's connect-phase wording; the read-phase wording is "Operation
	 * timed out after ..." and is deliberately absent here.
	 *
	 * @var string[]
	 */
	const RETRYABLE_ERROR_FRAGMENTS = array(
		'could not resolve host',
		'failed to connect',
		'connection refused',
		'connection timed out after',
		'ssl connect error',
	);

	/**
	 * Drop-in replacement for wp_remote_request() with transient-failure
	 * retries. On non-retryable outcomes (including every response produced
	 * by the application) it behaves exactly like a single call.
	 *
	 * @param string $url                The request URL.
	 * @param array  $args               The WordPress HTTP API arguments.
	 * @param bool   $honour_retry_after Wait out a short Retry-After on a 429 or
	 *                                   503, once. Only for a surface that sends
	 *                                   those before acting on the request.
	 *
	 * @return array|\WP_Error The final response (or last transport error).
	 */
	public static function request( string $url, array $args, bool $honour_retry_after = false ) {

		$response = self::request_with_transport_retries( $url, $args );

		if ( ! $honour_retry_after ) {
			return $response;
		}

		$wait = self::short_retry_after( $response, $url );
		if ( 0 === $wait ) {
			return $response;
		}

		self::wait( 'Retry-After', 1, $wait, $response, $url, 1 );

		return self::request_with_transport_retries( $url, $args );
	}

	/**
	 * Send, retrying only the provably-unsent transport failures.
	 *
	 * @param string $url  The request URL.
	 * @param array  $args The WordPress HTTP API arguments.
	 *
	 * @return array|\WP_Error The final response (or last transport error).
	 */
	private static function request_with_transport_retries( string $url, array $args ) {

		// Fire-and-forget requests return before an outcome exists — nothing
		// to inspect, nothing safe to retry.
		if ( isset( $args['blocking'] ) && false === $args['blocking'] ) {
			return wp_remote_request( $url, $args );
		}

		// Excluded endpoints fail fast (see NON_RETRYABLE_URL_FRAGMENTS).
		foreach ( self::NON_RETRYABLE_URL_FRAGMENTS as $fragment ) {
			if ( false !== strpos( $url, $fragment ) ) {
				return wp_remote_request( $url, $args );
			}
		}

		/**
		 * Filter the retry delay schedule (seconds between attempts).
		 *
		 * Each entry is one retry: the default array( 2, 5, 10 ) means up to
		 * three retries — after 2s, 5 more, then 10 more — sized to bridge a
		 * platform front-door blip including its ~10s worst case. Return an
		 * empty array to disable retrying entirely.
		 *
		 * @param int[]  $delays The delay schedule in seconds.
		 * @param string $url    The request URL.
		 */
		$delays = apply_filters( 'automator_api_transient_retry_delays', array( 2, 5, 10 ), $url );
		$delays = is_array( $delays ) ? array_values( $delays ) : array();

		$response = wp_remote_request( $url, $args );

		foreach ( $delays as $index => $delay ) {

			if ( ! self::is_retryable( $response, $url ) ) {
				return $response;
			}

			self::wait( 'Transient transport failure', $index + 1, (int) $delay, $response, $url, count( $delays ) );

			$response = wp_remote_request( $url, $args );
		}

		return $response;
	}

	/**
	 * Announce, log and sleep before a retry.
	 *
	 * @param string          $reason   Why the request is sent again, for the log.
	 * @param int             $attempt  The retry number (1-based).
	 * @param int             $delay    Seconds to sleep.
	 * @param array|\WP_Error $response The failed response being retried.
	 * @param string          $url      The request URL.
	 * @param int             $of       How many retries the schedule allows.
	 *
	 * @return void
	 */
	private static function wait( string $reason, int $attempt, int $delay, $response, string $url, int $of ): void {

		/**
		 * Fires before a retry attempt: a transient transport failure, or a
		 * short Retry-After the caller asked to have waited out.
		 *
		 * @param int             $attempt  The retry number (1-based).
		 * @param int             $delay    Seconds slept before this retry.
		 * @param array|\WP_Error $response The failed response being retried.
		 * @param string          $url      The request URL.
		 */
		do_action( 'automator_api_transient_retry', $attempt, $delay, $response, $url );

		if ( function_exists( 'automator_log' ) ) {
			automator_log(
				sprintf( '%s; retry %d of %d after %ds: %s', $reason, $attempt, $of, $delay, self::describe( $response ) ),
				'Transient_Retry'
			);
		}

		if ( 0 < $delay ) {
			sleep( $delay ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_sleep -- Bounded: the filtered transport schedule, or one Retry-After no longer than RETRY_AFTER_MAX_WAIT_SECONDS.
		}
	}

	/**
	 * The Retry-After to wait out before sending again, or 0 when the answer
	 * is not a refusal that asks for one, or asks for a longer wait than a
	 * request should hold.
	 *
	 * @param array|\WP_Error $response The wp_remote_request() result.
	 * @param string          $url      The request URL.
	 *
	 * @return int Seconds.
	 */
	private static function short_retry_after( $response, string $url ): int {

		if ( is_wp_error( $response ) || ! in_array( (int) wp_remote_retrieve_response_code( $response ), self::RETRY_AFTER_STATUS_CODES, true ) ) {
			return 0;
		}

		/**
		 * Filter the longest Retry-After waited out inside a request.
		 *
		 * @param int    $seconds Default RETRY_AFTER_MAX_WAIT_SECONDS.
		 * @param string $url     The request URL.
		 */
		$max_wait = (int) Dispatcher::filter( 'automator_api_retry_after_max_wait', self::RETRY_AFTER_MAX_WAIT_SECONDS, $url );
		$wait     = Response::retry_after_seconds( $response );

		return 0 < $wait && $wait <= $max_wait ? $wait : 0;
	}

	/**
	 * Whether a response is a provably-unsent transient failure.
	 *
	 * @param array|\WP_Error $response The wp_remote_request() result.
	 * @param string          $url      The request URL.
	 *
	 * @return bool
	 */
	private static function is_retryable( $response, string $url ): bool {

		$retryable = false;

		if ( is_wp_error( $response ) ) {
			$message = strtolower( (string) $response->get_error_message() );
			foreach ( self::RETRYABLE_ERROR_FRAGMENTS as $fragment ) {
				if ( false !== strpos( $message, $fragment ) ) {
					$retryable = true;
					break;
				}
			}
		} else {
			$retryable = in_array( (int) wp_remote_retrieve_response_code( $response ), self::RETRYABLE_STATUS_CODES, true );
		}

		/**
		 * Filter whether a failed response is retryable.
		 *
		 * The default only ever allows provably-unsent failures; forcing
		 * true for statuses the application produced (e.g. 5xx) risks
		 * double-executing non-idempotent actions — know what you are doing.
		 *
		 * @param bool            $retryable Whether the failure will be retried.
		 * @param array|\WP_Error $response  The wp_remote_request() result.
		 * @param string          $url       The request URL.
		 */
		return (bool) apply_filters( 'automator_api_transient_retry_is_retryable', $retryable, $response, $url );
	}

	/**
	 * One-line description of a failed response for the log.
	 *
	 * @param array|\WP_Error $response The wp_remote_request() result.
	 *
	 * @return string
	 */
	private static function describe( $response ): string {

		if ( is_wp_error( $response ) ) {
			$code = (string) $response->get_error_code();
			return preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $code ) ? 'WordPress HTTP error: ' . $code : 'WordPress HTTP error';
		}

		return 'HTTP ' . (int) wp_remote_retrieve_response_code( $response );
	}
}
