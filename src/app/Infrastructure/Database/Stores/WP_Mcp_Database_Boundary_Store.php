<?php
/**
 * WordPress database metadata for the MCP table boundary.
 *
 * @package Uncanny_Automator
 * @since 8.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Mcp_Database_Boundary_Store;

final class WP_Mcp_Database_Boundary_Store implements Mcp_Database_Boundary_Store {

	/** @since 8.0.0 @var \wpdb */
	private $wpdb;

	/** @since 8.0.0 @param \wpdb $wpdb WordPress database connection. */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}
	/**
	 * Selected database.
	 *
	 * @return ?
	 */
	public function selected_database(): ?string {
		return $this->read_scalar( 'SELECT DATABASE()' );
	}
	/**
	 * Table name mode.
	 *
	 * @return ?
	 */
	public function table_name_mode(): ?string {
		return $this->read_scalar( 'SELECT @@lower_case_table_names' );
	}
	/**
	 * Server version.
	 *
	 * @return ?
	 */
	public function server_version(): ?string {
		return $this->read_scalar( 'SELECT VERSION()' );
	}
	/**
	 * Partial revokes.
	 *
	 * @return ?
	 */
	public function partial_revokes(): ?string {
		return $this->read_scalar( 'SELECT @@partial_revokes' );
	}
	/**
	 * Grants.
	 *
	 * @return ?
	 */
	public function grants(): ?array {
		$grants = $this->wpdb->get_col( 'SHOW GRANTS' );
		$failed = '' !== (string) $this->wpdb->last_error;
		// MariaDB may include password hashes in SHOW GRANTS; do not retain its last result.
		$this->wpdb->flush();
		return ! $failed && is_array( $grants ) ? $grants : null;
	}
	/**
	 * Table boundaries.
	 *
	 * @param string $schema The schema.
	 * @param array $names The name.
	 * @param string $prefix The prefix.
	 * @param bool $ignore_case The ignore case.
	 * @return ?
	 */
	public function table_boundaries( string $schema, array $names, string $prefix, bool $ignore_case ): ?array {
		$installations = $this->installation_prefixes( $schema, $prefix, $ignore_case, false );
		if ( null === $installations ) {
			return null;
		}
		$params       = array();
		$scope        = $this->schema_scope( 'candidate', $schema, $ignore_case, $params );
		$folded       = $ignore_case ? 'LOWER(candidate.TABLE_NAME)' : 'candidate.TABLE_NAME';
		$placeholders = implode( ', ', array_fill( 0, count( $names ), $ignore_case ? 'LOWER(%s)' : '%s' ) );
		$params       = array_merge( $params, array_values( $names ) );
		$query        = 'SELECT candidate.TABLE_NAME, candidate.TABLE_TYPE, ' . $folded . ' AS folded_name FROM information_schema.TABLES candidate WHERE ' . $scope . ' AND BINARY ' . $folded . ' IN (' . $placeholders . ')';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Live information_schema read, must not be cached. The SQL is assembled from literal fragments only and every value is a placeholder.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $query, $params ) );
		if ( '' !== (string) $this->wpdb->last_error || ! is_array( $rows ) ) {
			return null;
		}
		$result = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $row->TABLE_NAME, $row->TABLE_TYPE, $row->folded_name ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return null;
			}
			$result[] = array(
				'name'      => (string) $row->TABLE_NAME, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'type'      => (string) $row->TABLE_TYPE, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'is_nested' => $this->belongs_to_installation( (string) $row->folded_name, $installations ) ? '1' : '0',
			);
		}
		return $result;
	}
	/**
	 * Table page.
	 *
	 * @param string $schema The schema.
	 * @param string $prefix The prefix.
	 * @param array $extra The extra.
	 * @param array $restricted The restricted.
	 * @param bool $network_main The network main.
	 * @param bool $ignore_case The ignore case.
	 * @param bool $schema_case The schema case.
	 * @param string $search The search term.
	 * @param int $limit The limit.
	 * @return ?
	 */
	public function table_page( string $schema, string $prefix, array $extra, array $restricted, bool $network_main, bool $ignore_case, bool $schema_case, string $search, int $limit ): ?array {
		$params  = array();
		$query   = 'SELECT candidate.TABLE_NAME, candidate.TABLE_ROWS, candidate.TABLE_TYPE FROM information_schema.TABLES candidate WHERE '
			. $this->schema_scope( 'candidate', $schema, $schema_case, $params );
		$allowed = array();
		if ( '' !== $prefix ) {
			$installations = $this->installation_prefixes( $schema, $prefix, $schema_case, $network_main );
			if ( null === $installations ) {
				return null;
			}
			$name         = $ignore_case ? 'LOWER(candidate.TABLE_NAME)' : 'candidate.TABLE_NAME';
			$prefix_match = 'BINARY LEFT(' . $name . ', CHAR_LENGTH(%s)) = BINARY %s';
			$params[]     = $ignore_case ? strtolower( $prefix ) : $prefix;
			$params[]     = $ignore_case ? strtolower( $prefix ) : $prefix;
			$allowed[]    = "(candidate.TABLE_TYPE = 'BASE TABLE' AND " . $prefix_match . $this->outside_installations( 'candidate', $installations, $schema_case, $params ) . ')';
		}
		if ( ! empty( $extra ) ) {
			$allowed[] = "(candidate.TABLE_TYPE IN ('BASE TABLE', 'VIEW') AND " . ( $ignore_case ? 'BINARY LOWER(candidate.TABLE_NAME)' : 'BINARY candidate.TABLE_NAME' ) . ' IN (' . implode( ', ', array_fill( 0, count( $extra ), '%s' ) ) . '))';
			$params    = array_merge( $params, $ignore_case ? array_map( 'strtolower', $extra ) : $extra );
		}
		$query .= ' AND (' . ( empty( $allowed ) ? '1 = 0' : implode( ' OR ', $allowed ) ) . ')';
		if ( $network_main && 1 === preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ) {
			$query   .= ' AND candidate.TABLE_NAME NOT REGEXP %s';
			$params[] = '^' . $prefix . '[0-9]+_';
		}
		if ( ! empty( $restricted ) ) {
			$query .= ' AND candidate.TABLE_NAME NOT IN (' . implode( ', ', array_fill( 0, count( $restricted ), '%s' ) ) . ')';
			$params = array_merge( $params, $restricted );
		}
		if ( '' !== $search ) {
			$query   .= ' AND LOCATE(%s, candidate.TABLE_NAME) > 0';
			$params[] = $search;
		}
		$query   .= ' ORDER BY candidate.TABLE_NAME ASC LIMIT %d';
		$params[] = $limit;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Live information_schema read, must not be cached. The SQL is assembled from literal fragments only and every value is a placeholder.
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $query, $params ) );
		if ( '' !== (string) $this->wpdb->last_error || ! is_array( $rows ) ) {
			return null;
		}
		$result = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $row->TABLE_NAME, $row->TABLE_TYPE ) || ! property_exists( $row, 'TABLE_ROWS' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return null;
			}
			$result[] = array(
				'name' => (string) $row->TABLE_NAME, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'type' => (string) $row->TABLE_TYPE, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'rows' => (int) $row->TABLE_ROWS, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			);
		}
		return $result;
	}

	/**
	 * @since 8.0.0
	 * @param string $query Scalar metadata query.
	 * @return string|null
	 */
	private function read_scalar( string $query ): ?string {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Live metadata read, must not be cached. Only fixed metadata queries are passed by this store.
		$value = $this->wpdb->get_var( $query );
		return '' === (string) $this->wpdb->last_error && is_scalar( $value ) ? (string) $value : null;
	}

	/**
	 * Limit a metadata read to this site's database.
	 *
	 * The plain equality is what lets the server read one database's metadata:
	 * MySQL 8 looks the schema up by name, and MySQL 5.7 and MariaDB open only
	 * that database. A BINARY or LOWER() comparison alone cannot be used that
	 * way, so the server reads the metadata of every database it hosts. The
	 * second comparison keeps the exact match where information_schema compares
	 * names without case.
	 *
	 * @since 8.0.0
	 * @param string $alias Metadata row alias.
	 * @param string $schema Database name.
	 * @param bool   $schema_case Whether the server ignores name case.
	 * @param array  $params Parameters to append.
	 * @return string
	 */
	private function schema_scope( string $alias, string $schema, bool $schema_case, array &$params ): string {
		$params[] = $schema;
		$params[] = $schema;
		return $alias . '.TABLE_SCHEMA = %s AND '
			. ( $schema_case ? 'BINARY LOWER(' . $alias . '.TABLE_SCHEMA) = BINARY LOWER(%s)' : 'BINARY ' . $alias . '.TABLE_SCHEMA = BINARY %s' );
	}

	/**
	 * Name prefixes of the other WordPress installations in this site's database.
	 *
	 * Another installation has both core options and posts tables under a longer
	 * prefix than the site's; a plugin's own options table (such as uap_options)
	 * does not make one. One scoped read collects both kinds of table, so the
	 * check costs the same whatever the number of candidate tables. Names come
	 * back folded by the server when it ignores case, so every later comparison
	 * is exact.
	 *
	 * On a network's main site the subsites' numbered tables are left out of the
	 * listing by name, so their options and posts tables are not read: a large
	 * network would otherwise add one prefix per subsite to the listing.
	 *
	 * @since 8.0.0
	 * @param string $schema Database name.
	 * @param string $prefix Site prefix, or empty when nothing is discovered by prefix.
	 * @param bool   $ignore_case Whether the server ignores name case.
	 * @param bool   $network_main Whether the listing leaves out the network's subsite tables.
	 * @return string[]|null Installation prefixes, or null when the read failed.
	 */
	private function installation_prefixes( string $schema, string $prefix, bool $ignore_case, bool $network_main ): ?array {
		if ( '' === $prefix ) {
			return array();
		}
		$params     = array();
		$name       = $ignore_case ? 'LOWER(nested.TABLE_NAME)' : 'nested.TABLE_NAME';
		$comparison = $ignore_case ? strtolower( $prefix ) : $prefix;
		$query      = 'SELECT ' . $name . ' AS folded_name FROM information_schema.TABLES nested WHERE '
			. $this->schema_scope( 'nested', $schema, $ignore_case, $params )
			. " AND nested.TABLE_TYPE IN ('BASE TABLE', 'VIEW')"
			. ' AND BINARY LEFT(' . $name . ', CHAR_LENGTH(%s)) = BINARY %s'
			. " AND ((BINARY RIGHT(" . $name . ", 7) = BINARY 'options' AND CHAR_LENGTH(nested.TABLE_NAME) > CHAR_LENGTH(%s) + 7)"
			. " OR BINARY RIGHT(" . $name . ", 5) = BINARY 'posts')";
		$params[]   = $comparison;
		$params[]   = $comparison;
		$params[]   = $comparison;
		if ( $network_main && 1 === preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ) {
			$query   .= ' AND nested.TABLE_NAME NOT REGEXP %s';
			$params[] = '^' . $prefix . '[0-9]+_';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Live information_schema read, must not be cached. The SQL is assembled from literal fragments only and every value is a placeholder.
		$names = $this->wpdb->get_col( $this->wpdb->prepare( $query, $params ) );
		if ( '' !== (string) $this->wpdb->last_error || ! is_array( $names ) ) {
			return null;
		}
		$options = array();
		$posts   = array();
		foreach ( $names as $folded ) {
			$folded = (string) $folded;
			if ( 'options' === substr( $folded, -7 ) ) {
				$options[] = substr( $folded, 0, -7 );
				continue;
			}
			$posts[ $folded ] = true;
		}
		$installations = array();
		foreach ( $options as $installation ) {
			if ( isset( $posts[ $installation . 'posts' ] ) ) {
				$installations[ $installation ] = $installation;
			}
		}
		return array_values( $installations );
	}

	/**
	 * Whether a folded table name starts with an installation prefix.
	 *
	 * @since 8.0.0
	 * @param string   $folded Table name, folded as the installation prefixes are.
	 * @param string[] $installations Installation prefixes.
	 * @return bool
	 */
	private function belongs_to_installation( string $folded, array $installations ): bool {
		foreach ( $installations as $installation ) {
			if ( 0 === strpos( $folded, $installation ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * SQL that keeps a row out of every installation prefix.
	 *
	 * @since 8.0.0
	 * @param string   $alias Metadata row alias.
	 * @param string[] $installations Installation prefixes.
	 * @param bool     $ignore_case Whether the prefixes are folded.
	 * @param array    $params Parameters to append.
	 * @return string An AND clause, or an empty string when there is no other installation.
	 */
	private function outside_installations( string $alias, array $installations, bool $ignore_case, array &$params ): string {
		if ( empty( $installations ) ) {
			return '';
		}
		$name    = $ignore_case ? 'LOWER(' . $alias . '.TABLE_NAME)' : $alias . '.TABLE_NAME';
		$matches = array();
		foreach ( $installations as $installation ) {
			$matches[] = 'BINARY LEFT(' . $name . ', CHAR_LENGTH(%s)) = BINARY %s';
			$params[]  = $installation;
			$params[]  = $installation;
		}
		return ' AND NOT (' . implode( ' OR ', $matches ) . ')';
	}
}
