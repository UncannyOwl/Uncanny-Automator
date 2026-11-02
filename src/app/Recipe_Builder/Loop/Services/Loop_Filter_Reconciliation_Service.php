<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Loop\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store\Loop_Filter_Ancestor_Rule_Builder;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Ancestor_Rule_Evaluator;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;

/**
 * Removes a loop's loop filters whose ancestor rules no longer hold under the
 * loop's current iteration type. Operates on `uo-block-modifier` rows
 * (discriminator `loop_filter`).
 *
 * Backend mirror of the frontend getBlockModifiersToClear / getInvalidatedModifiers:
 * resolves each filter to its catalog rules and evaluates them against the
 * single-block chain [the loop carrying its new fields]. A filter whose code is
 * unknown to the registry is kept (mirrors the FE's "undefined → valid").
 */
class Loop_Filter_Reconciliation_Service {

	/**
	 * Unified modifier persistence boundary (`uo-block-modifier` posts) —
	 * the storage unified loops carry their filters in.
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Loop-filter registry used to resolve a filter's iteration_types by code.
	 *
	 * @var Filter_Registry_Service
	 */
	private Filter_Registry_Service $registry;

	/**
	 * Builds required ancestor rules from a filter's iteration_types.
	 *
	 * @var Loop_Filter_Ancestor_Rule_Builder
	 */
	private Loop_Filter_Ancestor_Rule_Builder $rule_builder;

	/**
	 * Evaluates ancestor rules against the loop chain.
	 *
	 * @var Ancestor_Rule_Evaluator
	 */
	private Ancestor_Rule_Evaluator $evaluator;

	/**
	 * Constructor.
	 *
	 * @param Filter_Registry_Service|null           $registry     Optional registry (lazy default: singleton).
	 * @param Loop_Filter_Ancestor_Rule_Builder|null $rule_builder Optional builder (lazy default).
	 * @param Ancestor_Rule_Evaluator|null           $evaluator    Optional evaluator (lazy default).
	 * @param Block_Modifier_Store|null              $modifier_store Optional modifier store (lazy default).
	 */
	public function __construct(
		?Filter_Registry_Service $registry = null,
		?Loop_Filter_Ancestor_Rule_Builder $rule_builder = null,
		?Ancestor_Rule_Evaluator $evaluator = null,
		?Block_Modifier_Store $modifier_store = null
	) {
		$this->registry       = $registry ?? Filter_Registry_Service::instance();
		$this->rule_builder   = $rule_builder ?? new Loop_Filter_Ancestor_Rule_Builder();
		$this->evaluator      = $evaluator ?? new Ancestor_Rule_Evaluator();
		$this->modifier_store = $modifier_store ?? Database::get_block_modifier_store();
	}

	/**
	 * Create an instance with explicit dependencies (primarily for tests).
	 *
	 * @param Filter_Registry_Service|null           $registry       Custom registry.
	 * @param Loop_Filter_Ancestor_Rule_Builder|null $rule_builder   Custom builder.
	 * @param Ancestor_Rule_Evaluator|null           $evaluator      Custom evaluator.
	 * @param Block_Modifier_Store|null              $modifier_store Custom modifier store.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Filter_Registry_Service $registry = null,
		?Loop_Filter_Ancestor_Rule_Builder $rule_builder = null,
		?Ancestor_Rule_Evaluator $evaluator = null,
		?Block_Modifier_Store $modifier_store = null
	): self {
		return new self( $registry, $rule_builder, $evaluator, $modifier_store );
	}

	/**
	 * Delete the loop's filters that no longer satisfy their ancestor rules under
	 * the given iteration config.
	 *
	 * @param int   $loop_id             The loop block post id (parent of its modifier rows).
	 * @param array $iterable_expression The loop's persisted iteration config (post-update).
	 *
	 * @return int[] Ids of the loop filters that were deleted.
	 */
	public function reconcile( int $loop_id, array $iterable_expression ): array {
		$chain = array(
			array(
				'block_type' => Block_Type::LOOP,
				'fields'     => Loop_Field_Translator::to_fields( $iterable_expression ),
			),
		);

		$deleted = array();
		foreach ( $this->modifier_store->get_by_block( $loop_id, 'loop_filter' ) as $modifier ) {
			if ( $this->violates( $chain, (string) ( $modifier['code'] ?? '' ) ) ) {
				$modifier_id = (int) ( $modifier['id'] ?? 0 );
				$this->modifier_store->delete( $modifier_id );
				$deleted[] = $modifier_id;
			}
		}

		return $deleted;
	}

	/**
	 * Whether a filter code's ancestor rules are violated by the loop chain.
	 * Unknown codes are kept (mirrors the FE's "undefined → valid").
	 *
	 * @param array  $chain The single-block loop chain carrying the new fields.
	 * @param string $code  Loop-filter code.
	 */
	private function violates( array $chain, string $code ): bool {
		$definition = $this->registry->get_filter_definition( $code );
		if ( null === $definition ) {
			return false;
		}

		$candidate = array(
			'required_block_ancestors' => $this->rule_builder->build( $definition['iteration_types'] ?? array() ),
		);

		return $this->evaluator->is_violation( $chain, $candidate );
	}
}
