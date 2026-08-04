<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Application\Mcp;

/**
 * Consumer-owned license port for the MCP SDK producer path.
 *
 * The MCP client needs the cached license snapshot plus stable identity
 * fields used to build the encrypted SDK payload.
 */
interface Mcp_License_Provider_Interface {

	/**
	 * Get cached license data with optional refresh semantics.
	 *
	 * @param bool $force_refresh Whether to bypass the shared transient cache.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_license_data( bool $force_refresh = false ): ?array;

	/**
	 * Get the license key.
	 *
	 * @return string
	 */
	public function get_key(): string;

	/**
	 * Get the site name.
	 *
	 * @return string
	 */
	public function get_site_name(): string;

	/**
	 * Get the item name.
	 *
	 * @return string
	 */
	public function get_item_name(): string;
}
