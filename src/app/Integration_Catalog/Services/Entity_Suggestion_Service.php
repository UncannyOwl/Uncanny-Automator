<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Services;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity\Entity_Popularity_Calculator;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Suggestions\Suggestion_Curated_Defaults;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Registry_Service;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Ancestor_Rule_Evaluator;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Placement_Rule_Service;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Loop_Block;

/**
 * Orchestrates AI entity suggestions: popularity + curated blend, availability
 * and unsupported_entities filtering, TOKEN_MEETS_CONDITION pin. The Phase-1
 * algorithm lives here; Phase 2/3 swap internals behind suggest().
 *
 * Validation failures throw {@see Entity_Suggestion_Exception} with a string
 * code the controller maps to a WP_Error (400/403/404).
 */
class Entity_Suggestion_Service {

	/**
	 * Maximum suggestions returned per request.
	 */
	const MAX = 5;

	/**
	 * Score added to curated entries when blending with popularity.
	 */
	const CURATED_BOOST = 0.5;

	/**
	 * Entry pinned to index zero for filter_condition suggestions.
	 */
	const PINNED = array(
		'integration_code' => 'GEN',
		'code'             => 'TOKEN_MEETS_CONDITION',
	);

	/**
	 * Popularity signal source.
	 *
	 * @var Entity_Popularity_Calculator
	 */
	private $popularity;

	/**
	 * Integration availability oracle.
	 *
	 * @var Integration_Registry_Service
	 */
	private $registry;

	/**
	 * Block definition source.
	 *
	 * @var WP_Block_Registry
	 */
	private $block_registry;

	/**
	 * Catalog item lookup for trigger eligibility.
	 *
	 * @var Integration_Query_Service
	 */
	private $query;

	/**
	 * Placement-rule evaluator, mirroring the frontend's rejection semantics.
	 *
	 * @var Ancestor_Rule_Evaluator
	 */
	private $evaluator;

	/**
	 * Block and action placement rules, for action candidates.
	 *
	 * @var Placement_Rule_Service
	 */
	private $placement;

	/**
	 * Lazy-default DI per Rule 9c: production gets the defaults, tests inject fakes.
	 *
	 * @param Entity_Popularity_Calculator|null $popularity     Popularity calculator (test seam).
	 * @param Integration_Registry_Service|null $registry       Registry service (test seam).
	 * @param WP_Block_Registry|null            $block_registry Block definition registry (test seam).
	 * @param Integration_Query_Service|null    $query          Catalog item lookup (test seam).
	 * @param Ancestor_Rule_Evaluator|null      $evaluator      Loop-filter rule evaluator (test seam).
	 * @param Placement_Rule_Service|null       $placement      Block and action placement rules (test seam).
	 */
	public function __construct(
		?Entity_Popularity_Calculator $popularity = null,
		?Integration_Registry_Service $registry = null,
		?WP_Block_Registry $block_registry = null,
		?Integration_Query_Service $query = null,
		?Ancestor_Rule_Evaluator $evaluator = null,
		?Placement_Rule_Service $placement = null
	) {
		// Shared instance: rebuild coalescing is a per-instance flag, so a second
		// calculator registers a second shutdown rebuild.
		$this->popularity     = $popularity ?? Entity_Popularity_Calculator::instance();
		$this->registry       = $registry ?? Integration_Registry_Service::get_instance();
		$this->block_registry = $block_registry ?? new WP_Block_Registry();
		$this->query          = $query ?? Integration_Query_Service::get_instance();
		$this->evaluator      = $evaluator ?? new Ancestor_Rule_Evaluator();
		$this->placement      = $placement ?? Placement_Rule_Service::instance();
	}

