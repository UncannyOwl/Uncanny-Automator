<?php
/**
 * Scope Tag Evaluatable Interface
 *
 * Contract for entities that can have scope tags evaluated.
 * Implemented by Integration, Block, and any future entities that need scope tag resolution.
 *
 * @package Uncanny_Automator\Api\Services\Scope_Tag
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Scope_Tag;

/**
 * Interface for entities that support scope tag evaluation.
 *
 * @since 7.0.0
 */
interface Scope_Tag_Evaluatable {

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
