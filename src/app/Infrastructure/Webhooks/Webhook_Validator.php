<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

/**
 * Class Webhook_Validator
 *
 * Utility class providing common webhook validation patterns.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Webhooks
 */
class Webhook_Validator {

	/**
	 * Validate an HMAC signature against an expected hash.
	 *
	 * Used by Job_Callback_Controller (Phase 11) and potentially other secure webhooks.
	 *
	 * @param string $payload   The raw payload to verify.
	 * @param string $signature The signature provided in the request.
	 * @param string $secret    The shared secret key.
	 * @param string $algo      The hash algorithm. Default 'sha256'.
	 *
	 * @return bool
	 */
	public static function validate_hmac( string $payload, string $signature, string $secret, string $algo = 'sha256' ): bool {
		$expected = hash_hmac( $algo, $payload, $secret );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Validate a simple key-based authorization.
	 *
	 * Matches the pattern used by App_Webhooks::is_valid_webhook_key().
	 *
	 * @param string $provided_key The key provided in the request.
	 * @param string $stored_key   The expected key from storage.
	 *
	 * @return bool
	 */
	public static function validate_key( string $provided_key, string $stored_key ): bool {
		if ( empty( $provided_key ) || empty( $stored_key ) ) {
			return false;
		}

		return hash_equals( $stored_key, $provided_key );
	}
}
