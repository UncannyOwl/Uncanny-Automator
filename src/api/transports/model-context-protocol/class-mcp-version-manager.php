<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

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
	private const SUPPORTED_VERSIONS = array(
		'2025-11-25',
		'2025-06-18',
		'2025-03-26',
	);

	/**
	 * Default version for backwards compatibility when header is missing.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private const DEFAULT_VERSION = '2025-03-26';

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
	 * Check if a client version is compatible with server.
	 *
	 * Currently requires exact match, but could implement
	 * semantic versioning compatibility in the future.
	 *
	 * @since 7.0.0
	 *
	 * @param string $client_version Client's requested version.
	 * @return bool True if compatible, false otherwise.
	 */
	public static function is_compatible( string $client_version ): bool {
		return self::is_supported( $client_version );
	}

	/**
	 * Negotiate the best version between client and server.
	 *
	 * Returns the client's version if supported, otherwise null.
	 *
	 * @since 7.0.0
	 *
	 * @param string $client_version Client's requested version.
	 * @return string|null Negotiated version or null if incompatible.
	 */
	public static function negotiate( string $client_version ): ?string {
		if ( self::is_supported( $client_version ) ) {
			return $client_version;
		}
		return null;
	}
}
