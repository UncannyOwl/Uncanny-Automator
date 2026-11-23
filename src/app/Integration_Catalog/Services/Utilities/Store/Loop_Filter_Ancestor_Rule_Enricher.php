<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Services\Item_Availability_Provider;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_Registry_Service;

/**
 * Stamps ancestor rules onto loop-filter catalog rows so the builder knows where each filter may be used.
 *
 * Attaches required_block_ancestors to loop-filter modifier rows, derived from
 * the filter's iteration_types: from the loop-filter registry, or, for a filter
 * whose requirements are not met, from the row Item_Availability_Provider lists
 * for it. Codes neither knows are left untouched (the frontend treats absent
 * rules as "always applies").
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
	 * The single reader of the loop filters whose requirements are not met.
	 *
	 * @since 8.1.0
	 *
	 * @var Item_Availability_Provider
	 */
	private Item_Availability_Provider $availability;

	/**
	 * Constructor.
	 *
	 * @param Filter_Registry_Service|null           $registry     Optional registry (lazy default: singleton).
	 * @param Loop_Filter_Ancestor_Rule_Builder|null $rule_builder Optional builder (lazy default: new instance).
	 * @param Item_Availability_Provider|null        $availability Optional provider (lazy default: singleton).
	 */
	public function __construct(
		?Filter_Registry_Service $registry = null,
		?Loop_Filter_Ancestor_Rule_Builder $rule_builder = null,
		?Item_Availability_Provider $availability = null
	) {
		$this->registry     = $registry ?? Filter_Registry_Service::instance();
		$this->rule_builder = $rule_builder ?? new Loop_Filter_Ancestor_Rule_Builder();
		$this->availability = $availability ?? Item_Availability_Provider::instance();
	}

	/**
	 * Create an instance with explicit dependencies (primarily for tests).
	 *
	 * @param Filter_Registry_Service|null           $registry     Custom registry.
	 * @param Loop_Filter_Ancestor_Rule_Builder|null $rule_builder Custom builder.
	 * @param Item_Availability_Provider|null        $availability Custom provider.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Filter_Registry_Service $registry = null,
		?Loop_Filter_Ancestor_Rule_Builder $rule_builder = null,
		?Item_Availability_Provider $availability = null
	): self {
		return new self( $registry, $rule_builder, $availability );
	}

	/**
	 * Attach required_block_ancestors to each loop-filter row whose code resolves
	 * to a filter with iteration types: a registered one, or one whose requirements
	 * are not met that the catalog lists. Unresolved codes are left as-is.
	 *
	 * @since 7.0.0
	 * @since 8.1.0 Takes the integration code; a blocked filter keeps its rule.
	 *
	 * @param string              $integration_code The owning integration code.
	 * @param array<string,array> $loop_filters     Normalized loop-filter rows keyed by code.
	 *
	 * @return array<string,array> Same rows, with required_block_ancestors attached where derivable.
	 */
	public function enrich( string $integration_code, array $loop_filters ): array {
		// Codes compare case-insensitively, as the registry lookup on the same line does.
		$blocked = array_change_key_case( $this->availability->listed_unavailable( Block_Modifier_Types::LOOP_FILTER, $integration_code ), CASE_UPPER );

		foreach ( array_keys( $loop_filters ) as $code ) {
			$definition      = $this->registry->get_filter_definition( (string) $code ) ?? ( $blocked[ strtoupper( (string) $code ) ] ?? null );
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
