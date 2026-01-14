<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Condition\Registry;

/**
 * Action Condition Registry Interface.
 *
 * Defines the contract for action condition registry implementations.
 * Provides methods to discover and retrieve action condition definitions
 * from the legacy WordPress filter system.
 *
 * @since 7.0.0
 */
interface Action_Condition_Registry {

	/**
	 * Get all available action conditions.
	 *
	 * @return array Array of condition definitions grouped by integration.
	 */
	public function get_all_conditions(): array;

	/**
	 * Get conditions for a specific integration.
	 *
	 * @param string $integration_code Integration code (e.g., 'WP', 'GEN', 'LD').
	 * @return array Array of condition definitions for the integration.
	 */
	public function get_conditions_by_integration( string $integration_code ): array;

	/**
	 * Get a specific condition definition.
	 *
	 * @param string $integration_code Integration code.
	 * @param string $condition_code Condition code.
	 * @return array|null Condition definition or null if not found.
	 */
	public function get_condition_definition( string $integration_code, string $condition_code ): ?array;

	/**
	 * Check if a condition exists.
	 *
	 * @param string $integration_code Integration code.
	 * @param string $condition_code Condition code.
	 * @return bool True if condition exists.
	 */
	public function condition_exists( string $integration_code, string $condition_code ): bool;

	/**
	 * Search conditions by term.
	 *
	 * @param string $search_term Search term to match against condition names.
	 * @param string $integration_filter Optional integration to filter by.
	 * @return array Array of matching condition definitions.
	 */
	public function search_conditions( string $search_term, string $integration_filter = '' ): array;

	/**
	 * Get field schema for a specific condition (JSON Schema format).
	 *
	 * @param string $integration_code Integration code.
	 * @param string $condition_code Condition code.
	 * @return array Field schema definitions for the condition in JSON Schema format.
	 */
	public function get_condition_fields( string $integration_code, string $condition_code ): array;

	/**
	 * Get raw field definitions for a specific condition.
	 *
	 * Returns the raw field array from the WordPress filter without
	 * JSON Schema conversion. Includes supports_markdown and supports_tinymce flags.
	 *
	 * @param string $integration_code Integration code.
	 * @param string $condition_code Condition code.
	 * @return array Raw field definitions array.
	 */
	public function get_raw_condition_fields( string $integration_code, string $condition_code ): array;

	/**
	 * Get available integrations that have conditions.
	 *
	 * @return array Array of integration codes with condition counts.
	 */
	public function get_available_integrations(): array;
}
