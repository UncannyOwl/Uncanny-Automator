<?php

namespace Uncanny_Automator;

/**
 * Partitions hydrated trigger token values by their declared tokenIdentifier.
 *
 * @package Uncanny_Automator
 */
class Token_Identifier_Partitioner {

	/**
	 * Group hydrated values by tokenIdentifier and write one row per bucket.
	 *
	 * @param array  $token_definitions Token defs with `tokenId` and optional `tokenIdentifier`.
	 * @param array  $values            Flat tokenId => value map from hydrate_tokens().
	 * @param string $default_code      Identifier used when a token def doesn't declare one.
	 * @param array  $records           Trigger records passed to db->token->save().
	 *
	 * @return void
	 */
	public static function partition_and_save( array $token_definitions, array $values, $default_code, array $records ) {

		$buckets = self::partition( $token_definitions, $values, $default_code );

		foreach ( $buckets as $identifier => $bucket_values ) {
			Automator()->db->token->save(
				$identifier,
				wp_json_encode( $bucket_values ),
				$records
			);
		}
	}

	/**
	 * Pure partitioner — returns the bucketed structure without writing.
	 *
	 * Two-pass walk:
	 *  1. Tokens declared in define_tokens() route to whichever identifier
	 *     they declared (defaulting to the trigger code).
	 *  2. Any value left over — typically framework-auto-generated tokens
	 *     from `relevant_tokens` configs on field defs in options() — falls
	 *     into the default-code bucket so it still gets persisted.
	 *
	 * The leftover pass is what keeps behaviour bit-for-bit identical for
	 * integrations that don't set any custom tokenIdentifier: every value
	 * falls into the leftover bucket and lands in a single row keyed by the
	 * trigger code, exactly like the pre-patch save_tokens() did.
	 *
	 * @param array  $token_definitions
	 * @param array  $values
	 * @param string $default_code
	 *
	 * @return array<string, array<string, mixed>> identifier => [tokenId => value]
	 */
	public static function partition( array $token_definitions, array $values, $default_code ) {

		$buckets = array();
		$claimed = array();

		foreach ( $token_definitions as $token ) {
			$token_id = $token['tokenId'] ?? '';
			if ( '' === $token_id || ! array_key_exists( $token_id, $values ) ) {
				continue;
			}

			$identifier = ! empty( $token['tokenIdentifier'] )
				? $token['tokenIdentifier']
				: $default_code;

			$buckets[ $identifier ][ $token_id ] = $values[ $token_id ];
			$claimed[ $token_id ]                = true;
		}

		foreach ( $values as $token_id => $value ) {
			if ( isset( $claimed[ $token_id ] ) ) {
				continue;
			}
			$buckets[ $default_code ][ $token_id ] = $value;
		}

		return $buckets;
	}
}
