<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services;

/**
 * FieldsOutput envelope → flat scalar map, the shape legacy handlers consume.
 *
 * flatten() reduces envelopes ({ value, custom, … }) to the flat
 * code => scalar map legacy webhook/email handlers expect, honoring the
 * automator_custom_value sentinel. Non-scalar value/custom entries are
 * out-of-contract and degrade to '' (matching Fields_Bag::normalise()),
 * never to PHP's 'Array' cast.
 *
 * Renamed from Field_Values_Hasher: the write-time invalidation redesign
 * (spec Stage 3) removed hash() entirely — staleness is now detected at
 * the update paths, not by comparing value envelopes here.
 */
final class Field_Values_Flattener {

	/**
	 * Sentinel select value meaning "use the custom companion value".
	 *
	 * @var string
	 */
	const CUSTOM_SENTINEL = 'automator_custom_value';

	/**
	 * Reduce a FieldsOutput map to the flat code => scalar array legacy
	 * webhook/email handlers expect.
	 *
	 * @param array|object $fields FieldsOutput map.
	 * @return array<string, string>
	 */
	public static function flatten( $fields ): array {
		$flat = array();

		foreach ( (array) $fields as $code => $entry ) {
			$entry = (array) $entry;
			$value = isset( $entry['value'] ) && is_scalar( $entry['value'] ) ? (string) $entry['value'] : '';

			if ( self::CUSTOM_SENTINEL === $value && isset( $entry['custom'] ) && is_scalar( $entry['custom'] ) ) {
				$value = (string) $entry['custom'];
			}

			$flat[ (string) $code ] = $value;
		}

		return $flat;
	}
}
