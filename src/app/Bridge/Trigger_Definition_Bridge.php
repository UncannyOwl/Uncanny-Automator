<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy trigger definition registry.
 *
 * Wraps `Automator()->get_triggers()` and `Automator()->get_trigger( $code )`
 * so consumers can fetch trigger definitions through a typed contract
 * instead of touching the global.
 *
 * @since 7.4.0
 */
interface Trigger_Definition_Bridge {

	/**
	 * Get every registered trigger definition.
	 *
	 * Wraps `Automator()->get_triggers()`.
	 *
	 * @return array Map of trigger code => raw legacy definition array.
	 */
	public function get_all_trigger_definitions(): array;

	/**
	 * Get a single trigger definition by code.
	 *
	 * Wraps `Automator()->get_trigger( $code )`.
	 *
	 * @param string $code Trigger code.
	 * @return array|null Raw legacy definition array, or null if not registered.
	 */
	public function get_trigger_definition( string $code ): ?array;
}
