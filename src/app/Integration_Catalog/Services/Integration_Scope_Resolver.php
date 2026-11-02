<?php
/**
 * Integration Scope Resolver
 *
 * Resolves scope-specific integration data with dependencies and tags.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Services
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Services;

use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Context;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependencies_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Scope_Tag\Scope_Tag_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity\Entity_Popularity_Calculator;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use InvalidArgumentException;

/**
 * Resolves scope-specific integration data.
 *
 * Responsibilities:
 * - Filter integrations by scope (trigger, action, loop_filter, filter_condition)
 * - Resolve dependencies (license, plugin, account)
 * - Attach scope-specific usage from the stored usage census
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
	 * Stored usage census.
	 *
	 * @var Entity_Popularity_Calculator|null
	 */
	private $usage_census = null;

	/**
	 * Usage per integration code for the scope being resolved, read once per resolve.
	 *
	 * @since 8.0.0
	 *
	 * @var array<string,array{count:int,last_used:int}>
	 */
	private $usage = array();

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
	 * @param Integration_Store|null            $store                 Store instance
	 * @param Dependencies_Resolver|null        $dependencies_resolver Dependencies resolver
	 * @param Scope_Tag_Resolver|null           $scope_tag_resolver    Tag resolver
	 * @param Entity_Popularity_Calculator|null $usage_census          Stored usage census
	 */
	public function __construct(
		?Integration_Store $store = null,
		?Dependencies_Resolver $dependencies_resolver = null,
		?Scope_Tag_Resolver $scope_tag_resolver = null,
		?Entity_Popularity_Calculator $usage_census = null
	) {
		$this->store                 = $store;
		$this->dependencies_resolver = $dependencies_resolver;
		$this->scope_tag_resolver    = $scope_tag_resolver;
		$this->usage_census          = $usage_census;
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
		if ( ! Integration_Item_Types::is_valid( $scope ) && ! Block_Modifier_Types::is_valid( $scope ) ) {
			throw new InvalidArgumentException( sprintf( 'Invalid scope: %s', esc_html( $scope ) ) );
		}

		$this->scope = $scope;

		if ( null === $this->store ) {
			$this->store                 = Integration_Store::get_instance();
			$this->dependencies_resolver = new Dependencies_Resolver( new Dependency_Context() );
			$this->scope_tag_resolver    = new Scope_Tag_Resolver();
		}

		$this->usage_census ??= Entity_Popularity_Calculator::instance();
		$this->usage          = $this->usage_census->get_integration_usage(
			$this->scope_is_modifier() ? Wire_Node_Type::BLOCK_MODIFIER : Wire_Node_Type::ITEM,
			$scope
		);
	}

	/**
	 * Resolve all integrations for a scope.
	 *
	 * Returns integrations filtered and enriched for the specified scope:
	 * - Filters out integrations without items for the scope
	 * - Adds scope-specific usage (count + last used)
	 * - Adds resolved dependencies
	 * - Adds scope tags
	 *
	 * @param string $scope    The scope: 'trigger', 'action', 'loop_filter', or 'filter_condition'
	 * @param bool   $for_rest Whether to use REST format (excludes backend-only properties).
	 *
	 * @return array<string, array> Scoped integration data keyed by code
	 * @throws InvalidArgumentException If scope is invalid
	 */
	public function resolve_scoped_integrations( string $scope, bool $for_rest = false ): array {
		$this->init( $scope );

		$integrations = $this->store->get_all();
		$scoped       = array();

		foreach ( $integrations as $integration ) {
			$resolved = $this->resolve_single( $integration, $for_rest );
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
	 * @param bool        $for_rest    Whether to use REST format (excludes backend-only properties).
	 *
	 * @return array|null Scoped integration data or null if no items for scope
	 */
	private function resolve_single( Integration $integration, bool $for_rest = false ): ?array {
		if ( $this->scope_is_modifier() ) {
			$collection      = $integration->get_block_modifiers();
			$collection_data = $for_rest ? $collection->to_rest() : $collection->to_array();
		} else {
			$collection      = $integration->get_items();
			$collection_data = $for_rest ? $collection->to_rest() : $collection->to_array();
		}

		$scoped_entries = $this->get_scoped_items( $collection_data );

		if ( empty( $scoped_entries ) ) {
			return null;
		}

		return $this->build_scoped_data( $integration, $scoped_entries );
	}

	/**
	 * Whether the current scope refers to a block-modifier type
	 * (filter_condition, loop_filter) rather than an item type
	 * (trigger, action).
	 */
	private function scope_is_modifier(): bool {
		return Block_Modifier_Types::is_valid( $this->scope );
	}

	/**
	 * Resolve a single integration by code for a scope.
	 *
	 * @param string $code     Integration code
	 * @param string $scope    The scope
	 * @param bool   $for_rest Whether to use REST format (excludes backend-only properties).
	 *
	 * @return array|null Scoped integration data or null if not found/no items
	 * @throws InvalidArgumentException If scope is invalid
	 */
	public function resolve_scoped_integration( string $code, string $scope, bool $for_rest = false ): ?array {
		$this->init( $scope );

		$integration = $this->store->get_by_code( $code );
		if ( null === $integration ) {
			return null;
		}

		return $this->resolve_single( $integration, $for_rest );
	}

	/**
	 * Build scoped data for an integration.
	 *
	 * @param Integration $integration  Integration object
	 * @param array       $scoped_items Items filtered for current scope
	 *
	 * @return array Integration with scoped properties
	 */
	private function build_scoped_data( Integration $integration, array $scoped_entries ): array {
		// Start with REST format (excludes internal properties).
		$data = $integration->to_rest();

		$data['usage'] = $this->usage[ $integration->get_code()->get_value() ] ?? array(
			'count'     => 0,
			'last_used' => 0,
		);

		$integration_dependencies = $this->dependencies_resolver->resolve_integration_dependencies( $integration );
		$data['dependencies']     = $this->dependencies_resolver->to_array( $integration_dependencies );

		$scope_tags   = $this->scope_tag_resolver->resolve_integration_tags( $integration, $integration_dependencies );
		$data['tags'] = $scope_tags->to_array();

		// Project the resolved scope into the right slot; stamp empty `{}` on the sibling slot
		// so the frontend at-most-one-bucket refine on items + block_modifiers passes.
		$resolved = $this->format_scoped_entries( $scoped_entries, $integration, $integration_dependencies );

		if ( $this->scope_is_modifier() ) {
			$data['items']           = (object) array();
			$data['block_modifiers'] = $resolved;
		} else {
			$data['items']           = $resolved;
			$data['block_modifiers'] = (object) array();
		}

		// Connected status for app integrations.
		if ( $integration->is_app() ) {
			$data['connected'] = $integration->get_connected()->get_value();
		}

		// Enforce dep cascade so the frontend superRefine doesn't reject.
		$this->apply_dependency_cascade( $data );

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
		$scoped = $items[ $this->scope ] ?? array();

		// Handle stdClass from to_rest() (empty items converted to object for JS compatibility).
		if ( $scoped instanceof \stdClass ) {
			return (array) $scoped;
		}

		return $scoped;
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
	private function format_scoped_entries( array $scoped_entries, Integration $integration, array $integration_dependencies ): array {
		foreach ( $scoped_entries as $code => $entry ) {
			$entry_dependencies                      = $this->dependencies_resolver->resolve_item_dependencies( $entry, $integration, $integration_dependencies );
			$scoped_entries[ $code ]['dependencies'] = $this->dependencies_resolver->integration_items_to_array( $entry_dependencies, $integration_dependencies );

			$entry_scope_tags                = $this->scope_tag_resolver->resolve_item_tags( $entry, $integration, $integration_dependencies, $entry_dependencies );
			$scoped_entries[ $code ]['tags'] = $entry_scope_tags->to_array();
		}

		return array(
			$this->scope => $scoped_entries,
		);
	}

	/**
	 * Force item / modifier dep cascade so the frontend ModelIntegrationScopedSchema.superRefine passes.
	 *
	 * Frontend invariant: if integration `dependencies.all_met === false`, every item / modifier
	 * `dependencies.all_met` must also be `false`. Backend resolves them independently so we
	 * post-process the data to enforce the invariant. Mutates `$data` in place.
	 */
	private function apply_dependency_cascade( array &$data ): void {
		if ( true === ( $data['dependencies']['all_met'] ?? true ) ) {
			return;
		}

		foreach ( array( 'items', 'block_modifiers' ) as $slot ) {
			if ( ! is_array( $data[ $slot ] ) ) {
				continue;
			}
			foreach ( $data[ $slot ] as $type => &$entries ) {
				if ( ! is_array( $entries ) ) {
					continue;
				}
				foreach ( $entries as $code => &$entry ) {
					if ( isset( $entry['dependencies']['all_met'] ) ) {
						$entry['dependencies']['all_met'] = false;
					}
				}
				unset( $entry );
			}
			unset( $entries );
		}
	}
}
