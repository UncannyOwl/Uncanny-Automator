<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Http;

/**
 * Safe support evidence for failed WordPress HTTP requests.
 *
 * @since 7.7.0
 */
final class Transport_Failure {

	/**
	 * Keep the transport code, but never log credentials, URLs or request bodies.
	 *
	 * @param \WP_Error $error The WordPress HTTP API failure.
	 * @return string Plain-text recovery message for the caller.
	 */
	public static function message( \WP_Error $error ): string {
		$code = (string) $error->get_error_code();
		$code = preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $code ) ? $code : 'http_request_failed';
		$curl = null;
		if ( preg_match( '/^cURL error ([0-9]{1,3}):/', substr( (string) $error->get_error_message(), 0, 256 ), $matches ) ) {
			$curl = (int) $matches[1];
		}
		$causes      = array(
			6  => 'the destination hostname could not be resolved',
			7  => 'the connection to the destination could not be opened',
			28 => 'the request timed out before a complete response arrived',
			35 => 'the secure connection could not be established',
			60 => 'the destination HTTPS certificate could not be verified',
		);
		$reason      = $causes[ $curl ] ?? 'the HTTP request failed before a complete response arrived';
		$reference   = wp_generate_uuid4();
		$observed_at = gmdate( 'Y-m-d\TH:i:s\Z' );
		$details     = array(
			'event'       => 'automator_platform_transport_failed',
			'code'        => $code,
			'curl_code'   => $curl,
			'reference'   => $reference,
			'observed_at' => $observed_at,
			'outcome'     => 'unknown',
		);
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Bounded support diagnostics; no request or exception text is logged.
		error_log( (string) wp_json_encode( $details ) );

		return sprintf(
			'Could not complete the request to the Automator App Platform: %s (%s%s). Ask the hosting provider to check outbound connections, DNS and HTTPS from this WordPress site. Check Automator > Logs and the destination service before repeating the action. Reference: %s. Time (UTC): %s.',
			$reason,
			$code,
			null === $curl ? '' : ', cURL ' . $curl,
			$reference,
			$observed_at
		);
	}
}
