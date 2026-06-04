<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy action definition registry.
 *
 * Wraps `Automator()->get_actions()` and `Automator()->get_action( $code )`
 * so consumers (registries, services, MCP tools) can fetch action
 * definitions through a typed contract instead of touching the global.
 *
 * @since 7.4.0
 */
interface Action_Definition_Bridge {

	/**
	 * Get every registered action definition.
	 *
	 * Wraps `Automator()->get_actions()`.
	 *
	 * @return array Map of action code => raw legacy definition array.
	 */
	public function get_all_action_definitions(): array;

	/**
	 * Get a single action definition by code.
	 *
	 * Wraps `Automator()->get_action( $code )`.
	 *
	 * @param string $code Action code.
	 * @return array|null Raw legacy definition array, or null if not registered.
	 */
	public function get_action_definition( string $code ): ?array;
}
