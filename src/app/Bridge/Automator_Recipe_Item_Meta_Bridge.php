<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Bridge;

use Uncanny_Automator\Json_String_Repair;

/**
 * Reads item post-meta as single flat values (Rule 3: post-meta lives in Bridge/),
 * matching the legacy get_recipe_data() flatten exactly: first meta row per key
 * via reset(), structured-JSON-looking strings repaired via Json_String_Repair,
 * serialized strings passed through RAW (the legacy token generators receive
 * them unserialized), and the WPMAGICBUTTON default-to-post-id quirk preserved.
 */
final class Automator_Recipe_Item_Meta_Bridge implements Recipe_Item_Meta_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_item_meta( int $item_id ): array {
		// Guard explicitly: get_post_custom()/get_post_meta(0) would fall back
		// to get_the_ID() (the current global post) and silently read the
		// wrong post's meta. get_post_meta( $id ) with no key is byte-identical
		// to the legacy get_post_custom() call for every real id, minus that
		// fallback.
		if ( $item_id <= 0 ) {
			return array();
		}
		$raw  = get_post_meta( $item_id );
		$flat = array();
		if ( ! is_array( $raw ) ) {
			return $flat;
		}
		foreach ( $raw as $key => $values ) {
			$value = is_array( $values ) ? reset( $values ) : $values;
			if ( Json_String_Repair::looks_like_structured_json( $value ) ) {
				$value = Json_String_Repair::repair( $value );
			}
			$flat[ $key ] = $value;
		}
		// Legacy get_recipe_data quirk: unsaved magic-button triggers default
		// their WPMAGICBUTTON meta to the item post id.
		if ( 'WPMAGICBUTTON' === (string) ( $flat['code'] ?? '' ) && ! array_key_exists( 'WPMAGICBUTTON', $flat ) ) {
			$flat['WPMAGICBUTTON'] = $item_id;
		}
		return $flat;
	}
}
