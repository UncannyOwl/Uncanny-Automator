<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Layout;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Delay_Block;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Projection-time decorator that stamps `deferred_order` on every block node of
 * a serialized `flow` whose catalog layout is {@see Block_Layout::DEFERRED}.
 *
 * The number is the block's position among the deferred blocks the flow
 * carries, from 0, and is what the frontend orders their markers and their
 * section boxes by. Only the nodes the flow actually carries are numbered, so a
 * run's flow, which omits nodes added after the run started, stays contiguous.
 *
 * Ranking reads the `delay_schedule` vocabulary because Delay is the only block
 * that declares the deferred layout. A known scheduled time (a run's record)
 * ranks first, earliest first; then delay mode by duration, shortest first;
 * then schedule mode by date, earliest first; then token mode, whose wait is
 * unknown until a run resolves it. Any mode other than schedule or token waits
 * `delay_seconds`, as the runner does. Ties keep tree order.
 *
 * Runs at projection time and is never cached: the order follows the block's
 * fields, which the cached tree already carries, so a fresh tree gives fresh
 * numbers.
 *
 * @since 8.0.0
 */
final class Deferred_Order_Decorator {

	/**
	 * Rank group of a block whose run recorded the time it was scheduled for.
	 *
	 * @var int
	 */
	private const RANK_SCHEDULED = 0;

	/**
	 * Rank group of a block that waits a duration.
	 *
	 * @var int
	 */
	private const RANK_DELAY = 1;

	/**
	 * Rank group of a block that waits for a fixed date and time.
	 *
	 * @var int
	 */
	private const RANK_SCHEDULE = 2;

	/**
	 * Rank group of a block whose wait a token decides.
	 *
	 * @var int
	 */
	private const RANK_TOKEN = 3;

	/**
	 * Block definitions, read for each block type's declared layout.
	 *
	 * @var Block_Registry
	 */
	private Block_Registry $blocks;

	/**
	 * Whether each block type seen in the current call is deferred, by block type.
	 *
	 * @var array<string,bool>
	 */
	private array $deferred_types = array();

	/**
	 * Lazy-default DI: production reads the shared block registry; tests may
	 * inject their own.
	 *
	 * @param Block_Registry|null $blocks Block definition registry.
	 */
	public function __construct( ?Block_Registry $blocks = null ) {
		$this->blocks = $blocks ?? new WP_Block_Registry();
	}

	/**
	 * Stamp `deferred_order` on every deferred block node in the flow.
	 *
	 * @param array<string,mixed> $flow          The serialized flow ({ entry, …, content }).
	 * @param array<string,mixed> $tree          The schema tree the flow was projected from.
	 * @param callable|null       $scheduled_for `function ( array $tree_block ): ?int` — when a run actually
	 *                                           scheduled the block, or null; null for a recipe.
	 * @return array<string,mixed> The flow with `deferred_order` on each deferred block node.
	 */
	public function decorate( array $flow, array $tree, ?callable $scheduled_for = null ): array {
		$this->deferred_types = array();

		$deferred_ids = $this->deferred_ids( $flow );
		if ( empty( $deferred_ids ) ) {
			return $flow;
		}

		$order = $this->rank( $deferred_ids, $this->tree_blocks( $tree ), $scheduled_for );

		if ( isset( $flow[ Wire_Key::CONTENT ] ) && is_array( $flow[ Wire_Key::CONTENT ] ) ) {
			$flow[ Wire_Key::CONTENT ] = $this->stamp_list( $flow[ Wire_Key::CONTENT ], $order );
		}

		return $flow;
	}

	/**
	 * Ids of the flow's deferred block nodes, in walk order.
	 *
	 * @param array<string,mixed> $flow The serialized flow.
	 * @return list<string> Ids as strings; filter blocks may carry string ids.
	 */
	private function deferred_ids( array $flow ): array {
		$ids = array();

		foreach ( Flow_Walker::nodes( $flow ) as $entry ) {
			$node = $entry['node'];

			if ( Wire_Node_Type::BLOCK !== ( $node[ Wire_Key::TYPE ] ?? '' ) ) {
				continue;
			}

			if ( $this->is_deferred( (string) ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' ) ) ) {
				$ids[] = (string) ( $node[ Wire_Key::ID ] ?? '' );
			}
		}

		return $ids;
	}

	/**
	 * Whether a block type declares the deferred layout.
	 *
	 * @param string $block_type Block type.
	 * @return bool
	 */
	private function is_deferred( string $block_type ): bool {
		if ( ! isset( $this->deferred_types[ $block_type ] ) ) {
			$definition                          = $this->blocks->get_block_definition( $block_type );
			$this->deferred_types[ $block_type ] = null !== $definition && Block_Layout::DEFERRED === $definition->get_layout();
		}

		return $this->deferred_types[ $block_type ];
	}

	/**
	 * Every block entry in the tree, keyed by id as a string.
	 *
	 * @param array<string,mixed> $tree The schema tree.
	 * @return array<string,array<string,mixed>>
	 */
	private function tree_blocks( array $tree ): array {
		$blocks = array();
		$this->collect_tree_blocks( is_array( $tree['content'] ?? null ) ? $tree['content'] : array(), $blocks );

		return $blocks;
	}

	/**
	 * Depth-first collection of the block entries in a tree item list.
	 *
	 * @param array<int,mixed>                  $items  Tree items.
	 * @param array<string,array<string,mixed>> $blocks Accumulator, keyed by id as a string.
	 * @return void
	 */
	private function collect_tree_blocks( array $items, array &$blocks ): void {
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || 'block' !== ( $item['kind'] ?? '' ) ) {
				continue;
			}

			$blocks[ (string) ( $item['id'] ?? '' ) ] = $item;

			foreach ( is_array( $item['paths'] ?? null ) ? $item['paths'] : array() as $path ) {
				if ( is_array( $path ) && is_array( $path['items'] ?? null ) ) {
					$this->collect_tree_blocks( $path['items'], $blocks );
				}
			}
		}
	}

	/**
	 * Each deferred block's position, from 0, by id.
	 *
	 * @param list<string>                      $ids           Deferred block ids, in walk order.
	 * @param array<string,array<string,mixed>> $tree_blocks   Tree block entries by id.
	 * @param callable|null                     $scheduled_for When a run scheduled a block, or null.
	 * @return array<string,int>
	 */
	private function rank( array $ids, array $tree_blocks, ?callable $scheduled_for ): array {
		$ranks = array();
		foreach ( $ids as $index => $id ) {
			$ranks[] = array(
				'id'  => $id,
				'key' => $this->rank_key( $tree_blocks[ $id ] ?? array(), $scheduled_for, $index ),
			);
		}

		// Every key ends with the walk index, so no two compare equal and the order holds on PHP 7.4, whose usort is not stable.
		usort(
			$ranks,
			function ( array $a, array $b ): int {
				return $a['key'] <=> $b['key'];
			}
		);

		$order = array();
		foreach ( $ranks as $position => $rank ) {
			$order[ $rank['id'] ] = $position;
		}

		return $order;
	}

	/**
	 * The sort key of one deferred block: rank group, value within the group, walk index.
	 *
	 * @param array<string,mixed> $block         Tree block entry.
	 * @param callable|null       $scheduled_for When a run scheduled a block, or null.
	 * @param int                 $index         Walk index.
	 * @return array{0:int,1:int,2:int}
	 */
	private function rank_key( array $block, ?callable $scheduled_for, int $index ): array {
		$scheduled = null === $scheduled_for ? null : $scheduled_for( $block );
		if ( null !== $scheduled ) {
			return array( self::RANK_SCHEDULED, (int) $scheduled, $index );
		}

		$mode = strtolower( Fields_Bag::from_array( is_array( $block['fields'] ?? null ) ? $block['fields'] : array() )->scalar( Delay_Block::FIELD_MODE ) );

		if ( Delay_Block::MODE_SCHEDULE === $mode ) {
			return array( self::RANK_SCHEDULE, (int) ( $block['schedule_at'] ?? 0 ), $index );
		}

		if ( Delay_Block::MODE_TOKEN === $mode ) {
			return array( self::RANK_TOKEN, 0, $index );
		}

		return array( self::RANK_DELAY, (int) ( $block['delay_seconds'] ?? 0 ), $index );
	}

	/**
	 * Stamp the numbered block nodes in a node list, at any depth.
	 *
	 * @param array<int,mixed> $nodes Wire nodes.
	 * @param array<string,int> $order Positions by block id.
	 * @return array<int,mixed>
	 */
	private function stamp_list( array $nodes, array $order ): array {
		foreach ( $nodes as $i => $node ) {
			if ( ! is_array( $node ) || Wire_Node_Type::BLOCK !== ( $node[ Wire_Key::TYPE ] ?? '' ) ) {
				continue;
			}

			$id = (string) ( $node[ Wire_Key::ID ] ?? '' );
			if ( isset( $order[ $id ] ) ) {
				$node[ Wire_Key::DEFERRED_ORDER ] = $order[ $id ];
			}

			foreach ( is_array( $node[ Wire_Key::PATHS ] ?? null ) ? $node[ Wire_Key::PATHS ] : array() as $p => $path ) {
				if ( is_array( $path ) && is_array( $path[ Wire_Key::CONTENT ] ?? null ) ) {
					$node[ Wire_Key::PATHS ][ $p ][ Wire_Key::CONTENT ] = $this->stamp_list( $path[ Wire_Key::CONTENT ], $order );
				}
			}

			$nodes[ $i ] = $node;
		}

		return $nodes;
	}
}
