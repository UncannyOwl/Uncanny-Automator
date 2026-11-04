<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Loop\Services;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Loop\Enums\Loop_Token_Refusal;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Token_Scope;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Availability_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Flow_Walker;
use Uncanny_Automator\App\Recipe_Builder\Token\Domain\Loopable_Token_Id;
use Uncanny_Automator\App\Recipe_Builder\Token\Integration\Registry\Integration_Token_Registry;
use Uncanny_Automator\App\Recipe_Builder\Token\Integration\Registry\WP_Integration_Token_Registry;

/**
 * The one rule for which loopable tokens a loop can iterate.
 *
 * A loop can iterate any loopable token (a trigger's, an action's, or an
 * integration's universal one) whose producer has run by the time the loop
 * starts:
 *
 * - every entry trigger's;
 * - an action's that comes before the loop at the loop's own level or at the
 *   level of any block the loop is in;
 * - every universal loopable token of an active integration.
 *
 * Not valid: a token from an action inside the loop, after the loop, inside
 * another block (a token from inside a block is not available after that
 * block), in another path of a block the loop is in, or from a trigger or action
 * this site cannot run. This is the recipe builder's token scope, so the loop's
 * Token field picker offers exactly these tokens: the wire carries them on each
 * loop block (`Loop_Token_Scope_Decorator`) and `save_loop` validates against
 * the same answer.
 *
 * Reads the serialized wire `flow`, with `tokens[]` and `availability` already
 * decorated.
 *
 * @since 8.0.1
 */
final class Loop_Token_Scope_Service {

	/**
	 * Integration token registry, or null to read a fresh one each time so the
	 * universal tokens follow the integrations active now.
	 *
	 * @var Integration_Token_Registry|null
	 */
	private ?Integration_Token_Registry $registry;

	/**
	 * Lazy-default DI: production reads a fresh registry per call; tests inject one.
	 *
	 * @param Integration_Token_Registry|null $registry Integration token registry.
	 */
	public function __construct( ?Integration_Token_Registry $registry = null ) {
		$this->registry = $registry;
	}

	/**
	 * The scope at an existing loop.
	 *
	 * @param array<string, mixed> $flow    A serialized wire flow.
	 * @param int|string           $loop_id The loop block's id.
	 *
	 * @return Loop_Token_Scope|null Null when the flow has no loop block with that id.
	 */
	public function at_loop( array $flow, $loop_id ): ?Loop_Token_Scope {
		$nodes = Flow_Walker::nodes( $flow );

		foreach ( $nodes as $index => $entry ) {
			if ( $this->is_loop( $entry['node'] ) && (string) ( $entry['node'][ Wire_Key::ID ] ?? '' ) === (string) $loop_id ) {
				return $this->scope( $nodes, $this->target( $index, $entry ), $this->universal_token_ids() );
			}
		}

		return null;
	}

	/**
	 * The scope at a loop added to the end of the recipe's top level, where `save_loop` adds one.
	 *
	 * @param array<string, mixed> $flow A serialized wire flow.
	 *
	 * @return Loop_Token_Scope
	 */
	public function at_new_loop( array $flow ): Loop_Token_Scope {
		$nodes  = Flow_Walker::nodes( $flow );
		$target = array(
			'index' => count( $nodes ),
			'id'    => null,
			'path'  => array(),
		);

		return $this->scope( $nodes, $target, $this->universal_token_ids() );
	}

	/**
	 * The scope at every loop of the flow, read with one registry read.
	 *
	 * @param array<string, mixed> $flow A serialized wire flow.
	 *
	 * @return array<string, Loop_Token_Scope> By loop block id, as a string.
	 */
	public function for_every_loop( array $flow ): array {
		$nodes     = Flow_Walker::nodes( $flow );
		$scopes    = array();
		$universal = null;

		foreach ( $nodes as $index => $entry ) {
			if ( ! $this->is_loop( $entry['node'] ) ) {
				continue;
			}

			$universal = $universal ?? $this->universal_token_ids();

			$scopes[ (string) ( $entry['node'][ Wire_Key::ID ] ?? '' ) ] = $this->scope( $nodes, $this->target( $index, $entry ), $universal );
		}

		return $scopes;
	}

