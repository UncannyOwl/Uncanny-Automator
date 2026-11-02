<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;

/**
 * Read-only walks over the canonical tree's `content` shape, shared by the walker, the children
 * runner and the resume entry.
 *
 * A loop body is a boundary: its actions live in loop entry items, not in `uap_action_log`, so
 * the row-oriented walks stop at a loop block.
 */
final class Tree_Walk {

	/**
	 * Every `kind: action` node under the items, in tree order, descending through block paths but not into loop bodies.
	 *
	 * @param array<int, array<string, mixed>> $items Tree items (`content[]` or a path's `items[]`).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function action_nodes( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			$kind = (string) ( $item['kind'] ?? '' );
			if ( 'action' === $kind ) {
				$out[] = $item;
				continue;
			}
			if ( 'block' !== $kind || Block_Type::LOOP === (string) ( $item['block_type'] ?? '' ) ) {
				continue;
			}
			foreach ( (array) ( $item['paths'] ?? array() ) as $path ) {
				foreach ( self::action_nodes( (array) ( $path['items'] ?? array() ) ) as $node ) {
					$out[] = $node;
				}
			}
		}

		return $out;
	}

	/**
	 * The ids of {@see self::action_nodes()}.
	 *
	 * @param array<int, array<string, mixed>> $items Tree items.
	 *
	 * @return int[]
	 */
	public static function action_ids( array $items ): array {
		return array_map(
			static function ( array $node ): int {
				return (int) $node['id'];
			},
			self::action_nodes( $items )
		);
	}

	/**
	 * Locate a block node by id anywhere under the items, loop bodies included.
	 *
	 * @param array<int, array<string, mixed>> $items    Tree items.
	 * @param int                              $block_id Block post id.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find_block( array $items, int $block_id ): ?array {
		foreach ( $items as $item ) {
			if ( 'block' !== (string) ( $item['kind'] ?? '' ) ) {
				continue;
			}
			if ( (int) ( $item['id'] ?? 0 ) === $block_id ) {
				return $item;
			}
			foreach ( (array) ( $item['paths'] ?? array() ) as $path ) {
				$found = self::find_block( (array) ( $path['items'] ?? array() ), $block_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Locate an action or block node by id under the items, descending through block paths but not into loop bodies.
	 *
	 * @param array<int, array<string, mixed>> $items   Tree items.
	 * @param int                              $node_id Post id.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find_node( array $items, int $node_id ): ?array {
		foreach ( self::action_nodes( $items ) as $node ) {
			if ( (int) $node['id'] === $node_id ) {
				return $node;
			}
		}

		return self::find_block( $items, $node_id );
	}

	/**
	 * One declared path of a block node, or null when the block does not declare it.
	 *
	 * @param array<string, mixed> $block_node Block node.
	 * @param string               $path_code  Path code.
	 *
	 * @return array{path_code: string, items: array}|null
	 */
	public static function path( array $block_node, string $path_code ): ?array {
		foreach ( (array) ( $block_node['paths'] ?? array() ) as $path ) {
			if ( (string) ( $path['path_code'] ?? '' ) === $path_code ) {
				return array(
					'path_code' => $path_code,
					'items'     => (array) ( $path['items'] ?? array() ),
				);
			}
		}

		return null;
	}

	/**
	 * Whether anything runnable follows a node in walk order: a later sibling at its own level or at the level of
	 * any block enclosing it. A switched-off action and a delay or schedule block are not work (the block
	 * only books its later run and returns); any other block is, whatever it holds.
	 *
	 * @since 8.0.0
	 *
	 * @param array<int, array<string, mixed>> $items   Tree items at one level.
	 * @param int                              $node_id The action or block to look after.
	 *
	 * @return bool|null Null when the node is not among these items or inside their blocks.
	 */
	public static function has_work_after( array $items, int $node_id ): ?bool {
		$items = array_values( $items );
		foreach ( $items as $index => $item ) {
			$rest = array_slice( $items, $index + 1 );
			if ( (int) ( $item['id'] ?? 0 ) === $node_id ) {
				return self::has_work( $rest );
			}
			if ( 'block' !== (string) ( $item['kind'] ?? '' ) ) {
				continue;
			}
			foreach ( (array) ( $item['paths'] ?? array() ) as $path ) {
				$inside = self::has_work_after( (array) ( $path['items'] ?? array() ), $node_id );
				if ( null !== $inside ) {
					return $inside || self::has_work( $rest );
				}
			}
		}

		return null;
	}

	/**
	 * Whether any of the items is runnable work: a switched-on action, or a block other than delay / schedule.
	 *
	 * @param array<int, array<string, mixed>> $items Tree items at one level.
	 *
	 * @return bool
	 */
	private static function has_work( array $items ): bool {
		foreach ( $items as $item ) {
			$kind = (string) ( $item['kind'] ?? '' );
			if ( 'block' === $kind ) {
				if ( Block_Type::DELAY_SCHEDULE === (string) ( $item['block_type'] ?? '' ) ) {
					continue;
				}
				return true;
			}
			if ( 'action' === $kind && false !== ( $item['is_item_on'] ?? true ) ) {
				return true;
			}
		}

		return false;
	}
}
