<?php
/**
 * Block License Resolver
 *
 * Resolves license-related dependencies for blocks.
 *
 * @package Uncanny_Automator\Api\Services\Dependency\Resolvers\Block\License
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Dependency\Resolvers\Block\License;

use Uncanny_Automator\Api\Components\Block\Block;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\Api\Services\Dependency\Resolvers\Abstract_Resolver;
use Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\License\License_Scenario;

/**
 * Resolves license dependencies for blocks (tier requirements only).
 *
 * Blocks only need to validate tier/plan requirements. Unlike integrations,
 * blocks do not have app credits or connection requirements.
 *
 * @since 7.0.0
 *
 * @property Block $entity
 * @property License_Scenario $scenario
 */
class License_Resolver extends Abstract_Resolver {

	/**
	 * Setup resolver.
	 *
	 * Blocks share the same License_Scenario as integrations.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->scenario = new License_Scenario( $this->context );
	}

	/**
	 * Check if should be evaluated.
	 *
	 * License dependencies are evaluated when block requires Pro tier.
	 *
	 * @param Dependency_Evaluatable $entity Entity object (Block)
	 * @param array|null $item Item data (null for entity-level)
	 *
	 * @return bool True if should be evaluated
	 */
	public function should_evaluate( Dependency_Evaluatable $entity, $item = null ) {
		$this->set_properties( $entity, $item );

		// Only evaluate for blocks that require a Pro tier.
		return $this->is_block() && 'lite' !== $this->get_required_tier();
	}

	/**
	 * Evaluate license dependency for blocks.
	 *
	 * @return array Dependencies array
	 */
	public function evaluate() {
		$dependencies = array();
		$tier         = $this->get_required_tier();
		$plan_service = $this->context->get_plan_service();

		// Check if user has valid pro license.
		$is_met = $this->context->is_pro_license_valid();
		if ( $is_met ) {
			// Validate tier requirement.
			$is_met = $plan_service->meets_tier_requirement( $tier );
		}

		$dependencies[] = $this->resolve( $is_met );

		return $dependencies;
	}

	/**
	 * Resolve license dependency for block.
	 *
	 * @param bool $is_met Whether the dependency is met
	 *
	 * @return Dependency
	 */
	private function resolve( bool $is_met ) {
		$scenario_id  = $this->scenario->get_scenario_id( $this->get_required_tier(), 'tier' );
		$display_name = $this->get_name();

		return $this->create_dependency(
			array(
				'type'        => 'license',
				'id'          => sprintf( 'license-tier-%s', $this->get_code() ),
				'name'        => $display_name,
				'description' => $this->entity->get_dependency_description(),
				'is_met'      => $is_met,
				'cta'         => $this->scenario->create_cta( $scenario_id, $display_name ),
				'scenario_id' => $scenario_id,
				'tags'        => $this->get_tags(),
			)
		);
	}

	/**
	 * Get tags for this license dependency.
	 *
	 * @return array Array of tag arrays.
	 */
	private function get_tags(): array {
		return array(
			array(
				'scenario_id' => 'license',
				'label'       => 'License',
				'icon'        => 'badge-check',
			),
		);
	}
}
