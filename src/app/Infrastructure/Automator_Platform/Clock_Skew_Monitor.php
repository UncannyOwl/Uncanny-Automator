<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * How far this server's clock is from the platform's — one record of "this
 * site's clock is off", whichever direction measured it.
 *
 * Both directions of the v1 signature carry a timestamp, and each side
 * refuses one too far from its own clock: the platform answers this site's
 * request `401 CLOCK_SKEW` past {@see Hmac_Validator::SKEW_SECONDS} (platform
 * answers contract 4.2), and this site's callback receiver answers the
 * platform's delivery `401 SIGNATURE_REJECTED` `timestamp_skew` past
 * {@see Hmac_Validator::allowance_seconds()} — the same 300 seconds, unless
 * the site tightened it (callback delivery contract §4). Re-registering fixes
 * neither, and neither does sending again: only the server clock does. So the
 * difference is kept for an admin notice
 * ({@see \Uncanny_Automator\App\Presentation\Admin\Clock_Skew_Notice}).
 *
 * Two producers feed the same record through the same two verbs,
 * {@see refused()} and {@see accepted()}:
 *
 *  - Outbound, on `http_response`: the answer's HTTP `Date` header — the
 *    platform's clock — compared with this server's. WHY `http_response`:
 *    like the signature itself ({@see Platform_Request_Signature_Injector}),
 *    it hears every transport — {@see Client}, {@see Platform_Http} and the
 *    raw OAuth start.
 *  - Inbound, from the callback receiver
 *    ({@see Webhooks\Integrations_Webhook_Controller}): this site's clock less
 *    the timestamp the platform signed, measured only on a delivery whose
 *    signature verified (an unsigned timestamp measures nothing).
 *
 * The measurement clears itself, so the notice can neither stick nor flap:
 *
 *  - The next signed exchange the other side accepts — a site-signed request
 *    the platform answers below 400, or a delivery the receiver verifies —
 *    clears it when it measures the clock inside {@see clear_within_seconds()}
 *    (four fifths of the allowance deliveries get), or measures nothing (no
 *    usable `Date`). The record stands while either side would refuse, so it
 *    clears against the stricter allowance: a request the platform accepts
 *    while this site, having tightened its own, still refuses deliveries,
 *    keeps it.
 *  - An accepted exchange still within that last fifth of the limit keeps it,
 *    with the new measurement: a clock that close is accepted on one exchange
 *    and refused on the next, and the notice must not flap between the two.
 *  - Whatever accepted exchanges measure, it is not shown more than
 *    {@see self::STALE_AFTER_SECONDS} after the last refusal for the clock:
 *    exchanges that keep being accepted cannot hold it up for ever. Each
 *    refusal starts that day again.
 *
 * Answers that say nothing about the clock (the license layer, the other
 * signature refusals, any surface the signature does not cover) leave it be.
 *
 * @since 8.0.0
 */
final class Clock_Skew_Monitor {

	/**
	 * Option holding the last measurement:
	 * `{seconds: int|null, measured_at: int, refused_at: int}` — the measured
	 * difference, when it was measured, and when a signed exchange was last
	 * refused for the clock.
	 */
	public const OPTION = 'automator_platform_clock_skew';

	/**
	 * The notice is not shown longer than this after the last refusal.
	 */
	public const STALE_AFTER_SECONDS = DAY_IN_SECONDS;

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

		if ( ! is_array( $response ) || ! is_array( $args ) || ! is_string( $url ) ) {
			return $response;
		}

		if ( ! Platform_Request_Signature_Injector::is_site_signed_url( $url ) || ! Platform_Request_Signature_Injector::carries_site_signature( $args ) ) {
			return $response;
		}

		if ( 400 > (int) wp_remote_retrieve_response_code( $response ) ) {
			$this->accepted( self::difference( $response ) );
			return $response;
		}

