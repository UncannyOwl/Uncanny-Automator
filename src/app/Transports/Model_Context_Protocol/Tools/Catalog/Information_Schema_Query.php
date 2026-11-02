<?php
/**
 * Information Schema Query Trait.
 *
 * Shared helpers for MySQL tools that introspect information_schema: schema
 * name resolution, query failure detection and the per-site table boundary
 * on WordPress networks.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Application\Mcp\Database_Boundary_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Database_Table_Access_Settings;

/**
 * Trait Information_Schema_Query.
 */
trait Information_Schema_Query {

	/** @var bool|null Whether the connected MySQL server resolves table names without case. */
	private ?bool $case_insensitive_table_names = null;
	/** @var bool|null Whether the server reported a valid table-name mode. */
	private ?bool $known_table_name_mode = null;

	/**
	 * Resolve the schema (database) name that holds the WordPress tables.
	 *
	 * Database drop-ins such as HyperDB may leave DB_NAME undefined, so the
	 * wpdb name is used first and the constant only as a fallback. Reads are
	 * refused if DATABASE() reports a different selected database.
	 *
	 * @return string Schema name, or an empty string when none is known.
	 */
	private function get_schema_name(): string {
		global $wpdb;

		$name = isset( $wpdb->dbname ) && is_string( $wpdb->dbname ) ? $wpdb->dbname : '';

		if ( '' === $name && defined( 'DB_NAME' ) ) {
			$name = (string) DB_NAME;
		}

		return $name;
	}

