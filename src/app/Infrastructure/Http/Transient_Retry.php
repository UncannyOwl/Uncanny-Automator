<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Http;

/**
 * Class Transient_Retry
 *
 * Wraps wp_remote_request() with a small, bounded retry for transient
 * transport failures that PROVE the request never reached the Automator
 * Platform application:
 *
 * - Connection-class WP_Error: DNS resolution failure, refused/failed TCP
 *   connect, connect-phase timeout, SSL connect failure. No bytes left the
 *   site, so nothing executed server-side.
 * - Cloudflare origin-unreachable statuses: 521, 522, 523, 525, 526. The
 *   edge could not hand the request to the origin at all — the same set
 *   Cloudflare's own zero-downtime failover retries for exactly this
 *   reason.
 *
 * Because the request provably never executed, a retry can never
 * double-run a non-idempotent /v2 action — which is what makes this safe
 * to apply to EVERY platform call, POSTs included.
 *
 * Deliberately NOT retried (the request may already have executed):
 * - Read/operation timeouts (cURL 28 "Operation timed out ..."), where the
 *   request was sent and the response never arrived.
 * - Cloudflare 520 (ambiguous) and 524 (origin accepted, then timed out).
 * - Any HTTP response produced by the application itself (4xx/5xx).
 *
 * @since 7.4.1
 * @package Uncanny_Automator\App\Infrastructure\Http
 */
class Transient_Retry {

	/**
	 * Cloudflare statuses proving the origin never received the request.
	 *
	 * @var int[]
	 */
	const RETRYABLE_STATUS_CODES = array( 521, 522, 523, 525, 526 );

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
	 * @param string $url  The request URL.
	 * @param array  $args The WordPress HTTP API arguments.
	 *
	 * @return array|\WP_Error The final response (or last transport error).
	 */
	public static function request( string $url, array $args ) {

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

			/**
			 * Fires before a transient-failure retry attempt.
			 *
			 * @param int             $attempt  The retry number (1-based).
			 * @param int             $delay    Seconds slept before this retry.
			 * @param array|\WP_Error $response The failed response being retried.
			 * @param string          $url      The request URL.
			 */
			do_action( 'automator_api_transient_retry', $index + 1, (int) $delay, $response, $url );

			if ( function_exists( 'automator_log' ) ) {
				automator_log(
					sprintf( 'Transient transport failure; retry %d of %d after %ds: %s', $index + 1, count( $delays ), (int) $delay, self::describe( $response ) ),
					'Transient_Retry'
				);
			}

			if ( 0 < (int) $delay ) {
				sleep( (int) $delay ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_sleep -- Bounded (max sum of the filtered schedule); bridging a seconds-long platform front-door blip is the entire point.
			}

			$response = wp_remote_request( $url, $args );
		}

		return $response;
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
			return (string) $response->get_error_message();
		}

		return 'HTTP ' . (int) wp_remote_retrieve_response_code( $response );
	}
}
