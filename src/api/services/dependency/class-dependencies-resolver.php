<?php
/**
 * Dependency Resolver
 *
 * Orchestrates dependency resolution for blocks, integrations, and their items.
 *
 * @package Uncanny_Automator\Api\Services\Dependency
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Dependency;

use Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\License\License_Resolver;
use Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\Plugin\Plugin_Resolver;
use Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\Account\Account_Resolver;
use Uncanny_Automator\Api\Services\Dependency\Resolvers\Block\License\License_Resolver as Block_License_Resolver;
use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Components\Block\Block;

/**
 * Orchestrates dependency resolution for blocks, integrations, and their items.
 *
 * Coordinates three types of dependencies:
 * - license: Automator license/tier requirements
 * - plugin: Plugin installation/activation requirements (integrations only)
 * - account: App connection requirements (integrations only)
 *
 * @since 7.0.0
 */
class Dependencies_Resolver {

	/**
	 * Dependency context.
	 *
	 * @var Dependency_Context
	 */
	private $context;

	/**
	 * Registered integration dependency resolvers.
	 *
	 * @var array<object>
	 */
	private $resolvers;

	/**
	 * Registered block dependency resolvers.
	 *
	 * @var array<object>
	 */
	private $block_resolvers;

	/**
	 * Constructor.
	 *
	 * @param Dependency_Context $context Dependency resolution context
	 *
	 * @return void
	 */
	public function __construct( Dependency_Context $context ) {
		$this->context         = $context;
		$this->resolvers       = $this->register_integration_resolvers();
		$this->block_resolvers = $this->register_block_resolvers();
	}

	/**
	 * Register integration dependency resolvers.
	 *
	 * @return array<object> Array of resolver instances
	 */
	private function register_integration_resolvers() {
		return array(
			new License_Resolver( $this->context ),
			new Plugin_Resolver( $this->context ),
			new Account_Resolver( $this->context ),
		);
	}

	/**
	 * Register block dependency resolvers.
	 *
	 * Blocks only require license/tier validation.
	 *
	 * @return array<object> Array of resolver instances
	 */
	private function register_block_resolvers() {
		return array(
			new Block_License_Resolver( $this->context ),
		);
	}

	/**
	 * Resolve integration-level dependencies.
	 *
	 * @param Integration $integration Integration object
	 *
	 * @return array Dependencies array with 'all_met' and 'items'
	 */
	public function resolve_integration_dependencies( Integration $integration ) {
		$dependencies = array();

		// Loop through registered resolvers and evaluate if applicable.
		foreach ( $this->resolvers as $resolver ) {
			if ( $resolver->should_evaluate( $integration ) ) {
				$resolved_deps = $resolver->evaluate();
				$dependencies  = array_merge( $dependencies, $resolved_deps );
			}
		}

		return $this->format_dependencies_response( $dependencies );
	}

	/**
	 * Resolve item-level dependencies.
	 *
	 * Items run through the same dependency checks as integrations,
	 * but use the item's tier for license checks.
	 * Filters out dependencies that are already present in the integration dependencies,
	 * as items inherit the parent integration's dependencies.
	 *
	 * @param array $item Item data with code, name, required_tier
	 * @param Integration $integration Integration object
	 * @param array $integration_dependencies Integration-level dependencies (optional)
	 *
	 * @return array Dependencies array with 'all_met' and 'items'
	 */
	public function resolve_item_dependencies( array $item, Integration $integration, array $integration_dependencies = array() ) {
		$dependencies = array();

		// Loop through registered resolvers and evaluate if applicable.
		foreach ( $this->resolvers as $resolver ) {
			if ( $resolver->should_evaluate( $integration, $item ) ) {
				$resolved_deps = $resolver->evaluate();
				$dependencies  = array_merge( $dependencies, $resolved_deps );
			}
		}

		// Filter out dependencies that are already present in integration dependencies.
		$dependencies = $this->filter_inherited_dependencies( $dependencies, $integration_dependencies );

		return $this->format_dependencies_response( $dependencies );
	}

