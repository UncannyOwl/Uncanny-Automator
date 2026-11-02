<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;

/**
 * Which steps a keep-mode block delete must remove because they would land
 * somewhere the placement rules reject.
 *
 * Backend mirror of the frontend's getBlockKeepContentPlan: every moved step
 * is checked at its landing position, at every depth, through
 * Placement_Rule_Service, the same rules getChainNestingViolation walks. The
 * two must agree exactly — a step the dialog showed as deleted and the
 * backend kept reappears on the safety-net refetch.
 *
 * Evaluates on/off alike: the frontend plan has no live filter.
 */
final class Block_Keep_Content_Planner {

	/**
	 * Depth guard against a parent cycle in corrupt data.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 32;

	/**
	 * Block and child row reader.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * The placement rules.
	 *
	 * @var Placement_Rule_Service
	 */
	private Placement_Rule_Service $rules;

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @param Block_Store|null            $block_store Block store.
	 * @param Placement_Rule_Service|null $rules       Placement rules.
	 */
	public function __construct( ?Block_Store $block_store = null, ?Placement_Rule_Service $rules = null ) {
		$this->block_store = $block_store ?? Database::get_block_store();
		$this->rules       = $rules ?? Placement_Rule_Service::instance();
	}

	/**
	 * The moved steps, at any depth, that break a placement rule at their new position.
	 *
	 * @param array  $block        Block being deleted, as the store returns it.
	 * @param array  $moved        Its direct steps, as the store returns them.
	 * @param string $landing_path Path the moved steps land on; empty at the recipe root.
	 *
	 * @return array<int,array{id:int,entity_type:string,code:string,reason:string}> Steps to delete, each with its block type or action code and the reason the rule gives (empty when the rule gives none). A listed block's contents go with it and are not listed.
	 */
	public function violating_steps( array $block, array $moved, string $landing_path ): array {
		$chain     = $this->rules->chain_for( (int) ( $block['parent_id'] ?? 0 ), $landing_path );
		$violators = array();

		foreach ( $moved as $step ) {
			$this->collect( $step, $chain, $violators, 0 );
		}

		return $violators;
	}

	/**
	 * Check one step; when it survives and is a block, check its contents
	 * against the chain extended with the step itself.
	 *
	 * @param array                                   $step      Store row.
	 * @param array<int,array>                        $chain     Ancestors the step sits under after the move, outermost first.
	 * @param array<int,array{id:int,entity_type:string,code:string,reason:string}> $violators Accumulator.
	 * @param int                                     $depth     Recursion depth.
	 *
	 * @return void
	 */
	private function collect( array $step, array $chain, array &$violators, int $depth ): void {
		$is_block  = 'block' === ( $step['entity_type'] ?? '' );
		$violation = $this->rules->violation( $chain, $this->candidate( $step ) );
		if ( null !== $violation ) {
			$violators[] = array(
				'id'          => self::step_id( $step ),
				'entity_type' => $is_block ? 'block' : 'item',
				'code'        => $is_block ? (string) ( $step['block_type'] ?? '' ) : (string) ( $step['meta']['code'] ?? '' ),
				'reason'      => $violation->get_reason(),
			);
			return;
		}

		if ( ! $is_block || $depth >= self::MAX_DEPTH ) {
			return;
		}

		foreach ( $this->block_store->get_children( self::step_id( $step ) ) as $child ) {
			$deeper   = $chain;
			$deeper[] = Placement_Rule_Service::entry(
				(string) ( $step['block_type'] ?? '' ),
				is_array( $step['fields'] ?? null ) ? $step['fields'] : array(),
				(string) ( $child['path_code'] ?? '' )
			);
			$this->collect( $child, $deeper, $violators, $depth + 1 );
		}
	}

	/**
	 * The candidate a store row is: a block by its type, an action by its code and integration.
	 *
	 * @param array $step Store row.
	 *
	 * @return array
	 */
	private function candidate( array $step ): array {
		if ( 'block' === ( $step['entity_type'] ?? '' ) ) {
			return $this->rules->block_candidate( (string) ( $step['block_type'] ?? '' ) );
		}

		return $this->rules->action_candidate(
			(string) ( $step['meta']['code'] ?? '' ),
			(string) ( $step['meta']['integration'] ?? '' )
		);
	}

	/**
	 * A store row's post id — `id` on block rows, `ID` on item rows.
	 *
	 * @param array $row Store row.
	 *
	 * @return int
	 */
	private static function step_id( array $row ): int {
		return (int) ( $row['id'] ?? $row['ID'] ?? 0 );
	}
}
