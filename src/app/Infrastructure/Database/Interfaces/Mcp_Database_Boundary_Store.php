<?php
/**
 * Database facts used to enforce the MCP table boundary.
 *
 * @package Uncanny_Automator
 * @since 8.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

interface Mcp_Database_Boundary_Store {

	/** @since 8.0.0 @return string|null The selected schema, or null when it cannot be read. */
	public function selected_database(): ?string;

	/** @since 8.0.0 @return string|null MySQL table-name mode, or null when unavailable. */
	public function table_name_mode(): ?string;

	/** @since 8.0.0 @return string|null Database server version, or null when unavailable. */
	public function server_version(): ?string;

	/** @since 8.0.0 @return string[]|null Current-session grants, or null when unavailable. */
	public function grants(): ?array;

	/** @since 8.0.0 @return string|null Partial-revoke mode, or null when unavailable. */
	public function partial_revokes(): ?string;

	/**
	 * @since 8.0.0
	 * @param string   $schema Database name.
	 * @param string[] $names Requested names.
	 * @param string   $prefix Site table prefix.
	 * @param bool     $ignore_case Whether the server ignores name case.
	 * @return array|null Metadata rows, or null on failure.
	 */
	public function table_boundaries( string $schema, array $names, string $prefix, bool $ignore_case ): ?array;

	/**
	 * @since 8.0.0
	 * @param string   $schema Database name.
	 * @param string   $prefix Site prefix, or empty to omit automatic discovery.
	 * @param string[] $extra Owner-approved names.
	 * @param string[] $restricted Network names excluded for this executor.
	 * @param bool     $network_main Whether this is the main network site.
	 * @param bool     $ignore_case Whether allowed names ignore case.
	 * @param bool     $schema_case Whether the schema ignores case.
	 * @param string   $search Literal name search.
	 * @param int      $limit Maximum rows to retrieve.
	 * @return array|null Metadata rows, or null on failure.
	 */
	public function table_page( string $schema, string $prefix, array $extra, array $restricted, bool $network_main, bool $ignore_case, bool $schema_case, string $search, int $limit ): ?array;
}
