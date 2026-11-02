<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services;

/**
 * Locate one node in a recipe wire array by kind + id.
 *
 * Pure function over the wire the FE consumes — the node-tests routes
 * use presence in the recipe's own wire as BOTH the existence check and
 * the ownership check (a node id from another recipe is simply absent).
 * Traversal mirrors Recipe_Entity_Availability_Decorator: entry.triggers,
 * content[], then each block's modifiers[] and paths[].content[].
 */
final class Node_Locator {

	/**
	 * Find a node by kind + id, or null.
	 *
	 * @param array  $wire    Full recipe wire array (Recipe_Wire_Service::get_wire_array()).
	 * @param string $kind    item | block | block_modifier.
	 * @param int    $node_id Node post id.
	 * @return array|null The serialized wire node, or null when absent.
	 */
	public static function locate( array $wire, string $kind, int $node_id ): ?array {
		$flow = isset( $wire['flow'] ) && is_array( $wire['flow'] ) ? $wire['flow'] : array();

		$triggers = $flow['entry']['triggers'] ?? array();
		$content  = $flow['content'] ?? array();

		return self::search( array_merge( is_array( $triggers ) ? $triggers : array(), is_array( $content ) ? $content : array() ), $kind, $node_id );
	}

	/**
	 * Depth-first search of a node list.
	 *
	 * @param array  $nodes   Wire node list.
	 * @param string $kind    Wanted kind.
	 * @param int    $node_id Wanted id.
	 * @return array|null
	 */
	private static function search( array $nodes, string $kind, int $node_id ): ?array {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			if ( ( $node['type'] ?? '' ) === $kind && (int) ( $node['id'] ?? 0 ) === $node_id ) {
				return $node;
			}

			if ( 'block' !== ( $node['type'] ?? '' ) ) {
				continue;
			}

			$children = is_array( $node['modifiers'] ?? null ) ? $node['modifiers'] : array();
			foreach ( ( is_array( $node['paths'] ?? null ) ? $node['paths'] : array() ) as $path ) {
				if ( is_array( $path ) && is_array( $path['content'] ?? null ) ) {
					$children = array_merge( $children, $path['content'] );
				}
			}

			$found = self::search( $children, $kind, $node_id );
			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}
}
