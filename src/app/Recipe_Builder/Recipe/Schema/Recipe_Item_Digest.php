<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema;

/**
 * Flattens one canonical recipe tree into census rows.
 *
 * Pure: array in, list of rows out, no database. Every cross-recipe
 * census (manifest, trigger index, popularity, logs icons, the recipe
 * integration filter, the Recipes admin columns, Pro webhook index) folds over these rows
 * instead of walking storage itself, so a storage change touches
 * Recipe_Tree_Builder and nothing else.
 *
 * Row shape:
 *
 *     { entity: trigger|action|block|filter_condition|loop_filter,
 *       post_id: int, code: string, integration_code: string,
 *       is_on: bool, block_path: int[] }
 *
 * Item rows (trigger, action) also carry `custom_name: ?string`,
 * the author's label. It is a display input only: a census identifies an
 * item by entity + code + integration_code.
 *
 * `block_path` lists the ids of the blocks the row sits inside, outermost
 * first; empty at the recipe root. A block row's `code` is its block type.
 * Rows follow tree order: triggers, then content depth-first with each
 * block's modifiers before its paths.
 */
final class Recipe_Item_Digest {

	/**
	 * Item kinds that produce a row of their own kind.
	 *
	 * @var string[]
	 */
	private const ITEM_KINDS = array( 'trigger', 'action' );

	/**
	 * Flatten a tree (the shape Recipe_Tree_Builder::build() returns) into census rows.
	 *
	 * @param array<string, mixed> $tree Canonical tree.
	 * @return list<array<string, mixed>> Census rows; see the class docblock for the shape.
	 */
	public static function from_tree( array $tree ): array {
		$rows = array();

		foreach ( (array) ( $tree['triggers'] ?? array() ) as $trigger ) {
			if ( is_array( $trigger ) && 'trigger' === ( $trigger['kind'] ?? '' ) ) {
				$rows[] = self::item_row( $trigger, array() );
			}
		}

		self::walk( $rows, (array) ( $tree['content'] ?? array() ), array() );

		return $rows;
	}

	/**
	 * Append rows for a list of content nodes.
	 *
	 * @param array $rows       Row accumulator, appended to in place.
	 * @param array $nodes      Tree nodes.
	 * @param int[] $block_path Enclosing block ids, outermost first.
	 * @return void
	 */
	private static function walk( array &$rows, array $nodes, array $block_path ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$kind = (string) ( $node['kind'] ?? '' );

			if ( 'block' === $kind ) {
				self::block_rows( $rows, $node, $block_path );
				continue;
			}

			if ( in_array( $kind, self::ITEM_KINDS, true ) ) {
				$rows[] = self::item_row( $node, $block_path );
			}
		}
	}

	/**
	 * Append the block's own row, its modifiers' rows, then its paths' items.
	 *
	 * @param array $rows       Row accumulator, appended to in place.
	 * @param array $block      Block node.
	 * @param int[] $block_path Enclosing block ids, outermost first.
	 * @return void
	 */
	private static function block_rows( array &$rows, array $block, array $block_path ): void {
		$block_id = (int) ( $block['id'] ?? 0 );

		$rows[] = array(
			'entity'           => 'block',
			'post_id'          => $block_id,
			'code'             => (string) ( $block['block_type'] ?? '' ),
			'integration_code' => '',
			'is_on'            => true,
			'block_path'       => $block_path,
		);

		$inner_path = array_merge( $block_path, array( $block_id ) );

		foreach ( (array) ( $block['modifiers'] ?? array() ) as $modifier ) {
			if ( ! is_array( $modifier ) ) {
				continue;
			}
			$rows[] = array(
				'entity'           => (string) ( $modifier['block_modifier_type'] ?? '' ),
				'post_id'          => (int) ( $modifier['id'] ?? 0 ),
				'code'             => (string) ( $modifier['code'] ?? '' ),
				'integration_code' => (string) ( $modifier['integration_code'] ?? '' ),
				'is_on'            => true,
				'block_path'       => $inner_path,
			);
		}

		foreach ( (array) ( $block['paths'] ?? array() ) as $path ) {
			self::walk( $rows, (array) ( $path['items'] ?? array() ), $inner_path );
		}
	}

	/**
	 * Build the census row for a trigger or action node.
	 *
	 * @param array $node       Item node.
	 * @param int[] $block_path Enclosing block ids, outermost first.
	 * @return array{entity: string, post_id: int, code: string, integration_code: string, is_on: bool, block_path: int[], custom_name: ?string}
	 */
	private static function item_row( array $node, array $block_path ): array {
		$custom_name = $node['custom_name'] ?? null;

		return array(
			'entity'           => (string) ( $node['kind'] ?? '' ),
			'post_id'          => (int) ( $node['id'] ?? 0 ),
			'code'             => (string) ( $node['code'] ?? '' ),
			'integration_code' => (string) ( $node['integration_code'] ?? '' ),
			'is_on'            => (bool) ( $node['is_item_on'] ?? false ),
			'block_path'       => $block_path,
			'custom_name'      => null === $custom_name ? null : (string) $custom_name,
		);
	}
}