	/**
	 * Resolve block-level dependencies.
	 *
	 * @param Block $block Block object
	 *
	 * @return array Dependencies array with 'all_met' and 'items'
	 */
	public function resolve_block_dependencies( Block $block ) {
		$dependencies = array();

		// Loop through registered block resolvers and evaluate if applicable.
		foreach ( $this->block_resolvers as $resolver ) {
			if ( $resolver->should_evaluate( $block ) ) {
				$resolved_deps = $resolver->evaluate();
				$dependencies  = array_merge( $dependencies, $resolved_deps );
			}
		}

		return $this->format_dependencies_response( $dependencies );
	}

	/**
	 * Filter out dependencies that are already present in integration dependencies.
	 *
	 * Items inherit their parent integration's dependencies, so we only need to
	 * include dependencies that are unique to the item (different type).
	 *
	 * @param array $item_dependencies Array of Dependency objects from item
	 * @param array $integration_dependencies Integration dependencies array with 'items' key (Dependency objects)
	 *
	 * @return array Filtered array of Dependency objects
	 */
	private function filter_inherited_dependencies( array $item_dependencies, array $integration_dependencies ) {
		// If no integration dependencies provided, return all item dependencies.
		if ( empty( $integration_dependencies['items'] ) ) {
			return $item_dependencies;
		}

		// Extract dependency types from integration dependencies.
		$integration_types = array();
		foreach ( $integration_dependencies['items'] as $integration_dep ) {
			$integration_types[ $integration_dep->get_type() ] = true;
		}

		// Filter out item dependencies that have the same type as integration dependencies.
		return array_filter(
			$item_dependencies,
			function ( $item_dep ) use ( $integration_types ) {
				return ! isset( $integration_types[ $item_dep->get_type() ] );
			}
		);
	}

	/**
	 * Format dependencies response with 'all_met' flag and Dependency objects.
	 *
	 * @param array $dependencies Array of Dependency objects
	 *
	 * @return array Dependencies array with 'all_met' and 'items' (Dependency objects)
	 */
	private function format_dependencies_response( array $dependencies ) {
		// Check if all dependencies are met.
		$all_met = true;
		foreach ( $dependencies as $dep ) {
			if ( ! $dep->is_met() ) {
				$all_met = false;
				break;
			}
		}

		return array(
			'all_met' => $all_met,
			'items'   => $dependencies,
		);
	}

	/**
	 * Convert dependencies response to array format.
	 *
	 * Converts Dependency objects to arrays for JSON serialization.
	 *
	 * @param array $dependencies_response Dependencies response with 'all_met' and 'items' (Dependency objects)
	 *
	 * @return array Dependencies array with 'all_met' and 'items' (arrays)
	 */
	public function to_array( array $dependencies_response ) {
		$dependency_arrays = array_map(
			function ( $dep ) {
				return $dep->to_array();
			},
			$dependencies_response['items']
		);

		return array(
			'all_met' => $dependencies_response['all_met'],
			'items'   => $dependency_arrays,
		);
	}

	/**
	 * Convert integration items to array format.
	 *
	 * @param array $dependencies_response Dependencies response with 'all_met' and 'items' (Dependency objects)
	 * @param array $integration_dependencies Integration dependencies array with 'all_met' key
	 *
	 * @return array Dependencies array with 'all_met' and 'items' (arrays)
	 */
	public function integration_items_to_array( array $dependencies_response, array $integration_dependencies ) {
		$response = $this->to_array( $dependencies_response );
		// If the integration dependencies are not met, override the response all_met to false.
		if ( true === $response['all_met'] && false === $integration_dependencies['all_met'] ) {
			return array(
				'all_met' => false,
				'items'   => $response['items'],
			);
		}

		return $response;
	}
}
