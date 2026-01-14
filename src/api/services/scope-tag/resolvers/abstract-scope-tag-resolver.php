<?php
/**
 * Abstract Scope Tag Resolver
 *
 * Base class for all scope tag resolvers.
 *
 * @package Uncanny_Automator\Api\Services\Scope_Tag\Resolvers
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Scope_Tag\Resolvers;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Services\Scope_Tag\Scope_Tag_Evaluatable;

/**
 * Abstract base for scope tag resolvers.
 *
 * Provides common functionality and enforces contract for concrete resolvers.
 *
 * @since 7.0.0
 */
abstract class Abstract_Scope_Tag_Resolver {

	/**
	 * Entity object (Integration, Block, etc).
	 *
	 * @var Scope_Tag_Evaluatable
	 */
	protected $entity;

	/**
	 * Item data (for item-level tags).
	 *
	 * @var array|null
	 */
	protected $item = null;

	/**
	 * Entity dependencies.
	 *
	 * @var array<string, mixed> Array with 'all_met' and 'items' (Dependency objects)
	 */
	protected $entity_dependencies = array();

	/**
	 * Item dependencies.
	 *
	 * @var array<string, mixed> Array with 'all_met' and 'items' (Dependency objects)
	 */
	protected $item_dependencies = array();

	/**
	 * Check if this resolver should evaluate.
	 *
	 * Stores the entity and item for use in evaluate().
	 *
	 * @param Scope_Tag_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data (null for entity-level)
	 * @param array<string, mixed> $entity_dependencies Entity-level dependencies with 'all_met' and 'items' (Dependency objects)
	 * @param array<string, mixed> $item_dependencies Item-level dependencies with 'all_met' and 'items' (Dependency objects)
	 *
	 * @return bool True if this resolver should evaluate
	 */
	abstract public function should_evaluate( Scope_Tag_Evaluatable $entity, $item = null, array $entity_dependencies = array(), array $item_dependencies = array() );

	/**
	 * Evaluate and return tags.
	 *
	 * Called after should_evaluate() returns true.
	 *
	 * @return array Array of tag arrays
	 */
	abstract public function evaluate();

	/**
	 * Store context for evaluation.
	 *
	 * Call this in should_evaluate() before returning.
	 *
	 * @param Scope_Tag_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data
	 * @param array<string, mixed> $entity_dependencies Entity-level dependencies with 'all_met' and 'items' (Dependency objects)
	 * @param array<string, mixed> $item_dependencies Item-level dependencies with 'all_met' and 'items' (Dependency objects)
	 *
	 * @return void
	 */
	protected function set_properties( Scope_Tag_Evaluatable $entity, $item = null, array $entity_dependencies = array(), array $item_dependencies = array() ) {
		$this->entity              = $entity;
		$this->item                = $item;
		$this->entity_dependencies = $entity_dependencies;
		$this->item_dependencies   = $item_dependencies;
	}

	/**
	 * Get license tag for a tier.
	 *
	 * Shared method for license tag resolvers (integration and block).
	 *
	 * @param string $tier The tier (pro-basic, pro-plus, pro-elite)
	 *
	 * @return array|null Tag array or null if tier not found
	 */
	protected function get_license_tag( string $tier ) {
		$tag_map = array(
			'pro-basic' => array(
				'type'        => 'license',
				'scenario_id' => 'license-pro-basic',
				'label'       => esc_html__( 'Pro', 'uncanny-automator' ),
				'color'       => 'success',
				'helper'      => esc_html__( 'You must have a valid Pro Basic license or higher', 'uncanny-automator' ),
			),
			'pro-plus'  => array(
				'type'        => 'license',
				'scenario_id' => 'license-pro-plus',
				'label'       => esc_html__( 'Pro Plus', 'uncanny-automator' ),
				'color'       => 'success',
				'helper'      => esc_html__( 'You must have a valid Pro Plus license or higher', 'uncanny-automator' ),
			),
			'pro-elite' => array(
				'type'        => 'license',
				'scenario_id' => 'license-pro-elite',
				'label'       => esc_html__( 'Pro Elite', 'uncanny-automator' ),
				'color'       => 'success',
				'icon'        => 'gem',
				'helper'      => esc_html__( 'You must have a valid Pro Elite license', 'uncanny-automator' ),
			),
		);

		return $tag_map[ $tier ] ?? null;
	}
}
