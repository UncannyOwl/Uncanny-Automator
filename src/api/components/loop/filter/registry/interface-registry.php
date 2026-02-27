<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter\Registry;

use Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects\Code;

/**
 * Registry Interface.
 *
 * Contract for filter type registration and discovery.
 * Database-agnostic interface for filter definitions.
 *
 * @since 7.0.0
 */
interface Registry {

	/**
	 * Get all available filter types.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filter definitions.
	 */
	public function get_available_filters( array $options = array() ): array;

	/**
	 * Get specific filter definition.
	 *
	 * @param Code  $code    Filter code.
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array|null Filter definition or null if not found.
	 */
	public function get_filter_definition( Code $code, array $options = array() ): ?array;

	/**
	 * Get filters by integration.
	 *
	 * @param string $integration Integration name.
	 * @return array Array of filters for the integration.
	 */
	public function get_filters_by_integration( string $integration ): array;

	/**
	 * Register a filter type.
	 *
	 * @param string $code       Filter code.
	 * @param array  $definition Filter definition.
	 */
	public function register_filter( string $code, array $definition ): void;

	/**
	 * Check if filter is registered.
	 *
	 * @param Code $code Filter code.
	 * @return bool True if registered.
	 */
	public function is_registered( Code $code ): bool;

	/**
	 * Get filters for users iteration type.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filters for users iteration.
	 */
	public function get_user_filters( array $options = array() ): array;

	/**
	 * Get filters for posts iteration type.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filters for posts iteration.
	 */
	public function get_post_filters( array $options = array() ): array;

	/**
	 * Get filters for token iteration type.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of filters for token iteration.
	 */
	public function get_token_filters( array $options = array() ): array;
}
