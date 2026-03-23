<?php
/**
 * Blocks Store
 *
 * Provides scoped blocks data for use in the recipe builder.
 *
 * @package Uncanny_Automator\Api\Services\Blocks
 * @since 7.0.0
 */
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Block;

use Uncanny_Automator\Api\Components\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Context;
use Uncanny_Automator\Api\Services\Dependency\Dependencies_Resolver;
use Uncanny_Automator\Api\Services\Scope_Tag\Scope_Tag_Resolver;

/**
 * Block Store
 *
 * Provides access to all registered blocks with dependencies and tags.
 *
 * @package Uncanny_Automator\Api\Services\Blocks
 * @since 7.0.0
 */
class Block_Store {

	/**
	 * Block registry.
	 *
	 * @var WP_Block_Registry
	 */
	private WP_Block_Registry $registry;

	/**
	 * Dependencies resolver.
	 *
	 * @var Dependencies_Resolver
	 */
	private Dependencies_Resolver $dependencies_resolver;

	/**
	 * Scope tag resolver.
	 *
	 * @var Scope_Tag_Resolver
	 */
	private Scope_Tag_Resolver $scope_tag_resolver;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->registry              = new WP_Block_Registry();
		$this->dependencies_resolver = new Dependencies_Resolver( new Dependency_Context() );
		$this->scope_tag_resolver    = new Scope_Tag_Resolver();
	}

	/**
	 * Get all blocks with dependencies and tags.
	 *
	 * @return array Array of block data arrays.
	 */
	public function get_all(): array {
		$blocks  = $this->registry->get_available_blocks();
		$results = array();

		foreach ( $blocks as $block ) {
			// Resolve dependencies and tags.
			$dependencies = $this->dependencies_resolver->resolve_block_dependencies( $block );
			$tags         = $this->scope_tag_resolver->resolve_block_tags( $block, $dependencies );

			// Convert block to array.
			$block_type                 = $block->get_type();
			$block_data                 = $block->to_array();
			$block_data['dependencies'] = $this->dependencies_resolver->to_array( $dependencies );
			$block_data['tags']         = $tags->to_array();

			// Add block to results.
			$results[ $block_type ] = $block_data;
		}

		return $results;
	}
}
