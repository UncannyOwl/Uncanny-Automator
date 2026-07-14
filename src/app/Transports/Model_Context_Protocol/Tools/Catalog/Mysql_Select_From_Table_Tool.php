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

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

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
		return 'Query data from database tables with optional JOINs. Read-only SELECT with WHERE filtering and multi-table joins. Use mysql_get_table_columns first to understand table schemas. Max 100 rows. Use structured WHERE/JOIN predicate objects for OR groups, IN lists, NULL checks, and join filters; do not send structured predicates as JSON strings. Legacy SQL strings are accepted only for simple AND-joined predicates and IN lists. Raw OR, BETWEEN, subqueries, comments, function calls, and arbitrary SQL expressions fail closed. Useful for finding option values, user data, custom plugin data, or correlating data across related tables.';
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
								'type'        => array( 'string', 'object', 'array' ),
								'description' => 'JOIN predicates. Prefer structured objects: {"left":"p.ID","operator":"=","right":"pm.post_id"} or {"column":"pm.meta_key","operator":"=","value":"_price"}. Legacy strings are accepted only for simple comparisons joined by AND.',
							),
						),
						'required'   => array( 'table', 'on' ),
					),
				),
				'where'   => array(
					'type'        => array( 'string', 'object', 'array' ),
					'description' => 'WHERE predicates. Prefer structured objects: {"column":"pm.meta_key","operator":"=","value":"_price"}, {"column":"post_type","operator":"IN","value":["post","page"]}, or {"relation":"OR","conditions":[...]}. Legacy strings are accepted only for simple prepared predicates joined by AND.',
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

		// Build SELECT columns - sanitize while allowing explicit aggregate functions and table.column syntax.
		$select_cols = $this->sanitize_columns( $columns );
		if ( is_wp_error( $select_cols ) ) {
			return Json_Rpc_Response::create_error_response( $select_cols->get_error_message() );
		}

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
			$where_sql = $this->build_where_clause( $where );

			if ( is_wp_error( $where_sql ) ) {
				return Json_Rpc_Response::create_error_response( $where_sql->get_error_message() );
			}

			if ( '' !== $where_sql ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query .= ' WHERE ' . $where_sql;
			}
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

			// Build ON predicates from identifiers only; never interpolate raw SQL.
			$on_sql = $this->build_join_on_clause( $join['on'], $index );

			if ( is_wp_error( $on_sql ) ) {
				return $on_sql;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql .= " {$type} JOIN {$table_ref} ON {$on_sql}";
		}

		return $sql;
	}

	// ---------------------------------------------------------------------
	// Predicate builders.
	// ---------------------------------------------------------------------

	/**
	 * Build a prepared WHERE clause from structured or legacy-simple predicates.
	 *
	 * @param mixed $where WHERE predicate input.
	 * @return string|\WP_Error SQL predicate or error.
	 */
	private function build_where_clause( $where ) {
		if ( is_string( $where ) ) {
			if ( '' === trim( $where ) ) {
				return '';
			}

			$where = $this->parse_legacy_where_string( $where );
			if ( is_wp_error( $where ) ) {
				return $where;
			}
		}

		return $this->build_where_predicate_group( $where );
	}

	/**
	 * Build a JOIN ON clause from structured or legacy-simple predicates.
	 *
	 * @param mixed $on         JOIN predicate input.
	 * @param int   $join_index Zero-based join index.
	 * @return string|\WP_Error SQL predicate or error.
	 */
	private function build_join_on_clause( $on, int $join_index ) {
		if ( is_string( $on ) ) {
			$on = $this->parse_legacy_join_on_string( $on, $join_index );
			if ( is_wp_error( $on ) ) {
				return $on;
			}
		}

		if ( $this->is_condition_list( $on ) ) {
			$parts = array();

			foreach ( $on as $condition ) {
				$predicate = $this->build_join_predicate( $condition, $join_index );
				if ( is_wp_error( $predicate ) ) {
					return $predicate;
				}
				$parts[] = $predicate;
			}

			return implode( ' AND ', $parts );
		}

		return $this->build_join_predicate( $on, $join_index );
	}

	/**
	 * Build a grouped WHERE predicate.
	 *
	 * @param mixed $where WHERE predicate input.
	 * @return string|\WP_Error SQL predicate or error.
	 */
	private function build_where_predicate_group( $where ) {
		return $this->build_where_predicate_group_at_depth( $where, 0 );
	}

	/**
	 * Build a grouped WHERE predicate with bounded recursion.
	 *
	 * @param mixed $where WHERE predicate input.
	 * @param int   $depth Current group depth.
	 * @return string|\WP_Error SQL predicate or error.
	 */
	private function build_where_predicate_group_at_depth( $where, int $depth ) {
		if ( $depth > 10 ) {
			return new \WP_Error( 'invalid_where', 'WHERE predicate groups are nested too deeply.' );
		}

		if ( ! is_array( $where ) || empty( $where ) ) {
			return new \WP_Error( 'invalid_where', 'WHERE must be a non-empty predicate object or list.' );
		}

		if ( isset( $where['conditions'] ) ) {
			if ( ! is_array( $where['conditions'] ) || empty( $where['conditions'] ) ) {
				return new \WP_Error( 'invalid_where', 'WHERE conditions must be a non-empty array.' );
			}

			$relation = strtoupper( (string) ( $where['relation'] ?? 'AND' ) );
			if ( ! in_array( $relation, array( 'AND', 'OR' ), true ) ) {
				return new \WP_Error( 'invalid_where', 'WHERE relation must be AND or OR.' );
			}

			$parts = array();
			foreach ( $where['conditions'] as $condition ) {
				$predicate = $this->build_where_predicate_group_at_depth( $condition, $depth + 1 );
				if ( is_wp_error( $predicate ) ) {
					return $predicate;
				}
				$parts[] = '(' . $predicate . ')';
			}

			return implode( ' ' . $relation . ' ', $parts );
		}

		if ( $this->is_condition_list( $where ) ) {
			$parts = array();
			foreach ( $where as $condition ) {
				$predicate = $this->build_where_value_predicate( $condition );
				if ( is_wp_error( $predicate ) ) {
					return $predicate;
				}
				$parts[] = $predicate;
			}

			return implode( ' AND ', $parts );
		}

		return $this->build_where_value_predicate( $where );
	}

	/**
	 * Build one prepared column-to-value WHERE predicate.
	 *
	 * @param mixed $condition Predicate object.
	 * @return string|\WP_Error SQL predicate or error.
	 */
	private function build_where_value_predicate( $condition ) {
		global $wpdb;

		if ( ! is_array( $condition ) ) {
			return new \WP_Error( 'invalid_where', 'WHERE predicate must be an object.' );
		}

		$column = $this->sanitize_column_ref( (string) ( $condition['column'] ?? '' ) );
		if ( '' === $column ) {
			return new \WP_Error( 'invalid_where', 'WHERE predicate column must be a valid identifier.' );
		}

		$operator = $this->normalize_where_operator( (string) ( $condition['operator'] ?? '=' ) );
		if ( '' === $operator ) {
			return new \WP_Error( 'invalid_where', 'WHERE predicate operator is not supported.' );
		}

		$value = $condition['value'] ?? null;

		if ( in_array( $operator, array( 'IS', 'IS NOT' ), true ) ) {
			if ( null === $value || 'NULL' === strtoupper( (string) $value ) ) {
				return "{$column} {$operator} NULL";
			}

			return new \WP_Error( 'invalid_where', 'WHERE IS predicates only support NULL values.' );
		}

		if ( in_array( $operator, array( 'IN', 'NOT IN' ), true ) ) {
			if ( ! is_array( $value ) || empty( $value ) || ! $this->is_condition_list( $value ) ) {
				return new \WP_Error( 'invalid_where', 'WHERE IN predicates require a non-empty value array.' );
			}

			$placeholders = array();
			$values       = array();
			foreach ( $value as $item ) {
				if ( null === $item || ! is_scalar( $item ) ) {
					return new \WP_Error( 'invalid_where', 'WHERE IN predicate values must be non-null scalar values.' );
				}
				$placeholders[] = $this->placeholder_for_value( $item );
				$values[]       = $item;
			}

			$sql = "{$column} {$operator} (" . implode( ', ', $placeholders ) . ')';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are generated from scalar value types only.
			return $wpdb->prepare( $sql, $values );
		}

		if ( ! $this->is_bindable_value( $value ) ) {
			return new \WP_Error( 'invalid_where', 'WHERE predicate value must be scalar or null.' );
		}

		if ( null === $value ) {
			if ( '=' === $operator ) {
				return $column . ' IS NULL';
			}

			if ( in_array( $operator, array( '!=', '<>' ), true ) ) {
				return $column . ' IS NOT NULL';
			}

			return new \WP_Error( 'invalid_where', 'NULL values only support =, !=, <>, IS, or IS NOT.' );
		}

		$sql = "{$column} {$operator} " . $this->placeholder_for_value( $value );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Column/operator are allowlisted and the value is bound here.
		return $wpdb->prepare( $sql, $value );
	}

	/**
	 * Build one JOIN predicate.
	 *
	 * @param mixed $condition  Predicate object.
	 * @param int   $join_index Zero-based join index.
	 * @return string|\WP_Error SQL predicate or error.
	 */
	private function build_join_predicate( $condition, int $join_index ) {
		if ( ! is_array( $condition ) ) {
			return new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON predicate must be an object.', $join_index + 1 ) );
		}

		if ( isset( $condition['column'] ) ) {
			return $this->build_where_value_predicate( $condition );
		}

		$left  = $this->sanitize_column_ref( (string) ( $condition['left'] ?? '' ) );
		$right = $this->sanitize_column_ref( (string) ( $condition['right'] ?? '' ) );

		if ( '' === $left || '' === $right ) {
			return new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON predicate must use valid identifiers.', $join_index + 1 ) );
		}

		$operator = $this->normalize_join_operator( (string) ( $condition['operator'] ?? '=' ) );
		if ( '' === $operator ) {
			return new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON operator is not supported.', $join_index + 1 ) );
		}

		return "{$left} {$operator} {$right}";
	}

	/**
	 * Parse legacy WHERE strings into prepared predicate objects.
	 *
	 * @param string $where Raw legacy WHERE string.
	 * @return array|\WP_Error
	 */
	private function parse_legacy_where_string( string $where ) {
		$where = trim( $where );
		if ( '' === $where ) {
			return array();
		}

		$recovery_error = $this->get_legacy_where_recovery_error( $where );
		if ( is_wp_error( $recovery_error ) ) {
			return $recovery_error;
		}

		if ( $this->contains_unsafe_legacy_clause_syntax( $where ) ) {
			return new \WP_Error( 'invalid_where', 'WHERE only supports simple predicates joined by AND. Use structured predicate objects for complex filters, for example {"relation":"OR","conditions":[{"column":"post_status","operator":"=","value":"publish"},{"column":"post_status","operator":"=","value":"private"}]}.' );
		}

		$parts      = preg_split( '/\s+AND\s+/i', $where );
		$conditions = array();

		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( preg_match( '/^([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?)\s+(NOT\s+IN|IN)\s*\((.*)\)$/i', $part, $matches ) ) {
				$value = $this->parse_legacy_literal_list( trim( $matches[3] ) );
				if ( is_wp_error( $value ) ) {
					return $value;
				}

				$conditions[] = array(
					'column'   => $matches[1],
					'operator' => $matches[2],
					'value'    => $value,
				);
				continue;
			}

			if ( ! preg_match( '/^([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?)\s*(NOT\s+LIKE|LIKE|IS\s+NOT|IS|!=|<>|>=|<=|=|>|<)\s*(.+)$/i', $part, $matches ) ) {
				return new \WP_Error( 'invalid_where', 'WHERE contains an unsupported predicate shape. Use {"column":"column_name","operator":"=","value":"value"} or an array of those objects for AND conditions.' );
			}

			$value = $this->parse_legacy_literal( trim( $matches[3] ) );
			if ( is_wp_error( $value ) ) {
				return $value;
			}

			$conditions[] = array(
				'column'   => $matches[1],
				'operator' => $matches[2],
				'value'    => $value,
			);
		}

		return $conditions;
	}

	/**
	 * Parse legacy JOIN strings into identifier predicates.
	 *
	 * @param string $on         Raw legacy ON string.
	 * @param int    $join_index Zero-based join index.
	 * @return array|\WP_Error
	 */
	private function parse_legacy_join_on_string( string $on, int $join_index ) {
		$on             = trim( $on );
		$recovery_error = $this->get_legacy_join_recovery_error( $on, $join_index );
		if ( is_wp_error( $recovery_error ) ) {
			return $recovery_error;
		}

		if ( '' === $on || $this->contains_unsafe_legacy_clause_syntax( $on ) ) {
			return new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON only supports identifier comparisons or value predicates joined by AND. Use {"left":"p.ID","operator":"=","right":"pm.post_id"} for column joins and {"column":"pm.meta_key","operator":"=","value":"_price"} for join filters.', $join_index + 1 ) );
		}

		$parts      = preg_split( '/\s+AND\s+/i', $on );
		$conditions = array();

		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( preg_match( '/^([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?)\s*(<=>|!=|<>|>=|<=|=|>|<)\s*([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?)$/', $part, $matches ) ) {
				$conditions[] = array(
					'left'     => $matches[1],
					'operator' => $matches[2],
					'right'    => $matches[3],
				);
				continue;
			}

			if ( ! preg_match( '/^([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?)\s*(NOT\s+LIKE|LIKE|IS\s+NOT|IS|!=|<>|>=|<=|=|>|<)\s*(.+)$/i', $part, $matches ) ) {
				return new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON contains an unsupported predicate shape.', $join_index + 1 ) );
			}

			$value = $this->parse_legacy_literal( trim( $matches[3] ) );
			if ( is_wp_error( $value ) ) {
				return new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON contains an unsupported predicate value.', $join_index + 1 ) );
			}

			$conditions[] = array(
				'column'   => $matches[1],
				'operator' => $matches[2],
				'value'    => $value,
			);
		}

		return $conditions;
	}

	/**
	 * Return an actionable error for legacy WHERE strings that need structured predicates.
	 *
	 * @param string $where Legacy WHERE string.
	 * @return false|\WP_Error
	 */
	private function get_legacy_where_recovery_error( string $where ) {
		if ( $this->looks_like_json_structure( $where ) ) {
			return new \WP_Error( 'invalid_where', 'WHERE structured predicates must be sent as JSON objects or arrays, not encoded strings. Retry with {"where":{"column":"post_status","operator":"=","value":"publish"}} instead of {"where":"{\"column\":\"post_status\",\"value\":\"publish\"}"}.' );
		}

		if ( preg_match( '/\bOR\b/i', $where ) ) {
			return new \WP_Error( 'invalid_where', 'WHERE only supports simple predicates joined by AND. To use OR, retry with a structured group such as {"relation":"OR","conditions":[{"column":"post_status","operator":"=","value":"publish"},{"column":"post_status","operator":"=","value":"private"}]}.' );
		}

		if ( preg_match( '/\bBETWEEN\b/i', $where ) ) {
			return new \WP_Error( 'invalid_where', 'WHERE legacy strings do not support BETWEEN. Retry with two structured predicates: [{"column":"post_date","operator":">=","value":"2026-01-01"},{"column":"post_date","operator":"<=","value":"2026-07-01"}].' );
		}

		return false;
	}

	/**
	 * Return an actionable error for legacy JOIN ON strings that need structured predicates.
	 *
	 * @param string $on         Legacy JOIN ON string.
	 * @param int    $join_index Zero-based join index.
	 * @return false|\WP_Error
	 */
	private function get_legacy_join_recovery_error( string $on, int $join_index ) {
		if ( $this->looks_like_json_structure( $on ) ) {
			return new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON structured predicates must be sent as JSON objects or arrays, not encoded strings. Retry with {"on":{"left":"p.ID","operator":"=","right":"pm.post_id"}}.', $join_index + 1 ) );
		}

		if ( preg_match( '/\bOR\b/i', $on ) ) {
			return new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON only supports identifier comparisons or value predicates joined by AND. To use alternate join logic, send structured predicates and move row alternatives into WHERE {"relation":"OR","conditions":[...]}.', $join_index + 1 ) );
		}

		return false;
	}

	/**
	 * Detect encoded structured predicate input.
	 *
	 * @param string $value Raw value.
	 * @return bool
	 */
	private function looks_like_json_structure( string $value ): bool {
		$value = trim( $value );
		if ( '' === $value || ! in_array( $value[0], array( '{', '[' ), true ) ) {
			return false;
		}

		$decoded = json_decode( $value, true );
		return JSON_ERROR_NONE === json_last_error() && is_array( $decoded );
	}

	/**
	 * Parse a legacy scalar literal without evaluating it as SQL.
	 *
	 * @param string $literal Raw literal.
	 * @return int|float|string|null|\WP_Error
	 */
	private function parse_legacy_literal( string $literal ) {
		if ( 'NULL' === strtoupper( $literal ) ) {
			return null;
		}

		if ( preg_match( '/^-?\d+$/', $literal ) ) {
			return (int) $literal;
		}

		if ( preg_match( '/^-?\d+\.\d+$/', $literal ) ) {
			return (float) $literal;
		}

		if ( preg_match( '/^\'([^\']*)\'$/', $literal, $matches ) || preg_match( '/^"([^"]*)"$/', $literal, $matches ) ) {
			return $matches[1];
		}

		return new \WP_Error( 'invalid_where', 'WHERE literals must be quoted strings, numbers, or NULL.' );
	}

	/**
	 * Parse a comma-delimited legacy literal list.
	 *
	 * @param string $literal_list Raw list without surrounding parentheses.
	 * @return array|\WP_Error
	 */
	private function parse_legacy_literal_list( string $literal_list ) {
		if ( '' === trim( $literal_list ) ) {
			return new \WP_Error( 'invalid_where', 'WHERE IN lists must not be empty.' );
		}

		$values = array();
		foreach ( explode( ',', $literal_list ) as $literal ) {
			$value = $this->parse_legacy_literal( trim( $literal ) );
			if ( is_wp_error( $value ) || null === $value ) {
				return new \WP_Error( 'invalid_where', 'WHERE IN lists must contain only quoted strings or numbers.' );
			}
			$values[] = $value;
		}

		return $values;
	}

	/**
	 * Detect SQL expression syntax outside the legacy predicate mini-language.
	 *
	 * @param string $clause Raw clause.
	 * @return bool
	 */
	private function contains_unsafe_legacy_clause_syntax( string $clause ): bool {
		return (bool) preg_match( '/(--|\/\*|\*\/|;|\bOR\b|\bSELECT\b|\bUNION\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b|\bSLEEP\b|\bBENCHMARK\b)/i', $clause );
	}

	/**
	 * Normalize supported WHERE operators.
	 *
	 * @param string $operator Raw operator.
	 * @return string Allowlisted operator or empty string.
	 */
	private function normalize_where_operator( string $operator ): string {
		$operator = strtoupper( preg_replace( '/\s+/', ' ', trim( $operator ) ) );
		$allowed  = array( '=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT' );

		return in_array( $operator, $allowed, true ) ? $operator : '';
	}

	/**
	 * Normalize supported JOIN operators.
	 *
	 * @param string $operator Raw operator.
	 * @return string Allowlisted operator or empty string.
	 */
	private function normalize_join_operator( string $operator ): string {
		$operator = strtoupper( preg_replace( '/\s+/', ' ', trim( $operator ) ) );
		$allowed  = array( '=', '!=', '<>', '<=>', '>', '>=', '<', '<=' );

		return in_array( $operator, $allowed, true ) ? $operator : '';
	}

	/**
	 * Check whether a value may be passed to wpdb::prepare.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	private function is_bindable_value( $value ): bool {
		return null === $value || is_scalar( $value );
	}

	/**
	 * Resolve the wpdb placeholder for a scalar value.
	 *
	 * @param mixed $value Value to bind.
	 * @return string Placeholder.
	 */
	private function placeholder_for_value( $value ): string {
		if ( is_int( $value ) ) {
			return '%d';
		}

		if ( is_float( $value ) ) {
			return '%f';
		}

		return '%s';
	}

	/**
	 * Identify JSON-style lists.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	private function is_condition_list( $value ): bool {
		return is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) );
	}

	/**
	 * Sanitize a one- or two-part column reference.
	 *
	 * @param string $column_ref Raw column reference.
	 * @return string Backticked column reference or empty string.
	 */
	private function sanitize_column_ref( string $column_ref ): string {
		$column_ref = trim( $column_ref );
		if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column_ref ) ) {
			return '';
		}

		$parts       = explode( '.', $column_ref );
		$column_name = end( $parts );
		if ( in_array( strtolower( (string) $column_name ), self::REDACTED_COLUMNS, true ) ) {
			return '';
		}

		return '`' . implode( '`.`', $parts ) . '`';
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
			$column = $this->sanitize_column_ref( $part );
			if ( '' !== $column ) {
				$sanitized[] = $column;
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
		return $this->sanitize_column_ref( $orderby );
	}

	/**
	 * Sanitize SELECT column list.
	 *
	 * Supports column aliases with explicit AS syntax.
	 *
	 * @since 7.2.4
	 *
	 * @param string $columns Raw columns string.
	 * @return string|\WP_Error Sanitized SQL column list or error.
	 */
	private function sanitize_columns( string $columns ) {
		if ( '*' === $columns ) {
			return '*';
		}

		$column_list = array_map(
			function ( $col ) {
				$raw = trim( $col );

				$alias = '';
				$expr  = $raw;

				// Explicit alias form: "expression AS alias".
				if ( preg_match( '/^(.+?)\s+AS\s+([a-zA-Z_][a-zA-Z0-9_]*)$/i', $raw, $matches ) ) {
					$expr  = trim( $matches[1] );
					$alias = $matches[2];
				}

				$expr = $this->sanitize_select_expression( $expr );
				if ( '' === $expr ) {
					return '';
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
			return new \WP_Error( 'invalid_columns', 'No valid SELECT columns were provided.' );
		}

		return implode( ', ', $column_list );
	}

	/**
	 * Sanitize one SELECT expression.
	 *
	 * @param string $expr Raw expression.
	 * @return string Sanitized expression or empty string.
	 */
	private function sanitize_select_expression( string $expr ): string {
		$expr = trim( $expr );
		if ( '' === $expr ) {
			return '';
		}

		if ( '*' === $expr ) {
			return '*';
		}

		$column = $this->sanitize_column_ref( $expr );
		if ( '' !== $column ) {
			return $column;
		}

		if ( preg_match( '/^(COUNT|SUM|AVG|MIN|MAX)\(\s*(\*|[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?)\s*\)$/i', $expr, $matches ) ) {
			$function = strtoupper( $matches[1] );
			$argument = '*' === $matches[2] ? '*' : $this->sanitize_column_ref( $matches[2] );

			if ( '' === $argument ) {
				return '';
			}

			return "{$function}({$argument})";
		}

		return '';
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
