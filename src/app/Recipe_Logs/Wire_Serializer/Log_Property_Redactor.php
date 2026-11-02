<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

/**
 * Hides the credentials a "Request headers" log property carries before it
 * reaches the wire.
 *
 * Webhook-style triggers record the headers of the request that fired them
 * as one JSON object of header name => values (`WP_REST_Request::get_headers()`,
 * names lowercased with `_` for `-`). The Logs page and the MCP `get_logs`
 * tool both read a run through the recipe-log serializers, so replacing the
 * credential headers here keeps them off every reader. The other headers
 * stay readable.
 *
 * @since 8.0.0
 */
final class Log_Property_Redactor {

	/**
	 * What a hidden header value reads as.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	public const MARKER = '[REDACTED]';

	/**
	 * The label request headers are recorded under. Pro's webhook triggers write
	 * it with Free's text domain under the `Webhook` context, a string no
	 * translation file carries, so the stored label is this source text.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	private const REQUEST_HEADERS_LABEL = 'Request headers';

	/**
	 * Header names (normalized: lowercase, `-` separators) whose value is a credential.
	 *
	 * @since 8.0.0
	 *
	 * @var array<int,string>
	 */
	private const CREDENTIAL_HEADERS = array( 'authorization', 'authentication', 'proxy-authorization', 'cookie', 'set-cookie', 'x-automator-creds' );

	/**
	 * Fragments that make any header name a credential header.
	 *
	 * @since 8.0.0
	 *
	 * @var array<int,string>
	 */
	private const CREDENTIAL_FRAGMENTS = array( 'token', 'secret', 'api-key', 'apikey' );

	/**
	 * A wire property with its credential headers hidden; any property but "Request headers" comes back unchanged.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,mixed> $property Wire property (`type`, `label`, string `value`, optional `attributes`).
	 * @return array<string,mixed> The property, its credential header values replaced by the marker.
	 */
	public function redact( array $property ): array {
		if ( 0 !== strcasecmp( trim( (string) ( $property['label'] ?? '' ) ), self::REQUEST_HEADERS_LABEL ) ) {
			return $property;
		}

		$property['value'] = $this->redact_headers( (string) ( $property['value'] ?? '' ) );

		return $property;
	}

	/**
	 * Whether a header name names a credential, in either spelling WordPress or HTTP uses (`X_Api_Key`, `x-api-key`).
	 *
	 * @since 8.0.0
	 *
	 * @param string $name Header name.
	 * @return bool True when the header's value must not be shown.
	 */
	private function is_credential_header( string $name ): bool {
		$normalized = str_replace( '_', '-', strtolower( trim( $name ) ) );

		if ( in_array( $normalized, self::CREDENTIAL_HEADERS, true ) ) {
			return true;
		}

		foreach ( self::CREDENTIAL_FRAGMENTS as $fragment ) {
			if ( false !== strpos( $normalized, $fragment ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The headers JSON with each credential header's value replaced by the marker. A value that is not a JSON
	 * object has no header names to check, so the whole value is hidden.
	 *
	 * @since 8.0.0
	 *
	 * @param string $value The property value.
	 * @return string The redacted value; empty stays empty.
	 */
	private function redact_headers( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$headers = json_decode( $value );
		if ( ! $headers instanceof \stdClass ) {
			return self::MARKER;
		}

		foreach ( array_keys( get_object_vars( $headers ) ) as $name ) {
			if ( $this->is_credential_header( (string) $name ) ) {
				$headers->{$name} = self::MARKER;
			}
		}

		return (string) wp_json_encode( $headers );
	}
}
