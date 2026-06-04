<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Integration registry — pipeline-facing wrapper around integration discovery
 * and plugin status checks.
 *
 * Consumes {@see Integration_Registry_Bridge} for every legacy
 * `Automator()->*` lookup. The bridge is the only place in `src/app/`
 * permitted to talk to the legacy global; this class delegates and adds
 * pipeline-specific concerns (`get_plugin_status()` and its filter).
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
class Integration_Registry {

	/**
	 * Anti-corruption boundary to the legacy integration registry.
	 *
	 * @var Integration_Registry_Bridge
	 */
	private Integration_Registry_Bridge $integrations;

	/**
	 * @param Integration_Registry_Bridge|null $integrations Optional bridge override (tests).
	 */
	public function __construct( ?Integration_Registry_Bridge $integrations = null ) {
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
	}

	// ── Integration code lookups ──

	/**
	 * @param string $action_code The action code.
	 *
	 * @return string|null The integration slug.
	 */
	public function get_action_integration( string $action_code ): ?string {
		return $this->integrations->get_integration_for_action( $action_code );
	}

	/**
	 * @param string $trigger_code The trigger code.
	 *
	 * @return string|null The integration slug.
	 */
	public function get_trigger_integration( string $trigger_code ): ?string {
		return $this->integrations->get_integration_for_trigger( $trigger_code );
	}

	/**
	 * @param string $closure_code The closure code.
	 *
	 * @return string|null The integration slug.
	 */
	public function get_closure_integration( string $closure_code ): ?string {
		return $this->integrations->get_integration_for_closure( $closure_code );
	}

	// ── Plugin status ──

	/**
	 * Get plugin status for an integration.
	 *
	 * Reads directly from Set_Up_Automator::$active_integrations_code.
	 * Returns 0 (inactive), 1 (active), or null (invalid/unknown integration).
	 *
	 * Callers compare against 0 or 1 as needed — legacy code is inconsistent
	 * about how null is treated (Stage 2 passes through, Stage 4 blocks).
	 *
	 * @param mixed $integration The integration slug.
	 *
	 * @return int|null
	 */
	public function get_plugin_status( $integration ): ?int {

		if ( null === $integration || ! is_string( $integration ) ) {
			return null;
		}

		$active = in_array( $integration, \Uncanny_Automator\Set_Up_Automator::$active_integrations_code, true ) ? 1 : 0;

		return absint( Dispatcher::filter( 'uncanny_automator_maybe_add_integration', $active, $integration ) );
	}

	/**
	 * Check if the plugin for an integration is active.
	 *
	 * Convenience wrapper — replaces the `0 === get_plugin_status()` pattern
	 * scattered across stages and services.
	 *
	 * @param string|null $integration The integration slug.
	 *
	 * @return bool
	 */
	public function is_plugin_active( ?string $integration ): bool {
		return 0 !== $this->get_plugin_status( $integration );
	}

	/**
	 * Check if an app integration is connected.
	 *
	 * Non-app integrations (no 'connected' key) return true.
	 *
	 * @param string $integration The integration slug.
	 *
	 * @return bool
	 */
	public function is_app_connected( string $integration ): bool {
		return $this->integrations->is_app_connected( $integration );
	}

	// ── Execution functions ──

	/**
	 * @param string $action_code The action code.
	 *
	 * @return callable|null The execution function, or null if not found.
	 */
	public function get_action_execution_function( string $action_code ): ?callable {
		return $this->integrations->get_action_execution_callback( $action_code );
	}

	/**
	 * @param string $closure_code The closure code.
	 *
	 * @return callable|null The execution function, or null if not found.
	 */
	public function get_closure_execution_function( string $closure_code ): ?callable {
		return $this->integrations->get_closure_execution_callback( $closure_code );
	}
}
