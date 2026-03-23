<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Registry;

use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Code;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_User_Type;

/**
 * Action Registry Interface.
 *
 * Contract for action type registration and discovery.
 * Database-agnostic interface for action definitions.
 *
 * @since 7.0.0
 */
interface Action_Registry {

	/**
	 * Get all available action types.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of action definitions.
	 */
	public function get_available_actions( array $options = array() ): array;

	/**
	 * Get specific action definition.
	 *
	 * @param Action_Code $code Action code.
	 * @param array       $options Format options: ['include_schema' => bool].
	 * @return array|null Action definition or null if not found.
	 */
	public function get_action_definition( Action_Code $code, array $options = array() ): ?array;

	/**
	 * Get actions by type.
	 *
	 * @param Action_User_Type $type Action type.
	 * @param array            $options Format options: ['include_schema' => bool].
	 * @return array Array of actions for the specified type.
	 */
	public function get_actions_by_type( Action_User_Type $type, array $options = array() ): array;

	/**
	 * Register an action type.
	 *
	 * @param string $code Action code.
	 * @param array  $definition Action definition.
	 */
	public function register_action( string $code, array $definition ): void;

	/**
	 * Check if action is registered.
	 *
	 * @param Action_Code $code Action code.
	 * @return bool True if registered.
	 */
	public function is_registered( Action_Code $code ): bool;

	/**
	 * Get actions by integration.
	 *
	 * @param string $integration Integration name.
	 * @return array Array of actions for the integration.
	 */
	public function get_actions_by_integration( string $integration ): array;

	/**
	 * Get WordPress native actions.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of WordPress native actions.
	 */
	public function get_wordpress_native_actions( array $options = array() ): array;

	/**
	 * Get API integration actions.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of API integration actions.
	 */
	public function get_api_actions( array $options = array() ): array;
}