	/**
	 * Where a loop node sits in the walk.
	 *
	 * @param int                                                      $index Walk index.
	 * @param array{node: array<string, mixed>, path: list<int|string>} $entry Walk entry.
	 *
	 * @return array{index: int, id: string|null, path: list<int|string>}
	 */
	private function target( int $index, array $entry ): array {
		return array(
			'index' => $index,
			'id'    => (string) ( $entry['node'][ Wire_Key::ID ] ?? '' ),
			'path'  => $entry['path'],
		);
	}

	/**
	 * Split the recipe's loopable tokens into the ones valid at the target and the refused ones.
	 *
	 * @param list<array{node: array<string, mixed>, path: list<int|string>}> $nodes     The flow's nodes in walk order.
	 * @param array{index: int, id: string|null, path: list<int|string>}       $target    The loop's place.
	 * @param string[]                                                         $universal Universal loopable token ids.
	 *
	 * @return Loop_Token_Scope
	 */
	private function scope( array $nodes, array $target, array $universal ): Loop_Token_Scope {
		$valid    = array();
		$refusals = array();

		foreach ( $nodes as $index => $entry ) {
			$token_ids = $this->loopable_token_ids( $entry['node'] );

			if ( empty( $token_ids ) || $index === $target['index'] ) {
				continue;
			}

			$refusal = $this->refusal( $entry, $index, $target );

			foreach ( $token_ids as $token_id ) {
				if ( null === $refusal ) {
					$valid[] = $token_id;
					continue;
				}
				$refusals[ $token_id ] = $refusal;
			}
		}

		return new Loop_Token_Scope( $valid, $universal, $refusals );
	}

	/**
	 * Why a producer's tokens are not valid at the target, or null when they are.
	 *
	 * @param array{node: array<string, mixed>, path: list<int|string>} $entry  The producer's walk entry.
	 * @param int                                                      $index  The producer's walk index.
	 * @param array{index: int, id: string|null, path: list<int|string>} $target The loop's place.
	 *
	 * @return array{reason: string, block_id: int|string|null}|null
	 */
	private function refusal( array $entry, int $index, array $target ): ?array {
		$refusal = $this->placement_refusal( $entry, $index, $target );

		if ( null === $refusal && $this->is_unavailable( $entry['node'] ) ) {
			return $this->refused( Loop_Token_Refusal::PRODUCER_UNAVAILABLE, null );
		}

		return $refusal;
	}

	/**
	 * Why the producer's place keeps its tokens from the target, or null when it does not.
	 *
	 * Paths alternate a block id and a path code from the root, so where the
	 * producer's path first differs from the loop's tells the two apart: at a
	 * path code they are in different paths of one block; at a block id the
	 * producer is inside a block the loop is not in.
	 *
	 * @param array{node: array<string, mixed>, path: list<int|string>} $entry  The producer's walk entry.
	 * @param int                                                      $index  The producer's walk index.
	 * @param array{index: int, id: string|null, path: list<int|string>} $target The loop's place.
	 *
	 * @return array{reason: string, block_id: int|string|null}|null
	 */
	private function placement_refusal( array $entry, int $index, array $target ): ?array {
		$node = $entry['node'];
		$path = $entry['path'];

		if ( $this->is_trigger( $node ) ) {
			return null;
		}

		if ( null !== $target['id'] && in_array( $target['id'], $this->block_ids( $path ), true ) ) {
			return $this->refused( Loop_Token_Refusal::INSIDE_LOOP, null );
		}

		$fork = $this->fork( $path, $target['path'] );

		if ( $fork < count( $path ) && $fork < count( $target['path'] ) && 1 === $fork % 2 ) {
			return $this->refused( Loop_Token_Refusal::OTHER_PATH, $path[ $fork - 1 ] );
		}

		if ( $index > $target['index'] ) {
			return $this->refused( Loop_Token_Refusal::AFTER_LOOP, null );
		}

		if ( $fork < count( $path ) ) {
			return $this->refused( Loop_Token_Refusal::INSIDE_OTHER_BLOCK, $path[ $fork ] );
		}

		return $this->own_tokens_refusal( $node, $target['path'], $fork );
	}

