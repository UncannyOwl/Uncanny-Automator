<?php
/**
 * Plugin Resolver
 *
 * Resolves plugin installation/activation dependencies.
 *
 * @package Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\Plugin
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\Plugin;

use Uncanny_Automator\Api\Components\Integration\Enums\Distribution_Type;
use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use Uncanny_Automator\Api\Services\Dependency\Resolvers\Abstract_Resolver;
use Uncanny_Automator\Api\Services\Integration\Integration_Store;

/**
 * Resolves plugin installation/activation dependencies.
 *
 * NOTE: This resolver is Integration-specific and will not evaluate for other entity types.
 *
 * @since 7.0.0
 *
 * @property Integration $entity
 * @property Plugin_Scenario $scenario
 */
class Plugin_Resolver extends Abstract_Resolver {

	/**
	 * Plugin details.
	 *
	 * @var Integration_Plugin_Details|null
	 */
	private $plugin;

	/**
	 * Track codes being resolved to prevent circular dependencies.
	 *
	 * @var array
	 */
	private static array $resolution_stack = array();

	/**
	 * Maximum recursion depth.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 10;

	/**
	 * Store context for evaluation.
	 *
	 * @param Dependency_Evaluatable $entity Entity object
	 * @param array|null $item Item data
	 *
	 * @return void
	 */
	protected function set_properties( Dependency_Evaluatable $entity, $item = null ) {
		$this->entity = $entity;
		$this->item   = $item;

		// Plugin resolver only works with Integration entities
		if ( $this->is_integration() ) {
			$this->plugin = $this->entity->get_details()->get_plugin();
		}
	}

	/**
	 * Check if should be evaluated.
	 *
	 * Only evaluate for plugin-type integrations (not built-in or apps).
	 * Returns false for non-Integration entities.
	 *
	 * @param Dependency_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data (null for entity-level)
	 *
	 * @return bool True if should be evaluated
	 */
	public function should_evaluate( Dependency_Evaluatable $entity, $item = null ) {
		$this->set_properties( $entity, $item );

		// Only evaluate for Integration entities that are plugin type
		return $this->is_integration() && $this->entity->is_plugin();
	}

	/**
	 * Evaluate plugin dependency.
	 *
	 * Returns flat array with parent dependencies first, then self.
	 *
	 * @return array Array of Dependency objects
	 */
	public function evaluate() {
		$code = $this->get_code();

		// Circular dependency check.
		if ( in_array( $code, self::$resolution_stack, true ) ) {
			return array();
		}

		// Depth limit check.
		if ( count( self::$resolution_stack ) >= self::MAX_DEPTH ) {
			return array( $this->resolve_self() );
		}

		// Push current to stack.
		self::$resolution_stack[] = $code;

		try {
			$dependencies = array();

			// 1. Recursively resolve parent dependencies FIRST.
			if ( $this->plugin && ! empty( $this->plugin->get_integration_required() ) ) {
				$parent_code         = $this->plugin->get_integration_required();
				$parent_dependencies = $this->resolve_parent_integration( $parent_code );
				$dependencies        = array_merge( $dependencies, $parent_dependencies );
			}

			// 2. Then add self LAST.
			$dependencies[] = $this->resolve_self();

			return $dependencies;
		} finally {
			// Always pop from stack.
			array_pop( self::$resolution_stack );
		}
	}

	/**
	 * Resolve this plugin's own dependency.
	 *
	 * @return Dependency
	 */
	private function resolve_self() {
		$is_installed     = $this->is_active_integration( $this->get_code() );
		$developer        = $this->entity->get_details()->get_developer();
		$integration_name = $this->get_name();
		$scenario_id      = $this->get_scenario_id();

		// Check for sub-dependencies (e.g., WooCommerce Subscriptions requires WooCommerce)
		$dependency_ids = array();
		if ( $this->plugin && ! empty( $this->plugin->get_integration_required() ) ) {
			$dependency_ids[] = sprintf( 'installable-%s', $this->plugin->get_integration_required() );
		}

		return $this->create_dependency(
			array(
				'type'         => 'installable',
				'id'           => sprintf( 'installable-%s', $this->get_code() ),
				'name'         => $integration_name,
				'description'  => $this->scenario->get_description( $scenario_id, $integration_name ),
				'is_met'       => $is_installed,
				'is_disabled'  => $this->calculate_is_disabled( $dependency_ids ),
				'dependencies' => $dependency_ids,
				'cta'          => $this->scenario->create_cta( $scenario_id, $integration_name, $developer->get_site() ),
				'scenario_id'  => $scenario_id,
				'icon'         => $this->entity->get_details()->get_icon(),
				'developer'    => $developer->to_array(),
				'tags'         => $this->get_tags(),
			)
		);
	}

