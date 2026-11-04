<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Loop_Token_Scope_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Token_Scope;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Projection-time decorator that stamps `loopable_token_ids` on every loop block
 * node of a serialized `flow`, at any depth.
 *
 * The list is every loopable token the loop can iterate, as
 * `Loop_Token_Scope_Service` decides it: the recipe builder offers exactly these
 * in the loop's Token field, and `save_loop` accepts exactly these. Runs after
 * the token and availability decorators, whose output it reads, and is never
 * cached: universal tokens follow the integrations active now.
 *
 * @since 8.0.1
 */
final class Loop_Token_Scope_Decorator {

	/**
	 * The loop token rule.
	 *
	 * @var Loop_Token_Scope_Service
	 */
	private Loop_Token_Scope_Service $scopes;

	/**
	 * Lazy-default DI: production builds the shared rule; tests may inject one over a stub registry.
	 *
	 * @param Loop_Token_Scope_Service|null $scopes The loop token rule.
	 */
	public function __construct( ?Loop_Token_Scope_Service $scopes = null ) {
		$this->scopes = $scopes ?? new Loop_Token_Scope_Service();
	}

	/**
	 * Stamp each loop block node with the loopable tokens it can iterate.
	 *
	 * @param array<string, mixed> $flow The serialized flow, tokens and availability decorated.
	 *
	 * @return array<string, mixed> The flow with `loopable_token_ids` on each loop block node.
	 */
	public function decorate( array $flow ): array {
		$scopes = $this->scopes->for_every_loop( $flow );

		if ( empty( $scopes ) || ! is_array( $flow[ Wire_Key::CONTENT ] ?? null ) ) {
			return $flow;
		}

		$flow[ Wire_Key::CONTENT ] = $this->stamp_list( $flow[ Wire_Key::CONTENT ], $scopes );

		return $flow;
	}

	/**
	 * Stamp the loop blocks of a content list and of every block path below it.
	 *
	 * @param array<int, mixed>               $nodes  A content list.
	 * @param array<string, Loop_Token_Scope> $scopes Scope by loop block id.
	 *
	 * @return array<int, mixed>
	 */
	private function stamp_list( array $nodes, array $scopes ): array {
		foreach ( $nodes as $position => $node ) {
			if ( ! is_array( $node ) || Wire_Node_Type::BLOCK !== ( $node[ Wire_Key::TYPE ] ?? '' ) ) {
				continue;
			}

			$id = (string) ( $node[ Wire_Key::ID ] ?? '' );

			if ( Block_Type::LOOP === ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' ) && isset( $scopes[ $id ] ) ) {
				$node[ Wire_Key::LOOPABLE_TOKEN_IDS ] = $scopes[ $id ]->get_token_ids();
			}

			if ( is_array( $node[ Wire_Key::PATHS ] ?? null ) ) {
				$node[ Wire_Key::PATHS ] = $this->stamp_paths( $node[ Wire_Key::PATHS ], $scopes );
			}

			$nodes[ $position ] = $node;
		}

		return $nodes;
	}

	/**
	 * Stamp the content of each path of a block.
	 *
	 * @param array<int, mixed>               $paths  A block's `paths`.
	 * @param array<string, Loop_Token_Scope> $scopes Scope by loop block id.
	 *
	 * @return array<int, mixed>
	 */
	private function stamp_paths( array $paths, array $scopes ): array {
		foreach ( $paths as $position => $branch ) {
			if ( is_array( $branch ) && is_array( $branch[ Wire_Key::CONTENT ] ?? null ) ) {
				$branch[ Wire_Key::CONTENT ] = $this->stamp_list( $branch[ Wire_Key::CONTENT ], $scopes );
				$paths[ $position ]          = $branch;
			}
		}

		return $paths;
	}
}
