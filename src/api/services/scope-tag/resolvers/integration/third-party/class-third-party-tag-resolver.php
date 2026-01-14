<?php
/**
 * Third Party Tag Resolver
 *
 * Resolves third-party integration scope tags.
 *
 * @package Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Integration\Third_Party
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Integration\Third_Party;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Services\Scope_Tag\Scope_Tag_Evaluatable;
use Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Abstract_Scope_Tag_Resolver;

/**
 * Resolves third-party scope tags.
 *
 * NOTE: This resolver is Integration-specific and will not evaluate for other entity types.
 *
 * @since 7.0.0
 */
class Third_Party_Tag_Resolver extends Abstract_Scope_Tag_Resolver {

	/**
	 * Check if should be evaluated.
	 *
	 * Third-party tags are evaluated when integration is a third-party integration.
	 * Returns false for non-Integration entities.
	 *
	 * @param Scope_Tag_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data (null for entity-level)
	 * @param array $entity_dependencies Entity-level dependencies
	 * @param array $item_dependencies Item-level dependencies
	 *
	 * @return bool True if should be evaluated
	 */
	public function should_evaluate( Scope_Tag_Evaluatable $entity, $item = null, array $entity_dependencies = array(), array $item_dependencies = array() ) {
		// Only evaluate for Integration entities
		if ( ! $entity instanceof Integration ) {
			return false;
		}

		$this->set_properties( $entity, $item, $entity_dependencies, $item_dependencies );

		return $entity->is_third_party();
	}

	/**
	 * Evaluate third-party tag.
	 *
	 * @return array Array of tag arrays
	 */
	public function evaluate() {
		return array( $this->get_third_party_tag() );
	}

	/**
	 * Get the third-party tag.
	 *
	 * @return array
	 */
	private function get_third_party_tag(): array {
		return array(
			'type'        => 'third-party',
			'scenario_id' => 'third-party',
			'label'       => esc_html__( 'Third Party', 'uncanny-automator' ),
		);
	}
}