	/**
	 * Get tags for this plugin dependency.
	 *
	 * @return array Array of tag arrays.
	 */
	private function get_tags(): array {
		$distribution_type = $this->plugin
			? $this->plugin->get_distribution_type()
			: Distribution_Type::COMMERCIAL;

		// Base tag for all installable dependencies.
		$tags = array(
			array(
				'scenario_id' => 'installable',
				'label'       => esc_html_x( 'Plugin', 'Dependency', 'uncanny-automator' ),
				'icon'        => 'puzzle-piece',
			),
		);

		// Add distribution-specific tag.
		switch ( $distribution_type ) {
			case Distribution_Type::COMMERCIAL:
				$tags[] = array(
					'scenario_id' => 'installable-' . Distribution_Type::SLUG_COMMERCIAL,
					'label'       => esc_html_x( 'Commercial', 'Dependency', 'uncanny-automator' ),
					'icon'        => 'dollar-sign',
				);
				break;
			case Distribution_Type::OPEN_SOURCE:
				$tags[] = array(
					'scenario_id' => 'installable-' . Distribution_Type::SLUG_OPEN_SOURCE,
					'label'       => esc_html_x( 'Open source', 'Dependency', 'uncanny-automator' ),
					'icon'        => 'earth-americas',
				);
				break;
			case Distribution_Type::WP_ORG:
				$tags[] = array(
					'scenario_id' => 'installable-' . Distribution_Type::SLUG_WP_ORG,
					'label'       => esc_html_x( 'Open source', 'Dependency', 'uncanny-automator' ),
					'icon'        => 'earth-americas',
				);
				break;
		}

		return $tags;
	}

	/**
	 * Calculate if dependency is disabled by unmet sub-dependencies.
	 *
	 * For example, WooCommerce Subscriptions can't be installed if WooCommerce isn't active.
	 *
	 * @param array $dependency_ids Array of dependency IDs this depends on
	 *
	 * @return bool True if dependency is blocked by unmet sub-dependencies
	 */
	private function calculate_is_disabled( array $dependency_ids ) {
		if ( empty( $dependency_ids ) ) {
			return false;
		}

		foreach ( $dependency_ids as $dep_id ) {
			// Check if installable dependency is met.
			if ( str_starts_with( $dep_id, 'installable-' ) ) {
				$required_code = str_replace( 'installable-', '', $dep_id );
				if ( ! $this->is_active_integration( $required_code ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if integration is active.
	 *
	 * @param string $code Integration code
	 *
	 * @return bool True if integration is active
	 */
	private function is_active_integration( string $code ) {
		return Integration_Registry_Service::get_instance()->has_integration( $code );
	}

	/**
	 * Get plugin scenario ID.
	 *
	 * Determines scenario based on plugin distribution type.
	 *
	 * @return string Scenario ID matching frontend schema
	 */
	private function get_scenario_id() {
		// Default for null plugin details.
		if ( ! $this->plugin ) {
			return $this->scenario->get_scenario_id( Distribution_Type::COMMERCIAL );
		}

		// Determine distribution type.
		$distribution_type = $this->plugin->get_distribution_type();

		// Let scenario service determine the scenario ID.
		return $this->scenario->get_scenario_id( $distribution_type );
	}

	/**
	 * Resolve a parent integration and its dependencies recursively.
	 *
	 * @param string $parent_code Integration code (e.g., 'WC')
	 *
	 * @return array Array of Dependency objects (parent's parents + parent)
	 */
	private function resolve_parent_integration( string $parent_code ): array {
		// Get parent Integration from store singleton.
		$parent_integration = Integration_Store::get_instance()->get_by_code( $parent_code );

		if ( null === $parent_integration ) {
			return array();
		}

		// Create a new resolver instance for the parent.
		$parent_resolver = new self( $this->context, $this->scenario );

		// Check if should evaluate (is it a plugin-type integration?).
		if ( ! $parent_resolver->should_evaluate( $parent_integration ) ) {
			return array();
		}

		// Recursively evaluate parent (returns flat array: grandparents + parent).
		return $parent_resolver->evaluate();
	}
}
