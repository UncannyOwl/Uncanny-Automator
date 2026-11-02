<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Portability;

/**
 * Isolates the node (item) or subtree (block + its modifiers + paths) rooted at
 * a given entity id from a canonical Recipe_Tree_Builder tree. Pure read.
 */
final class Recipe_Subtree_Extractor {

	/**
	 * Find the node whose id matches `$entity_id` anywhere in the tree and
	 * return it with its full subtree intact.
	 *
	 * @param array<string,mixed> $tree      Full tree from Recipe_Tree_Builder::build().
	 * @param int                 $entity_id Root entity post id to extract.
	 * @return array<string,mixed>|null      The matched node (subtree intact), or null.
	 */
	public function extract( array $tree, int $entity_id ): ?array {
		foreach ( (array) ( $tree['triggers'] ?? array() ) as $trigger ) {
			$found = $this->search_node( $trigger, $entity_id );
			if ( null !== $found ) {
				return $found;
			}
		}
		foreach ( (array) ( $tree['content'] ?? array() ) as $node ) {
			$found = $this->search_node( $node, $entity_id );
			if ( null !== $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * Depth-first match of one node and everything beneath it (block
	 * modifiers and path items included).
	 *
	 * @param array<string,mixed> $node      Tree node to search from.
	 * @param int                 $entity_id Entity post id to match.
	 * @return array<string,mixed>|null      The matched node, or null.
	 */
	private function search_node( array $node, int $entity_id ): ?array {
		if ( (int) ( $node['id'] ?? 0 ) === $entity_id ) {
			return $node;
		}

		// Modifiers (filter_condition / loop_filter) are leaf nodes — no nested children to recurse.
		foreach ( (array) ( $node['modifiers'] ?? array() ) as $modifier ) {
			if ( (int) ( $modifier['id'] ?? 0 ) === $entity_id ) {
				return $modifier;
			}
		}

		foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
			foreach ( (array) ( $path['items'] ?? array() ) as $item ) {
				$found = $this->search_node( $item, $entity_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}
}
