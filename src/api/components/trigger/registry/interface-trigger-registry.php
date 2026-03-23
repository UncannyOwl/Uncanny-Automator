<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Registry;

use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Code;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_User_Type;

/**
 * Trigger Registry Interface.
 *
 * Contract for trigger type registration and discovery.
 * Database-agnostic interface for trigger definitions.
 *
 * @since 7.0.0
 */
interface Trigger_Registry {

	/**
	 * Get all available trigger types.
	 *
	 * @return array Array of trigger definitions.
	 */
	public function get_available_triggers(): array;

	/**
	 * Get specific trigger definition.
	 *
	 * @param Trigger_Code $code Trigger code.
	 * @return array|null Trigger definition or null if not found.
	 */
	public function get_trigger_definition( Trigger_Code $code ): ?array;

	/**
	 * Get triggers by type.
	 *
	 * @param Trigger_User_Type $type Trigger user type.
	 * @return array Array of triggers for the specified type.
	 */
	public function get_triggers_by_type( Trigger_User_Type $type ): array;

	/**
	 * Register a trigger type.
	 *
	 * @param string $code Trigger code.
	 * @param array  $definition Trigger definition.
	 */
	public function register_trigger( string $code, array $definition ): void;

	/**
	 * Check if trigger is registered.
	 *
	 * @param Trigger_Code $code Trigger code.
	 * @return bool True if registered.
	 */
	public function is_registered( Trigger_Code $code ): bool;

	/**
	 * Get triggers by integration.
	 *
	 * @param string $integration Integration name.
	 * @return array Array of triggers for the integration.
	 */
	public function get_triggers_by_integration( string $integration ): array;
}
