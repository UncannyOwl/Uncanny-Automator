<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Token\Services;

/**
 * Rewrites entity-id references inside recipe field values when a subtree is
 * duplicated/imported. Map keys are OLD ids, values are NEW ids. Only ids
 * present in the map are rewritten — references to entities outside the
 * duplicated set are intentionally left pointing at the originals.
 *
 * Pure: no WordPress, no storage. The patterns come from the legacy
 * Copy_Recipe_Parts duplication path, map-driven here instead of accumulating
 * instance state.
 *
 * These are deliberately NOT the Token_Dependency_Extractor patterns. That
 * class matches one fully-closed token family ({{ACTION_FIELD|META:…}}) because
 * it reads dependencies out of well-formed values; rewriting has to survive the
 * malformed and truncated token text that real recipes accumulate, so it
 * matches four families on their opening segment only and never requires the
 * closing `}}`. Anchoring these to match the extractor would silently stop
 * rewriting the broken references duplication most needs to fix.
 */
final class Reference_Rewriter {

	/**
	 * Field keys whose value is a bare entity post id rather than a `{{…}}`
	 * token string. Magic link/button triggers store their own post id under
	 * these keys; pattern-based rewriting can't catch a bare integer, so these
	 * are rewritten by key. Mirrors the legacy Copy_Recipe_Parts key list.
	 *
	 * @var string[]
	 */
	public const BARE_ID_FIELD_KEYS = array(
		'WPMAGICLINK',
		'WPMAGICBUTTON',
		'ANONWPMAGICLINK',
		'ANONWPMAGICBUTTON',
	);

	/**
	 * Rewrite the value of every {@see self::BARE_ID_FIELD_KEYS} entry whose
	 * bare-id value appears in the map. Same scope contract as
	 * {@see self::rewrite()}: ids absent from the map are preserved, so a
	 * reference to an entity outside the duplicated set keeps pointing at the
	 * original. The value's scalar type (string vs int) is preserved.
	 *
	 * @param array<string,mixed> $fields Flat field bag (key => value).
	 * @param array<int,int>      $id_map old_id => new_id.
	 * @return array<string,mixed> Same shape as input, bare-id values rewritten.
	 */
	public function rewrite_bare_id_fields( array $fields, array $id_map ): array {
		if ( empty( $id_map ) ) {
			return $fields;
		}
		foreach ( self::BARE_ID_FIELD_KEYS as $key ) {
			if ( ! isset( $fields[ $key ] ) || ! is_numeric( $fields[ $key ] ) ) {
				continue;
			}
			$old = (int) $fields[ $key ];
			if ( ! isset( $id_map[ $old ] ) ) {
				continue;
			}
			$fields[ $key ] = is_string( $fields[ $key ] )
				? (string) $id_map[ $old ]
				: $id_map[ $old ];
		}
		return $fields;
	}

	/**
	 * Rewrite every id-bearing token reference in a field value.
	 *
	 * @param string|array<mixed>|mixed $value  Field value (string, nested array, or scalar).
	 * @param array<int,int>            $id_map  old_id => new_id.
	 *   Overlapping key/value sets are safe: every id is resolved once against the original
	 *   map, so a rewritten value is never re-examined. Import needs this — its old ids come
	 *   from a foreign export file and can collide with ids the same import allocates.
	 * @return string|array<mixed>|mixed         Same shape as input, references rewritten.
	 */
	public function rewrite( $value, array $id_map ) {
		if ( empty( $id_map ) ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->rewrite( $v, $id_map );
			}
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}
		$normalized = array();
		foreach ( $id_map as $old => $new ) {
			$normalized[ (int) $old ] = (int) $new;
		}

		return $this->rewrite_string( $value, $normalized );
	}

	/**
	 * Apply every id-bearing token pattern in one pass per pattern.
	 *
	 * Each pattern captures the id and resolves it through the map inside the
	 * callback, so a replacement is never re-scanned. That is what makes an
	 * overlapping map safe — the previous shape ran one `preg_replace` per map
	 * entry over the already-mutated string, so a value written by an earlier
	 * entry could be matched again by a later one.
	 *
	 * Patterns are unchanged, and bind the same positions as before: the two
	 * TOKEN_EXTENDED variants target segment 2 and segment 3 respectively and
	 * cannot compete, because `[^:}]+` never crosses a colon.
	 *
	 * @param string         $content Field value.
	 * @param array<int,int> $map     old_id => new_id, keys and values already ints.
	 *
	 * @return string
	 */
	private function rewrite_string( string $content, array $map ): string {
		$patterns = array(
			// Bare prefix tokens: {{<id>:  {{<id>;  [[<id>:  [[<id>;
			'/(\{\{|\[\[)(\d++)(:|;)/',
			// ACTION_FIELD / ACTION_META
			'/(\{\{ACTION_(?:FIELD|META):)(\d++)(:)/',
			// TRIGGER_COMMON
			'/(\{\{TRIGGER_COMMON:)(\d++)(:)/',
			// TOKEN_EXTENDED 3-segment variant — binds segment 2.
			// Intentionally unanchored vs legacy closing }}; well-formed tokens are unaffected.
			'/(\{\{TOKEN_EXTENDED:[^:}]+:)(\d++)(:)/',
			// TOKEN_EXTENDED 4-segment (action) variant — binds segment 3.
			'/(\{\{TOKEN_EXTENDED:[^:}]+:[^:}]+:)(\d++)(:)/',
		);

		$resolve = static function ( array $m ) use ( $map ): string {
			$old = (int) $m[2];

			return $m[1] . ( $map[ $old ] ?? $old ) . $m[3];
		};

		$rewritten = $content;
		foreach ( $patterns as $pattern ) {
			$replaced = preg_replace_callback( $pattern, $resolve, $rewritten );
			if ( null === $replaced ) {
				// A PCRE error (backtrack or recursion limit, invalid UTF-8) in any pattern returns the value
				// as it came: every reference at its original id, never some rewritten and some not.
				return $content;
			}
			$rewritten = $replaced;
		}

		return $rewritten;
	}
}
