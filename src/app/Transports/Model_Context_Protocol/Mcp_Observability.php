<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use Uncanny_Automator\App\Events\Dispatcher;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Redacted protocol telemetry collected around real REST dispatch.
 */
final class Mcp_Observability {

	/** @var array<string,float> */
	private array $started = array();

	/**
	 * Register REST dispatch hooks.
	 */
	public function init(): void {
		add_filter( 'rest_request_before_callbacks', array( $this, 'begin' ), 1, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'complete' ), 10, 3 );
	}

	/**
	 * Mark the start of a matched MCP route.
	 *
	 * @param mixed $response Existing response.
	 * @param mixed $handler Matched handler.
	 * @return mixed
	 */
	public function begin( $response, $handler, WP_REST_Request $request ) {
		if ( $this->is_mcp_request( $request ) ) {
			$this->started[ spl_object_hash( $request ) ] = microtime( true );
		}

		return $response;
	}

	/**
	 * Emit only routing and outcome data after the response exists.
	 *
	 * @param mixed $response REST response.
	 * @param mixed $server REST server.
	 * @return mixed
	 */
	public function complete( $response, $server, WP_REST_Request $request ) {
		if ( ! $this->is_mcp_request( $request ) ) {
			return $response;
		}

		$key     = spl_object_hash( $request );
		$started = $this->started[ $key ] ?? microtime( true );
		unset( $this->started[ $key ] );
		$status        = $response instanceof WP_REST_Response ? $response->get_status() : 500;
		$data          = $response instanceof WP_REST_Response ? $response->get_data() : null;
		$code          = is_array( $data ) && isset( $data['error']['code'] ) ? (int) $data['error']['code'] : null;
		$result        = is_array( $data ) ? ( $data['result'] ?? null ) : null;
		$is_tool_error = ( is_array( $result ) && ! empty( $result['isError'] ) )
			|| ( is_object( $result ) && ! empty( $result->isError ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MCP uses camelCase.

		Dispatcher::action(
			'automator_mcp_protocol_event',
			array(
				'protocol_version' => $this->safe_header( $request, 'mcp-protocol-version', 32 ),
				'method'           => $this->safe_header( $request, 'mcp-method', 100 ),
				'tool'             => $this->safe_header( $request, 'mcp-name', 160 ),
				'http_status'      => (int) $status,
				'error_code'       => $code,
				'outcome'          => $status >= 400 || null !== $code || $is_tool_error ? 'error' : 'success',
				'latency_ms'       => max( 0, (int) round( ( microtime( true ) - $started ) * 1000 ) ),
				'timestamp'        => time(),
			)
		);

		return $response;
	}
	/**
	 * Is mcp request.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return bool
	 */
	private function is_mcp_request( WP_REST_Request $request ): bool {
		return '/' . Mcp_Rest_Controller::ROUTE_NAMESPACE . '/' . Mcp_Rest_Controller::ROUTE_BASE
			=== untrailingslashit( (string) $request->get_route() );
	}
	/**
	 * Safe header.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string $name The name.
	 * @param int $limit The limit.
	 * @return string
	 */
	private function safe_header( WP_REST_Request $request, string $name, int $limit ): string {
		$value = sanitize_text_field( (string) $request->get_header( $name ) );

		return substr( $value, 0, $limit );
	}
}
