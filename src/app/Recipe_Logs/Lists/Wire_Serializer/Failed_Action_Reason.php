<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Wire_Serializer;

use Uncanny_Automator\Resend_Action_Service;

/**
 * Why a failed App action failed: the full text, and its one-line form.
 *
 * App actions often record no error of their own, so the text falls back
 * the way the Resend App actions tool did: the action's note, then the
 * error in the stored API response, then the bare HTTP status.
 *
 * @since 8.1.0
 */
final class Failed_Action_Reason {

	/**
	 * The longest one-line reason.
	 *
	 * @var int
	 */
	const MAX_REASON_LENGTH = 300;

	/**
	 * Static-only class.
	 */
	private function __construct() {
	}

	/**
	 * The full failure text, never empty.
	 *
	 * @param string $note         The action's note: its last error, or its stored message.
	 * @param mixed  $response_raw The stored `uap_api_log.response`.
	 * @param string $http_status  The stored `uap_api_log.status`.
	 *
	 * @return string
	 */
	public static function detail( string $note, $response_raw, string $http_status ): string {
		$note = trim( $note );

		if ( '' !== $note ) {
			return $note;
		}

		$extracted = self::response_error( $response_raw );

		if ( '' !== $extracted ) {
			return $extracted;
		}

		$http_status = trim( $http_status );

		if ( '' !== $http_status ) {
			return 'HTTP ' . $http_status;
		}

		return esc_html_x( 'The action failed without saying why.', 'Logs list failed action', 'uncanny-automator' );
	}

	/**
	 * The failure text as one line for the list.
	 *
	 * @param string $detail The full failure text.
	 *
	 * @return string
	 */
	public static function reason( string $detail ): string {
		$line = trim( (string) preg_replace( '/\s+/u', ' ', $detail ) );

		if ( function_exists( 'mb_strlen' ) && mb_strlen( $line ) > self::MAX_REASON_LENGTH ) {
			return rtrim( mb_substr( $line, 0, self::MAX_REASON_LENGTH - 1 ) ) . '…';
		}

		return $line;
	}

	/**
	 * The first meaningful error in a stored API response: the decoded array's
	 * error description or message, else a serialized description or message
	 * in the raw value (a legacy response object).
	 *
	 * @param mixed $response_raw The stored response.
	 *
	 * @return string Empty when there is none.
	 */
	private static function response_error( $response_raw ): string {
		$decoded = Resend_Action_Service::decode_api_log_value( $response_raw );

		if ( is_array( $decoded ) ) {
			$candidates = array(
				$decoded['error']['description'] ?? null,
				$decoded['error']['message'] ?? null,
				$decoded['message'] ?? null,
				$decoded['data']['error'] ?? null,
			);

			foreach ( $candidates as $candidate ) {
				if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
					return trim( $candidate );
				}
			}
		}

		if ( is_string( $response_raw ) && '' !== $response_raw ) {
			foreach ( array( '/"description";s:\d+:"([^"]*)"/', '/"message";s:\d+:"([^"]*)"/' ) as $pattern ) {
				if ( 1 === preg_match( $pattern, $response_raw, $matches ) && '' !== trim( $matches[1] ) ) {
					return trim( $matches[1] );
				}
			}
		}

		return '';
	}
}
