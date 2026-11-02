<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Minimal JSON HTTP transport for platform-side calls that don't need the
 * full {@see Client} request/response pipeline.
 *
 * Used by {@see Platform_Site_Registration} and
 * {@see Site_Heartbeat_Cron} to POST tiny JSON payloads without the
 * license-header injection, api-log persistence, or event dispatching the
 * main Client performs. Kept as an injectable collaborator so tests can
 * replace it without mocking WordPress HTTP.
 *
 * @since 8.0.0
 */
final class Platform_Http {

	/**
	 * Seconds a request may take before WordPress gives up on it.
	 *
	 * @since 8.0.0
	 */
	public const TIMEOUT_SECONDS = 15;

	/**
	 * POST a JSON body and return the decoded response array.
	 *
	 * Returns an empty array on transport error; callers should treat an
	 * empty result as "platform unreachable" rather than a null response.
	 *
	 * @param string                $url     Full URL to POST to.
	 * @param array<string, mixed>  $body    Payload (will be wp_json_encoded).
	 * @param array<string, string> $headers Extra request headers.
	 *
	 * @return array<string, mixed>
	 */
	public function post( string $url, array $body, array $headers = array() ): array {

		if ( ! self::platform_enabled() ) {
			return array();
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array_merge(
					array( 'Content-Type' => 'application/json' ),
					$headers
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => self::TIMEOUT_SECONDS,
			)
		);

		return $this->decode( $response );
	}

	/**
	 * GET a JSON resource and return the decoded response array.
	 *
	 * Returns an empty array on transport error; callers should treat an
	 * empty result as "platform unreachable" rather than a null response.
	 *
	 * @param string                $url     Full URL to GET.
	 * @param array<string, string> $headers Request headers (e.g. license headers).
	 *
	 * @return array<string, mixed>
	 */
	public function get( string $url, array $headers = array() ): array {

		if ( ! self::platform_enabled() ) {
			return array();
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'timeout' => self::TIMEOUT_SECONDS,
			)
		);

		return $this->decode( $response );
	}

	/**
	 * The plugin-side kill switch for the platform plane: one mitigation support
	 * can hand a customer. Off stops every direct platform call this transport
	 * makes — registration, the C1 completion poll, the heartbeat, capability
	 * discovery — and reads to each caller as "platform unreachable", a state
	 * they all already handle.
	 *
	 * @return bool
	 */
	public static function platform_enabled(): bool {

		/**
		 * Filter whether this site talks to the Automator platform plane.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'automator_platform_enabled', true );
	}

	/**
	 * Decode a WP HTTP response into the platform envelope array.
	 *
	 * Callers classify on the envelope's `statusCode`. A reply WITHOUT an
	 * envelope (a proxy 502, a WAF page, a bare framework error) used to decode
	 * to an empty array — status 0, indistinguishable from a timeout — so the
	 * real HTTP status is filled in when the body carries none. The envelope's
	 * own status always wins.
	 *
	 * A numeric `Retry-After` is surfaced as `retryAfter` (seconds) so the
	 * platform can pace its clients. The HTTP-date form is ignored rather than
	 * misread — the platform only ever sends seconds.
	 *
	 * @param array<string, mixed>|\WP_Error $response WP HTTP response.
	 *
	 * @return array<string, mixed> Empty on transport error.
	 */
	private function decode( $response ): array {

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();

		$http_status = (int) wp_remote_retrieve_response_code( $response );
		if ( ! isset( $decoded['statusCode'] ) && $http_status > 0 ) {
			$decoded['statusCode'] = $http_status;
		}

		$retry_after = Response::retry_after_seconds( $response );
		if ( 0 < $retry_after ) {
			$decoded['retryAfter'] = $retry_after;
		}

		return $decoded;
	}
}
