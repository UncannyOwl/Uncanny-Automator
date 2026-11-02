<?php
/**
 * Policy for the MCP database table boundary.
 *
 * @package Uncanny_Automator
 * @since 8.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Mcp;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Mcp_Database_Boundary_Store;
use Uncanny_Automator\App\Infrastructure\Database\Database;

final class Database_Boundary_Service {

	private Mcp_Database_Boundary_Store $store;
	private string $schema;
	private string $prefix;
	private array $extra;
	private bool $ignore_case;
	private bool $known_mode;

	/**
	 * @since 8.0.0
	 * @param Mcp_Database_Boundary_Store $store Database metadata source.
	 * @param string $schema WordPress database name.
	 * @param string $prefix Site table prefix.
	 * @param string[] $extra Owner-approved names.
	 * @param bool $ignore_case Whether database names ignore case.
	 * @param bool $known_mode Whether the name mode is known.
	 */
	private function __construct( Mcp_Database_Boundary_Store $store, string $schema, string $prefix, array $extra, bool $ignore_case, bool $known_mode ) {
		$this->store       = $store;
		$this->schema      = $schema;
		$this->prefix      = $prefix;
		$this->extra       = $extra;
		$this->ignore_case = $ignore_case;
		$this->known_mode  = $known_mode;
	}

	/**
	 * @since 8.0.0
	 * @param string   $schema WordPress database name.
	 * @param string   $prefix Site table prefix.
	 * @param string[] $extra Exact owner-approved names.
	 * @param bool     $ignore_case Whether names ignore case.
	 * @param bool     $known_mode Whether the name mode is known.
	 * @return self
	 */
	public static function create( string $schema, string $prefix, array $extra, bool $ignore_case, bool $known_mode ): self {
		return new self( Database::get_mcp_database_boundary_store(), $schema, $prefix, $extra, $ignore_case, $known_mode );
	}

	/**
	 * @since 8.0.0
	 * @param Mcp_Database_Boundary_Store $store Database metadata source.
	 * @param string   $schema WordPress database name.
	 * @param string   $prefix Site table prefix.
	 * @param string[] $extra Exact owner-approved names.
	 * @param bool     $ignore_case Whether names ignore case.
	 * @param bool     $known_mode Whether the name mode is known.
	 * @return self
	 */
	public static function create_with_dependencies( Mcp_Database_Boundary_Store $store, string $schema, string $prefix, array $extra, bool $ignore_case, bool $known_mode ): self {
		return new self( $store, $schema, $prefix, $extra, $ignore_case, $known_mode );
	}

	/** @since 8.0.0 @return string|null */
	public static function current_table_name_mode(): ?string {
		return Database::get_mcp_database_boundary_store()->table_name_mode();
	}

	/**
	 * @since 8.0.0
	 * @return bool
	 */
	public function has_supported_site_prefix(): bool {
		$prefix = $this->prefix;
		return '' === $prefix || 1 === preg_match( '/^[A-Za-z0-9_]+$/D', $prefix );
	}


	/**
	 * Refuse reads when a drop-in or plugin selected a different database.
	 *
	 * @since 8.0.0
	 * @return bool
	 */
	public function has_expected_selected_database(): bool {
		$expected = $this->schema;
		if ( '' === $expected ) {
			return false;
		}
		$selected = $this->store->selected_database();
		if ( null === $selected || '' === $selected ) {
			return false;
		}
		if ( $selected === $expected ) {
			return true;
		}
		return $this->known_mode && $this->ignore_case && 0 === strcasecmp( $selected, $expected );
	}


	/**
	 * Qualify a sanitized table name with the database used for its checks.
	 *
	 * @since 8.0.0
	 * @param string $table Table name.
	 * @return string
	 */
	public function qualify_table_name( string $table ): string {
		return '`' . str_replace( '`', '``', $this->schema ) . '`.`' . str_replace( '`', '``', $table ) . '`';
	}


	/**
	 * Match a table to an exact name approved by the single-site owner.
	 *
	 * @since 8.0.0
	 * @param string $table Database table or view name.
	 * @return bool
	 */
	public function is_owner_approved_table( string $table ): bool {
		$extra = $this->extra;
		if ( $this->ignore_case ) {
			return in_array( strtolower( $table ), array_map( 'strtolower', $extra ), true );
		}
		return in_array( $table, $extra, true );
	}

	/**
	 * @since 8.0.0
	 * @param string[] $names Requested names.
	 * @return bool
	 */
	public function requires_discovery( array $names ): bool {
		foreach ( $names as $name ) {
			if ( ! $this->is_owner_approved_table( (string) $name ) ) {
				return true;
			}
		}
		return false;
	}

	/** @since 8.0.0 @return bool */
	public function can_discover(): bool {
		return $this->known_mode && $this->has_supported_site_prefix() && $this->has_schema_table_visibility();
	}

	/** @since 8.0.0 @return string */
	public function discovery_failure_message(): string {
		if ( ! $this->known_mode || ! $this->has_supported_site_prefix() ) {
			return 'The database table boundary cannot be checked. Please check the database connection and try again.';
		}
		return $this->schema_visibility_message();
	}


	/**
	 * Check that the current database identity can see every table in the schema.
	 *
	 * INFORMATION_SCHEMA hides tables without privileges. Without schema-wide
	 * visibility a missing options table cannot prove there is no nested site.
	 * Grant results can contain password hashes and must never be returned or logged.
	 *
	 * @since 8.0.0
	 * @return bool
	 */
	public function has_schema_table_visibility(): bool {
		$version = $this->store->server_version();
		if ( null === $version || '' === $version ) {
			return false;
		}
		if ( false !== stripos( $version, 'mariadb' ) ) {
			if ( 1 !== preg_match( '/(\d+)\.(\d+)\.\d+-MariaDB/i', $version, $match ) || (int) $match[1] > 13 || ( 13 === (int) $match[1] && (int) $match[2] >= 1 ) ) {
				return false;
			}
		} elseif ( 1 !== preg_match( '/^\d+\.\d+\.\d+/', $version ) || 1 === preg_match( '/tidb|vitess|oceanbase/i', $version ) ) {
			return false;
		}

		$grants = $this->store->grants();
		if ( null === $grants || empty( $grants ) ) {
			return false;
		}

		$granted = false;
		$revoked = false;
		foreach ( $grants as $grant ) {
			if ( ! is_string( $grant ) || 1 === preg_match( '/^DENY\b/i', $grant ) ) {
				return false;
			}
			if ( 1 === preg_match( '/^REVOKE\b/i', $grant ) ) {
				if ( 1 !== preg_match( '/^REVOKE\s+([A-Z, ]+)\s+ON\s+(\S+)\s+FROM\s+/i', $grant, $match ) ) {
					return false;
				}
				if ( $this->grant_has_select( $match[1] ) && $this->grant_covers_schema( $match[2] ) ) {
					$revoked = true;
				}
				continue;
			}
			if ( 1 === preg_match( '/^GRANT\s+([A-Z, ]+)\s+ON\s+(\S+)\s+TO\s+/i', $grant, $match )
				&& $this->grant_has_select( $match[1] )
				&& $this->grant_covers_schema( $match[2], $version ) ) {
				$granted = true;
			}
		}
		return $granted && ! $revoked;
	}


	/**
	 * @since 8.0.0
	 * @param string $privileges Comma-separated privileges from SHOW GRANTS.
	 * @return bool
	 */
	private function grant_has_select( string $privileges ): bool {
		$values = array_map( 'trim', explode( ',', strtoupper( $privileges ) ) );
		return in_array( 'SELECT', $values, true ) || in_array( 'ALL PRIVILEGES', $values, true ) || in_array( 'ALL', $values, true );
	}


	/**
	 * Accept global or matching database grants; ambiguous patterns fail closed.
	 *
	 * @since 8.0.0
	 * @param string $scope   Scope from SHOW GRANTS.
	 * @param string $version Server version for wildcard grants, or empty for a revoke.
	 * @return bool
	 */
	public function grant_covers_schema( string $scope, string $version = '' ): bool {
		if ( '*.*' === $scope ) {
			return true;
		}
		if ( 1 !== preg_match( '/^`((?:``|[^`])+)`\.\*$/D', $scope, $match ) ) {
			return false;
		}
		return $this->grant_pattern_matches_schema( str_replace( '``', '`', $match[1] ), $this->schema, $version );
	}


	/**
	 * Match MySQL's database-grant wildcard syntax only when the server uses it.
	 *
	 * @since 8.0.0
	 * @param string $pattern Database scope in SHOW GRANTS.
	 * @param string $schema Actual database name.
	 * @param string $version Database server version, or empty for a revoke.
	 * @return bool
	 */
	private function grant_pattern_matches_schema( string $pattern, string $schema, string $version ): bool {
		$same_name = $this->ignore_case ? 0 === strcasecmp( $pattern, $schema ) : $pattern === $schema;
		if ( false === strpbrk( $pattern, '\\_%' ) ) {
			return $same_name;
		}
		// MySQL partial revocations name a schema literally, not as a pattern.
		// An escaped revoke is ambiguous, so conservatively treat it as relevant.
		if ( '' === $version ) {
			return false !== strpos( $pattern, '\\' ) || $same_name;
		}
		if ( false === stripos( $version, 'mariadb' ) ) {
			if ( 1 !== preg_match( '/^\d+\.\d+\.\d+/', $version, $match ) ) {
				return false;
			}
			if ( version_compare( $match[0], '8.0.16', '>=' ) ) {
				$partial_revokes = $this->store->partial_revokes();
				if ( null === $partial_revokes ) {
					return false;
				}
				if ( in_array( strtoupper( (string) $partial_revokes ), array( '1', 'ON' ), true ) ) {
					return false === strpos( $pattern, '\\' ) && $same_name;
				}
				if ( ! in_array( strtoupper( (string) $partial_revokes ), array( '0', 'OFF' ), true ) ) {
					return false;
				}
			}
		}
		if ( 1 === preg_match( '/[^\x20-\x7e]/', $pattern . $schema ) ) {
			return false;
		}
		$regex  = '';
		$length = strlen( $pattern );
		for ( $index = 0; $index < $length; ++$index ) {
			$character = $pattern[ $index ];
			if ( '\\' === $character ) {
				++$index;
				if ( ! isset( $pattern[ $index ] ) || ! in_array( $pattern[ $index ], array( '\\', '_', '%' ), true ) ) {
					return false;
				}
				$regex .= preg_quote( $pattern[ $index ], '~' );
			} elseif ( '%' === $character || '_' === $character ) {
				$regex .= '%' === $character ? '.*' : '.';
			} else {
				$regex .= preg_quote( $character, '~' );
			}
		}
		$flags = $this->ignore_case ? 'si' : 's';
		return 1 === preg_match( '~\A' . $regex . '\z~' . $flags, $schema );
	}


	/**
	 * @since 8.0.0
	 * @return string
	 */
	public function schema_visibility_message(): string {
		return 'Automator cannot verify database table visibility. Ask your hosting provider to check schema-wide SELECT access for the WordPress database user. On a single site, you can still approve exact table or view names in Site tools settings.';
	}

	/**
	 * @since 8.0.0
	 * @param string $type Database object type.
	 * @param string $is_nested Nested-installation flag.
	 * @param bool $approved Whether the owner approved this exact name.
	 * @return bool
	 */
	public function permits_type( string $type, string $is_nested, bool $approved ): bool {
		return in_array( $type, array( 'BASE TABLE', 'VIEW' ), true ) && ( $approved || ( 'BASE TABLE' === $type && '0' === $is_nested ) );
	}

	/**
	 * @since 8.0.0
	 * @param string[] $names Names to inspect.
	 * @return array|null
	 */
	public function table_boundaries( array $names ): ?array {
		return $this->store->table_boundaries( $this->schema, $names, $this->prefix, $this->ignore_case );
	}

	/**
	 * @since 8.0.0
	 * @param string $prefix Prefix to discover, or empty for owner-approved names only.
	 * @param string[] $restricted Network tables to hide.
	 * @param bool $network_main Whether the current site is the main network site.
	 * @param bool $ignore_case Whether allowed names ignore case.
	 * @param string $search Literal search text.
	 * @param int $limit Row limit.
	 * @return array|null
	 */
	public function table_page( string $prefix, array $restricted, bool $network_main, bool $ignore_case, string $search, int $limit ): ?array {
		return $this->store->table_page( $this->schema, $prefix, $this->extra, $restricted, $network_main, $ignore_case, $this->ignore_case, $search, $limit );
	}
}