		if ( 'CLOCK_SKEW' === Response::from_wp_response( $response, 0.0 )->error_type() ) {
			$this->refused( self::difference( $response ) );
		}

		return $response;
	}

	/**
	 * A signed exchange was refused for the clock: record the difference and
	 * start the {@see self::STALE_AFTER_SECONDS} day again.
	 *
	 * @param int|null $seconds This server's clock less the other side's
	 *                          (negative when behind); null when unknown.
	 *
	 * @return void
	 */
	public function refused( ?int $seconds ): void {

		$this->record( $seconds, time() );
	}

	/**
	 * A signed exchange was accepted: clear the measurement once the clock is
	 * well inside the allowance (or the exchange measured nothing), or refresh
	 * it — keeping when the clock was last refused — while it is still near
	 * the limit. Nothing is read further, and nothing written, when no
	 * measurement is held.
	 *
	 * @param int|null $seconds This server's clock less the other side's
	 *                          (negative when behind); null when unknown.
	 *
	 * @return void
	 */
	public function accepted( ?int $seconds ): void {

		$record = automator_get_option( self::OPTION, null );

		if ( ! is_array( $record ) ) {
			return;
		}

		if ( null === $seconds || abs( $seconds ) <= self::clear_within_seconds() ) {
			automator_delete_option( self::OPTION );
			return;
		}

		$this->record( $seconds, (int) ( $record['refused_at'] ?? 0 ) );
	}

	/**
	 * An accepted exchange measuring the clock this close clears the record:
	 * four fifths of the allowance this site gives deliveries
	 * ({@see Hmac_Validator::allowance_seconds()}) — 240 of the default 300
	 * seconds — so a clock right at the limit does not flap the notice.
	 *
	 * That allowance, not the platform's: it is never wider
	 * ({@see Hmac_Validator::SKEW_SECONDS} is its ceiling), so it is the
	 * stricter of the two, and a clock inside it is accepted both ways.
	 * Proportional, so a tightened allowance keeps a margin of its own.
	 *
	 * @return int Seconds.
	 */
	private static function clear_within_seconds(): int {

		return intdiv( 4 * Hmac_Validator::allowance_seconds(), 5 );
	}

	/**
	 * Seconds this server's clock is ahead of the clock that stamped a
	 * response's `Date` header (negative when behind), or null when the
	 * response carries no usable date.
	 *
	 * @param array|\WP_Error $wp_response WP HTTP response.
	 *
	 * @return int|null
	 */
	public static function difference( $wp_response ): ?int {

		$date   = wp_remote_retrieve_header( $wp_response, 'date' );
		$server = is_string( $date ) && '' !== $date ? strtotime( $date ) : false;

		return false === $server ? null : time() - $server;
	}

	/**
	 * The measurement to show, or null when there is none or the last refusal
	 * is more than {@see self::STALE_AFTER_SECONDS} old.
	 *
	 * @return array{seconds: int|null, measured_at: int, refused_at: int}|null
	 */
	public function recorded(): ?array {

		$record = automator_get_option( self::OPTION, array() );

		if ( ! is_array( $record ) || ! isset( $record['measured_at'], $record['refused_at'] ) ) {
			return null;
		}

		if ( time() - (int) $record['refused_at'] > self::STALE_AFTER_SECONDS ) {
			return null;
		}

		return array(
			'seconds'     => isset( $record['seconds'] ) ? (int) $record['seconds'] : null,
			'measured_at' => (int) $record['measured_at'],
			'refused_at'  => (int) $record['refused_at'],
		);
	}

	/**
	 * @param int|null $seconds    The measured difference, null when unknown.
	 * @param int      $refused_at When a signed exchange was last refused for the clock.
	 *
	 * @return void
	 */
	private function record( ?int $seconds, int $refused_at ): void {

		automator_update_option(
			self::OPTION,
			array(
				'seconds'     => $seconds,
				'measured_at' => time(),
				'refused_at'  => $refused_at,
			),
			false
		);
	}
}
