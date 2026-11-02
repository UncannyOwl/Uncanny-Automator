<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * C2 full-request signature for the signed connection routes (site identity
 * spec). Mirrors the platform's `App\Support\ConnectionRequestSignature`
 * byte-for-byte: signs the WHOLE request — method, canonical target, body,
 * timestamp, nonce — with the per-site signing secret, so a caller holding a
 * license key alone cannot forge, tamper with, or replay a connection request.
 *
 * Signing string:
 *   v1:{timestamp}:{nonce}:{UPPER_METHOD}:{canonical_target}:{lowercase_sha256(raw_body)}
 *
 * HMAC-SHA256 over the base64-DECODED site secret → lowercase hex (the same
 * convention as Hmac_Validator's decode_secret), transmitted as
 * `X-Automator-Signature: v1=<hex>`. Pure: no store, no clock — the transport
 * (Client::send) feeds it the finalized request pieces after all body filters.
 *
 * @since 8.0.0
 */
final class Connection_Request_Signer {

	/**
	 * Lowercase-hex SHA-256 of the raw body ('' hashes the empty string).
	 *
	 * @param string $raw_body The exact transmitted body bytes.
	 * @return string
	 */
	public function body_hash( string $raw_body ): string {

		return hash( 'sha256', $raw_body );
	}

	/**
	 * RFC-3986 canonical target: path, plus a canonical query when present.
	 *
	 * The query is built from the RAW pairs (never a parsed map): each pair is
	 * split on its first `=`, decoded and re-encoded to a single RFC-3986 form,
	 * then the pairs are sorted by encoded key then value with duplicates
	 * preserved. Identical transform on both sides.
	 *
	 * @param string $path      The request path (leading slash).
	 * @param string $raw_query The raw query string (no leading `?`).
	 * @return string
	 */
	public function canonical_target( string $path, string $raw_query ): string {

		if ( '' === $raw_query ) {
			return $path;
		}

		$pairs = array();
		foreach ( explode( '&', $raw_query ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}

			$parts   = array_pad( explode( '=', $pair, 2 ), 2, '' );
			$pairs[] = array( rawurlencode( rawurldecode( $parts[0] ) ), rawurlencode( rawurldecode( $parts[1] ) ) );
		}

		usort(
			$pairs,
			static function ( array $a, array $b ): int {
				return $a[0] === $b[0] ? strcmp( $a[1], $b[1] ) : strcmp( $a[0], $b[0] );
			}
		);

		$canonical = implode(
			'&',
			array_map(
				static function ( array $p ): string {
					return $p[0] . '=' . $p[1];
				},
				$pairs
			)
		);

		return $path . '?' . $canonical;
	}

	/**
	 * The exact string that is HMAC'd.
	 *
	 * @param string $method          HTTP method.
	 * @param string $canonical_target Canonical target.
	 * @param string $raw_body        Transmitted body bytes.
	 * @param string $timestamp       Unix seconds.
	 * @param string $nonce           base64url nonce.
	 * @return string
	 */
	public function signing_string( string $method, string $canonical_target, string $raw_body, string $timestamp, string $nonce ): string {

		return implode(
			':',
			array(
				'v1',
				$timestamp,
				$nonce,
				strtoupper( $method ),
				$canonical_target,
				$this->body_hash( $raw_body ),
			)
		);
	}

	/**
	 * Lowercase-hex HMAC-SHA256 over the raw (base64-decoded) site-secret bytes.
	 *
	 * @param string $secret_b64       Base64 site signing secret.
	 * @param string $method           HTTP method.
	 * @param string $canonical_target Canonical target.
	 * @param string $raw_body         Transmitted body bytes.
	 * @param string $timestamp        Unix seconds.
	 * @param string $nonce            base64url nonce.
	 * @return string
	 */
	public function sign( string $secret_b64, string $method, string $canonical_target, string $raw_body, string $timestamp, string $nonce ): string {

		$raw = base64_decode( $secret_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding the base64 wire secret to raw HMAC key bytes.

		return hash_hmac( 'sha256', $this->signing_string( $method, $canonical_target, $raw_body, $timestamp, $nonce ), false === $raw ? '' : $raw );
	}
}
