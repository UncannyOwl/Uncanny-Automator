<?php
/**
 * License Resolver
 *
 * Resolves license-related dependencies for integrations.
 *
 * @package Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\License
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\License;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\Api\Services\Dependency\Resolvers\Abstract_Resolver;

/**
 * Resolves license dependencies (tier requirements, API credits).
 *
 * @since 7.0.0
 *
 * @property Integration $entity
 * @property License_Scenario $scenario
 */
class License_Resolver extends Abstract_Resolver {

	/**
	 * Check if should be evaluated.
	 *
	 * @param Dependency_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data (null for entity-level)
	 *
	 * @return bool True if should be evaluated
	 */
	public function should_evaluate( Dependency_Evaluatable $entity, $item = null ) {
		$this->set_properties( $entity, $item );
		return $this->is_integration();
	}

	/**
	 * Evaluate license dependency.
	 *
	 * @return array Dependencies array
	 */
	public function evaluate() {
		$dependencies = array();
		$tier         = $this->get_required_tier();
		$plan_service = $this->context->get_plan_service();

		// Always create license dependency if entity requires specific tier.
		if ( 'lite' !== $tier ) {
			// Check if user has valid pro license.
			$is_met = $this->context->is_pro_license_valid();
			if ( $is_met ) {
				// Validate tier requirement.
				$is_met = $plan_service->meets_tier_requirement( $tier );
			}

			$dependencies[] = $this->resolve( $is_met, 'tier' );
		}

		// For app integrations, always create credits dependency if user doesn't have Pro.
		if ( $this->entity->is_app() && ! $plan_service->is_pro() ) {
			$dependencies[] = $this->resolve( $this->context->has_credits(), 'credits' );
		}

		return $dependencies;
	}

	/**
	 * Resolve license dependency.
	 *
	 * @param bool $is_met Whether the dependency is met
	 * @param string $type Type of license dependency ('tier' or 'credits')
	 *
	 * @return Dependency
	 */
	private function resolve( bool $is_met, string $type = 'tier' ) {
		$scenario_id  = $this->scenario->get_scenario_id( $this->get_required_tier(), $type );
		$display_name = $this->get_name_with_context(); // Add integration context to item names

		return $this->create_dependency(
			array(
				'type'        => 'license',
				'id'          => sprintf( 'license-%s-%s', $type, $this->get_code() ),
				'name'        => $this->scenario->get_name( $scenario_id ),
				'description' => $this->scenario->get_description( $scenario_id, $display_name ),
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
				'label'       => esc_html_x( 'License', 'Dependency', 'uncanny-automator' ),
				'icon'        => 'badge-check',
			),
		);
	}
}