	/**
	 * Produce the top suggestions for a slot.
	 *
	 * @param int         $recipe_id Recipe post ID the suggestion is scoped to.
	 * @param string      $kind      `'item'` or `'block_modifier'`.
	 * @param string      $scope     Item type or block-modifier type literal.
	 * @param string      $parent_id Parent node id (recipe root id, or a block id).
	 * @param string|null $path_code Path code when the parent is a block, else null.
	 *
	 * @return array<int,array{integration_code:string,code:string}>
	 * @throws Entity_Suggestion_Exception When validation fails.
	 */
	public function suggest( int $recipe_id, string $kind, string $scope, string $parent_id, ?string $path_code = null ): array {
		$this->assert_scope_belongs_to_kind( $kind, $scope );

		$recipe = Database::get_recipe_store()->get( $recipe_id );
		if ( null === $recipe ) {
			throw new Entity_Suggestion_Exception( 'recipe_not_found', 404 );
		}
		$recipe_type = $recipe->get_recipe_type()->get_value();

		$parent_block = $this->resolve_parent_block( $recipe_id, $parent_id );
		$this->assert_path_code_rules( $parent_block, $path_code, $kind );

		if ( 'block_modifier' === $kind && null !== $parent_block && ! $this->parent_accepts_modifier( $parent_block, $scope ) ) {
			return array();
		}

		$scores = array();
		$this->blend_popularity( $scores, $kind, $scope );
		$this->blend_curated( $scores, $kind, $scope, $recipe_type, $this->resolve_loop_type( $parent_block ) );

		$candidates = $this->to_sorted_candidates( $scores );
		$candidates = $this->drop_inactive( $candidates );
		if ( 'block_modifier' === $kind && null !== $parent_block ) {
			$candidates = $this->drop_ancestor_violations( $candidates, $parent_block, $scope );
		}

		if ( 'item' === $kind && Integration_Item_Types::ACTION === $scope ) {
			$candidates = $this->drop_placement_violations( $candidates, $parent_block, $path_code );
		}

		if ( 'item' === $kind && Integration_Item_Types::TRIGGER === $scope ) {
			$candidates = $this->drop_ineligible_triggers( $candidates, $recipe_type );
		}

		if ( 'block_modifier' === $kind && Block_Modifier_Types::FILTER_CONDITION === $scope ) {
			$candidates = $this->pin_token_meets_condition( $candidates );
		}

		$top = array_slice( $candidates, 0, self::MAX );
		return array_map(
			function ( $c ) {
				return array(
					'integration_code' => $c['integration_code'],
					'code'             => $c['code'],
				);
			},
			$top
		);
	}

	/**
	 * Validate that the scope literal is legal for the kind.
	 *
	 * @param string $kind  Suggestion kind.
	 * @param string $scope Scope literal.
	 *
	 * @return void
	 * @throws Entity_Suggestion_Exception When kind/scope is invalid.
	 */
	private function assert_scope_belongs_to_kind( string $kind, string $scope ): void {
		$valid = array(
			'item'           => array( Integration_Item_Types::TRIGGER, Integration_Item_Types::ACTION ),
			'block_modifier' => array( Block_Modifier_Types::FILTER_CONDITION, Block_Modifier_Types::LOOP_FILTER ),
		);
		if ( ! isset( $valid[ $kind ] ) ) {
			throw new Entity_Suggestion_Exception( 'invalid_kind', 400 );
		}
		if ( ! in_array( $scope, $valid[ $kind ], true ) ) {
			throw new Entity_Suggestion_Exception( 'invalid_scope', 400 );
		}
	}

	/**
	 * Resolve the parent into a block row, or null for the recipe root.
	 *
	 * The parent must belong to the requested recipe — edit capability was
	 * checked against that recipe only, so a foreign block must not leak its
	 * block_type/paths/unsupported_entities into the response. Anything
	 * else — unknown, deleted, or foreign — is a 404, never a silent root
	 * fallback.
	 *
	 * @param int    $recipe_id Recipe post ID.
	 * @param string $parent_id Parent node id.
	 *
	 * @return array|null Block row (Infrastructure Block_Store::get shape) or null for recipe root.
	 * @throws Entity_Suggestion_Exception When the parent is unknown or owned by another recipe.
	 */
	private function resolve_parent_block( int $recipe_id, string $parent_id ) {
		if ( (string) $recipe_id === $parent_id ) {
			return null;
		}

		$block = Database::get_block_store()->get( (int) $parent_id );
		if ( null !== $block ) {
			if ( (int) ( $block['recipe_id'] ?? 0 ) !== $recipe_id ) {
				throw new Entity_Suggestion_Exception( 'parent_not_found', 404 );
			}
			return $block;
		}

		throw new Entity_Suggestion_Exception( 'parent_not_found', 404 );
	}

	/**
	 * Enforce path_code presence/validity relative to the parent and kind.
	 *
	 * Path codes scope ITEM placement inside a block's path. Block modifiers
	 * attach at block level — the MODIFIERS slot has no path context — so a
	 * path_code on a block_modifier request is as wrong as one at the root.
	 *
	 * @param array|null  $parent_block Resolved parent block row or null at root.
	 * @param string|null $path_code    Supplied path code.
	 * @param string      $kind         Suggestion kind.
	 *
	 * @return void
	 * @throws Entity_Suggestion_Exception When the path_code rules are violated.
	 */
	private function assert_path_code_rules( $parent_block, ?string $path_code, string $kind ): void {
		$has_path_code = null !== $path_code && '' !== $path_code;

		if ( null === $parent_block || 'block_modifier' === $kind ) {
			if ( $has_path_code ) {
				throw new Entity_Suggestion_Exception( 'path_code_forbidden', 400 );
			}
			return;
		}
		if ( ! $has_path_code ) {
			throw new Entity_Suggestion_Exception( 'path_code_required', 400 );
		}
		$def = $this->block_registry->get_block_definition( (string) $parent_block['block_type'] );
		if ( null === $def ) {
			return;
		}
		$valid = array();
		foreach ( $def->get_paths() as $path ) {
			$valid[] = $path->get_path_code();
		}
		if ( ! in_array( $path_code, $valid, true ) ) {
			throw new Entity_Suggestion_Exception( 'invalid_path_code', 400 );
		}
	}

