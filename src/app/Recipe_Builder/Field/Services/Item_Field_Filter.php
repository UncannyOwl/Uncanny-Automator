<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Services;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services\Node_Test_Run_Store;

/**
 * Item_Field_Filter — single source for the structural-meta exclusion
 * set used to separate "configured field values" from "item metadata"
 * (sentence snapshots, recipe_id, run-state storage) in trigger /
 * action aggregates.
 *
 * The aggregate `get_fields()` accessors filter their raw meta arrays
 * through {@see self::omit_structural_keys()} so the canonical exclusion
 * list lives in one place. {@see self::STRUCTURAL_META_KEYS} holds core
 * framework keys only; an integration-specific side-effect key (a cache
 * only that one integration writes) registers through the
 * `automator_item_structural_meta_keys` filter instead of growing this
 * constant — see {@see self::structural_keys()}.
 *
 * Stateless — exposed as static methods. No dependencies.
 */
final class Item_Field_Filter {

	/**
	 * Meta keys that exist on trigger / action posts but are
	 * not user-configured field values. Dedicated accessors on each
	 * aggregate expose these as named methods
	 * (`get_sentence_human_readable()`, etc.) and the structural keys must
	 * NOT bleed into the declarative field map the schema-v3 tree emits.
	 *
	 * `_automator_custom_item_name_` is deliberately NOT here. It is a field
	 * {@see Custom_Item_Name_Field} declares on every item, and the builder's
	 * edit form seeds itself from the wire `fields` map — fencing it off would
	 * render the input blank on every open, and saving any other field would
	 * then write '' over a name the author had set. It still has a named
	 * accessor (`get_custom_name()`) because the wire also carries it as a
	 * top-level `custom_name` for the row header. {@see Node_Test_Run_Store::META_KEY} /
	 * {@see Node_Test_Run_Store::PENDING_META_KEY} are Node_Test_Run_Store's
	 * run-state postmeta, not field configuration.
	 *
	 * Core framework keys only. Integration-owned side-effect keys (e.g.
	 * the webhooks integration's response-token cache) do not belong
	 * here — they register through the `automator_item_structural_meta_keys`
	 * filter from the integration's own code instead, so this class never
	 * has to know a given integration's storage quirks.
	 */
	public const STRUCTURAL_META_KEYS = array(
		'recipe_id',
		'sentence_human_readable',
		'sentence_human_readable_html',
		// Node-test run-state storage (Node_Test_Run_Store) — infrastructure
		// postmeta on the node post, never user-configured field values.
		Node_Test_Run_Store::META_KEY,
		Node_Test_Run_Store::PENDING_META_KEY,
	);

	/**
	 * Memoized {@see self::structural_keys()} result for this request.
	 *
	 * @var string[]|null
	 */
	private static ?array $merged = null;

	/**
	 * The full structural exclusion set: {@see self::STRUCTURAL_META_KEYS}
	 * plus any keys integrations register via the
	 * `automator_item_structural_meta_keys` filter (side-effect caches and
	 * similar item metadata that must not ride the wire fields map).
	 * Memoized per request — {@see self::omit_structural_keys()} runs on
	 * every item serialization.
	 *
	 * @return string[]
	 */
	private static function structural_keys(): array {
		if ( null === self::$merged ) {
			$filtered     = Dispatcher::filter( 'automator_item_structural_meta_keys', self::STRUCTURAL_META_KEYS );
			self::$merged = array_values( array_unique( array_filter( (array) $filtered, 'is_string' ) ) );
		}

		return self::$merged;
	}

	/**
	 * Drop the {@see self::structural_keys()} memo so the next call
	 * recomputes from the currently-registered filters.
	 *
	 * Test isolation only — production requests never need to call this;
	 * the memo living for the request's lifetime is the point.
	 *
	 * @return void
	 */
	public static function reset_structural_keys_cache(): void {
		self::$merged = null;
	}

	/**
	 * Return a copy of `$meta` with every {@see self::structural_keys()}
	 * entry removed. Preserves key order for the remaining entries.
	 *
	 * @param array<string, mixed> $meta Raw meta array (typically from an
	 *                                   item aggregate's `Action_Meta::to_array()`
	 *                                   or sibling).
	 * @return array<string, mixed>
	 */
	public static function omit_structural_keys( array $meta ): array {
		$structural_keys = self::structural_keys();

		$out = array();
		foreach ( $meta as $key => $value ) {
			if ( ! in_array( $key, $structural_keys, true ) ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}
}
