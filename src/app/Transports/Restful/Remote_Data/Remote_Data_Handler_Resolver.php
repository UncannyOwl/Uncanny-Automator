<?php
/**
 * Remote Data Handler Resolver.
 *
 * Transport-agnostic service that resolves an integration's remote-data
 * handler via the `automator_remote_data_instance_{id}` filter and
 * dispatches a request to it. Shared by the REST controller and the MCP
 * Dropdown_Controller so both can reach the same `remote_data_get_*`
 * methods without an HTTP roundtrip.
 *
 * @package Uncanny_Automator
 *
 * @since 7.3
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Remote_Data;

use Exception;

/**
 * Remote Data Handler Resolver.
 */
final class Remote_Data_Handler_Resolver {

	/**
	 * Resolve the helper instance registered for an integration ID.
	 *
	 * Integration helpers (extending Uncanny_Automator\Recipe\Abstract_Helpers)
	 * register themselves via the `automator_remote_data_instance_{id}` filter
	 * during Integration::register_integration(). This is the single read-side
	 * of that registration.
	 *
	 * @param string $integration_id Integration ID (e.g. 'aioseo', 'github-pro').
	 *
	 * @return object|null Helper instance or null if no handler is registered for the ID.
	 */
	public function resolve( string $integration_id ) {
		/**
		 * Filter to resolve the remote-data handler instance for an integration.
		 *
		 * @param object|null $instance The handler instance. Default null.
		 */
		$instance = apply_filters(
			'automator_remote_data_instance_' . sanitize_key( $integration_id ),
			null
		);

		return is_object( $instance ) ? $instance : null;
	}

	/**
	 * Resolve the handler and dispatch a request to it.
	 *
	 * Throws on missing handler or on a handler that does not implement the
	 * dispatch contract — callers (REST controller, MCP tool) catch and shape
	 * the exception into their own response envelope.
	 *
	 * @param string              $integration_id The integration ID (first URL segment for REST).
	 * @param string              $data_id        The data identifier (second URL segment for REST).
	 * @param Remote_Data_Request $request        Typed request DTO.
	 *
	 * @return array Response data array as produced by the handler's remote_data_get_* method.
	 *
	 * @throws Exception If no handler is registered for the integration ID,
	 *                   or the handler does not expose process_remote_data_request().
	 */
	public function dispatch( string $integration_id, string $data_id, Remote_Data_Request $request ): array {

		$instance = $this->resolve( $integration_id );

		if ( null === $instance ) {
			throw new Exception(
				esc_html_x( 'No remote data handler found for this integration.', 'Remote Data REST', 'uncanny-automator' )
			);
		}

		if ( ! method_exists( $instance, 'process_remote_data_request' ) ) {
			throw new Exception(
				esc_html_x( 'This integration does not support remote data processing.', 'Remote Data REST', 'uncanny-automator' )
			);
		}

		return $instance->process_remote_data_request( $data_id, $request );
	}
}
