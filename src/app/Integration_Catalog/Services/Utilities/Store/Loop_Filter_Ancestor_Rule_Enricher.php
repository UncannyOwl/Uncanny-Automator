<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store;

use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_Registry_Service;

/**
 * Stamps ancestor rules onto loop-filter catalog rows so the builder knows where each filter may be used.
 *
 * Attaches required_block_ancestors to loop-filter modifier rows, derived from
 * the filter's iteration_types in the loop-filter registry. Codes the registry
 * does not know are left untouched (the frontend treats absent rules as
 * "always applies").
 */
class Loop_Filter_Ancestor_Rule_Enricher {

	/**
	 * Loop-filter registry used to resolve a filter's iteration_types by code.
	 *
	 * @var Filter_Registry_Service
	 */
	private Filter_Registry_Service $registry;

	/**
	 * Builder that turns iteration_types into ancestor rules.
	 *
	 * @var Loop_Filter_Ancestor_Rule_Builder
	 */
	private Loop_Filter_Ancestor_Rule_Builder $rule_builder;

	/**
	 * Constructor.
	 *
	 * @param Filter_Registry_Service|null           $registry     Optional registry (lazy default: singleton).
	 * @param Loop_Filter_Ancestor_Rule_Builder|null $rule_builder Optional builder (lazy default: new instance).
	 */
	public function __construct(
		?Filter_Registry_Service $registry = null,
		?Loop_Filter_Ancestor_Rule_Builder $rule_builder = null
	) {
		$this->registry     = $registry ?? Filter_Registry_Service::instance();
		$this->rule_builder = $rule_builder ?? new Loop_Filter_Ancestor_Rule_Builder();
	}

	/**
	 * Create an instance with explicit dependencies (primarily for tests).
	 *
	 * @param Filter_Registry_Service|null           $registry     Custom registry.
	 * @param Loop_Filter_Ancestor_Rule_Builder|null $rule_builder Custom builder.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Filter_Registry_Service $registry = null,
		?Loop_Filter_Ancestor_Rule_Builder $rule_builder = null
	): self {
		return new self( $registry, $rule_builder );
	}

	/**
	 * Attach required_block_ancestors to each loop-filter row whose code resolves
	 * to a registered filter with iteration types. Unresolved codes are left as-is.
	 *
	 * @param array<string,array> $loop_filters Normalized loop-filter rows keyed by code.
	 *
	 * @return array<string,array> Same rows, with required_block_ancestors attached where derivable.
	 */
	public function enrich( array $loop_filters ): array {
		foreach ( array_keys( $loop_filters ) as $code ) {
			$definition      = $this->registry->get_filter_definition( (string) $code );
			$iteration_types = is_array( $definition ) ? ( $definition['iteration_types'] ?? array() ) : array();
			if ( ! is_array( $iteration_types ) ) {
				$iteration_types = array();
			}
			$rules = $this->rule_builder->build( $iteration_types );
			if ( ! empty( $rules ) ) {
				$loop_filters[ $code ]['required_block_ancestors'] = $rules;
			}
		}
		return $loop_filters;
	}
}
