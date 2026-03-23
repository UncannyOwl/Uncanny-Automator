<?php
/**
 * Scope Tag Resolver
 *
 * Orchestrates scope tag resolution for integrations, blocks, and their items.
 *
 * @package Uncanny_Automator\Api\Services\Scope_Tag
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Scope_Tag;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Components\Block\Block;
use Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Integration\License\License_Tag_Resolver;
use Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Integration\Third_Party\Third_Party_Tag_Resolver;
use Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Integration\Dependency\Dependency_Tag_Resolver;
use Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Block\License\License_Tag_Resolver as Block_License_Tag_Resolver;
use Uncanny_Automator\Api\Services\Scope_Tag\Resolvers\Availability\Availability_Tag_Resolver;
use Uncanny_Automator\Api\Components\Scope_Tag\Value_Objects\Scope_Tags;

/**
 * Orchestrates scope tag resolution for integrations, blocks, and items.
 *
 * Coordinates tag types:
 * - license: Pro tier requirements
 * - third-party: Third-party integration indicator (integrations only)
 * - dependency: Plugin/connection requirements (integrations only)
 * - availability: Locked state
 *
 * @since 7.0.0
 */
class Scope_Tag_Resolver {

	/**
	 * Registered integration tag resolvers.
	 *
	 * @var array<string, object>
	 */
	private $resolvers;

	/**
	 * Registered block tag resolvers.
	 *
	 * @var array<string, object>
	 */
	private $block_resolvers;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->resolvers       = $this->register_integration_resolvers();
		$this->block_resolvers = $this->register_block_resolvers();
	}

	/**
	 * Register integration tag resolvers.
	 *
	 * @return array<string, object> Array of resolver instances keyed by type
	 */
	private function register_integration_resolvers() {
		return array(
			'license'      => new License_Tag_Resolver(),
			'third-party'  => new Third_Party_Tag_Resolver(),
			'dependency'   => new Dependency_Tag_Resolver(),
			'availability' => new Availability_Tag_Resolver(),
		);
	}

	/**
	 * Register block tag resolvers.
	 *
	 * Blocks only have license and availability tags.
	 *
	 * @return array<string, object> Array of resolver instances keyed by type
	 */
	private function register_block_resolvers() {
		return array(
			'license'      => new Block_License_Tag_Resolver(),
			'availability' => new Availability_Tag_Resolver(),
		);
	}

	/**
	 * Resolve integration-level scope tags.
	 *
	 * @param Integration $integration Integration object
	 * @param array $integration_dependencies Integration-level dependencies with 'all_met' and 'items' (Dependency objects)
	 *
	 * @return Scope_Tags Scope tags value object
	 */
	public function resolve_integration_tags( Integration $integration, array $integration_dependencies = array() ) {
		$tags = array();

		// Loop through registered resolvers and evaluate if applicable.
		foreach ( $this->resolvers as $resolver ) {
			if ( $resolver->should_evaluate( $integration, null, $integration_dependencies, array() ) ) {
				$resolved_tags = $resolver->evaluate();
				$tags          = array_merge( $tags, $resolved_tags );
			}
		}

		// Order tags by type: License, Third Party, Dependency, Availability.
		$tags = $this->order_tags_by_type( $tags );

		return new Scope_Tags( $tags );
	}

	/**
	 * Resolve item-level scope tags.
	 *
	 * @param array $item Item data with code, name, required_tier
	 * @param Integration $integration Integration object
	 * @param array<string, mixed> $integration_dependencies Integration-level dependencies with 'all_met' and 'items' (Dependency objects)
	 * @param array<string, mixed> $item_dependencies Item-level dependencies with 'all_met' and 'items' (Dependency objects)
	 *
	 * @return Scope_Tags Scope tags value object
	 */
	public function resolve_item_tags( array $item, Integration $integration, array $integration_dependencies = array(), array $item_dependencies = array() ) {
		$tags = array();

		// Loop through registered resolvers and evaluate if applicable.
		foreach ( $this->resolvers as $type => $resolver ) {
			// Always evaluate license and third-party tags for items (same priority).
			if ( 'license' === $type || 'third-party' === $type ) {
				if ( $resolver->should_evaluate( $integration, $item, $integration_dependencies, $item_dependencies ) ) {
					$resolved_tags = $resolver->evaluate();
					$tags          = array_merge( $tags, $resolved_tags );
				}
				continue;
			}

			// After license and third-party, check if parent integration is locked.
			// If locked, add locked tag and stop evaluating further resolvers.
			if ( ! $this->are_integration_dependencies_met( $integration_dependencies ) ) {
				$tags[] = $this->resolvers['availability']->get_locked_tag();
				break;
			}

			// Continue with remaining resolvers ( Dependency and Availability ).
			if ( $resolver->should_evaluate( $integration, $item, $integration_dependencies, $item_dependencies ) ) {
				$resolved_tags = $resolver->evaluate();
				$tags          = array_merge( $tags, $resolved_tags );
			}
		}

		// Order tags by type: License, Third Party, Dependency, Availability.
		$tags = $this->order_tags_by_type( $tags );

		return new Scope_Tags( $tags );
	}

	/**
	 * Resolve block-level scope tags.
	 *
	 * @param Block $block Block object
	 * @param array $block_dependencies Block-level dependencies with 'all_met' and 'items' (Dependency objects)
	 *
	 * @return Scope_Tags Scope tags value object
	 */
	public function resolve_block_tags( Block $block, array $block_dependencies = array() ) {
		$tags = array();

		// Loop through registered block resolvers and evaluate if applicable.
		foreach ( $this->block_resolvers as $resolver ) {
			if ( $resolver->should_evaluate( $block, null, $block_dependencies, array() ) ) {
				$resolved_tags = $resolver->evaluate();
				$tags          = array_merge( $tags, $resolved_tags );
			}
		}

		// Order tags by type: License, Availability.
		$tags = $this->order_tags_by_type( $tags, $this->block_resolvers );

		return new Scope_Tags( $tags );
	}

	/**
	 * Check if integration dependencies are all met.
	 *
	 * @param array $integration_dependencies Integration dependencies array
	 *
	 * @return bool True if all integration dependencies are met
	 */
	private function are_integration_dependencies_met( array $integration_dependencies ) {
		return ! empty( $integration_dependencies['all_met'] ) && true === $integration_dependencies['all_met'];
	}

	/**
	 * Order tags by type priority.
	 *
	 * Priority order:
	 * 1. License
	 * 2. Third Party (integrations only)
	 * 3. Dependency (integrations only)
	 * 4. Availability
	 *
	 * @param array $tags Array of tag arrays
	 * @param array|null $resolvers Optional resolver set to use for ordering (defaults to integration resolvers)
	 *
	 * @return array Ordered array of tag arrays
	 */
	private function order_tags_by_type( array $tags, $resolvers = null ) {

		if ( empty( $tags ) ) {
			return $tags;
		}

		// Use provided resolvers or default to integration resolvers.
		$type_order = array_keys( $resolvers ?? $this->resolvers );

		usort(
			$tags,
			function ( $a, $b ) use ( $type_order ) {
				$a_type = $a['type'] ?? '';
				$b_type = $b['type'] ?? '';

				$a_priority = $type_order[ $a_type ] ?? 999;
				$b_priority = $type_order[ $b_type ] ?? 999;

				return $a_priority <=> $b_priority;
			}
		);

		return $tags;
	}
}
