<?php
/**
 * License Tag Resolver
 *
 * Resolves license-related scope tags for integrations and items.
 *
 * @package Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Integration\License
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Integration\License;

use Uncanny_Automator\Api\Services\Scope_Tag\Scope_Tag_Evaluatable;
use Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Abstract_Scope_Tag_Resolver;

/**
 * Resolves license scope tags (Pro Basic, Pro Plus, Pro Elite).
 *
 * @since 7.0.0
 */
class License_Tag_Resolver extends Abstract_Scope_Tag_Resolver {

	/**
	 * Check if should be evaluated.
	 *
	 * License tags are evaluated when entity/item requires Pro tier.
	 *
	 * @param Scope_Tag_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data (null for entity-level)
	 * @param array $entity_dependencies Entity-level dependencies
	 * @param array $item_dependencies Item-level dependencies
	 *
	 * @return bool True if should be evaluated
	 */
	public function should_evaluate( Scope_Tag_Evaluatable $entity, $item = null, array $entity_dependencies = array(), array $item_dependencies = array() ) {
		$this->set_properties( $entity, $item, $entity_dependencies, $item_dependencies );

		$tier = $this->get_required_tier();

		// Only evaluate if tier is not 'lite'.
		return 'lite' !== $tier;
	}

	/**
	 * Evaluate license tag.
	 *
	 * @return array Array of tag arrays
	 */
	public function evaluate() {
		$tier = $this->get_required_tier();
		$tag  = $this->get_license_tag( $tier );

		return $tag ? array( $tag ) : array();
	}

	/**
	 * Get required tier (entity or item).
	 *
	 * @return string Required tier
	 */
	private function get_required_tier() {
		return null !== $this->item
			? ( $this->item['required_tier'] ?? 'lite' )
			: $this->entity->get_entity_required_tier();
	}
}
