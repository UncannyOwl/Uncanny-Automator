<?php

namespace Uncanny_Automator\Integrations\Stripe;

abstract class WebhookVerificator {

	const EXPECTED_SCHEME = 'v1';

	private static $is_hash_equals_available = null;

	/**
	 * Verifies the signature header sent by Stripe. Throws an
	 * Exception\SignatureVerificationException exception if the verification fails for
	 * any reason.
	 *
	 * @param string $payload the payload sent by Stripe
	 * @param string $header the contents of the signature header sent by
	 *  Stripe
	 * @param string $secret secret used to generate the signature
	 * @param int $tolerance maximum difference allowed between the header's
	 *  timestamp and the current time
	 *
	 * @throws Exception\SignatureVerificationException if the verification fails
	 *
	 * @return bool
	 */
	public static function verify_header( $payload, $header, $secret, $tolerance = null ) {
		// Extract timestamp and signatures from header
		$timestamp  = self::get_timestamp( $header );
		$signatures = self::get_signatures( $header, self::EXPECTED_SCHEME );
		if ( intval( '-1' ) === intval( $timestamp ) ) {
			throw new \Exception(
				'Unable to extract timestamp and signatures from header'
			);
		}
		if ( empty( $signatures ) ) {
			throw new \Exception(
				'No signatures found with expected scheme'
			);
		}

		// Check if expected signature is found in list of signatures from
		// header
		$signed_payload     = "{$timestamp}.{$payload}";
		$expected_signature = self::compute_signature( $signed_payload, $secret );
		$signature_found    = false;
		foreach ( $signatures as $signature ) {
			if ( self::secure_compute( $expected_signature, $signature ) ) {
				$signature_found = true;

				break;
			}
		}
		if ( ! $signature_found ) {
			throw new \Exception(
				'No signatures found matching the expected signature for payload'
			);
		}

		// Check if timestamp is within tolerance
		if ( ( $tolerance > 0 ) && ( \abs( \time() - $timestamp ) > $tolerance ) ) {
			throw new \Exception(
				'Timestamp outside the tolerance zone'
			);
		}

		return true;
	}

	/**
	 * Extracts the timestamp in a signature header.
	 *
	 * @param string $header the signature header
	 *
	 * @return int the timestamp contained in the header, or -1 if no valid
	 *  timestamp is found
	 */
	private static function get_timestamp( $header ) {
		$items = explode( ',', $header );

		foreach ( $items as $item ) {
			$item_parts = \explode( '=', $item, 2 );
			if ( 't' === $item_parts[0] ) {
				if ( ! \is_numeric( $item_parts[1] ) ) {
					return -1;
				}

				return (int) ( $item_parts[1] );
			}
		}

		return -1;
	}

	/**
	 * Extracts the signatures matching a given scheme in a signature header.
	 *
	 * @param string $header the signature header
	 * @param string $scheme the signature scheme to look for
	 *
	 * @return array the list of signatures matching the provided scheme
	 */
	private static function get_signatures( $header, $scheme ) {
		$signatures = array();
		$items      = \explode( ',', $header );

		foreach ( $items as $item ) {
			$item_parts = \explode( '=', $item, 2 );
			if ( \trim( $item_parts[0] ) === $scheme ) {
				$signatures[] = $item_parts[1];
			}
		}

		return $signatures;
	}

	/**
	 * Computes the signature for a given payload and secret.
	 *
	 * The current scheme used by Stripe ("v1") is HMAC/SHA-256.
	 *
	 * @param string $payload the payload to sign
	 * @param string $secret the secret used to generate the signature
	 *
	 * @return string the signature as a string
	 */
	private static function compute_signature( $payload, $secret ) {
		return \hash_hmac( 'sha256', $payload, $secret );
	}

	/**
	 * Compares two strings for equality. The time taken is independent of the
	 * number of characters that match.
	 *
	 * @param string $a one of the strings to compare
	 * @param string $b the other string to compare
	 *
	 * @return bool true if the strings are equal, false otherwise
	 */
	public static function secure_compute( $a, $b ) {
		if ( null === self::$is_hash_equals_available ) {
			self::$is_hash_equals_available = \function_exists( 'hash_equals' );
		}

		if ( self::$is_hash_equals_available ) {
			return \hash_equals( $a, $b );
		}
		if ( \strlen( $a ) !== \strlen( $b ) ) {
			return false;
		}

		$result = 0;
		$a_len  = \strlen( $a );

		for ( $i = 0; $i < $a_len; ++$i ) {
			$result |= \ord( $a[ $i ] ) ^ \ord( $b[ $i ] );
		}

		return 0 === $result;
	}
}
