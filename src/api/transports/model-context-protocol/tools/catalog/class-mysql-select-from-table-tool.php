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
	 * Default preview cap for each string cell.
	 *
	 * Approx. 2k tokens using chars/4 estimation.
	 *
	 * @var int
	 */
	private const PREVIEW_MAX_CHARS = 8000;

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
				'table'           => array( 'type' => 'string' ),
				'rows'            => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'row_count'       => array( 'type' => 'integer' ),
				'has_more'        => array( 'type' => 'boolean' ),
				'truncated_cells' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'row_index'       => array( 'type' => 'integer' ),
							'column'          => array( 'type' => 'string' ),
							'original_chars'  => array( 'type' => 'integer' ),
							'returned_chars'  => array( 'type' => 'integer' ),
							'chars_remaining' => array( 'type' => 'integer' ),
						),
					),
				),
			),
			'required'   => array( 'table', 'rows', 'row_count', 'has_more', 'truncated_cells' ),
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

		// Sanitize primary table — supports optional alias (e.g., "wp_posts p" or "wp_posts AS p").
		$table_ref = $this->sanitize_table_ref( $table );
		$table     = $this->extract_table_name( $table );

		if ( empty( $table ) ) {
			return Json_Rpc_Response::create_error_response( 'Invalid table name.' );
		}

		// Collect all table names to verify (primary + joined).
		$all_tables = array( $table );

		foreach ( $joins as $join ) {
			if ( ! empty( $join['table'] ) ) {
				// Extract table name (may include alias: "wp_postmeta pm").
				$all_tables[] = $this->extract_table_name( (string) $join['table'] );
			}
		}

		if ( in_array( '', $all_tables, true ) ) {
			return Json_Rpc_Response::create_error_response( 'Invalid table name in query request.' );
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

		// Add ORDER BY if provided (single identifier only).
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
		$results         = $this->redact_sensitive_columns( $results );
		$truncation_meta = array();
		$results         = $this->truncate_large_cell_values( $results, self::PREVIEW_MAX_CHARS, $truncation_meta );

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Retrieved %d rows', count( $results ) ),
			array(
				'table'           => $table,
				'rows'            => $results,
				'row_count'       => count( $results ),
				'has_more'        => count( $results ) === $limit,
				'truncated_cells' => $truncation_meta,
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
	 * Accepts "wp_postmeta", "wp_postmeta pm", or "wp_postmeta AS pm".
	 *
	 * @since 7.2.4 Supports explicit AS aliases and rejects malformed table refs.
	 *
	 * @param string $table_ref Raw table reference.
	 * @return string Sanitized table reference with backticks.
	 */
	private function sanitize_table_ref( string $table_ref ): string {
		$parsed = $this->parse_table_ref( $table_ref );
		if ( null === $parsed ) {
			return '';
		}

		$table  = $parsed['table'];
		$alias  = $parsed['alias'];
		$result = '`' . $table . '`';

		if ( '' !== $alias ) {
			$result .= ' ' . $alias;
		}

		return $result;
	}

	/**
	 * Extract base table name from a table reference.
	 *
	 * @since 7.2.4
	 *
	 * @param string $table_ref Raw table reference.
	 * @return string Base table name or empty string when invalid.
	 */
	private function extract_table_name( string $table_ref ): string {
		$parsed = $this->parse_table_ref( $table_ref );
		if ( null === $parsed ) {
			return '';
		}

		return $parsed['table'];
	}

	/**
	 * Parse table ref into base table name and optional alias.
	 *
	 * @since 7.2.4
	 *
	 * @param string $table_ref Raw table reference.
	 * @return array<string,string>|null Parsed table/alias pair, or null when invalid.
	 */
	private function parse_table_ref( string $table_ref ): ?array {
		$raw = trim( $table_ref );
		if ( '' === $raw ) {
			return null;
		}

		if ( ! preg_match( '/^([a-zA-Z0-9_]+)(?:\s+([a-zA-Z0-9_]+)|\s+AS\s+([a-zA-Z0-9_]+))?$/i', $raw, $matches ) ) {
			return null;
		}

		$alias = $matches[2] ?? '';
		if ( '' === $alias ) {
			$alias = $matches[3] ?? '';
		}

		// Guard malformed refs like "table AS" where AS is captured as an alias token.
		if ( 'AS' === strtoupper( $alias ) ) {
			return null;
		}

		return array(
			'table' => $matches[1],
			'alias' => $alias,
		);
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
	 * Sanitize GROUP BY identifiers.
	 *
	 * Supports comma-separated list of table-qualified or plain identifiers.
	 *
	 * @since 7.2.4
	 *
	 * @param string $identifier Raw identifier string.
	 * @return string Sanitized identifier list.
	 */
	private function sanitize_identifier( string $identifier ): string {
		$parts     = array_map( 'trim', explode( ',', $identifier ) );
		$sanitized = array();

		foreach ( $parts as $part ) {
			if ( preg_match( '/^[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)?$/', $part ) ) {
				$sanitized[] = $part;
			}
		}

		return implode( ', ', $sanitized );
	}

	/**
	 * Sanitize ORDER BY identifier.
	 *
	 * Single identifier only. Supports table.column syntax.
	 *
	 * @since 7.2.4
	 *
	 * @param string $orderby Raw ORDER BY value.
	 * @return string Sanitized single column identifier.
	 */
	private function sanitize_orderby( string $orderby ): string {
		$orderby = trim( $orderby );
		return preg_match( '/^[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)?$/', $orderby ) ? $orderby : '';
	}

	/**
	 * Sanitize SELECT column list.
	 *
	 * Supports column aliases with explicit AS syntax.
	 *
	 * @since 7.2.4
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
				$raw = trim( $col );

				// Block SQL injection patterns.
				if ( preg_match( '/[\'";]|--|\/\*|\*\//', $raw ) ) {
					return '';
				}

				$alias = '';
				$expr  = $raw;

				// Explicit alias form: "expression AS alias".
				if ( preg_match( '/^(.+?)\s+AS\s+([a-zA-Z0-9_]+)$/i', $raw, $matches ) ) {
					$expr  = trim( $matches[1] );
					$alias = $matches[2];
				}

				// Allow: letters, numbers, underscore, parentheses, asterisk, dot, space.
				$expr = preg_replace( '/[^a-zA-Z0-9_(),*.\s]/', '', $expr );

				// Block subqueries and DML keywords in column expressions.
				if ( preg_match( '/\b(select|insert|update|delete|drop|union|into)\b/i', $expr ) ) {
					return '';
				}

				// Block redacted column names (sensitive auth data).
				foreach ( self::REDACTED_COLUMNS as $blocked ) {
					if ( preg_match( '/\b' . preg_quote( $blocked, '/' ) . '\b/i', $expr ) ) {
						return '';
					}
				}

				$expr = trim( $expr );
				if ( '' === $expr ) {
					return '';
				}

				// Functions or qualified columns remain as expressions.
				if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $expr ) && false === strpos( $expr, '.' ) && false === strpos( $expr, '(' ) ) {
					return '';
				}

				if ( preg_match( '/^[a-zA-Z0-9_]+$/', $expr ) ) {
					$expr = '`' . $expr . '`';
				}

				if ( '' !== $alias ) {
					$expr .= ' AS `' . $alias . '`';
				}

				return $expr;
			},
			explode( ',', $columns )
		);

		$column_list = array_filter( $column_list );

		if ( empty( $column_list ) ) {
			return '*';
		}

		return implode( ', ', $column_list );
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

	/**
	 * Truncate oversized string cell values for model-safe preview responses.
	 *
	 * @since 7.2.4
	 *
	 * @param array $rows             Query result rows.
	 * @param int   $max_chars        Maximum preview chars per string value.
	 * @param array $truncation_meta  Populated with truncation metadata.
	 *
	 * @return array
	 */
	private function truncate_large_cell_values( array $rows, int $max_chars, array &$truncation_meta ): array {
		foreach ( $rows as $row_index => &$row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			foreach ( $row as $column => $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}

				$original_chars = $this->char_length( $value );
				if ( $original_chars <= $max_chars ) {
					continue;
				}

				$chars_remaining = $original_chars - $max_chars;
				$suffix          = sprintf( ' (%d chars more...)', $chars_remaining );
				$suffix_chars    = $this->char_length( $suffix );
				$prefix_chars    = max( 0, $max_chars - $suffix_chars );
				$prefix          = $this->char_substr( $value, 0, $prefix_chars );
				$chars_remaining = max( 0, $original_chars - $prefix_chars );
				$suffix          = sprintf( ' (%d chars more...)', $chars_remaining );
				$suffix_chars    = $this->char_length( $suffix );
				$allowed_prefix  = max( 0, $max_chars - $suffix_chars );
				if ( $prefix_chars > $allowed_prefix ) {
					$prefix_chars    = $allowed_prefix;
					$prefix          = $this->char_substr( $value, 0, $prefix_chars );
					$chars_remaining = max( 0, $original_chars - $prefix_chars );
					$suffix          = sprintf( ' (%d chars more...)', $chars_remaining );
				}
				$truncated = $prefix . $suffix;

				$row[ $column ]    = $truncated;
				$truncation_meta[] = array(
					'row_index'       => (int) $row_index,
					'column'          => (string) $column,
					'original_chars'  => $original_chars,
					'returned_chars'  => $this->char_length( $truncated ),
					'chars_remaining' => $chars_remaining,
				);
			}
		}

		return $rows;
	}

	/**
	 * Multibyte-safe length helper.
	 *
	 * @since 7.2.4
	 *
	 * @param string $value Input string.
	 * @return int
	 */
	private function char_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Multibyte-safe substring helper.
	 *
	 * @since 7.2.4
	 *
	 * @param string $value  Input string.
	 * @param int    $offset Character offset.
	 * @param int    $length Max chars to return.
	 * @return string
	 */
	private function char_substr( string $value, int $offset, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			$chunk = mb_substr( $value, $offset, $length, 'UTF-8' );
			return is_string( $chunk ) ? $chunk : '';
		}

		return substr( $value, $offset, $length );
	}
}