	/**
	 * Prepare an information_schema query for one or more table names.
	 *
	 * The plain schema equality lets the server read only this site's database
	 * metadata; the BINARY comparison keeps the exact match. A BINARY or LOWER()
	 * comparison alone makes the server read the metadata of every database.
	 *
	 * @param string $query_template SQL template containing {table_placeholders}.
	 * @param array  $table_names    Sanitized table names.
	 * @return string
	 */
	private function prepare_information_schema_query( string $query_template, array $table_names ): string {
		global $wpdb;

		$ignore_case        = $this->table_names_ignore_case();
		$table_placeholders = implode( ', ', array_fill( 0, count( $table_names ), $ignore_case ? 'LOWER(%s)' : '%s' ) );
		$table_column       = $ignore_case ? 'BINARY LOWER(TABLE_NAME)' : 'BINARY TABLE_NAME';
		$schema_comparison  = 'TABLE_SCHEMA = %s AND ' . ( $ignore_case ? 'BINARY LOWER(TABLE_SCHEMA) = BINARY LOWER(%s)' : 'BINARY TABLE_SCHEMA = BINARY %s' );
		$query              = str_replace( 'TABLE_SCHEMA = %s', $schema_comparison, $query_template );
		$query              = str_replace( 'TABLE_NAME IN ({table_placeholders})', $table_column . ' IN ({table_placeholders})', $query );
		$query              = str_replace( '{table_placeholders}', $table_placeholders, $query );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholder-expanded template is prepared before it is returned.
		return $wpdb->prepare(
			$query,
			array_merge( array( $this->get_schema_name(), $this->get_schema_name() ), array_values( $table_names ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/**
	 * Compare discovered table names using the server's table-name behavior.
	 *
	 * @since 8.0.0
	 * @param string[] $requested Requested names.
	 * @param string[] $found Names returned by the database.
	 * @return string[]
	 */
	private function missing_table_names( array $requested, array $found ): array {
		return $this->table_names_ignore_case() ? array_udiff( $requested, $found, 'strcasecmp' ) : array_diff( $requested, $found );
	}

	/**
	 * Return an error response when the last database query failed.
	 *
	 * wpdb returns an empty array rather than null when SQL fails (unknown
	 * column, ONLY_FULL_GROUP_BY, collation mismatch), so "no rows" and "the
	 * query failed" are only distinguishable through last_error. Call this
	 * immediately after each query, before any other query resets it.
	 *
	 * @return array|null Error response, or null when the query succeeded.
	 */
	private function get_query_failure_response(): ?array {
		global $wpdb;

		if ( '' === (string) $wpdb->last_error ) {
			return null;
		}

		return Json_Rpc_Response::create_error_response( 'Query failed: ' . $wpdb->last_error );
	}

	/**
	 * Whether this site is part of a WordPress network.
	 *
	 * Seam for tests, which cannot switch a single-site install to multisite.
	 *
	 * @return bool
	 */
	protected function is_network_scoped(): bool {
		return is_multisite();
	}

	/**
	 * Table prefix of the current site (includes the blog ID on subsites).
	 *
	 * @return string
	 */
	protected function get_site_table_prefix(): string {
		global $wpdb;

		return (string) $wpdb->prefix;
	}

	/**
	 * Table prefix shared by the whole network.
	 *
	 * @return string
	 */
	protected function get_base_table_prefix(): string {
		global $wpdb;

		return (string) $wpdb->base_prefix;
	}

	/**
	 * @since 8.0.0
	 * @param string[] $extra Exact owner-approved names.
	 * @return Database_Boundary_Service
	 */
	private function database_boundary_service( array $extra = array() ): Database_Boundary_Service {
		$ignore_case = $this->table_names_ignore_case();
		return Database_Boundary_Service::create(
			$this->get_schema_name(),
			$this->get_site_table_prefix(),
			$extra,
			$ignore_case,
			$this->has_known_table_name_mode()
		);
	}

	/** @since 8.0.0 @return bool */
	private function has_expected_selected_database(): bool {
		return $this->database_boundary_service()->has_expected_selected_database();
	}

	/**
	 * @since 8.0.0
	 * @param string $table Table name.
	 * @return string
	 */
	private function qualify_table_name( string $table ): string {
		return $this->database_boundary_service()->qualify_table_name( $table );
	}

	/**
	 * Network tables that the authenticated executor may not inspect.
	 *
	 * The main-site prefix also matches shared users and network metadata. A
	 * site's database consent, or an Agent credential, does not make its admin
	 * a network administrator. Use the executor rather than ambient WP identity.
	 *
	 * @param User_Context $user_context Authenticated caller.
	 * @return string[]
	 */
	private function get_restricted_network_tables( User_Context $user_context ): array {
		global $wpdb;

		if ( ! $this->is_network_scoped() || is_super_admin( $user_context->get_executor() ) ) {
			return array();
		}

		// wpdb includes both global_tables and ms_global_tables on a network,
		// as well as CUSTOM_USER_TABLE / CUSTOM_USER_META_TABLE mappings.
		return array_values( $wpdb->tables( 'global' ) );
	}

	/**
	 * Whether a table may be read from the current site.
	 *
	 * A single site sees tables with its prefix and owner-approved extras. On a
	 * network each site only sees the tables that carry its own prefix. The main site's prefix
	 * is the base prefix that every subsite prefix extends, so the main site
	 * additionally excludes "{base}{blog_id}_" tables belonging to subsites.
	 * Shared network tables additionally require a super-admin executor. Table
	 * type and nested-installation checks are applied after this name check.
	 *
	 * @param string       $table        Table name.
	 * @param User_Context $user_context Authenticated caller.
	 * @return bool
	 */
	private function is_table_available_on_this_site( string $table, User_Context $user_context ): bool {
		if ( ! $this->is_network_scoped() ) {
			$prefix = $this->get_site_table_prefix();
			$extra  = $this->get_extra_tables();
			if ( ( '' !== $prefix && 0 === strpos( $table, $prefix ) ) || in_array( $table, $extra, true ) ) {
				return true;
			}

			// Fold case only when MySQL itself resolves both names to the same table.
			if ( ! $this->table_names_ignore_case() ) {
				return false;
			}
			$table = strtolower( $table );
			return ( '' !== $prefix && 0 === strpos( $table, strtolower( $prefix ) ) ) || in_array( $table, array_map( 'strtolower', $extra ), true );
		}

		// lower_case_table_names=1/2 lets MySQL resolve differently cased names
		// to the same table. Such aliases must not bypass network authority.
		$restricted = array_map( 'strtolower', $this->get_restricted_network_tables( $user_context ) );
		if ( in_array( strtolower( $table ), $restricted, true ) ) {
			return false;
		}

		$prefix = $this->get_site_table_prefix();

		if ( '' === $prefix || 0 !== strpos( $table, $prefix ) ) {
			return false;
		}

		$base = $this->get_base_table_prefix();

		if ( $prefix === $base && 1 === preg_match( '/^' . preg_quote( $base, '/' ) . '[0-9]+_/', $table ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Extra tables may only extend a single site's boundary.
	 *
	 * @since 8.0.0
	 * @return string[]
	 */
	private function get_extra_tables(): array {
		if ( $this->is_network_scoped() ) {
			return array();
		}

		return ( new Database_Table_Access_Settings() )->tables();
	}

	/**
	 * Table names are case-insensitive only when the server says so.
	 *
	 * A missing or invalid answer fails closed by requiring exact case.
	 *
	 * @since 8.0.0
	 * @return bool
	 */
	protected function table_names_ignore_case(): bool {
		if ( null === $this->case_insensitive_table_names ) {
			$value                              = Database_Boundary_Service::current_table_name_mode();
			$this->known_table_name_mode        = null !== $value && in_array( $value, array( '0', '1', '2' ), true );
			$this->case_insensitive_table_names = in_array( $value, array( '1', '2' ), true );
		}

		return $this->case_insensitive_table_names;
	}

	/**
	 * Refuse metadata access if the database cannot report its name rules.
	 *
	 * @since 8.0.0
	 * @return bool
	 */
	private function has_known_table_name_mode(): bool {
		$this->table_names_ignore_case();
		if ( null === $this->known_table_name_mode ) {
			$value                       = Database_Boundary_Service::current_table_name_mode();
			$this->known_table_name_mode = null !== $value && in_array( $value, array( '0', '1', '2' ), true );
		}
		return $this->known_table_name_mode;
	}

	/**
	 * Return an error response when any table lies outside this site's boundary.
	 *
	 * @param string[]     $table_names  Table names the caller wants to read.
	 * @param User_Context $user_context Authenticated caller.
	 * @return array|null Error response, or null when every table is available.
	 */
	private function get_table_boundary_response( array $table_names, User_Context $user_context ): ?array {
		if ( ! $this->has_expected_selected_database() ) {
			return Json_Rpc_Response::create_error_response( 'The WordPress database connection is pointing to an unexpected database. Ask your hosting provider to check the connection, then try again.' );
		}
		$blocked = array();

		foreach ( $table_names as $table ) {
			if ( ! $this->is_table_available_on_this_site( (string) $table, $user_context ) ) {
				$blocked[] = (string) $table;
			}
		}

		if ( empty( $blocked ) ) {
			if ( '' === $this->get_schema_name() ) {
				return Json_Rpc_Response::create_error_response( 'The database table boundary cannot be checked. Please check the database connection and try again.' );
			}
			$service = $this->database_boundary_service( $this->get_extra_tables() );
			if ( $service->requires_discovery( $table_names ) && ! $service->can_discover() ) {
				return Json_Rpc_Response::create_error_response( $service->discovery_failure_message() );
			}
			$rows = $service->table_boundaries( $table_names );
			if ( null === $rows ) {
				return Json_Rpc_Response::create_error_response( 'Query failed: database table metadata is unavailable.' );
			}
			foreach ( $rows as $row ) {
				if ( ! $service->permits_type( $row['type'], $row['is_nested'], $service->is_owner_approved_table( $row['name'] ) ) ) {
					$blocked[] = $row['name'];
				}
			}
			if ( empty( $blocked ) ) {
				return null;
			}
		}

		if ( ! $this->is_network_scoped() ) {
			return Json_Rpc_Response::create_error_response(
				sprintf(
					'Tables not available on this site: %s. Database tools can read base tables starting with "%s", except those of another WordPress installation (a group with its own options and posts tables), and exact tables or views the site owner approves in Site tools settings.',
					implode( ', ', $blocked ),
					$this->get_site_table_prefix()
				)
			);
		}

		return Json_Rpc_Response::create_error_response(
			sprintf(
				'Tables not available on this site: %s. On a WordPress network each site can read only its own base tables, which start with "%s"; tables of another WordPress installation (a group with its own options and posts tables) and views are excluded. Shared network tables additionally require a network administrator.',
				implode( ', ', $blocked ),
				$this->get_site_table_prefix()
			)
		);
	}
}
