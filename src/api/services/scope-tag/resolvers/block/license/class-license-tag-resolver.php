<?php
/**
 * Block License Tag Resolver
 *
 * Resolves license-related scope tags for blocks (Pro badge).
 *
 * @package Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Block\License
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Block\License;

use Uncanny_Automator\Api\Services\Scope_Tag\Scope_Tag_Evaluatable;
use Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Abstract_Scope_Tag_Resolver;

/**
 * Resolves license scope tags for blocks.
 *
 * Shows "Pro" badge when block requires Pro tier.
 *
 * @since 7.0.0
 */
class License_Tag_Resolver extends Abstract_Scope_Tag_Resolver {

	/**
	 * Check if should be evaluated.
	 *
	 * License tags are evaluated when block requires Pro tier.
	 *
	 * @param Scope_Tag_Evaluatable $entity Entity object (Block)
	 * @param array|null $item Item data (null for entity-level)
	 * @param array $entity_dependencies Entity-level dependencies
	 * @param array $item_dependencies Item-level dependencies
	 *
	 * @return bool True if should be evaluated
	 */
	public function should_evaluate( Scope_Tag_Evaluatable $entity, $item = null, array $entity_dependencies = array(), array $item_dependencies = array() ) {
		$this->set_properties( $entity, $item, $entity_dependencies, $item_dependencies );

		// Show Pro badge if block requires Pro tier.
		$required_tier = $this->entity->get_entity_required_tier();
		return 'lite' !== $required_tier;
	}

	/**
	 * Evaluate license tag for block.
	 *
	 * @return array Array of tag arrays
	 */
	public function evaluate() {
		$tier = $this->entity->get_entity_required_tier();
		$tag  = $this->get_license_tag( $tier );

		return $tag ? array( $tag ) : array();
	}
}
