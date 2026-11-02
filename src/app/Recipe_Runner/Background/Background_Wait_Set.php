<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Background;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Tree_Walk;
use Uncanny_Automator\Automator_Status;

/**
 * The run's unfinished background work as one walk sees it: actions whose job is still open, with their
 * integration codes, and the nodes already recorded as waiting. The walker consults it before every node and
 * feeds it as actions go into the background; the completion entry rebuilds it from the rows and the record.
 */
final class Background_Wait_Set {

	/**
	 * Awaiting actions: `action_id => integration_code`.
	 *
	 * @var array<int, string>
	 */
	private array $awaiting;

	/**
	 * Blocked nodes: `node_id => { kind, waiting_on, integration_code, covers }`, in the order they were blocked,
	 * plus `claimed_by` (the job id of the completion running or skipping the node) while one holds it.
	 *
	 * @var array<int, array{kind: string, waiting_on: int[], integration_code: string, covers: int[], claimed_by?: string}>
	 */
	private array $blocked;

	/**
	 * Start from known state.
	 *
	 * @param array<int, string>                $awaiting `action_id => integration_code`.
	 * @param array<int, array<string, mixed>> $blocked  `node_id => record`.
	 */
	public function __construct( array $awaiting = array(), array $blocked = array() ) {
		$this->awaiting = array();
		foreach ( $awaiting as $action_id => $integration_code ) {
			$this->awaiting[ (int) $action_id ] = (string) $integration_code;
		}
		$this->blocked = array();
		foreach ( $blocked as $node_id => $record ) {
			$this->blocked[ (int) $node_id ] = self::normalise( (array) $record );
		}
	}

	/**
	 * The set for a run in progress: awaiting rows (integration from the tree) plus the blocked-nodes record.
	 *
	 * @param Execution_Log_Store              $log_store     Rows and meta.
	 * @param int                              $recipe_log_id The run.
	 * @param array<int, array<string, mixed>> $items         The tree's `content`.
	 *
	 * @return self
	 */
	public static function from_run( Execution_Log_Store $log_store, int $recipe_log_id, array $items ): self {
		$integrations = array();
		foreach ( Tree_Walk::action_nodes( $items ) as $node ) {
			$integrations[ (int) $node['id'] ] = (string) ( $node['integration_code'] ?? '' );
		}

		$awaiting = array();
		foreach ( $log_store->get_action_log_rows( $recipe_log_id ) as $row ) {
			$row = (array) $row;
			if ( Automator_Status::COMPLETED_AWAITING === (int) $row['completed'] ) {
				$action_id              = (int) $row['automator_action_id'];
				$awaiting[ $action_id ] = $integrations[ $action_id ] ?? '';
			}
		}

		$blocked = json_decode( (string) $log_store->get_recipe_log_meta( $recipe_log_id, Execution_Log_Store::META_BLOCKED_NODES ), true );

		return new self( $awaiting, is_array( $blocked ) ? $blocked : array() );
	}

	/**
	 * The unfinished actions this node has to wait for: its token dependencies that are awaiting, blocked or
	 * inside a blocked block, plus every awaiting or blocked action of its own integration.
	 *
	 * Records sit in walk order. A node that has a record of its own (one being released by a completion) counts
	 * only the records before it: a later sibling never blocks an earlier node. A node with no record (the walk
	 * meeting it for the first time) counts every record, since all of them came before it.
	 *
	 * @param array<string, mixed> $node Tree node with `kind`, `id`, `depends_on` and, for an action, `integration_code`.
	 *
	 * @return int[] Sorted, unique.
	 */
	public function blockers_for( array $node ): array {
		if ( $this->is_empty() ) {
			return array();
		}

		$earlier    = $this->records_before( (int) ( $node['id'] ?? 0 ) );
		$unfinished = $this->unfinished_action_ids( $earlier );
		$blockers   = array_intersect( array_map( 'intval', (array) ( $node['depends_on'] ?? array() ) ), $unfinished );

		$integration = 'action' === (string) ( $node['kind'] ?? '' ) ? (string) ( $node['integration_code'] ?? '' ) : '';
		if ( '' !== $integration ) {
			foreach ( $this->awaiting as $action_id => $code ) {
				if ( $code === $integration ) {
					$blockers[] = $action_id;
				}
			}
			foreach ( $earlier as $node_id => $record ) {
				if ( 'action' === $record['kind'] && $record['integration_code'] === $integration ) {
					$blockers[] = $node_id;
				}
			}
		}

		$blockers = array_values( array_unique( array_map( 'intval', $blockers ) ) );
		sort( $blockers );

		return $blockers;
	}

	/**
	 * An action's job was accepted.
	 *
	 * @param int    $action_id        The action.
	 * @param string $integration_code Its integration.
	 *
	 * @return void
	 */
	public function add_awaiting( int $action_id, string $integration_code ): void {
		$this->awaiting[ $action_id ] = $integration_code;
	}

