<?php
/**
 * Integration Query Service
 *
 * Unified query API for integration lookups (scoped and unscoped).
 *
 * @package Uncanny_Automator\Api\Services\Integration
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Item;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\Traits\Singleton;

/**
 * Query service for integration lookups.
 *
 * Provides the public API for querying integration data:
 * - Unscoped queries via Integration_Store
 * - Scoped queries via Integration_Scope_Resolver
 * - Item queries for recipe REST handlers
 *
 * @since 7.0.0
 */
class Integration_Query_Service {

	use Singleton;

	/**
	 * Integration store instance.
	 *
	 * @var Integration_Store|null
	 */
	private ?Integration_Store $store = null;

	/**
	 * Feed service instance.
	 *
	 * @var Integration_Feed_Service|null
	 */
	private ?Integration_Feed_Service $feed_service = null;

	/**
	 * Scope resolver instance.
	 *
	 * @var Integration_Scope_Resolver|null
	 */
	private ?Integration_Scope_Resolver $scope_resolver = null;

	/**
	 * Initialize dependencies lazily.
	 *
	 * Performance: Only instantiates when first needed.
	 *
	 * @return void
	 */
	private function init(): void {
		if ( null === $this->store ) {
			$this->store          = Integration_Store::get_instance();
			$this->feed_service   = Integration_Feed_Service::get_instance();
			$this->scope_resolver = new Integration_Scope_Resolver();
		}
	}

	/**
	 * Set store (for testing/DI).
	 *
	 * @param Integration_Store $store Store instance
	 *
	 * @return void
	 */
	public function set_store( Integration_Store $store ): void {
		$this->store = $store;
	}

	/**
	 * Set scope resolver (for testing/DI).
	 *
	 * @param Integration_Scope_Resolver $scope_resolver Scope resolver instance
	 *
	 * @return void
	 */
	public function set_scope_resolver( Integration_Scope_Resolver $scope_resolver ): void {
		$this->scope_resolver = $scope_resolver;
	}

	// =========================================================================
	// Unscoped Queries (Integration Objects)
	// =========================================================================

	/**
	 * Get a single integration by code.
	 *
	 * Returns the full Integration domain object (unscoped).
	 *
	 * @param string $code Integration code (e.g., 'WC', 'ACTIVE_CAMPAIGN').
	 *
	 * @return Integration|null Integration object or null if not found.
	 */
	public function get_integration( string $code ): ?Integration {
		$this->init();
		return $this->store->get_by_code( $code );
	}

	/**
	 * Get all integrations.
	 *
	 * Returns all Integration domain objects (unscoped).
	 *
	 * @return array<Integration> Array of Integration objects
	 */
	public function get_all_integrations(): array {
		$this->init();
		return $this->store->get_all();
	}

	/**
	 * Get all integrations as array (REST format).
	 *
	 * Returns integration data arrays keyed by code (unscoped).
	 *
	 * @return array<string, array> Integration arrays keyed by code
	 */
	public function get_all_integrations_to_rest(): array {
		$this->init();
		return $this->store->get_all_to_rest();
	}

	// =========================================================================
	// Scoped Queries (Enriched Arrays)
	// =========================================================================

	/**
	 * Get all integrations for a scope.
	 *
	 * Returns integrations filtered and enriched for the specified scope:
	 * - Filters out integrations without items for the scope
	 * - Adds popularity scores
	 * - Adds resolved dependencies
	 * - Adds scope tags
	 *
	 * @param string $scope The scope: 'trigger', 'action', 'loop_filter', or 'filter_condition'
	 *
	 * @return array<string, array> Scoped integration data keyed by code
	 */
	public function get_scoped_integrations( string $scope ): array {
		$this->init();
		return $this->scope_resolver->resolve_scoped_integrations( $scope );
	}

