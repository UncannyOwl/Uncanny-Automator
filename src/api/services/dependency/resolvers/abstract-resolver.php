<?php
/**
 * Abstract Dependency Resolver
 *
 * Base class for all dependency resolvers.
 *
 * @package Uncanny_Automator\Api\Services\Dependency\Resolvers
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Dependency\Resolvers;

use Uncanny_Automator\Api\Components\Block\Block;
use Uncanny_Automator\Api\Components\Dependency\Dependency;
use Uncanny_Automator\Api\Components\Dependency\Dependency_Config;
use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Context;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Evaluatable;

/**
 * Abstract base for dependency resolvers.
 *
 * Provides common functionality and enforces contract for concrete resolvers.
 *
 * @since 7.0.0
 */
abstract class Abstract_Resolver {

	/**
	 * Dependency context.
	 *
	 * @var Dependency_Context
	 */
	protected $context;

	/**
	 * Scenario instance.
	 *
	 * @var Abstract_Scenario|null
	 */
	protected $scenario = null;

	/**
	 * Entity object (Integration, Block, etc).
	 *
	 * @var Dependency_Evaluatable
	 */
	protected $entity;

	/**
	 * Item data (for item-level dependencies).
	 *
	 * @var array|null
	 */
	protected $item = null;

	/**
	 * Constructor.
	 *
	 * @param Dependency_Context $context Dependency resolution context
	 *
	 * @return void
	 */
	public function __construct( Dependency_Context $context ) {
		$this->context = $context;
		$this->setup();
		$this->init_scenario_service();
	}

	/**
	 * Setup method.
	 *
	 * Optional hook for concrete resolvers to perform initialization.
	 * Called automatically after context is set.
	 *
	 * @return void
	 */
	protected function setup() {
		// Override in concrete resolvers if needed.
	}

	/**
	 * Initialize scenario service.
	 *
	 * Automatically instantiates the corresponding scenario class
	 * based on the resolver's namespace and class name.
	 *
	 * @return void
	 */
	private function init_scenario_service() {
		// Get the resolver's class name (e.g., "Plugin_Resolver")
		$resolver_class = get_class( $this );

		// Replace "_Resolver" with "_Scenario" to get scenario class name
		$scenario_class = str_replace( '_Resolver', '_Scenario', $resolver_class );

		// Instantiate scenario with context if the class exists
		if ( class_exists( $scenario_class ) ) {
			$this->scenario = new $scenario_class( $this->context );
		}
	}

	/**
	 * Check if this resolver should evaluate.
	 *
	 * Stores the entity and item for use in evaluate().
	 *
	 * @param Dependency_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data (null for entity-level)
	 *
	 * @return bool True if this resolver should evaluate
	 */
	abstract public function should_evaluate( Dependency_Evaluatable $entity, $item = null );

	/**
	 * Evaluate and return dependencies.
	 *
	 * Called after should_evaluate() returns true.
	 *
	 * @return array Array of Dependency objects
	 */
	abstract public function evaluate();

	/**
	 * Get entity code.
	 *
	 * @return string Entity code
	 */
	protected function get_code() {
		return $this->entity->get_entity_code();
	}

	/**
	 * Get entity name.
	 *
	 * Always returns the entity name. For item-level display names,
	 * use get_name_with_context() which includes human-readable formatting.
	 *
	 * @return string Entity name
	 */
	protected function get_name() {
		return $this->entity->get_entity_name();
	}

	/**
	 * Get display name for item dependencies.
	 *
	 * Returns sentence.short if available for human-readable name,
	 * otherwise falls back to entity name to avoid exposing raw codes.
	 *
	 * Note: Only called from get_name_with_context() when item is set.
	 *
	 * @return string Display name
	 */
	protected function get_display_name() {
		// Use sentence.short if available (human-readable)
		if ( null !== $this->item && ! empty( $this->item['sentence']['short'] ) ) {
			return $this->item['sentence']['short'];
		}
		// Fall back to entity name (avoids exposing raw item codes)
		return $this->entity->get_entity_name();
	}

	/**
	 * Get name with full entity context.
	 *
	 * For items: Returns "Entity Name : Item Name" for added clarity,
	 * unless item name already starts with entity name (avoids duplication).
	 * For entities: Returns entity name.
	 *
	 * @return string Fully qualified name with entity context
	 */
	protected function get_name_with_context() {
		$entity_name = $this->entity->get_entity_name();

		// Entity-level: just return entity name
		if ( null === $this->item ) {
			return $entity_name;
		}

		// Item-level: add entity prefix for context
		$item_name = $this->get_display_name();

		// Only add entity prefix if item name doesn't already start with it
		if ( strpos( $item_name, $entity_name ) !== 0 ) {
			return sprintf( '%s : %s', $entity_name, $item_name );
		}

		return $item_name;
	}

	/**
	 * Get required tier (entity or item).
	 *
	 * @return string Required tier
	 */
	protected function get_required_tier() {
		return null !== $this->item
			? ( $this->item['required_tier'] ?? 'lite' )
			: $this->entity->get_entity_required_tier();
	}

	/**
	 * Store context for evaluation.
	 *
	 * Call this in should_evaluate() before returning.
	 *
	 * @param Dependency_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data
	 *
	 * @return void
	 */
	protected function set_properties( Dependency_Evaluatable $entity, $item = null ) {
		$this->entity = $entity;
		$this->item   = $item;
	}

	/**
	 * Check if entity is an Integration.
	 *
	 * @return bool
	 */
	protected function is_integration(): bool {
		return $this->entity instanceof Integration;
	}

	/**
	 * Check if entity is a Block.
	 *
	 * @return bool
	 */
	protected function is_block(): bool {
		return $this->entity instanceof Block;
	}

	/**
	 * Create a Dependency from configuration array.
	 *
	 * Centralizes Dependency_Config and Dependency instantiation.
	 * Concrete resolvers provide configuration data, abstract handles object creation.
	 *
	 * @param array $config Configuration array with keys:
	 *   - type (string, required): Dependency type (e.g., 'license', 'installable', 'account')
	 *   - id (string, required): Unique dependency ID
	 *   - name (string, required): Display name
	 *   - description (string, required): Description text
	 *   - is_met (bool, required): Whether dependency is met
	 *   - cta (Dependency_Cta, required): Call-to-action object
	 *   - scenario_id (string, required): Scenario identifier
	 *   - is_disabled (bool, optional): Whether dependency is disabled (default: false)
	 *   - dependencies (array, optional): Sub-dependency IDs (default: [])
	 *   - icon (string, optional): Icon URL
	 *   - developer (array, optional): Developer details
	 *
	 * @return Dependency Configured dependency object
	 */
	protected function create_dependency( array $config ) {
		return new Dependency( Dependency_Config::from_array( $config ) );
	}
}
