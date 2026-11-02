<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Populates each item node's token definitions at wire-projection time.
 *
 * Mirrors Recipe_Entity_Availability_Decorator's walk (entry.triggers[],
 * content[], block paths[].content[] at any depth) but stamps ONLY item
 * nodes: filter/delay blocks never publish tokens (FE contract), loop-block
 * stamping arrives with the loop-tokens phase, and modifiers neither publish
 * tokens nor contain token-bearing children, so the walk skips them entirely.
 * Also computes the may-be-empty topology flag: all trigger nodes when the
 * entry operator is `any` with >1 trigger (only one fires per run), and every
 * descendant of a conditional block (filter / delay_schedule paths), at any
 * depth. Never cached on the schema tree — token derivation reads live config
 * in edit-page/REST context.
 */
final class Recipe_Entity_Token_Decorator {

	/**
	 * Block types whose path descendants run conditionally.
	 */
	private const CONDITIONAL_BLOCK_TYPES = array( Block_Type::FILTER, Block_Type::DELAY_SCHEDULE );

	/**
	 * Per-node token producer (Recipe_Entity_Token_Resolver or a
	 * resolve_tokens()-compatible fake).
	 *
	 * @var Recipe_Entity_Token_Resolver|object
	 */
	private $resolver;

	/**
	 * Lazy-default DI: production gets the real resolver; tests inject a spy.
	 *
	 * @param Recipe_Entity_Token_Resolver|object|null $resolver Per-node token producer.
	 */
	public function __construct( $resolver = null ) {
		$this->resolver = $resolver ?? new Recipe_Entity_Token_Resolver();
	}

	/**
	 * Decorate a serialized flow with per-node token definitions.
	 *
	 * @param array<string,mixed> $flow      The serialized flow ({ entry, run_as, content }).
	 * @param int                 $recipe_id Recipe id.
	 * @return array<string,mixed>
	 */
	public function decorate( array $flow, int $recipe_id ): array {
		if ( isset( $flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] ) && is_array( $flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] ) ) {
			// Under `any` with >1 trigger only one fires per run, so every
			// trigger's tokens may be absent downstream.
			$any_operator = 'any' === (string) ( $flow[ Wire_Key::ENTRY ][ Wire_Key::OPERATOR ] ?? '' )
				&& count( $flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] ) > 1;

			$flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] = $this->decorate_list( $flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ], $recipe_id, $any_operator );
		}

		if ( isset( $flow[ Wire_Key::CONTENT ] ) && is_array( $flow[ Wire_Key::CONTENT ] ) ) {
			$flow[ Wire_Key::CONTENT ] = $this->decorate_list( $flow[ Wire_Key::CONTENT ], $recipe_id, false );
		}

		return $flow;
	}

	/**
	 * Decorate each node in a list, skipping non-array entries.
	 *
	 * @param array<int,mixed> $nodes        A list of serialized flow nodes.
	 * @param int              $recipe_id    Recipe id.
	 * @param bool             $may_be_empty Topology flag inherited by every node in the list.
	 * @return array<int,mixed>
	 */
	private function decorate_list( array $nodes, int $recipe_id, bool $may_be_empty ): array {
		foreach ( $nodes as $i => $node ) {
			if ( is_array( $node ) ) {
				$nodes[ $i ] = $this->decorate_node( $node, $recipe_id, $may_be_empty );
			}
		}

		return $nodes;
	}

	/**
	 * Stamp one item node's tokens, or recurse into a block's paths (children
	 * of a conditional block inherit the may-be-empty flag at any depth).
	 *
	 * @param array<string,mixed> $node         A single serialized flow node.
	 * @param int                 $recipe_id    Recipe id.
	 * @param bool                $may_be_empty Topology flag for this node.
	 * @return array<string,mixed>
	 */
	private function decorate_node( array $node, int $recipe_id, bool $may_be_empty ): array {
		$type = (string) ( $node[ Wire_Key::TYPE ] ?? '' );

		if ( Wire_Node_Type::ITEM === $type ) {
			$node[ Wire_Key::TOKENS ] = $this->resolver->resolve_tokens( $node, $recipe_id, $may_be_empty );
			return $node;
		}

		if ( Wire_Node_Type::BLOCK === $type ) {
			// Loop blocks publish their iteration tokens; filter/delay blocks
			// never publish tokens (FE contract) and keep the flow serializer's
			// []. Modifiers never publish tokens and hold no token-bearing
			// children — not visited.
			if ( Block_Type::LOOP === ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' ) ) {
				$node[ Wire_Key::TOKENS ] = $this->resolver->resolve_tokens( $node, $recipe_id, $may_be_empty );
			}

			$child_flag = $may_be_empty
				|| in_array( (string) ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' ), self::CONDITIONAL_BLOCK_TYPES, true );

			if ( isset( $node[ Wire_Key::PATHS ] ) && is_array( $node[ Wire_Key::PATHS ] ) ) {
				foreach ( $node[ Wire_Key::PATHS ] as $p => $path ) {
					if ( is_array( $path ) && isset( $path[ Wire_Key::CONTENT ] ) && is_array( $path[ Wire_Key::CONTENT ] ) ) {
						$node[ Wire_Key::PATHS ][ $p ][ Wire_Key::CONTENT ] = $this->decorate_list( $path[ Wire_Key::CONTENT ], $recipe_id, $child_flag );
					}
				}
			}
		}

		return $node;
	}
}
