<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

/**
 * MCP Version Manager.
 *
 * Centralizes protocol version management for the MCP server.
 * Single source of truth for supported versions.
 *
 * @since 7.0.0
 */
class Mcp_Version_Manager {

	/**
	 * Supported protocol versions (newest first).
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private const SUPPORTED_VERSIONS = array( '2026-07-28', '2025-11-25', '2025-06-18', '2025-03-26' );

	/**
	 * Stateless per-request protocol revisions.
	 *
	 * @var array
	 */
	private const MODERN_VERSIONS = array( '2026-07-28' );

	/**
	 * Initialize-handshake protocol revisions (newest first).
	 *
	 * The three revisions share one tools-only wire shape over Streamable HTTP,
	 * so a client on any of them is answered with the revision it asked for.
	 * 2024-11-05 (HTTP+SSE with a GET stream) is not offered.
	 *
	 * @var array
	 */
	private const INITIALIZE_VERSIONS = array( '2025-11-25', '2025-06-18', '2025-03-26' );

	/**
	 * Revision assumed when an operational request carries no version header.
	 *
	 * The MCP-Protocol-Version header was introduced in 2025-06-18; the
	 * specification tells servers to assume 2025-03-26 when it is absent.
	 *
	 * @var string
	 */
	private const LEGACY_DEFAULT_VERSION = '2025-03-26';

	/**
	 * Default revision for modern internal callers.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private const DEFAULT_VERSION = '2026-07-28';

	/**
	 * Get the latest supported protocol version.
	 *
	 * @since 7.0.0
	 *
	 * @return string Latest protocol version.
	 */
	public static function get_latest_version(): string {
		return self::SUPPORTED_VERSIONS[0];
	}

	/**
	 * Get all supported protocol versions.
	 *
	 * @since 7.0.0
	 *
	 * @return array List of supported versions (newest first).
	 */
	public static function get_supported_versions(): array {
		return self::SUPPORTED_VERSIONS;
	}

	/**
	 * Get revisions advertised by the stateless discovery method.
	 *
	 * @return array
	 */
	public static function get_modern_versions(): array {
		return self::MODERN_VERSIONS;
	}

	/**
	 * Get revisions available through the initialize handshake.
	 *
	 * @return array
	 */
	public static function get_initialize_versions(): array {
		return self::INITIALIZE_VERSIONS;
	}

	/**
	 * Revision assumed for a headerless operational request.
	 *
	 * @return string
	 */
	public static function get_legacy_default_version(): string {
		return self::LEGACY_DEFAULT_VERSION;
	}

	/**
	 * Get the default version for backwards compatibility.
	 *
	 * @since 7.0.0
	 *
	 * @return string Default protocol version.
	 */
	public static function get_default_version(): string {
		return self::DEFAULT_VERSION;
	}

	/**
	 * Check if a protocol version is supported.
	 *
	 * @since 7.0.0
	 *
	 * @param string $version Version to check.
	 * @return bool True if supported, false otherwise.
	 */
	public static function is_supported( string $version ): bool {
		return in_array( $version, self::SUPPORTED_VERSIONS, true );
	}

	/**
	 * Check whether a revision uses stateless per-request metadata.
	 */
	public static function is_modern( string $version ): bool {
		return in_array( $version, self::MODERN_VERSIONS, true );
	}

	/**
	 * Check whether a revision uses the initialize handshake.
	 */
	public static function is_initialize_version( string $version ): bool {
		return in_array( $version, self::INITIALIZE_VERSIONS, true );
	}

	/**
	 * Negotiate an initialize-era request.
	 *
	 * The lifecycle contract requires the server to return its newest supported
	 * initialize revision when the requested revision is unavailable.
	 */
	public static function negotiate_initialize( string $client_version ): string {
		if ( self::is_initialize_version( $client_version ) ) {
			return $client_version;
		}

		return self::INITIALIZE_VERSIONS[0];
	}
}
