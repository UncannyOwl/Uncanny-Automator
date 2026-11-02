<?php
/**
 * Dependency Tag Resolver
 *
 * Resolves dependency-related scope tags (Not connected, Not installed, Not active).
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Scope_Tag\Resolvers\Integration\Dependency
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Scope_Tag\Resolvers\Integration\Dependency;

use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Integration_Catalog\Scope_Tag\Scope_Tag_Evaluatable;
use Uncanny_Automator\App\Integration_Catalog\Scope_Tag\Resolvers\Abstract_Scope_Tag_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Services\Availability\Integration_Plugin_Presence;

/**
 * Resolves dependency scope tags.
 *
 * NOTE: This resolver is Integration-specific and will not evaluate for other entity types.
 *
 * @since 7.0.0
 */
class Dependency_Tag_Resolver extends Abstract_Scope_Tag_Resolver {

	/**
	 * Whether an integration's plugin is on disk, active or not.
	 *
	 * @var Integration_Plugin_Presence
	 */
	private Integration_Plugin_Presence $presence;

	/**
	 * @param Integration_Plugin_Presence|null $presence Plugin lookups; defaults to the site's.
	 */
	public function __construct( ?Integration_Plugin_Presence $presence = null ) {
		$this->presence = $presence ?? new Integration_Plugin_Presence();
	}

	/**
	 * Check if should be evaluated.
	 *
	 * Dependency tags are evaluated for integrations only (not items).
	 * Items don't get dependency tags - they get locked tags instead.
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

		// Only evaluate for entity-level (not items).
		if ( null !== $item ) {
			return false;
		}

		// Must be plugin or app to get dependency tags.
		if ( ! $entity->is_plugin() && ! $entity->is_app() ) {
			return false;
		}

		// Early return if no dependencies to check.
		if ( empty( $entity_dependencies['items'] ) ) {
			return false;
		}

		// Set properties for further evaluation.
		$this->set_properties( $entity, $item, $entity_dependencies, $item_dependencies );

		return true;
	}

	/**
	 * Evaluate dependency tag.
	 *
	 * @return array Array of tag arrays
	 */
	public function evaluate() {
		// Cast to Integration since we know it is from should_evaluate check
		/** @var Integration $integration */
		$integration = $this->entity;

		// Identify all unmet dependency types (excluding license).
		$unmet_types = $this->get_unmet_dependency_types();

		// Priority 1: a plugin to install, or else one installed that only needs activating.
		if ( in_array( 'installable', $unmet_types, true ) ) {
			return array( $this->has_unmet_plugin_not_on_disk() ? $this->get_not_installed_tag() : $this->get_not_active_tag() );
		}

		// Priority 2: Not connected (only if integration requires connection).
		if ( $integration->requires_connection() && in_array( 'account', $unmet_types, true ) ) {
			return array( $this->get_not_connected_tag() );
		}

		// No dependency tags to show.
		return array();
	}

	/**
	 * Get unmet dependency types.
	 *
	 * @return array Array of unmet dependency types (e.g., ['installable', 'account'])
	 */
	private function get_unmet_dependency_types() {
		$unmet_types = array();

		foreach ( $this->entity_dependencies['items'] as $dep ) {
			if ( ! $dep->is_met() ) {
				$type = $dep->get_type();
				// Skip license dependencies (handled by license resolver).
				if ( 'license' !== $type ) {
					$unmet_types[] = $type;
				}
			}
		}

		return $unmet_types;
	}

	/**
	 * Whether any unmet plugin row names a plugin that is not on disk. The rows are the ones the
	 * plugin dependency resolver builds, one per plugin, with the id "installable-{integration code}".
	 *
	 * @return bool
	 */
	private function has_unmet_plugin_not_on_disk(): bool {
		foreach ( $this->entity_dependencies['items'] as $dep ) {
			if ( 'installable' !== $dep->get_type() || $dep->is_met() ) {
				continue;
			}
			if ( ! $this->presence->is_installed( substr( $dep->get_id(), strlen( 'installable-' ) ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the not active tag, for a plugin that is installed but not active.
	 *
	 * @return array
	 */
	private function get_not_active_tag(): array {
		return array(
			'type'        => 'dependency',
			'scenario_id' => 'dependency-not-active',
			'label'       => esc_html__( 'Not active', 'uncanny-automator' ),
			'icon'        => 'link-simple-slash',
		);
	}

	/**
	 * Get the not connected tag.
	 *
	 * @return array
	 */
	private function get_not_connected_tag(): array {
		return array(
			'type'        => 'dependency',
			'scenario_id' => 'dependency-not-connected',
			'label'       => esc_html__( 'Not connected', 'uncanny-automator' ),
			'icon'        => 'link-simple-slash',
		);
	}

	/**
	 * Get the not installed tag.
	 *
	 * @return array
	 */
	private function get_not_installed_tag(): array {
		return array(
			'type'        => 'dependency',
			'scenario_id' => 'dependency-not-installed',
			'label'       => esc_html__( 'Not installed', 'uncanny-automator' ),
			'icon'        => 'link-simple-slash',
		);
	}
}
