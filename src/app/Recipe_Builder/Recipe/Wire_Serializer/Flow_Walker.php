<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Read-only walks over a serialized wire `flow`.
 *
 * Understands the wire shape only: `entry.triggers[]`, `content[]`, and a
 * block's `paths[] { path_code, content[] }`. Modifiers are not nodes here;
 * they never carry tokens. The token and availability decorators keep their
 * own walks because they mutate while walking and carry the may-be-empty
 * topology flag.
 */
final class Flow_Walker {

	/**
	 * Every item and block node, entry triggers first, then content depth-first.
	 *
	 * `path` is the ancestry from the root: alternating container block ids
	 * and path codes, empty at the root and for triggers.
	 *
	 * @param array<string, mixed> $flow A wire flow.
	 * @return list<array{node: array<string, mixed>, path: list<int|string>}>
	 */
	public static function nodes( array $flow ): array {
		$out = array();

		foreach ( self::list( $flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] ?? null ) as $trigger ) {
			if ( is_array( $trigger ) ) {
				$out[] = array(
					'node' => $trigger,
					'path' => array(),
				);
			}
		}

		self::walk( $out, self::list( $flow[ Wire_Key::CONTENT ] ?? null ), array() );

		return $out;
	}

	/**
	 * The first node whose wire `type` and `id` match, anywhere in the flow.
	 *
	 * Ids compare as strings: migrated filter blocks carry string ids such as
	 * `group-1` next to the integer post ids of loops and items.
	 *
	 * @param array<string, mixed> $flow       A wire flow.
	 * @param string               $type       `item` or `block`.
	 * @param int|string           $id         Node id.
	 * @param string|null          $block_type When given, a block must also carry this block_type.
	 * @return array<string, mixed>|null
	 */
	public static function find( array $flow, string $type, $id, ?string $block_type = null ): ?array {
		foreach ( self::nodes( $flow ) as $entry ) {
			$node           = $entry['node'];
			$is_same_type   = ( $node[ Wire_Key::TYPE ] ?? '' ) === $type;
			$is_same_id     = (string) ( $node[ Wire_Key::ID ] ?? '' ) === (string) $id;
			$is_block_match = null === $block_type || ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' ) === $block_type;

			if ( ! $is_same_type || ! $is_same_id || ! $is_block_match ) {
				continue;
			}

			return $node;
		}

		return null;
	}

	/**
	 * Every loop block node, at any depth, in walk order.
	 *
	 * @param array<string, mixed> $flow A wire flow.
	 * @return list<array<string, mixed>>
	 */
	public static function loops( array $flow ): array {
		$loops = array();

		foreach ( self::nodes( $flow ) as $entry ) {
			$node = $entry['node'];

			if ( Wire_Node_Type::BLOCK === ( $node[ Wire_Key::TYPE ] ?? '' ) && Block_Type::LOOP === ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' ) ) {
				$loops[] = $node;
			}
		}

		return $loops;
	}

	/**
	 * Append a content list and, for each block, the content of each of its paths.
	 *
	 * @param list<array{node: array<string, mixed>, path: list<int|string>}> $out   Accumulator.
	 * @param array<int, mixed>                                               $nodes Content list.
	 * @param list<int|string>                                                $path  Ancestry of these nodes.
	 * @return void
	 */
	private static function walk( array &$out, array $nodes, array $path ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$out[] = array(
				'node' => $node,
				'path' => $path,
			);

			if ( Wire_Node_Type::BLOCK !== ( $node[ Wire_Key::TYPE ] ?? '' ) ) {
				continue;
			}

			foreach ( self::list( $node[ Wire_Key::PATHS ] ?? null ) as $branch ) {
				if ( ! is_array( $branch ) ) {
					continue;
				}

				$child_path   = $path;
				$child_path[] = $node[ Wire_Key::ID ] ?? 0;
				$child_path[] = (string) ( $branch[ Wire_Key::PATH_CODE ] ?? '' );

				self::walk( $out, self::list( $branch[ Wire_Key::CONTENT ] ?? null ), $child_path );
			}
		}
	}

	/**
	 * A value as a list, or an empty list when it is not an array.
	 *
	 * @param mixed $value Any value.
	 * @return array<int, mixed>
	 */
	private static function list( $value ): array {
		return is_array( $value ) ? $value : array();
	}
}
