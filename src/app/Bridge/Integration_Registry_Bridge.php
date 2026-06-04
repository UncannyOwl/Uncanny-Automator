<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy integration registry.
 *
 * The legacy `Automator()->get_integration*()`, `->has_integration()`,
 * `->is_app_connected()`, and `->get->*_integration_from_*_code()` facades
 * sit behind this interface. Consumers depend on the interface, never on
 * the global, so they can be unit-tested with a fake implementation and
 * the legacy surface can be deprecated method-by-method.
 *
 * Per the api-layer skill: any code in `src/app/` that needs an integration
 * lookup MUST go through this contract. Raw `Automator()->*` calls outside
 * `src/app/bridge/` are forbidden.
 *
 * @since 7.4.0
 */
interface Integration_Registry_Bridge {

	/**
	 * Get a single registered integration by code.
	 *
	 * Wraps `Automator()->get_integration( $code )`.
	 *
	 * @param string $code Integration code (e.g. "WP", "LD", "WC").
	 * @return array|null Integration definition array, or null if not registered.
	 */
	public function get_integration( string $code ): ?array;

	/**
	 * Get every registered integration definition.
	 *
	 * Wraps `Automator()->get_all_integrations()` — the full registry,
	 * including integrations whose underlying plugin is not active on the
	 * current site.
	 *
	 * @return array Map of integration code => definition array.
	 */
	public function get_all_integrations(): array;

	/**
	 * Get only the integrations that are active on the current site.
	 *
	 * Wraps `Automator()->get_integrations()`. The active set is a subset
	 * of {@see self::get_all_integrations()} — only integrations whose
	 * plugin is loaded contribute entries here, and app integrations have
	 * a `connected` key reporting their auth state.
	 *
	 * @return array Map of integration code => definition array.
	 */
	public function get_active_integrations(): array;

	/**
	 * Get a human-readable integration name from its code.
	 *
	 * Wraps `Automator()->get_integration_name_by_code( $code )`.
	 *
	 * @param string $code Integration code.
	 * @return string Display name, or the code itself when no name is registered.
	 */
	public function get_integration_name_by_code( string $code ): string;

	/**
	 * Check whether an integration code is registered.
	 *
	 * Wraps `Automator()->has_integration( $code )`.
	 *
	 * @param string $code Integration code.
	 * @return bool True if the integration is registered.
	 */
	public function has_integration( string $code ): bool;

	/**
	 * Check whether an app integration is connected.
	 *
	 * Non-app integrations (no `connected` key in their definition) return
	 * true by convention — only app integrations report a connection state.
	 *
	 * @param string $code Integration code.
	 * @return bool True if connected (or not an app integration).
	 */
	public function is_app_connected( string $code ): bool;

	/**
	 * Resolve an integration code from an action code.
	 *
	 * Wraps `Automator()->get->action_integration_from_action_code( $action_code )`.
	 *
	 * @param string $action_code Action code.
	 * @return string|null Integration code, or null if not resolvable.
	 */
	public function get_integration_for_action( string $action_code ): ?string;

	/**
	 * Resolve an integration code from a trigger code.
	 *
	 * Wraps `Automator()->get->trigger_integration_from_trigger_code( $trigger_code )`.
	 *
	 * @param string $trigger_code Trigger code.
	 * @return string|null Integration code, or null if not resolvable.
	 */
	public function get_integration_for_trigger( string $trigger_code ): ?string;

	/**
	 * Resolve an integration code from a closure code.
	 *
	 * Wraps `Automator()->get->closure_integration_from_closure_code( $closure_code )`.
	 *
	 * @param string $closure_code Closure code.
	 * @return string|null Integration code, or null if not resolvable.
	 */
	public function get_integration_for_closure( string $closure_code ): ?string;

	/**
	 * Resolve an action's execution callback by action code.
	 *
	 * Wraps `Automator()->get->action_execution_function_from_action_code( $action_code )`.
	 *
	 * @param string $action_code Action code.
	 * @return callable|null Execution callback, or null if not registered.
	 */
	public function get_action_execution_callback( string $action_code );

	/**
	 * Resolve a closure's execution callback by closure code.
	 *
	 * Wraps `Automator()->get->closure_execution_function_from_closure_code( $closure_code )`.
	 *
	 * @param string $closure_code Closure code.
	 * @return callable|null Execution callback, or null if not registered.
	 */
	public function get_closure_execution_callback( string $closure_code );
}
