<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Token\Services;

/**
 * Token_Dependency_Extractor — single source for upstream-action-id
 * discovery from `{{ACTION_FIELD:N:CODE:FIELD}}` / `{{ACTION_META:N:CODE:FIELD}}`
 * token references.
 *
 * Two ingestion surfaces — both return a deduplicated, integer-typed list
 * of upstream action ids:
 *
 *   - {@see self::from_strings()} for a flat array of string values
 *     (e.g. the result of `get_post_meta($id)` after `reset()` per key).
 *   - {@see self::from_fields_bag()} for an arbitrary nested array
 *     (e.g. a field map containing repeaters / multi-value entries).
 *
 * **Dangling references are intentionally retained.** When the matched
 * action id doesn't exist in the recipe's tree, the extractor still
 * surfaces it — the runner relies on this to fail loudly on stuck
 * references rather than silently dropping them.
 *
 * **Self-references are filtered.** Pass the consumer's own id as
 * `$self_id` to exclude it from the result.
 */
final class Token_Dependency_Extractor {

	/**
	 * Canonical token-reference pattern. Matches both `{{ACTION_FIELD:…}}`
	 * and `{{ACTION_META:…}}`; captures the numeric action id only.
	 */
	private const PATTERN = '/{{ACTION_(?:FIELD|META):(\d+):[^:}]+:[^}]+}}/';

	/**
	 * Walk a flat list of strings and return unique upstream action ids.
	 *
	 * Non-string entries (nested arrays, integers, objects, null) are
	 * silently skipped so callers can hand the raw output of
	 * `get_post_meta()` (where values arrive as `array<string,array<string>>`)
	 * after a small flatten without filtering first.
	 *
	 * @param array $strings Flat array of values to scan.
	 * @param int   $self_id Action id to exclude from the result (0 = no exclusion).
	 *
	 * @return list<int>
	 */
	public function from_strings( array $strings, int $self_id = 0 ): array {
		$deps = array();
		foreach ( $strings as $value ) {
			$this->scan_value( $value, $self_id, $deps );
		}

		return array_map( 'intval', array_keys( $deps ) );
	}

	/**
	 * Recursively walk a nested array (field-map shape) and return unique
	 * upstream action ids. Same dedup + self-id semantics as
	 * {@see self::from_strings()}.
	 *
	 * @param array $fields  Flat or nested array of field values.
	 * @param int   $self_id Action / block id to exclude (0 = no exclusion).
	 *
	 * @return list<int>
	 */
	public function from_fields_bag( array $fields, int $self_id = 0 ): array {
		$deps = array();
		array_walk_recursive(
			$fields,
			function ( $value ) use ( &$deps, $self_id ) {
				$this->scan_value( $value, $self_id, $deps );
			}
		);

		return array_map( 'intval', array_keys( $deps ) );
	}

	/**
	 * Scan a single value, accumulating matches into the dedup map.
	 *
	 * @param mixed              $value   Raw value (any type — non-strings are skipped).
	 * @param int                $self_id Self id to exclude.
	 * @param array<int,bool>   &$deps    Dedup map keyed by upstream id (passed by reference).
	 */
	private function scan_value( $value, int $self_id, array &$deps ): void {
		if ( ! is_string( $value ) || '' === $value ) {
			return;
		}
		// Cheap pre-filter avoids the regex for the common non-token case.
		if ( false === strpos( $value, 'ACTION_FIELD:' ) && false === strpos( $value, 'ACTION_META:' ) ) {
			return;
		}
		if ( ! preg_match_all( self::PATTERN, $value, $matches ) ) {
			return;
		}
		foreach ( $matches[1] as $matched_id ) {
			$matched_id = (int) $matched_id;
			if ( $matched_id <= 0 || $matched_id === $self_id ) {
				continue;
			}
			$deps[ $matched_id ] = true;
		}
	}
}
