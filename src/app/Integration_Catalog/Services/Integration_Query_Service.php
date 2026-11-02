<?php
/**
 * Integration Query Service
 *
 * Unified query API for integration lookups (scoped and unscoped).
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Services
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Services;

use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Item;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Block_Modifier;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
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
	 * - Adds usage (count + last used)
	 * - Adds resolved dependencies
	 * - Adds scope tags
	 *
	 * @param string $scope    The scope: 'trigger', 'action', 'loop_filter', or 'filter_condition'
	 * @param bool   $for_rest Whether to use REST format (excludes backend-only properties).
	 *
	 * @return array<string, array> Scoped integration data keyed by code
	 */
	public function get_scoped_integrations( string $scope, bool $for_rest = false ): array {
		$this->init();
		return $this->scope_resolver->resolve_scoped_integrations( $scope, $for_rest );
	}

	/**
	 * Get a single integration for a scope.
	 *
	 * Returns enriched integration data for a specific integration and scope.
	 *
	 * @param string $code     Integration code
	 * @param string $scope    The scope
	 * @param bool   $for_rest Whether to use REST format (excludes backend-only properties).
	 *
	 * @return array|null Scoped integration data or null if not found/no items for scope
	 */
	public function get_scoped_integration( string $code, string $scope, bool $for_rest = false ): ?array {
		$this->init();
		return $this->scope_resolver->resolve_scoped_integration( $code, $scope, $for_rest );
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
	 * @param string|null $item_type        Optional scope to search (trigger, action, filter_condition, loop_filter).
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
	 * Get a single block modifier by integration code, modifier code and type.
	 *
	 * The modifier sibling of {@see self::get_item()}. The type is required
	 * because the same code may legitimately exist under two slots — GEN
	 * registers TOKEN_MEETS_CONDITION as both a filter condition and a loop
	 * filter — so a type-less lookup could return the wrong row's rules.
	 *
	 * @param string $integration_code Integration code (e.g., 'GEN', 'WP').
	 * @param string $modifier_code    Modifier code (e.g., 'ITEM_NOT_EMPTY').
	 * @param string $modifier_type    Block_Modifier_Types literal.
	 *
	 * @return Integration_Block_Modifier|null Modifier object, or null when the
	 *                                         type is invalid or nothing matches.
	 */
	public function get_block_modifier( string $integration_code, string $modifier_code, string $modifier_type ): ?Integration_Block_Modifier {
		if ( ! Block_Modifier_Types::is_valid( $modifier_type ) ) {
			return null;
		}

		$this->init();

		$integration = $this->store->get_by_code( $integration_code );

		if ( null === $integration ) {
			return null;
		}

		$getter    = $this->scope_to_getter( $modifier_type );
		$modifiers = $integration->get_block_modifiers()->$getter();

		return $modifiers[ $modifier_code ] ?? null;
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
}