	/**
	 * Whether the parent block declares the requested modifier type.
	 *
	 * @param array  $parent_block Resolved parent block row.
	 * @param string $scope        Block-modifier type literal.
	 *
	 * @return bool
	 */
	private function parent_accepts_modifier( $parent_block, string $scope ): bool {
		$def = $this->block_registry->get_block_definition( (string) $parent_block['block_type'] );
		if ( null === $def ) {
			return false;
		}
		foreach ( $def->get_modifier_types() as $type ) {
			if ( $scope === $type->get_id() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Blend normalized popularity counts into the score map.
	 *
	 * @param array<string,float> $scores Score map keyed by "INT:CODE" (by ref).
	 * @param string              $kind   Suggestion kind.
	 * @param string              $scope  Scope literal.
	 *
	 * @return void
	 */
	private function blend_popularity( array &$scores, string $kind, string $scope ): void {
		$counts = $this->popularity->get_usage_counts( $kind, $scope );
		if ( empty( $counts ) ) {
			return;
		}
		$max = max( $counts );
		foreach ( $counts as $key => $n ) {
			$scores[ $key ] = $max > 0 ? ( $n / $max ) : 0.0;
		}
	}

	/**
	 * Blend curated cold-start boosts into the score map.
	 *
	 * @param array<string,float> $scores      Score map keyed by "INT:CODE" (by ref).
	 * @param string              $kind        Suggestion kind.
	 * @param string              $scope       Scope literal.
	 * @param string              $recipe_type Recipe user type.
	 * @param string|null         $loop_type   Parent loop's iteration type, or null.
	 *
	 * @return void
	 */
	private function blend_curated( array &$scores, string $kind, string $scope, string $recipe_type, ?string $loop_type = null ): void {
		foreach ( Suggestion_Curated_Defaults::get_for( $kind, $scope, $recipe_type, $loop_type ) as $entry ) {
			$key            = $entry['integration_code'] . ':' . $entry['code'];
			$scores[ $key ] = ( $scores[ $key ] ?? 0.0 ) + self::CURATED_BOOST;
		}
	}

	/**
	 * Sort the score map descending and expand to candidate rows.
	 *
	 * @param array<string,float> $scores Score map keyed by "INT:CODE".
	 *
	 * @return array<int,array{integration_code:string,code:string,score:float}>
	 */
	private function to_sorted_candidates( array $scores ): array {
		arsort( $scores );
		$out = array();
		foreach ( $scores as $key => $score ) {
			$parts = explode( ':', $key, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$out[] = array(
				'integration_code' => $parts[0],
				'code'             => $parts[1],
				'score'            => (float) $score,
			);
		}
		return $out;
	}

	/**
	 * Drop candidates whose integration is not registered/active.
	 *
	 * @param array<int,array> $candidates Candidate rows.
	 *
	 * @return array<int,array>
	 */
	private function drop_inactive( array $candidates ): array {
		return array_values(
			array_filter(
				$candidates,
				function ( $c ) {
					return $this->registry->has_integration( $c['integration_code'] );
				}
			)
		);
	}

	/**
	 * Drop action candidates the placement rules reject where they would land,
	 * BEFORE the result limit so eligible candidates backfill: every block
	 * above the slot, each one's rows for the path the chain passes through,
	 * and the action's own declared rules, through the one evaluator the
	 * builder's tile check mirrors. At the recipe root the chain is empty and
	 * only a required-ancestor rule can reject — the one place the builder's
	 * own check never runs.
	 *
	 * @param array<int,array> $candidates   Ranked candidate rows.
	 * @param array|null       $parent_block Resolved parent block row, or null at the recipe root.
	 * @param string|null      $path_code    Supplied path code; null for the parent's default path.
	 *
	 * @return array<int,array>
	 */
	private function drop_placement_violations( array $candidates, $parent_block, ?string $path_code ): array {
		$chain = is_array( $parent_block )
			? $this->placement->chain_for( (int) ( $parent_block['id'] ?? 0 ), (string) ( $path_code ?? '' ) )
			: array();

		return array_values(
			array_filter(
				$candidates,
				function ( $c ) use ( $chain ) {
					return null === $this->placement->violation(
						$chain,
						$this->placement->action_candidate( (string) $c['code'], (string) $c['integration_code'] )
					);
				}
			)
		);
	}

	/**
	 * Pin GEN:TOKEN_MEETS_CONDITION to index zero, de-duping any existing entry.
	 *
	 * Must run AFTER drop_inactive: GEN is a Pro pseudo-integration the Free
	 * catalog registry doesn't know, so the availability filter would drop
	 * the pin on Free-only installs. Running last also means the pin overrides
	 * any future block-level unsupported_entities exclusion — acceptable while
	 * no block excludes it; revisit if one ever does.
	 *
	 * @param array<int,array> $candidates Candidate rows.
	 *
	 * @return array<int,array>
	 */
	private function pin_token_meets_condition( array $candidates ): array {
		$rest = array_values(
			array_filter(
				$candidates,
				function ( $c ) {
					return ! ( self::PINNED['integration_code'] === $c['integration_code'] && self::PINNED['code'] === $c['code'] );
				}
			)
		);
		array_unshift(
			$rest,
			array(
				'integration_code' => self::PINNED['integration_code'],
				'code'             => self::PINNED['code'],
				'score'            => 999.0,
			)
		);
		return $rest;
	}

	/**
	 * Drop trigger candidates whose user requirement mismatches the recipe
	 * type, BEFORE the result limit so lower-ranked eligible candidates
	 * backfill. A candidate that cannot be resolved in the catalog is
	 * dropped too (spec D4) — its eligibility is unknowable and the
	 * builder cannot add it.
	 *
	 * @param array<int,array> $candidates  Ranked candidate rows.
	 * @param string           $recipe_type 'user' or 'anonymous'.
	 * @return array<int,array>
	 */
	private function drop_ineligible_triggers( array $candidates, string $recipe_type ): array {
		$wants_user = 'anonymous' !== $recipe_type;

		return array_values(
			array_filter(
				$candidates,
				function ( $c ) use ( $wants_user ) {
					$item = $this->query->get_item( $c['integration_code'], $c['code'], Integration_Item_Types::TRIGGER );
					if ( null === $item ) {
						return false;
					}

					return (bool) $item->to_array()['requires_user_data'] === $wants_user;
				}
			)
		);
	}

	/**
	 * Drop modifier candidates the parent block's configuration rejects,
	 * BEFORE the result limit so lower-ranked eligible candidates backfill.
	 *
	 * The chain carries the parent's own `fields` rather than its
	 * `iterable_expression`: the frontend evaluates `fields[FIELD].value` when
	 * it decides whether a tile is clickable, and this endpoint exists to never
	 * offer what that check would reject. The two can legitimately diverge —
	 * an explicit iterable_expression overrides LOOP_TYPE on write.
	 *
	 * A candidate that cannot be resolved in the catalog is dropped: it is not
	 * in this site's catalog at all, so the builder cannot render it.
	 *
	 * @param array<int,array> $candidates   Ranked candidate rows.
	 * @param array            $parent_block Resolved parent block row.
	 * @param string           $scope        Block-modifier type literal.
	 *
	 * @return array<int,array>
	 */
	private function drop_ancestor_violations( array $candidates, array $parent_block, string $scope ): array {
		$fields = $parent_block['fields'] ?? array();

		$chain = array(
			array(
				'block_type' => (string) ( $parent_block['block_type'] ?? '' ),
				'fields'     => is_array( $fields ) ? $fields : array(),
			),
		);

		return array_values(
			array_filter(
				$candidates,
				function ( $c ) use ( $chain, $scope ) {
					$modifier = $this->query->get_block_modifier( $c['integration_code'], $c['code'], $scope );
					if ( null === $modifier ) {
						return false;
					}

					$row = $modifier->to_array();

					return ! $this->evaluator->is_violation(
						$chain,
						array(
							'required_block_ancestors'    => $row['required_block_ancestors'] ?? array(),
							'unsupported_block_ancestors' => $row['unsupported_block_ancestors'] ?? array(),
						)
					);
				}
			)
		);
	}

	/**
	 * The parent loop's configured iteration type, or null when the parent is
	 * not a loop or has not been configured yet.
	 *
	 * Reads the block's own `fields` for the same reason
	 * {@see self::drop_ancestor_violations()} does — the curated list it seeds
	 * must agree with the gate that then filters it.
	 *
	 * @param array|null $parent_block Resolved parent block row, or null at the recipe root.
	 *
	 * @return string|null
	 */
	private function resolve_loop_type( $parent_block ): ?string {
		if ( ! is_array( $parent_block ) || Block_Type::LOOP !== ( $parent_block['block_type'] ?? '' ) ) {
			return null;
		}

		$value = $parent_block['fields'][ Loop_Block::FIELD_LOOP_TYPE ]['value'] ?? '';

		return '' === $value ? null : (string) $value;
	}
}
