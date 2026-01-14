<?php
/**
 * Integration Scope Resolver
 *
 * Resolves scope-specific integration data with dependencies and tags.
 *
 * @package Uncanny_Automator\Api\Services\Integration
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Context;
use Uncanny_Automator\Api\Services\Dependency\Dependencies_Resolver;
use Uncanny_Automator\Api\Services\Scope_Tag\Scope_Tag_Resolver;
use Uncanny_Automator\Api\Services\Integration\Utilities\Popularity\Popularity_Calculator;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;
use InvalidArgumentException;

/**
 * Resolves scope-specific integration data.
 *
 * Responsibilities:
 * - Filter integrations by scope (trigger, action, loop_filter, filter_condition)
 * - Resolve dependencies (license, plugin, account)
 * - Calculate scope-specific popularity
 * - Apply scope tags (availability status)
 *
 * @since 7.0.0
 */
class Integration_Scope_Resolver {

	/**
	 * Base integration store.
	 *
	 * @var Integration_Store|null
	 */
	private $store = null;

	/**
	 * Dependencies resolver.
	 *
	 * @var Dependencies_Resolver|null
	 */
	private $dependencies_resolver = null;

	/**
	 * Scope tag resolver.
	 *
	 * @var Scope_Tag_Resolver|null
	 */
	private $scope_tag_resolver = null;

	/**
	 * Popularity calculator.
	 *
	 * @var Popularity_Calculator|null
	 */
	private $popularity_calculator = null;

	/**
	 * Current scope being resolved.
	 *
	 * @var string
	 */
	private $scope = '';

	/**
	 * Constructor.
	 *
	 * Accepts optional dependencies for testing.
	 *
	 * @param Integration_Store|null     $store                 Store instance
	 * @param Dependencies_Resolver|null $dependencies_resolver Dependencies resolver
	 * @param Scope_Tag_Resolver|null    $scope_tag_resolver    Tag resolver
	 * @param Popularity_Calculator|null $popularity_calculator Popularity calculator
	 */
	public function __construct(
		?Integration_Store $store = null,
		?Dependencies_Resolver $dependencies_resolver = null,
		?Scope_Tag_Resolver $scope_tag_resolver = null,
		?Popularity_Calculator $popularity_calculator = null
	) {
		$this->store                 = $store;
		$this->dependencies_resolver = $dependencies_resolver;
		$this->scope_tag_resolver    = $scope_tag_resolver;
		$this->popularity_calculator = $popularity_calculator;
	}

	/**
	 * Initialize dependencies lazily.
	 *
	 * Performance: Only instantiates when first needed.
	 *
	 * @param string $scope The scope to resolve
	 *
	 * @return void
	 * @throws InvalidArgumentException If scope is invalid
	 */
	private function init( string $scope ): void {
		if ( ! Integration_Item_Types::is_valid( $scope ) ) {
			throw new InvalidArgumentException( sprintf( 'Invalid scope: %s', esc_html( $scope ) ) );
		}

		$this->scope = $scope;

		if ( null === $this->store ) {
			$this->store                 = Integration_Store::get_instance();
			$this->dependencies_resolver = new Dependencies_Resolver( new Dependency_Context() );
			$this->scope_tag_resolver    = new Scope_Tag_Resolver();
			$this->popularity_calculator = new Popularity_Calculator();
		}
	}

	/**
	 * Resolve all integrations for a scope.
	 *
	 * Returns integrations filtered and enriched for the specified scope:
	 * - Filters out integrations without items for the scope
	 * - Adds scope-specific popularity scores
	 * - Adds resolved dependencies
	 * - Adds scope tags
	 *
	 * @param string $scope The scope: 'trigger', 'action', 'loop_filter', or 'filter_condition'
	 *
	 * @return array<string, array> Scoped integration data keyed by code
	 * @throws InvalidArgumentException If scope is invalid
	 */
	public function resolve_scoped_integrations( string $scope ): array {
		$this->init( $scope );

		$integrations = $this->store->get_all();
		$scoped       = array();

		foreach ( $integrations as $integration ) {
			$resolved = $this->resolve_single( $integration );
			if ( null !== $resolved ) {
				$code            = $integration->get_code()->get_value();
				$scoped[ $code ] = $resolved;
			}
		}

		return $scoped;
	}