	/**
	 * Whether a node before the loop, on the loop's own chain of levels, keeps its own tokens from the loop.
	 *
	 * An item there is a step before the loop. A block there is either one the
	 * loop is in, whose own tokens the loop sees, or one that ran before it,
	 * whose tokens stay inside it.
	 *
	 * @param array<string, mixed> $node        The producer.
	 * @param list<int|string>     $target_path The loop's path.
	 * @param int                  $depth       The producer's path length.
	 *
	 * @return array{reason: string, block_id: int|string|null}|null
	 */
	private function own_tokens_refusal( array $node, array $target_path, int $depth ): ?array {
		if ( Wire_Node_Type::BLOCK !== ( $node[ Wire_Key::TYPE ] ?? '' ) ) {
			return null;
		}

		$id = $node[ Wire_Key::ID ] ?? '';

		if ( isset( $target_path[ $depth ] ) && (string) $target_path[ $depth ] === (string) $id ) {
			return null;
		}

		return $this->refused( Loop_Token_Refusal::INSIDE_OTHER_BLOCK, $id );
	}

	/**
	 * The first position where two paths differ, or the shorter length when one starts the other.
	 *
	 * @param list<int|string> $path        A producer's path.
	 * @param list<int|string> $target_path The loop's path.
	 *
	 * @return int
	 */
	private function fork( array $path, array $target_path ): int {
		$shorter = min( count( $path ), count( $target_path ) );

		for ( $position = 0; $position < $shorter; $position++ ) {
			if ( (string) $path[ $position ] !== (string) $target_path[ $position ] ) {
				return $position;
			}
		}

		return $shorter;
	}

	/**
	 * The block ids of a path, as strings.
	 *
	 * @param list<int|string> $path A walk path.
	 *
	 * @return string[]
	 */
	private function block_ids( array $path ): array {
		$ids = array();

		foreach ( $path as $position => $segment ) {
			if ( 0 === $position % 2 ) {
				$ids[] = (string) $segment;
			}
		}

		return $ids;
	}

	/**
	 * A refusal.
	 *
	 * @param string          $reason   A Loop_Token_Refusal value.
	 * @param int|string|null $block_id The block it names.
	 *
	 * @return array{reason: string, block_id: int|string|null}
	 */
	private function refused( string $reason, $block_id ): array {
		return array(
			'reason'   => $reason,
			'block_id' => $block_id,
		);
	}

	/**
	 * The loopable token ids a node publishes.
	 *
	 * @param array<string, mixed> $node A wire node.
	 *
	 * @return string[]
	 */
	private function loopable_token_ids( array $node ): array {
		$ids    = array();
		$tokens = is_array( $node[ Wire_Key::TOKENS ] ?? null ) ? $node[ Wire_Key::TOKENS ] : array();

		foreach ( $tokens as $token ) {
			$id = is_array( $token ) ? (string) ( $token[ Wire_Key::ID ] ?? '' ) : '';

			if ( Loopable_Token_Id::is_loopable( $id ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * The universal loopable token ids of the active integrations.
	 *
	 * @return string[]
	 */
	private function universal_token_ids(): array {
		$registry = $this->registry ?? new WP_Integration_Token_Registry();
		$ids      = array();

		foreach ( array_keys( $registry->get_available_tokens() ) as $token_id ) {
			if ( Loopable_Token_Id::is_loopable( (string) $token_id ) ) {
				$ids[] = (string) $token_id;
			}
		}

		return $ids;
	}

	/**
	 * Whether a node is an entry trigger.
	 *
	 * @param array<string, mixed> $node A wire node.
	 *
	 * @return bool
	 */
	private function is_trigger( array $node ): bool {
		return Wire_Node_Type::ITEM === ( $node[ Wire_Key::TYPE ] ?? '' ) && Integration_Item_Types::TRIGGER === ( $node[ Wire_Key::ITEM_TYPE ] ?? '' );
	}

	/**
	 * Whether a node is a loop block.
	 *
	 * @param array<string, mixed> $node A wire node.
	 *
	 * @return bool
	 */
	private function is_loop( array $node ): bool {
		return Wire_Node_Type::BLOCK === ( $node[ Wire_Key::TYPE ] ?? '' ) && Block_Type::LOOP === ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' );
	}

	/**
	 * Whether the site cannot run a node.
	 *
	 * @param array<string, mixed> $node A wire node.
	 *
	 * @return bool
	 */
	private function is_unavailable( array $node ): bool {
		$availability = is_array( $node[ Wire_Key::AVAILABILITY ] ?? null ) ? $node[ Wire_Key::AVAILABILITY ] : array();

		return Availability_Status::UNAVAILABLE === ( $availability[ Wire_Key::STATUS ] ?? '' );
	}
}
