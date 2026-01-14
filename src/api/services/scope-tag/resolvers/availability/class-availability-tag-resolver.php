<?php
/**
 * Availability Tag Resolver
 *
 * Resolves availability-related scope tags (Locked).
 *
 * Shows "Locked" tag only when license dependencies are not met.
 * Does NOT show locked tag for plugin/account dependency issues.
 *
 * @package Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Availability
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Availability;

use Uncanny_Automator\Api\Services\Scope_Tag\Scope_Tag_Evaluatable;
use Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Abstract_Scope_Tag_Resolver;

/**
 * Resolves availability scope tags.
 *
 * Works with all entity types (Integration, Block, etc).
 *
 * @since 7.0.0
 */
class Availability_Tag_Resolver extends Abstract_Scope_Tag_Resolver {

	/**
	 * Check if should be evaluated.
	 *
	 * Availability tags are evaluated when LICENSE dependencies are not met.
	 * Plugin/account dependencies do NOT trigger the locked tag.
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

		// For entities: evaluate if license dependencies are NOT met.
		if ( null === $item ) {
			return $this->has_unmet_license_dependencies( $this->entity_dependencies );
		}

		// For items: only evaluate if parent dependencies are met (item-level lock check).
		// If parent dependencies are not met, parent lock is shown instead (handled separately).
		if ( ! $this->are_entity_dependencies_met() ) {
			return false;
		}

		return $this->has_unmet_license_dependencies( $this->item_dependencies );
	}

	/**
	 * Evaluate availability tag.
	 *
	 * @return array Array of tag arrays
	 */
	public function evaluate() {
		// For entities: locked if license dependencies are NOT met.
		if ( null === $this->item ) {
			if ( $this->has_unmet_license_dependencies( $this->entity_dependencies ) ) {
				return array( $this->get_locked_tag() );
			}
			return array();
		}

		// For items: check item-level license lock (parent lock already handled in main resolver).
		if ( $this->has_unmet_license_dependencies( $this->item_dependencies ) ) {
			return array( $this->get_locked_tag() );
		}

		return array();
	}

	/**
	 * Check if entity dependencies are all met.
	 *
	 * @return bool True if all entity dependencies are met
	 */
	private function are_entity_dependencies_met() {
		return ! empty( $this->entity_dependencies['all_met'] ) && true === $this->entity_dependencies['all_met'];
	}

	/**
	 * Check if there are unmet license dependencies.
	 *
	 * Only checks for license type dependencies, ignoring plugin/account dependencies.
	 *
	 * @param array $dependencies Dependencies array with 'items' key
	 *
	 * @return bool True if there are unmet license dependencies
	 */
	private function has_unmet_license_dependencies( array $dependencies ) {
		if ( empty( $dependencies['items'] ) ) {
			return false;
		}

		foreach ( $dependencies['items'] as $dependency ) {
			// Only check license type dependencies.
			if ( 'license' === $dependency->get_type() && ! $dependency->is_met() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the locked tag.
	 *
	 * @return array
	 */
	public function get_locked_tag(): array {
		return array(
			'type'        => 'availability',
			'scenario_id' => 'availability-locked',
			'label'       => esc_html__( 'Locked', 'uncanny-automator' ),
			'icon'        => 'lock',
		);
	}
}
