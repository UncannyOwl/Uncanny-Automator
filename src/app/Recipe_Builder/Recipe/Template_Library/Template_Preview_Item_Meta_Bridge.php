<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library;

use Uncanny_Automator\App\Bridge\Recipe_Item_Meta_Bridge;
use Uncanny_Automator\Json_String_Repair;

/**
 * Flat item meta for a template preview, read from the template's own tree.
 *
 * A preview's items are not posts on this site, and their ids are the
 * template's own (1, 2, …), which name unrelated posts here. The token
 * resolver reads an item's meta by id, so the preview gives it this reader
 * instead of the post-meta one: an item's meta is its fields from the
 * template, plus its `code` and `integration`, the same keys a saved item
 * carries. An id the template has no item for reads as no meta.
 *
 * @since 8.1.0
 */
final class Template_Preview_Item_Meta_Bridge implements Recipe_Item_Meta_Bridge {

	/**
	 * Item types whose nodes carry item meta.
	 *
	 * @since 8.1.0
	 *
	 * @var string[]
	 */
	const ITEM_TYPES = array( 'trigger', 'action' );

	/**
	 * Flat meta of each template item, keyed by item id.
	 *
	 * @since 8.1.0
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $meta = array();

	/**
	 * Index the items of a template's canonical tree.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string,mixed> $tree Canonical recipe tree of the template.
	 */
	public function __construct( array $tree ) {
		$this->index( $tree );
	}

	/**
	 * Flat meta of the template item with this id, or an empty array when the template has none.
	 *
	 * @since 8.1.0
	 *
	 * @param int $item_id Item id within the template.
	 * @return array<string,mixed>
	 */
	public function get_item_meta( int $item_id ): array {
		return $this->meta[ $item_id ] ?? array();
	}

	/**
	 * Walk the tree, wherever items sit (triggers, actions inside blocks and their paths), and keep each item's flat meta.
	 *
	 * @since 8.1.0
	 *
	 * @param array<mixed> $node A tree node or list of nodes.
	 * @return void
	 */
	private function index( array $node ): void {
		$item_id = (int) ( $node['id'] ?? 0 );

		if ( 0 < $item_id && in_array( $node['item_type'] ?? '', self::ITEM_TYPES, true ) && ! isset( $this->meta[ $item_id ] ) ) {
			$this->meta[ $item_id ] = $this->flat_meta( $node );
		}

		foreach ( $node as $child ) {
			if ( is_array( $child ) ) {
				$this->index( $child );
			}
		}
	}

	/**
	 * An item's fields as single values, with structured JSON strings repaired as the post-meta reader does, plus `code` and `integration`.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string,mixed> $item Item node of the tree.
	 * @return array<string,mixed>
	 */
	private function flat_meta( array $item ): array {
		$meta = array();

		foreach ( (array) ( $item['fields'] ?? array() ) as $key => $value ) {
			if ( Json_String_Repair::looks_like_structured_json( $value ) ) {
				$value = Json_String_Repair::repair( $value );
			}
			$meta[ (string) $key ] = $value;
		}

		$meta['code']        = (string) ( $item['code'] ?? '' );
		$meta['integration'] = (string) ( $item['integration_code'] ?? '' );

		return $meta;
	}
}
