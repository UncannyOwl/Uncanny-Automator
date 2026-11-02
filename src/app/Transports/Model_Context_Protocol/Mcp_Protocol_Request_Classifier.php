<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use WP_Error;
use WP_REST_Request;

/**
 * Select the MCP wire era before era-specific validation runs.
 */
final class Mcp_Protocol_Request_Classifier {

	public const ERA_LEGACY = 'legacy';

	public const ERA_MODERN = 'modern';

	/**
	 * Classify one parsed JSON-RPC message.
	 *
	 * @return string|WP_Error
	 */
	public function classify( WP_REST_Request $request, array $message ) {
		$method           = isset( $message['method'] ) && is_string( $message['method'] ) ? $message['method'] : '';
		$version          = trim( (string) $request->get_header( 'mcp-protocol-version' ) );
		$metadata_version = $this->get_metadata_version( $message );

		if ( 'initialize' === $method ) {
			if ( '' !== $version && ! Mcp_Version_Manager::is_initialize_version( $version ) ) {
				return $this->unsupported_version( $version, Mcp_Version_Manager::get_initialize_versions(), 'UA-MCP-101' );
			}

			if ( null !== $metadata_version ) {
				return $this->conflicting_version_evidence( $version, $metadata_version, 'UA-MCP-102' );
			}

			return self::ERA_LEGACY;
		}

		if ( '' !== $version ) {
			if ( Mcp_Version_Manager::is_modern( $version ) ) {
				return self::ERA_MODERN;
			}

			if ( Mcp_Version_Manager::is_initialize_version( $version ) ) {
				if ( null !== $metadata_version ) {
					return $this->conflicting_version_evidence( $version, $metadata_version, 'UA-MCP-103' );
				}

				return self::ERA_LEGACY;
			}

			return $this->unsupported_version( $version, Mcp_Version_Manager::get_supported_versions(), 'UA-MCP-104' );
		}

		if ( null !== $metadata_version ) {
			if ( Mcp_Version_Manager::is_modern( $metadata_version ) ) {
				return self::ERA_MODERN;
			}

			return $this->unsupported_version( $metadata_version, Mcp_Version_Manager::get_supported_versions(), 'UA-MCP-105' );
		}

		// No header and no per-request metadata: the MCP-Protocol-Version header
		// only exists since 2025-06-18, and the specification tells servers to
		// assume 2025-03-26 in its absence. That revision speaks the legacy wire
		// shape, so the request is classified rather than refused.
		return self::ERA_LEGACY;
	}

	/**
	 * Read the modern per-request version without treating arbitrary metadata as
	 * evidence of a protocol era.
	 */
	private function get_metadata_version( array $message ): ?string {
		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();
		$meta   = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
		$value  = $meta['io.modelcontextprotocol/protocolVersion'] ?? null;

		return is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;
	}

	/**
	 * Create a transport-level unsupported-version error.
	 */
	private function unsupported_version( string $requested, array $supported, string $ref ): WP_Error {
		return new WP_Error(
			'mcp_unsupported_version',
			'Unsupported MCP protocol version.',
			array(
				'status'        => 400,
				'json_rpc_code' => -32022,
				'supported'     => $supported,
				'requested'     => $requested,
				'ref'           => $ref,
			)
		);
	}

	/**
	 * Reject a legacy transport header paired with a modern metadata envelope.
	 */
	private function conflicting_version_evidence( string $header_version, string $metadata_version, string $ref ): WP_Error {
		return new WP_Error(
			'mcp_protocol_version_mismatch',
			'Protocol version header and request metadata do not match.',
			array(
				'status'           => 400,
				'json_rpc_code'    => -32020,
				'header_version'   => $header_version,
				'metadata_version' => $metadata_version,
				'ref'              => $ref,
			)
		);
	}
}