	/**
	 * Get a single integration for a scope.
	 *
	 * Returns enriched integration data for a specific integration and scope.
	 *
	 * @param string $code  Integration code
	 * @param string $scope The scope
	 *
	 * @return array|null Scoped integration data or null if not found/no items for scope
	 */
	public function get_scoped_integration( string $code, string $scope ): ?array {
		$this->init();
		return $this->scope_resolver->resolve_scoped_integration( $code, $scope );
	}

	// =========================================================================
	// Item Queries (for Recipe REST Handlers)
	// =========================================================================

	/**
	 * Get a specific integration item by codes.
	 *
	 * Used by recipe REST handlers to fetch item definitions.
	 * Searches for an item within an integration by its code.
	 *
	 * @param string      $integration_code Integration code (e.g., 'WC', 'ACTIVE_CAMPAIGN').
	 * @param string      $item_code        Item code (e.g., 'WCPURCHASESPRODUCT', 'AC_ADD_TAG').
	 * @param string|null $item_type        Optional scope to search (trigger, action, filter_condition, loop_filter, closure).
	 *
	 * @return Integration_Item|null Integration_Item object or null if not found.
	 */
	public function get_item( string $integration_code, string $item_code, ?string $item_type = null ): ?Integration_Item {
		$this->init();

		$integration = $this->store->get_by_code( $integration_code );

		if ( null === $integration ) {
			return null;
		}

		$items = $integration->get_items();

		// If type specified and valid, search only that type.
		if ( null !== $item_type && Integration_Item_Types::is_valid( $item_type ) ) {
			$getter     = $this->scope_to_getter( $item_type );
			$type_items = $items->$getter();
			return $type_items[ $item_code ] ?? null;
		}

		// Search all types.
		foreach ( Integration_Item_Types::get_all() as $scope ) {
			$getter     = $this->scope_to_getter( $scope );
			$type_items = $items->$getter();
			if ( isset( $type_items[ $item_code ] ) ) {
				return $type_items[ $item_code ];
			}
		}

		return null;
	}

	/**
	 * Convert a scope constant to its corresponding getter method name.
	 *
	 * @param string $scope Scope constant (e.g., 'trigger', 'action').
	 *
	 * @return string Getter method name (e.g., 'get_triggers', 'get_actions').
	 */
	private function scope_to_getter( string $scope ): string {
		return 'get_' . $scope . 's';
	}

	// =========================================================================
	// Collection Queries
	// =========================================================================

	/**
	 * Get all collections with their associated integration codes.
	 *
	 * Collections group integrations by category (e.g., "E-commerce", "CRM").
	 *
	 * @return array Array of collections with id, name, description, integration_codes
	 */
	public function get_collections(): array {
		$this->init();

		$json_data = $this->feed_service->fetch_raw();

		if ( empty( $json_data ) ) {
			return array();
		}

		return $this->build_collections_list( $json_data );
	}

	/**
	 * Build collections list from JSON data.
	 *
	 * Loops through all integrations and accumulates unique collections
	 * with their associated integration codes.
	 *
	 * @param array $json_data Raw data from complete.json
	 *
	 * @return array Array of unique collections with integration_codes
	 */
	private function build_collections_list( array $json_data ): array {
		$collections = array();

		foreach ( $json_data as $item ) {
			$code             = $item['integration_id'] ?? '';
			$item_collections = $item['collections'] ?? array();

			if ( empty( $code ) || empty( $item_collections ) || ! is_array( $item_collections ) ) {
				continue;
			}

			foreach ( $item_collections as $collection ) {
				$slug = $collection['slug'] ?? '';

				if ( empty( $slug ) ) {
					continue;
				}

				if ( ! isset( $collections[ $slug ] ) ) {
					$collections[ $slug ] = array(
						'id'                => $slug,
						'name'              => $collection['name'] ?? '',
						'description'       => $collection['description'] ?? '',
						'integration_codes' => array(),
					);
				}

				if ( ! in_array( $code, $collections[ $slug ]['integration_codes'], true ) ) {
					$collections[ $slug ]['integration_codes'][] = $code;
				}
			}
		}

		return array_values( $collections );
	}
}
