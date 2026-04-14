<?php
/**
 * MySQL Select From Table Tool.
 *
 * Executes SELECT queries on database tables with safety constraints.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * MySQL Select From Table Tool.
 */
class Mysql_Select_From_Table_Tool extends Abstract_MCP_Tool {

	use Information_Schema_Query;

	/**
	 * Maximum rows to return.
	 */
	private const MAX_LIMIT = 100;

	/**
	 * Columns redacted from all query results.
	 *
	 * These contain sensitive authentication data the agent never needs.
	 * The agent should use the dedicated `list_users` tool for user lookups.
	 *
	 * @var string[]
	 */
	private const REDACTED_COLUMNS = array(
		'user_pass',
		'user_activation_key',
	);

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'mysql_select_from_table';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'Query data from database tables with optional JOINs. Read-only SELECT with WHERE filtering and multi-table joins. Use mysql_get_table_columns first to understand table schemas. Max 100 rows. Useful for finding option values, user data, custom plugin data, or correlating data across related tables.';
	}

	/**
	 * Define input schema.
	 *
	 * @return array
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'table'   => array(
					'type'        => 'string',
					'description' => 'Primary table name with optional alias (e.g., "wp_posts" or "wp_posts p"). This is the FROM table.',
				),
				'columns' => array(
					'type'        => 'string',
					'description' => 'Comma-separated column names to select. Use "*" for all columns. For JOINs, use table.column syntax (e.g., "p.ID, p.post_title, pm.meta_value"). Supports aliases with AS.',
					'default'     => '*',
				),
				'joins'   => array(
					'type'        => 'array',
					'description' => 'Array of JOIN clauses to combine data from related tables.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'type'  => array(
								'type'        => 'string',
								'enum'        => array( 'INNER', 'LEFT', 'RIGHT' ),
								'default'     => 'INNER',
								'description' => 'Join type.',
							),
							'table' => array(
								'type'        => 'string',
								'description' => 'Table to join (e.g., "wp_postmeta" or "wp_postmeta pm" for alias).',
							),
							'on'    => array(
								'type'        => 'string',
								'description' => 'ON condition (e.g., "p.ID = pm.post_id").',
							),
						),
						'required'   => array( 'table', 'on' ),
					),
				),
				'where'   => array(
					'type'        => 'string',
					'description' => 'WHERE clause conditions WITHOUT the "WHERE" keyword. For JOINs, use table.column or alias.column syntax. Example: "pm.meta_key = \'_price\' AND pm.meta_value > 100"',
				),
				'groupby' => array(
					'type'        => 'string',
					'description' => 'GROUP BY column(s). Use with aggregate functions like COUNT(), SUM() in columns. Example: "p.ID" or "p.post_status"',
				),
				'orderby' => array(
					'type'        => 'string',
					'description' => 'Column name to sort by. Supports table.column syntax for JOINs.',
				),
				'order'   => array(
					'type'        => 'string',
					'enum'        => array( 'ASC', 'DESC' ),
					'default'     => 'ASC',
					'description' => 'Sort direction.',
				),
				'limit'   => array(
					'type'        => 'integer',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
					'description' => 'Maximum rows to return (1–100).',
				),
			),
			'required'   => array( 'table' ),
		);
	}

	/**
	 * Define output schema.
	 *
	 * @return array|null
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'table'       => array( 'type' => 'string' ),
				'rows'        => array(
					'type' => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'row_count'   => array( 'type' => 'integer' ),
				'has_more'    => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'table', 'rows', 'row_count', 'has_more' ),
		);
	}

	/**
	 * Execute tool.
	 *
	 * @param User_Context $user_context User context.
	 * @param array        $params       Tool parameters.
	 * @return array
	 */
	protected function execute_tool( User_Context $user_context, array $params ) {
		global $wpdb;

		$table   = $params['table'] ?? '';
		$columns = $params['columns'] ?? '*';
		$joins   = $params['joins'] ?? array();
		$where   = $params['where'] ?? '';
		$groupby = $params['groupby'] ?? '';
		$orderby = $params['orderby'] ?? '';
		$order   = strtoupper( $params['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
		$limit   = max( 1, min( (int) ( $params['limit'] ?? 20 ), self::MAX_LIMIT ) );

		if ( empty( $table ) ) {
			return Json_Rpc_Response::create_error_response( 'Table name is required.' );
		}

		// Sanitize primary table — supports optional alias (e.g., "wp_posts p").
		$table_ref   = $this->sanitize_table_ref( $table );
		$table_parts = preg_split( '/\s+/', trim( $table ) );
		$table       = preg_replace( '/[^a-zA-Z0-9_]/', '', $table_parts[0] );

		if ( empty( $table ) ) {
			return Json_Rpc_Response::create_error_response( 'Invalid table name.' );
		}

		// Collect all table names to verify (primary + joined).
		$all_tables = array( $table );

		foreach ( $joins as $join ) {
			if ( ! empty( $join['table'] ) ) {
				// Extract table name (may include alias: "wp_postmeta pm").
				$join_parts   = preg_split( '/\s+/', trim( $join['table'] ) );
				$all_tables[] = preg_replace( '/[^a-zA-Z0-9_]/', '', $join_parts[0] );
			}
		}

		$all_tables = array_unique( $all_tables );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The query string is prepared inside prepare_information_schema_query().
		$existing = $wpdb->get_col(
			$this->prepare_information_schema_query(
				'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN ({table_placeholders})',
				$all_tables
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$missing = array_diff( $all_tables, $existing );

		if ( ! empty( $missing ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Tables not found: %s', implode( ', ', $missing ) )
			);
		}

		// Build SELECT columns - sanitize while allowing SQL functions and table.column syntax.
		$select_cols = $this->sanitize_columns( $columns );

		// Build query — table_ref includes backticked name + optional alias.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT {$select_cols} FROM {$table_ref}";

		// Build JOIN clauses.
		if ( ! empty( $joins ) ) {
			$join_sql = $this->build_joins( $joins );

			if ( is_wp_error( $join_sql ) ) {
				return Json_Rpc_Response::create_error_response( $join_sql->get_error_message() );
			}

			$query .= $join_sql;
		}

		// Add WHERE clause if provided.
		if ( ! empty( $where ) ) {
			$validation = $this->validate_clause( $where, 'WHERE' );

			if ( is_wp_error( $validation ) ) {
				return Json_Rpc_Response::create_error_response( $validation->get_error_message() );
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query .= ' WHERE ' . $where;
		}

		// Add GROUP BY if provided.
		if ( ! empty( $groupby ) ) {
			$groupby_clean = $this->sanitize_identifier( $groupby );

			if ( ! empty( $groupby_clean ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query .= ' GROUP BY ' . $groupby_clean;
			}
		}

		// Add ORDER BY if provided (single column only — no commas).
		if ( ! empty( $orderby ) ) {
			$orderby_clean = $this->sanitize_orderby( $orderby );

			if ( ! empty( $orderby_clean ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query .= " ORDER BY {$orderby_clean} {$order}";
			}
		}

		// Add LIMIT.
		$query .= $wpdb->prepare( ' LIMIT %d', $limit );

		// Execute query.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( null === $results ) {
			return Json_Rpc_Response::create_error_response( 'Query failed: ' . $wpdb->last_error );
		}

		// Strip sensitive columns from results (covers SELECT * on wp_users, etc.).
		$results = $this->redact_sensitive_columns( $results );

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Retrieved %d rows', count( $results ) ),
			array(
				'table'    => $table,
				'rows'     => $results,
				'row_count' => count( $results ),
				'has_more' => count( $results ) === $limit,
			)
		);
	}

	/**
	 * Build JOIN clauses from structured array.
	 *
	 * @param array $joins Array of join definitions.
	 * @return string|\WP_Error SQL JOIN string or error.
	 */
	private function build_joins( array $joins ) {
		$allowed_types = array( 'INNER', 'LEFT', 'RIGHT' );
		$sql           = '';

		foreach ( $joins as $index => $join ) {
			if ( empty( $join['table'] ) || empty( $join['on'] ) ) {
				return new \WP_Error( 'invalid_join', sprintf( 'Join #%d requires both "table" and "on".', $index + 1 ) );
			}

			// Validate join type.
			$type = strtoupper( $join['type'] ?? 'INNER' );

			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'INNER';
			}

			// Sanitize table reference (table name + optional alias).
			$table_ref = $this->sanitize_table_ref( $join['table'] );

			if ( empty( $table_ref ) ) {
				return new \WP_Error( 'invalid_join', sprintf( 'Join #%d has invalid table name.', $index + 1 ) );
			}

			// Validate ON clause (same security as WHERE).
			$validation = $this->validate_clause( $join['on'], 'ON' );

			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql .= " {$type} JOIN {$table_ref} ON {$join['on']}";
		}

		return $sql;
	}

	/**
	 * Sanitize a table reference (table name with optional alias).
	 *
	 * Accepts "wp_postmeta" or "wp_postmeta pm".
	 *
	 * @param string $table_ref Raw table reference.
	 * @return string Sanitized table reference with backticks.
	 */
	private function sanitize_table_ref( string $table_ref ): string {
		$parts = preg_split( '/\s+/', trim( $table_ref ) );
		$table = preg_replace( '/[^a-zA-Z0-9_]/', '', $parts[0] ?? '' );

		if ( empty( $table ) ) {
			return '';
		}

		$result = '`' . $table . '`';

		// Optional alias.
		if ( ! empty( $parts[1] ) ) {
			$alias   = preg_replace( '/[^a-zA-Z0-9_]/', '', $parts[1] );
			$result .= ' ' . $alias;
		}

		return $result;
	}

	/**
	 * Validate a SQL clause (WHERE or ON) for dangerous patterns.
	 *
	 * @param string $clause The clause to validate.
	 * @param string $label  Label for error messages (WHERE or ON).
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_clause( string $clause, string $label ) {
		$blocked_words   = array(
			// DML operations.
			'drop',
			'delete',
			'truncate',
			'update',
			'insert',
			'alter',
			'create',
			'grant',
			'revoke',
			'union',
			'into',
			// DoS vectors.
			'sleep',
			'benchmark',
			'wait_for_delay',
		);
		$blocked_symbols = array( '--', '/*', '*/', ';' );

		foreach ( $blocked_words as $keyword ) {
			if ( preg_match( '/\b' . $keyword . '\b/i', $clause ) ) {
				return new \WP_Error(
					'blocked_keyword',
					sprintf( '%s clause contains blocked keyword: %s', $label, $keyword )
				);
			}
		}

		foreach ( $blocked_symbols as $symbol ) {
			if ( false !== strpos( $clause, $symbol ) ) {
				return new \WP_Error(
					'blocked_pattern',
					sprintf( '%s clause contains blocked pattern: %s', $label, $symbol )
				);
			}
		}

		return true;
	}

	/**
	 * Sanitize a column/identifier that may include table.column or alias syntax.
	 *
	 * @param string $identifier Raw identifier string.
	 * @return string Sanitized identifier.
	 */
	private function sanitize_identifier( string $identifier ): string {
		// Allow table.column syntax (e.g., p.ID, wp_posts.post_title).
		// Allow commas for GROUP BY with multiple columns.
		return preg_replace( '/[^a-zA-Z0-9_.,\s]/', '', $identifier );
	}

	/**
	 * Sanitize ORDER BY identifier (single column only — no commas).
	 *
	 * Unlike sanitize_identifier(), this strips commas to prevent
	 * multi-column ORDER BY injection.
	 *
	 * @param string $orderby Raw ORDER BY value.
	 * @return string Sanitized single column identifier.
	 */
	private function sanitize_orderby( string $orderby ): string {
		return preg_replace( '/[^a-zA-Z0-9_.\s]/', '', $orderby );
	}

	/**
	 * Sanitize SELECT column list.
	 *
	 * @param string $columns Raw columns string.
	 * @return string Sanitized SQL column list.
	 */
	private function sanitize_columns( string $columns ): string {
		if ( '*' === $columns ) {
			return '*';
		}

		$column_list = array_map(
			function ( $col ) {
				$col = trim( $col );

				// Block SQL injection patterns.
				if ( preg_match( '/[\'";]|--|\/\*|\*\//', $col ) ) {
					return '';
				}

				// Allow: letters, numbers, underscore, parentheses, asterisk, dot, space (for AS aliases).
				$col = preg_replace( '/[^a-zA-Z0-9_(),*.\s]/', '', $col );

				// Block subqueries and DML keywords in column expressions.
				if ( preg_match( '/\b(select|insert|update|delete|drop|union|into)\b/i', $col ) ) {
					return '';
				}

				// Block redacted column names (sensitive auth data).
				foreach ( self::REDACTED_COLUMNS as $blocked ) {
					if ( preg_match( '/\b' . preg_quote( $blocked, '/' ) . '\b/i', $col ) ) {
						return '';
					}
				}

				return $col;
			},
			explode( ',', $columns )
		);

		$column_list = array_filter( $column_list );

		if ( empty( $column_list ) ) {
			return '*';
		}

		return implode(
			', ',
			array_map(
				function ( $col ) {
					// Functions (contain parentheses) or table.column syntax (contain dot) — use as-is.
					if ( false !== strpos( $col, '(' ) || false !== strpos( $col, '.' ) ) {
						return $col;
					}
					// Plain column — wrap in backticks.
					return '`' . $col . '`';
				},
				$column_list
			)
		);
	}

	/**
	 * Remove sensitive columns from query result rows.
	 *
	 * Catches SELECT * on tables like wp_users where redacted columns
	 * would otherwise be returned. The agent never needs password hashes
	 * or activation keys — the dedicated list_users tool covers user lookups.
	 *
	 * @param array $rows Query result rows.
	 *
	 * @return array Rows with sensitive columns stripped.
	 */
	private function redact_sensitive_columns( array $rows ): array {

		if ( empty( $rows ) ) {
			return $rows;
		}

		// Check first row for redacted columns to avoid per-row overhead.
		$columns_to_strip = array_filter(
			self::REDACTED_COLUMNS,
			function ( $col ) use ( $rows ) {
				return array_key_exists( $col, $rows[0] );
			}
		);

		if ( empty( $columns_to_strip ) ) {
			return $rows;
		}

		foreach ( $rows as &$row ) {
			foreach ( $columns_to_strip as $col ) {
				unset( $row[ $col ] );
			}
		}

		return $rows;
	}
}
