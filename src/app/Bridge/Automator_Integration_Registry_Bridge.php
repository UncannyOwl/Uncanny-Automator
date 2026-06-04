<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Integration_Registry_Bridge}.
 *
 * The only place in `src/app/` that is permitted to call
 * `Automator()->get_integration*()` and friends. Every other consumer takes
 * the interface and gets this class as its lazy default.
 *
 * The `Automator_` prefix on this class signals "this one talks to the
 * legacy global". Future alternative implementations (test fakes, a
 * non-global rewrite) live alongside without that prefix.
 *
 * @since 7.4.0
 */
final class Automator_Integration_Registry_Bridge implements Integration_Registry_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_integration( string $code ): ?array {
		$integration = \Automator()->get_integration( $code );

		if ( ! is_array( $integration ) ) {
			return null;
		}

		return $integration;
	}

	/**
	 * @inheritDoc
	 */
	public function get_all_integrations(): array {
		$integrations = \Automator()->get_all_integrations();

		if ( ! is_array( $integrations ) ) {
			return array();
		}

		return $integrations;
	}

	/**
	 * @inheritDoc
	 */
	public function get_active_integrations(): array {
		$integrations = \Automator()->get_integrations();

		if ( ! is_array( $integrations ) ) {
			return array();
		}

		return $integrations;
	}

	/**
	 * @inheritDoc
	 */
	public function get_integration_name_by_code( string $code ): string {
		$name = \Automator()->get_integration_name_by_code( $code );

		return is_string( $name ) && '' !== $name ? $name : $code;
	}

	/**
	 * @inheritDoc
	 */
	public function has_integration( string $code ): bool {
		return (bool) \Automator()->has_integration( $code );
	}

	/**
	 * @inheritDoc
	 */
	public function is_app_connected( string $code ): bool {
		$active = $this->get_active_integrations();

		if ( ! isset( $active[ $code ]['connected'] ) ) {
			// Non-app integration — convention is "connected".
			return true;
		}

		return false !== $active[ $code ]['connected'];
	}

	/**
	 * @inheritDoc
	 */
	public function get_integration_for_action( string $action_code ): ?string {
		$result = \Automator()->get->action_integration_from_action_code( $action_code );

		return is_string( $result ) && '' !== $result ? $result : null;
	}

	/**
	 * @inheritDoc
	 */
	public function get_integration_for_trigger( string $trigger_code ): ?string {
		$result = \Automator()->get->trigger_integration_from_trigger_code( $trigger_code );

		return is_string( $result ) && '' !== $result ? $result : null;
	}

	/**
	 * @inheritDoc
	 */
	public function get_integration_for_closure( string $closure_code ): ?string {
		$result = \Automator()->get->closure_integration_from_closure_code( $closure_code );

		return is_string( $result ) && '' !== $result ? $result : null;
	}

	/**
	 * @inheritDoc
	 */
	public function get_action_execution_callback( string $action_code ) {
		$callback = \Automator()->get->action_execution_function_from_action_code( $action_code );

		return is_callable( $callback ) ? $callback : null;
	}

	/**
	 * @inheritDoc
	 */
	public function get_closure_execution_callback( string $closure_code ) {
		$callback = \Automator()->get->closure_execution_function_from_closure_code( $closure_code );

		return is_callable( $callback ) ? $callback : null;
	}
}
