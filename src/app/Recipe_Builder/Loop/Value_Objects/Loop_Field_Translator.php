<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects;

/**
 * Bidirectional translator between the wire-shape Fields_Bag map (LOOP_TYPE,
 * LOOP_TOKEN) and the loop storage shape (iterable_expression array).
 *
 * Single source of truth for loop field translation — `Loop_Block_Rest`
 * (write-side) and the schema v3 cache builder + wire serializer
 * (read-side) both delegate here.
 *
 * The storage `iterable_expression` shape:
 *   - `type` — required string ('users' | 'posts' | 'wp_users' | 'token' | …)
 *   - `fields` — optional JSON-encoded string. For `type='token'` this
 *     carries `{"TOKEN":{"type":"text","value":…}}`, the released shape the
 *     Pro runner reads. An interim `{"token_key":"…"}` shape also exists on
 *     rows written before that canonicalization; `to_fields()` stays
 *     bilingual to read them, but nothing writes it and the runner has no
 *     reader for it. The string encoding is a quirk of the existing
 *     controller, preserved for round-trip compatibility.
 *
 * Scope: LOOP_TYPE + LOOP_TOKEN only. The loop's `run_on` meta is a separate
 * storage key and is handled by the schema v3 tree builder, not bundled into
 * `iterable_expression`. Per-type extras (e.g. `LOOP_USER_ROLE` for 'users'
 * loops) aren't covered yet because the controller's current write path
 * doesn't handle them.
 *
 * Static methods only — pure transformation, no state.
 */
final class Loop_Field_Translator {

	/**
	 * Wire-shape fields → storage iterable_expression.
	 *
	 * The write-side controller delegates here, so this is the only place
	 * the wire-to-storage translation lives.
	 *
	 * @param array $fields Wire-shape map. Recognised keys: LOOP_TYPE, LOOP_TOKEN.
	 * @return array iterable_expression for `Loop_CRUD_Service::update_loop` / persistence.
	 */
	public static function from_fields( array $fields ): array {
		$loop_type = $fields['LOOP_TYPE']['value'] ?? '';

		if ( '' === $loop_type ) {
			return array();
		}

		$expression = array( 'type' => $loop_type );

		if ( 'token' === $loop_type && ! empty( $fields['LOOP_TOKEN']['value'] ) ) {
			// The stored shape is the RELEASED one the Pro runner reads
			// ({"TOKEN":{"value":...}}) — every legacy loop in the wild
			// stores it, and the runner has no other reader. to_fields()
			// stays bilingual for rows written before this canonicalization.
			$expression['fields'] = wp_json_encode(
				array(
					'TOKEN' => array(
						'type'  => 'text',
						'value' => $fields['LOOP_TOKEN']['value'],
					),
				)
			);
		}

		return $expression;
	}

	/**
	 * Storage iterable_expression → wire-shape fields.
	 *
	 * Inverse of `from_fields`. Decodes the JSON-encoded `fields` blob to
	 * extract `token_key`. Tolerates pre-decoded array input defensively
	 * (some callers may hand us the decoded shape) and corrupt JSON
	 * (returns LOOP_TYPE without LOOP_TOKEN rather than throwing).
	 *
	 * Empty / missing `type` returns an empty map — callers compose the
	 * resulting fields into the larger Fields_Bag-compatible structure.
	 *
	 * @param array $iterable_expression Storage shape with optional `type` and JSON-encoded `fields`.
	 * @return array<string, array{value: string, readable: string, custom: string}>
	 */
	public static function to_fields( array $iterable_expression ): array {
		$loop_type = (string) ( $iterable_expression['type'] ?? '' );

		if ( '' === $loop_type ) {
			return array();
		}

		$out = array(
			'LOOP_TYPE' => self::field( $loop_type ),
		);

		if ( 'token' === $loop_type ) {
			$token_key = self::extract_token_key( $iterable_expression['fields'] ?? null );
			if ( '' !== $token_key ) {
				$out['LOOP_TOKEN'] = self::field( $token_key );
			}
		}

		return $out;
	}

	/**
	 * Build a canonical wire-shape field entry for a single value.
	 *
	 * `readable` and `custom` default to '' — the schema v3 cache stamps
	 * raw values; presentation-time enrichment (e.g. resolving `'users'`
	 * to the display label "Users") happens in the wire serializer.
	 *
	 * @param string $value Field value.
	 * @return array{value: string, readable: string, custom: string}
	 */
	private static function field( string $value ): array {
		return array(
			'value'    => $value,
			'readable' => '',
			'custom'   => '',
		);
	}

	/**
	 * Extract the loop token from the iterable_expression's `fields` slot.
	 *
	 * Bilingual read — the slot carries one of two vocabularies:
	 *   - `{"TOKEN":{"value":...}}` — the RELEASED shape every legacy /
	 *     migrated loop stores and the Pro runner reads (also what
	 *     from_fields() writes).
	 *   - `{"token_key":...}` — the interim shape this translator wrote
	 *     before canonicalizing on the released one; rows saved through
	 *     that window still decode.
	 *
	 * Tolerates a JSON-encoded string (canonical), a pre-decoded array
	 * (defensive), and null / corrupt JSON / unrelated shapes (returns '').
	 *
	 * @param mixed $fields_raw Raw `fields` slot value.
	 * @return string Extracted token or '' when absent / undecodable.
	 */
	private static function extract_token_key( $fields_raw ): string {
		if ( is_string( $fields_raw ) && '' !== $fields_raw ) {
			$fields_raw = json_decode( $fields_raw, true );
		}

		if ( ! is_array( $fields_raw ) ) {
			return '';
		}

		$token_key = (string) ( $fields_raw['token_key'] ?? '' );
		if ( '' !== $token_key ) {
			return $token_key;
		}

		return (string) ( $fields_raw['TOKEN']['value'] ?? '' );
	}
}
