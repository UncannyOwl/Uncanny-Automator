<?php
/**
 * Dependency Evaluatable Interface
 *
 * Contract for entities that can have dependencies evaluated.
 * Implemented by Integration, Block, and any future entities that need dependency resolution.
 *
 * @package Uncanny_Automator\Api\Services\Dependency
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Dependency;

/**
 * Interface for entities that support dependency evaluation.
 *
 * @since 7.0.0
 */
interface Dependency_Evaluatable {

	/**
	 * Get entity code.
	 *
	 * Unique identifier for the entity.
	 *
	 * @return string Entity code.
	 */
	public function get_entity_code(): string;

	/**
	 * Get entity name.
	 *
	 * Display name for the entity.
	 *
	 * @return string Entity name.
	 */
	public function get_entity_name(): string;

	/**
	 * Get required tier.
	 *
	 * The plan/license tier required to use this entity.
	 *
	 * @return string Required tier (e.g., 'lite', 'pro-basic', 'pro-plus', 'pro-elite').
	 */
	public function get_entity_required_tier(): string;

	/**
	 * Get entity type.
	 *
	 * Returns the entity type for differentiation in resolvers.
	 *
	 * @return string Entity type (e.g., 'integration', 'block').
	 */
	public function get_entity_type(): string;
}