	/**
	 * A node was held back.
	 *
	 * @param int    $node_id          The action or block.
	 * @param string $kind             `action` or `block`.
	 * @param int[]  $waiting_on       The unfinished actions it waits for.
	 * @param string $integration_code The action's integration; empty for a block.
	 * @param int[]  $covers           For a block, the action ids under it, which count as unfinished while it waits.
	 *
	 * @return void
	 */
	public function add_blocked( int $node_id, string $kind, array $waiting_on, string $integration_code, array $covers ): void {
		$this->blocked[ $node_id ] = self::normalise(
			array(
				'kind'             => $kind,
				'waiting_on'       => $waiting_on,
				'integration_code' => $integration_code,
				'covers'           => $covers,
			)
		);
	}

	/**
	 * A blocked node was released or skipped.
	 *
	 * @param int $node_id The node.
	 *
	 * @return void
	 */
	public function forget( int $node_id ): void {
		unset( $this->blocked[ $node_id ] );
	}

	/**
	 * The record for a blocked node, as stored in the run meta; `claimed_by` is present only while a completion
	 * holds the node.
	 *
	 * @param int $node_id The node.
	 *
	 * @return array{kind: string, waiting_on: int[], integration_code: string, covers: int[], claimed_by?: string}|null
	 */
	public function record_for( int $node_id ): ?array {
		return $this->blocked[ $node_id ] ?? null;
	}

	/**
	 * Blocked nodes whose every awaited action has finished, in the order they were blocked.
	 *
	 * @param callable $is_finished `( int $action_id ): bool`.
	 *
	 * @return int[]
	 */
	public function ready( callable $is_finished ): array {
		$ready = array();
		foreach ( $this->blocked as $node_id => $record ) {
			$waiting = array_filter(
				$record['waiting_on'],
				static function ( int $action_id ) use ( $is_finished ): bool {
					return ! $is_finished( $action_id );
				}
			);
			if ( array() === $waiting ) {
				$ready[] = $node_id;
			}
		}

		return $ready;
	}

	/**
	 * Blocked nodes with a blocker that failed, in the order they were blocked. They are skipped whatever else
	 * they wait for.
	 *
	 * @param callable $has_failed `( int $action_id ): bool`.
	 *
	 * @return int[]
	 */
	public function doomed( callable $has_failed ): array {
		$doomed = array();
		foreach ( $this->blocked as $node_id => $record ) {
			foreach ( $record['waiting_on'] as $action_id ) {
				if ( $has_failed( $action_id ) ) {
					$doomed[] = $node_id;
					break;
				}
			}
		}

		return $doomed;
	}

	/**
	 * Every blocked node, in the order they were blocked.
	 *
	 * @return int[]
	 */
	public function blocked_node_ids(): array {
		return array_keys( $this->blocked );
	}

	/**
	 * Blocked nodes waiting on this action, in the order they were blocked.
	 *
	 * @param int $action_id The action.
	 *
	 * @return int[]
	 */
	public function dependents_of( int $action_id ): array {
		$out = array();
		foreach ( $this->blocked as $node_id => $record ) {
			if ( in_array( $action_id, $record['waiting_on'], true ) ) {
				$out[] = $node_id;
			}
		}

		return $out;
	}

	/**
	 * Whether nothing is awaiting and nothing is blocked.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->awaiting && array() === $this->blocked;
	}

	/**
	 * The blocked records before a node's own, in walk order; every record when the node has none.
	 *
	 * @param int $node_id The node being asked about.
	 *
	 * @return array<int, array{kind: string, waiting_on: int[], integration_code: string, covers: int[], claimed_by?: string}>
	 */
	private function records_before( int $node_id ): array {
		if ( ! isset( $this->blocked[ $node_id ] ) ) {
			return $this->blocked;
		}
		$before = array();
		foreach ( $this->blocked as $id => $record ) {
			if ( $id === $node_id ) {
				break;
			}
			$before[ $id ] = $record;
		}

		return $before;
	}

	/**
	 * Every action id that counts as unfinished: awaiting, blocked, or under a blocked block.
	 *
	 * @param array<int, array<string, mixed>> $blocked The blocked records to count.
	 *
	 * @return int[]
	 */
	private function unfinished_action_ids( array $blocked ): array {
		$ids = array_keys( $this->awaiting );
		foreach ( $blocked as $node_id => $record ) {
			if ( 'action' === $record['kind'] ) {
				$ids[] = $node_id;
			}
			foreach ( $record['covers'] as $covered ) {
				$ids[] = $covered;
			}
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * One record shape, whatever the source.
	 *
	 * @param array<string, mixed> $record Raw record.
	 *
	 * @return array{kind: string, waiting_on: int[], integration_code: string, covers: int[], claimed_by?: string}
	 */
	private static function normalise( array $record ): array {
		$normalised = array(
			'kind'             => 'block' === (string) ( $record['kind'] ?? '' ) ? 'block' : 'action',
			'waiting_on'       => array_values( array_unique( array_map( 'intval', (array) ( $record['waiting_on'] ?? array() ) ) ) ),
			'integration_code' => (string) ( $record['integration_code'] ?? '' ),
			'covers'           => array_values( array_unique( array_map( 'intval', (array) ( $record['covers'] ?? array() ) ) ) ),
		);
		if ( '' !== (string) ( $record['claimed_by'] ?? '' ) ) {
			$normalised['claimed_by'] = (string) $record['claimed_by'];
		}

		return $normalised;
	}
}