	/**
	 * Resolve a single integration for the current scope.
	 *
	 * @param Integration $integration Integration object
	 *
	 * @return array|null Scoped integration data or null if no items for scope
	 */
	private function resolve_single( Integration $integration ): ?array {
		$scoped_items = $this->get_scoped_items( $integration->get_items()->to_array() );

		if ( empty( $scoped_items ) ) {
			return null;
		}

		return $this->build_scoped_data( $integration, $scoped_items );
	}

	/**
	 * Resolve a single integration by code for a scope.
	 *
	 * @param string $code  Integration code
	 * @param string $scope The scope
	 *
	 * @return array|null Scoped integration data or null if not found/no items
	 * @throws InvalidArgumentException If scope is invalid
	 */
	public function resolve_scoped_integration( string $code, string $scope ): ?array {
		$this->init( $scope );

		$integration = $this->store->get_by_code( $code );
		if ( null === $integration ) {
			return null;
		}

		return $this->resolve_single( $integration );
	}

	/**
	 * Build scoped data for an integration.
	 *
	 * @param Integration $integration  Integration object
	 * @param array       $scoped_items Items filtered for current scope
	 *
	 * @return array Integration with scoped properties
	 */
	private function build_scoped_data( Integration $integration, array $scoped_items ): array {
		// Start with REST format (excludes internal properties).
		$data = $integration->to_rest();

		// Add popularity score.
		$data['popularity'] = $this->popularity_calculator->calculate_popularity( $integration, $this->scope );

		// Resolve and add dependencies.
		$integration_dependencies = $this->dependencies_resolver->resolve_integration_dependencies( $integration );
		$data['dependencies']     = $this->dependencies_resolver->to_array( $integration_dependencies );

		// Resolve and add scope tags.
		$scope_tags   = $this->scope_tag_resolver->resolve_integration_tags( $integration, $integration_dependencies );
		$data['tags'] = $scope_tags->to_array();

		// Add formatted items for this scope.
		$data['items'] = $this->format_scoped_items( $scoped_items, $integration, $integration_dependencies );

		// Add connected status for app integrations.
		if ( $integration->is_app() ) {
			$data['connected'] = $integration->get_connected()->get_value();
		}

		return $data;
	}

	/**
	 * Get items for the current scope.
	 *
	 * @param array $items All integration items
	 *
	 * @return array Items for current scope
	 */
	private function get_scoped_items( array $items ): array {
		return $items[ $this->scope ] ?? array();
	}

	/**
	 * Format scoped items with dependencies and tags.
	 *
	 * @param array       $scoped_items             Items for current scope
	 * @param Integration $integration              Integration object
	 * @param array       $integration_dependencies Integration-level dependencies
	 *
	 * @return array Formatted items structure
	 */
	private function format_scoped_items( array $scoped_items, Integration $integration, array $integration_dependencies ): array {
		foreach ( $scoped_items as $code => $item ) {
			// Resolve item-level dependencies.
			$item_dependencies                     = $this->dependencies_resolver->resolve_item_dependencies( $item, $integration, $integration_dependencies );
			$scoped_items[ $code ]['dependencies'] = $this->dependencies_resolver->integration_items_to_array( $item_dependencies, $integration_dependencies );

			// Resolve item-level tags.
			$item_scope_tags               = $this->scope_tag_resolver->resolve_item_tags( $item, $integration, $integration_dependencies, $item_dependencies );
			$scoped_items[ $code ]['tags'] = $item_scope_tags->to_array();
		}

		return array(
			$this->scope => $scoped_items,
		);
	}
}
