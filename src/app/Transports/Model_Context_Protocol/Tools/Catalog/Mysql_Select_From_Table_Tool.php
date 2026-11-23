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
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Database_Column_Redaction_Settings;

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
	 * Output keys of each selected value column (option_value, meta_value) with the
	 * output key of the hidden column that names its row, set while the columns are read.
	 *
	 * @var array<int,array{value:string,name:string,prefixes:string[]}>
	 */
	private $setting_pairs = array();

	/**
	 * What each output name of the column list reads, keyed by the lower-cased name:
	 * the column reference as written, or '' for a function.
	 *
	 * @var array<string,string>
	 */
	private $output_sources = array();

	/**
	 * Name columns whose redacted rows the query leaves out, because it filters, sorts,
	 * groups or aggregates by their value column, keyed by the reference.
	 *
	 * @var array<string,string[]> Sanitized name column reference => owner prefixes for its table.
	 */
	private $setting_exclusions = array();

	/** Whether the query left credential settings out, so the result can say so. */
	private $left_out_settings = false;

	/** Whether the select list is being read, where a value column is returned rather than matched on. */
	private $in_select = false;

	/**
	 * The owner's table column redaction for the request being built, or null outside one.
	 *
	 * @var Database_Column_Redaction_Settings|null
	 */
	private $redaction = null;

	/**
	 * Table names in the request, keyed by lower-cased table name and alias.
	 *
	 * @var array<string,string>
	 */
	private $query_tables = array();

	/**
	 * Each lower-cased table name and alias in the request, as the caller wrote it.
	 * MySQL on Linux matches aliases by case, so SQL Automator adds uses these (QA D101).
	 *
	 * @var array<string,string>
	 */
	private $query_aliases = array();

	/**
	 * How many of the request's tables the predicate being built can name: a JOIN ON sees the
	 * tables joined up to it. Null outside a JOIN ON, where every table is in scope.
	 *
	 * @var int|null
	 */
	private $tables_in_scope = null;

	/** @var string[] Table references in FROM and JOIN order for predicate type lookup. */
	private array $predicate_table_refs = array();

	/** @var array|null Column types for the current request, loaded only when needed. */
	private ?array $predicate_column_types = null;

	/**
	 * Redacted column references the request tried to filter, join, sort, group or aggregate by.
	 *
	 * @var string[]
	 */
	private $redaction_violations = array();

	/** Whether a redacted column may be referenced here (a COUNT shows no value). */
	private $allow_redacted = false;

	/** Prefix of the hidden column that names a selected setting's row. */
	private const HIDDEN_SETTING_NAME = 'automator_setting_name_';

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
					'description' => 'Select named columns, such as "p.ID, p.post_title". List the table columns to find available names. Each column needs its own name in the response: when two would share one, rename one with AS, such as "p.ID AS post_id". For a total without row data, use "COUNT(*) AS total".',
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
							'next_offset'     => array(
								'type'        => 'integer',
								'description' => 'Character offset for mysql_get_cell_chunk. Excludes the preview note. If column is an AS alias, use the unqualified database column as target_column, such as post_content for p.post_content AS body.',
							),
							'row_key'         => array(
								'type'        => 'object',
								'description' => 'Key arguments for mysql_get_cell_chunk when a permitted single-column primary key is known.',
								'properties'  => array(
									'key_column' => array( 'type' => 'string' ),
									'key_value'  => array( 'type' => array( 'string', 'integer' ) ),
								),
								'required'    => array( 'key_column', 'key_value' ),
							),
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
		$columns = $params['columns'] ?? '';
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
		$table_refs = array( (string) $params['table'] );

		foreach ( $joins as $join ) {
			if ( ! empty( $join['table'] ) ) {
				$table_refs[] = (string) $join['table'];
			}
		}
		$all_tables = array_map( array( $this, 'extract_table_name' ), $table_refs );

		if ( in_array( '', $all_tables, true ) ) {
			return Json_Rpc_Response::create_error_response( 'Invalid table name in query request.' );
		}

		$all_tables = array_unique( $all_tables );

		$boundary = $this->get_table_boundary_response( $all_tables, $user_context );
		if ( null !== $boundary ) {
			return $boundary;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The query string is prepared inside prepare_information_schema_query().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Live information_schema read for the table browser, must not be cached. The query is prepared inside prepare_information_schema_query().
		$existing = $wpdb->get_col(
			$this->prepare_information_schema_query(
				'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN ({table_placeholders})',
				$all_tables
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		// A failed probe must not be reported as "tables not found".
		$failure = $this->get_query_failure_response();
		if ( null !== $failure ) {
			return $failure;
		}

		$missing = $this->missing_table_names( $all_tables, (array) $existing );

		if ( ! empty( $missing ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Tables not found: %s', implode( ', ', $missing ) )
			);
		}

		$from = ' FROM ' . $table_ref;

		$this->redaction            = new Database_Column_Redaction_Settings();
		$this->redaction_violations = array();
		$this->setting_exclusions   = array();
		$this->left_out_settings    = false;
		$this->query_aliases        = array();
		$this->query_tables         = $this->query_table_names( (string) $params['table'], $joins );

		$this->predicate_table_refs   = $table_refs;
		$this->predicate_column_types = null;

		// Build JOIN clauses.
		if ( ! empty( $joins ) ) {
			$join_sql = $this->build_joins( $joins );

			if ( is_wp_error( $join_sql ) ) {
				return $this->redaction_refusal() ?? Json_Rpc_Response::create_error_response( $join_sql->get_error_message() );
			}

			$from .= $join_sql;
		}

		// Add WHERE clause if provided.
		if ( ! empty( $where ) ) {
			$where_sql = $this->build_where_clause( $where );

			if ( is_wp_error( $where_sql ) ) {
				return $this->redaction_refusal() ?? Json_Rpc_Response::create_error_response( $where_sql->get_error_message() );
			}

			if ( '' !== $where_sql ) {
				// Bracketed, so exclusions added below apply to every branch of an OR.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from .= ' WHERE ( ' . $where_sql . ' )';
			}
		}

		// Validate the projection after the table and predicates so existing clients retain their recovery guidance.
		$grouped     = '' !== trim( (string) $groupby );
		$select_cols = $this->sanitize_columns( $columns, $grouped );
		if ( is_wp_error( $select_cols ) ) {
			return $this->redaction_refusal() ?? Json_Rpc_Response::create_error_response( $select_cols->get_error_message() );
		}
		$groupby_clean = ! empty( $groupby ) ? $this->sanitize_identifier( $groupby ) : '';
		$orderby_clean = ! empty( $orderby ) ? $this->sanitize_orderby( $orderby ) : '';

		// A part that cannot be used is refused: dropping it would return ungrouped or unsorted
		// rows that look like the answer.
		$refusal = $this->redaction_refusal();
		if ( null !== $refusal ) {
			return $refusal;
		}
		$unusable = $this->unusable_identifiers( (string) $groupby, true );
		if ( $unusable ) {
			return Json_Rpc_Response::create_error_response( $this->identifier_refusal( 'groupby', $unusable, 'column names such as post_date or p.ID', 'Separate several columns with commas, such as "p.post_type, p.post_status".' ) );
		}
		$unusable = $this->unusable_identifiers( (string) $orderby, false );
		if ( $unusable ) {
			return Json_Rpc_Response::create_error_response( $this->identifier_refusal( 'orderby', $unusable, 'one column name such as post_date or p.ID', 'Use order for the direction.' ) );
		}

		if ( $this->setting_exclusions ) {
			// A value matched on, grouped or aggregated cannot be withheld per row afterwards; leave those rows out.
			$this->left_out_settings = true;
			$exclusions              = array();
			foreach ( $this->setting_exclusions as $name_column => $prefixes ) {
				$exclusions[] = Database_Secrets::sql_not_secret_setting( $name_column, $prefixes );
			}
			$from .= ( empty( $where_sql ) ? ' WHERE ' : ' AND ' ) . implode( ' AND ', $exclusions );
		}
		$query = 'SELECT ' . $select_cols . $from;

		if ( ! empty( $groupby_clean ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query .= ' GROUP BY ' . $groupby_clean;
		}

		// ORDER BY takes a single identifier.
		if ( ! empty( $orderby_clean ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query .= " ORDER BY {$orderby_clean} {$order}";
		}

		// Sorting or grouping by a redacted column would show its order or distinct values.
		$refusal = $this->redaction_refusal();
		if ( null !== $refusal ) {
			return $refusal;
		}

		// Probe one extra row to distinguish a full final page from more results.
		// This bounds returned rows, not the engine's work for joins or aggregates;
		// execution-time limits must be configured by the database host.
		$query .= $wpdb->prepare( ' LIMIT %d', $limit + 1 );

		// Execute query.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Live read for the table browser, must not be cached. The query is assembled from sanitized identifiers and column references; the WHERE predicates and LIMIT are already prepared.
		$results = $wpdb->get_results( $query, ARRAY_A );

		// wpdb returns an empty array on SQL errors, so "0 rows" is only
		// trustworthy when last_error is empty.
		$failure = $this->get_query_failure_response();
		if ( null !== $failure ) {
			return $failure;
		}

		if ( ! is_array( $results ) ) {
			$results = array();
		}

		$has_more = count( $results ) > $limit;
		$results  = array_slice( $results, 0, $limit );

		// Apply the sensitive-column boundary to output aliases as well.
		$results         = $this->redact_sensitive_columns( $results );
		$truncation_meta = array();
		$results         = $this->truncate_large_cell_values( $results, self::PREVIEW_MAX_CHARS, $truncation_meta );
		if ( $truncation_meta && ! $joins && ! $grouped ) {
			$this->add_truncated_cell_row_keys( $truncation_meta, $results, $table, $columns, $has_more ? null : $where );
		}

		return Json_Rpc_Response::create_success_response(
			// There is no offset, so say how to reach the rows after this page.
			sprintf( 'Retrieved %d rows', count( $results ) )
				. ( $has_more ? '. More rows match. To read the next rows, order by a unique column such as the primary key and add a WHERE condition past the last value received; there is no offset.' : '' )
				// An empty or lower total here does not prove a withheld setting is absent.
				. ( $this->left_out_settings ? '. Withheld settings are left out of filters, sorts, groups, joins and totals on setting values, so they are not counted here.' : '' ),
			array(
				'table'           => $table,
				'rows'            => $results,
				'row_count'       => count( $results ),
				'has_more'        => $has_more,
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
			$outer_exclusions         = $this->setting_exclusions;
			$this->setting_exclusions = array();
			$this->tables_in_scope    = $index + 2;
			$on_sql                   = $this->build_join_on_clause( $join['on'], $index );
			$this->tables_in_scope    = null;

			if ( is_wp_error( $on_sql ) ) {
				return $on_sql;
			}

			// A join matched on a setting's value leaves redacted rows out of the join itself. Left
			// out in WHERE instead, an outer join would drop the whole row only when a guess is right.
			$on_exclusions = array();
			foreach ( $this->setting_exclusions as $name_column => $prefixes ) {
				$on_exclusions[] = Database_Secrets::sql_not_secret_setting( $name_column, $prefixes );
			}
			$this->setting_exclusions = $outer_exclusions;
			if ( $on_exclusions ) {
				$this->left_out_settings = true;
				$on_sql                  = '( ' . $on_sql . ' ) AND ' . implode( ' AND ', $on_exclusions );
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
	 * @param mixed    $condition Predicate object.
	 * @param int|null $join_index Zero-based index when used in a JOIN.
	 * @return string|\WP_Error SQL predicate or error.
	 */
	private function build_where_value_predicate( $condition, ?int $join_index = null ) {
		global $wpdb;

		if ( ! is_array( $condition ) ) {
			return new \WP_Error( 'invalid_where', 'WHERE predicate must be an object.' );
		}

		$reference = trim( (string) ( $condition['column'] ?? '' ) );
		$column    = $this->sanitize_column_ref( $reference, $restricted );
		if ( $restricted ) {
			return null === $join_index
				? new \WP_Error( 'invalid_where', sprintf( 'WHERE predicate cannot use a restricted column (%s). Credential columns are never read or compared; filter on another column.', $reference ) )
				: new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON predicate cannot use a restricted column (%s). Credential columns are never read or compared; join on another column.', $join_index + 1, $reference ) );
		}
		if ( '' === $column ) {
			return new \WP_Error( 'invalid_where', 'WHERE predicate column must be a valid identifier.' );
		}

		$operator = $this->normalize_where_operator( (string) ( $condition['operator'] ?? '=' ) );
		if ( '' === $operator ) {
			return new \WP_Error( 'invalid_where', 'WHERE predicate operator is not supported.' );
		}

		$value = $condition['value'] ?? null;

		if ( in_array( $operator, array( 'IS', 'IS NOT' ), true ) ) {
			if ( null === $value || ( is_string( $value ) && 'NULL' === strtoupper( $value ) ) ) {
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
				$error = $this->numeric_predicate_error( $reference, $item, $join_index );
				if ( null !== $error ) {
					return $error;
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

		// LIKE compares text patterns even when its left operand is numeric.
		if ( ! in_array( $operator, array( 'LIKE', 'NOT LIKE' ), true ) ) {
			$error = $this->numeric_predicate_error( $reference, $value, $join_index );
			if ( null !== $error ) {
				return $error;
			}
		}
		$sql = "{$column} {$operator} " . $this->placeholder_for_value( $value );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Column/operator are allowlisted and the value is bound here.
		return $wpdb->prepare( $sql, $value );
	}

	/**
	 * Reject a malformed numeric comparison only when SQL resolves the column unambiguously.
	 *
	 * @since 8.1.0
	 *
	 * @param string   $reference Validated column name or table-qualified name.
	 * @param mixed    $value Comparison value.
	 * @param int|null $join_index Zero-based JOIN index, or null for WHERE.
	 * @return \WP_Error|null Predicate refusal, if any.
	 */
	private function numeric_predicate_error( string $reference, $value, ?int $join_index ): ?\WP_Error {
		if ( ! $this->is_non_numeric_string( $value ) ) {
			return null;
		}
		if ( null === $this->predicate_column_types && ! $this->load_predicate_column_types() ) {
			return new \WP_Error( 'database_column_types_unavailable', 'Could not check the database column types for this comparison.' );
		}

		$parts     = explode( '.', $reference );
		$column    = strtolower( array_pop( $parts ) );
		$qualifier = $parts[0] ?? '';
		if ( $this->table_names_ignore_case() ) {
			$qualifier = strtolower( $qualifier );
		}
		// JOIN ON can reference the tables introduced up to that join.
		$sources = null === $join_index ? $this->predicate_column_types : array_slice( $this->predicate_column_types, 0, $join_index + 2 );
		$types   = array();
		foreach ( $sources as $source ) {
			if ( ( '' === $qualifier || $source['qualifier'] === $qualifier ) && isset( $source['columns'][ $column ] ) ) {
				$types[] = $source['columns'][ $column ];
			}
		}
		if ( 1 !== count( $types ) || ! $this->is_numeric_column_type( $types[0] ) ) {
			return null;
		}
		$context = null === $join_index ? 'WHERE' : sprintf( 'Join #%d ON', $join_index + 1 );
		return new \WP_Error(
			null === $join_index ? 'invalid_where' : 'invalid_join',
			sprintf( '%s predicate value must be numeric for column %s.', $context, $reference )
		);
	}

	/**
	 * Read the types of columns in the already approved tables without reading row values.
	 *
	 * @since 8.1.0
	 *
	 * @return bool Whether all table metadata was loaded.
	 */
	private function load_predicate_column_types(): bool {
		$refs     = array_filter( array_map( array( $this, 'parse_table_ref' ), $this->predicate_table_refs ) );
		$names    = array_unique( array_column( $refs, 'table' ) );
		$by_table = $this->database_boundary_service()->column_types( $names );
		if ( ! $by_table ) {
			return false;
		}

		$ignore_case = $this->table_names_ignore_case();
		$sources     = array();
		foreach ( $refs as $ref ) {
			$name      = $ignore_case ? strtolower( $ref['table'] ) : $ref['table'];
			$qualifier = '' === $ref['alias'] ? $name : $ref['alias'];
			if ( ! isset( $by_table[ $name ] ) ) {
				return false;
			}
			$sources[] = array(
				'qualifier' => $ignore_case ? strtolower( $qualifier ) : $qualifier,
				'columns'   => $by_table[ $name ],
			);
		}
		$this->predicate_column_types = $sources;
		return true;
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
			return $this->build_where_value_predicate( $condition, $join_index );
		}

		$left_ref  = trim( (string) ( $condition['left'] ?? '' ) );
		$right_ref = trim( (string) ( $condition['right'] ?? '' ) );
		$left      = $this->sanitize_column_ref( $left_ref, $left_restricted );
		$right     = $this->sanitize_column_ref( $right_ref, $right_restricted );

		if ( $left_restricted || $right_restricted ) {
			$restricted = array_filter( array( $left_restricted ? $left_ref : '', $right_restricted ? $right_ref : '' ) );
			return new \WP_Error( 'invalid_join', sprintf( 'Join #%d ON predicate cannot use a restricted column (%s).', $join_index + 1, implode( ', ', array_unique( $restricted ) ) ) );
		}
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
		$aliases  = array(
			'IS NULL'     => 'IS',
			'IS NOT NULL' => 'IS NOT',
		);
		$operator = $aliases[ $operator ] ?? $operator;
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
	 * @param string    $column_ref Raw column reference.
	 * @param bool|null $restricted Whether a valid reference is a credential column.
	 * @return string Backticked column reference or empty string.
	 */
	private function sanitize_column_ref( string $column_ref, ?bool &$restricted = null ): string {
		$restricted = false;
		$column_ref = trim( $column_ref );
		if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column_ref ) ) {
			return '';
		}

		$parts       = explode( '.', $column_ref );
		$column_name = end( $parts );
		$restricted  = Database_Secrets::is_secret_column( (string) $column_name );
		if ( $restricted ) {
			return '';
		}
		if ( ! $this->allow_redacted && $this->is_owner_redacted( $column_ref ) ) {
			$this->redaction_violations[] = $column_ref;
			return '';
		}
		$name_column = Database_Secrets::name_column_for( (string) $column_name );
		if ( null !== $name_column && ! $this->in_select && null !== $this->redaction ) {
			// Matching on a setting's value would reveal a redacted one by guessing; leave those rows out.
			$this->exclude_redacted_settings( 2 === count( $parts ) ? strtolower( $parts[0] ) : '', $name_column, (string) $column_name );
		}

		return '`' . implode( '`.`', $parts ) . '`';
	}

	/**
	 * Leave the redacted settings of a name/value table out of the query.
	 *
	 * @param string $qualifier    Table alias or name, or '' when unqualified.
	 * @param string $name_column  The column that names each row.
	 * @param string $value_column The value column the request uses, such as meta_value.
	 * @return void
	 */
	private function exclude_redacted_settings( string $qualifier, string $name_column, string $value_column ): void {
		$prefix = $this->name_qualifier( $qualifier, $value_column, $name_column );
		if ( null === $prefix ) {
			return;
		}
		$reference = '`' . ( '' !== $prefix ? $prefix . '`.`' : '' ) . $name_column . '`';

		$this->setting_exclusions[ $reference ] = $this->owner_setting_prefixes( '' !== $prefix ? strtolower( $prefix ) : $qualifier );
	}

	/**
	 * A lower-cased table name or alias as the caller wrote it.
	 *
	 * @since 8.1.0
	 *
	 * @param string $qualifier Lower-cased table alias or name.
	 * @return string
	 */
	private function as_written( string $qualifier ): string {
		return $this->query_aliases[ $qualifier ] ?? $qualifier;
	}

	/**
	 * How to name the setting-name column of the table a value column comes from.
	 *
	 * The name must come from the table that supplies the value. A plugin table can
	 * have meta_value and no meta_key; its rows have no setting name to check, so
	 * nothing is paired with or excluded by one. An unqualified value in a join is
	 * matched to the one table that has that column, and its name column is
	 * qualified with that table, never taken from another table (QA D101). Inside
	 * a JOIN ON, only the tables joined up to it are looked at, as MySQL does. When
	 * column metadata can't be read, the reference is used as written, as before.
	 *
	 * @since 8.1.0
	 *
	 * @param string $qualifier    Lower-cased table alias or name, or '' when unqualified.
	 * @param string $value_column The value column, such as meta_value.
	 * @param string $name_column  The column that names each row, such as meta_key.
	 * @return string|null The qualifier as written ('' for none), or null when there is no name column to use.
	 */
	private function name_qualifier( string $qualifier, string $value_column, string $name_column ): ?string {
		if ( null === $this->predicate_column_types && ! $this->load_predicate_column_types() ) {
			return '' !== $qualifier ? $this->as_written( $qualifier ) : '';
		}
		$value_column = strtolower( $value_column );
		$name_column  = strtolower( $name_column );
		$in_scope     = null === $this->tables_in_scope ? $this->predicate_column_types : array_slice( $this->predicate_column_types, 0, $this->tables_in_scope );
		$sources      = array();
		foreach ( $in_scope as $source ) {
			$matches = '' !== $qualifier ? strtolower( $source['qualifier'] ) === $qualifier : isset( $source['columns'][ $value_column ] );
			if ( $matches ) {
				$sources[] = $source;
			}
		}
		if ( 1 !== count( $sources ) || ! isset( $sources[0]['columns'][ $name_column ] ) ) {
			return null;
		}
		if ( '' === $qualifier && 1 === count( $this->predicate_column_types ) ) {
			// One table in the whole request: the name needs no qualifier.
			return '';
		}
		return $this->as_written( strtolower( $sources[0]['qualifier'] ) );
	}

	/**
	 * The owner's redacted setting-name prefixes for the table a qualifier names.
	 *
	 * An unqualified reference could come from any table in the request.
	 *
	 * @param string $qualifier Table alias or name, or ''.
	 * @return string[]
	 */
	private function owner_setting_prefixes( string $qualifier ): array {
		if ( null === $this->redaction ) {
			return array();
		}
		$tables = '' !== $qualifier ? array( $this->query_tables[ $qualifier ] ?? $qualifier ) : array_values( array_unique( $this->query_tables ) );
		return $this->redaction->setting_prefixes( $tables );
	}

	/**
	 * Every table the request reads, keyed by its lower-cased name and alias.
	 *
	 * @param string $table Primary table reference.
	 * @param array  $joins Join definitions.
	 * @return array<string,string>
	 */
	private function query_table_names( string $table, array $joins ): array {
		$refs = array( $table );
		foreach ( $joins as $join ) {
			if ( is_array( $join ) && ! empty( $join['table'] ) ) {
				$refs[] = (string) $join['table'];
			}
		}
		$names = array();
		foreach ( $refs as $ref ) {
			$parsed = $this->parse_table_ref( $ref );
			if ( null === $parsed ) {
				continue;
			}
			$name                          = strtolower( $parsed['table'] );
			$alias                         = '' !== $parsed['alias'] ? strtolower( $parsed['alias'] ) : $name;
			$names[ $name ]                = $name;
			$names[ $alias ]               = $name;
			$this->query_aliases[ $name ]  = $parsed['table'];
			$this->query_aliases[ $alias ] = '' !== $parsed['alias'] ? $parsed['alias'] : $parsed['table'];
		}
		return $names;
	}

	/**
	 * Whether the owner's table column redaction covers a column reference.
	 *
	 * A qualified reference is matched against its table. An unqualified one could
	 * come from any table in the request, so it counts as redacted if any of them is.
	 *
	 * @param string $column_ref A one- or two-part column reference.
	 * @return bool
	 */
	private function is_owner_redacted( string $column_ref ): bool {
		if ( null === $this->redaction ) {
			return false;
		}
		$parts = explode( '.', $column_ref );
		if ( 2 === count( $parts ) ) {
			$qualifier = strtolower( $parts[0] );
			return $this->redaction->is_redacted( $parts[1], array( $this->query_tables[ $qualifier ] ?? $qualifier ) );
		}
		return $this->redaction->is_redacted( $parts[0], array_values( array_unique( $this->query_tables ) ) );
	}

	/**
	 * The refusal for a request that used a redacted column other than by selecting it.
	 *
	 * @return array|null JSON-RPC error, or null when none was used.
	 */
	private function redaction_refusal(): ?array {
		if ( empty( $this->redaction_violations ) ) {
			return null;
		}
		return Json_Rpc_Response::create_error_response(
			sprintf(
				'These columns are redacted on this site (Table column redaction) and cannot be used to filter, join, sort or group, or in SUM, AVG, MIN or MAX: %s. You can select them, and COUNT them; their values read as [REDACTED].',
				implode( ', ', array_unique( $this->redaction_violations ) )
			)
		);
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
		$result = $this->qualify_table_name( $table );

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
	 * The parts of a groupby or orderby value that are not a usable column reference.
	 *
	 * @param string $value Raw groupby or orderby value.
	 * @param bool   $several Whether the value may list several columns.
	 * @return string[] Unusable parts, as given.
	 */
	private function unusable_identifiers( string $value, bool $several ): array {
		$value = trim( $value );
		if ( '' === $value ) {
			return array();
		}
		$parts = $several ? array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) : array( $value );

		$unusable = array();
		foreach ( $parts as $part ) {
			// Checked without recording exclusions or redaction again; sanitizing already did that.
			if ( 1 !== preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $part ) ) {
				$unusable[] = $part;
				continue;
			}
			$column = substr( (string) strrchr( '.' . $part, '.' ), 1 );
			if ( Database_Secrets::is_secret_column( $column ) ) {
				$unusable[] = $part;
			}
		}
		return $unusable;
	}

	/**
	 * A refusal naming the unusable groupby or orderby parts and what the parameter accepts.
	 *
	 * @param string   $parameter groupby or orderby.
	 * @param string[] $unusable  The parts that cannot be used.
	 * @param string   $accepts   What the parameter takes.
	 * @param string   $hint      How to give several columns or a direction.
	 * @return string
	 */
	private function identifier_refusal( string $parameter, array $unusable, string $accepts, string $hint ): string {
		$restricted = array_filter(
			$unusable,
			static function ( $part ) {
				return Database_Secrets::is_secret_column( substr( (string) strrchr( '.' . $part, '.' ), 1 ) );
			}
		);
		if ( count( $restricted ) === count( $unusable ) ) {
			return sprintf( '%s cannot use %s: password hashes, post passwords, activation keys and WooCommerce API keys are restricted.', $parameter, implode( ', ', $unusable ) );
		}

		return sprintf(
			'%1$s takes %2$s, or a name given with AS in columns. It cannot use %3$s: functions, expressions and restricted columns are not supported. %4$s',
			$parameter,
			$accepts,
			implode( ', ', $unusable ),
			$hint
		);
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
		$source  = $this->output_sources[ strtolower( $orderby ) ] ?? null;
		if ( null === $source ) {
			return $this->sanitize_column_ref( $orderby );
		}
		// MySQL sorts by an output name before a column name, so the sort is judged by the column that output reads.
		if ( Database_Secrets::is_secret_column( $orderby ) || ( '' !== $source && '' === $this->sanitize_column_ref( $source ) ) ) {
			return '';
		}
		return '`' . $orderby . '`';
	}

	/**
	 * Sanitize SELECT column list.
	 *
	 * Supports column aliases with explicit AS syntax.
	 *
	 * @since 7.2.4
	 *
	 * @param string $columns Raw columns string.
	 * @param bool   $grouped Whether the query groups rows.
	 * @return string|\WP_Error Sanitized SQL column list or error.
	 */
	private function sanitize_columns( string $columns, bool $grouped = false ) {
		$refused              = array();
		$selected             = array();
		$aggregated           = array();
		$outputs              = array();
		$this->setting_pairs  = array();
		$this->output_sources = array();
		$column_list          = array_map(
			function ( $col ) use ( &$refused, &$selected, &$aggregated, &$outputs ) {
				$raw = trim( $col );
				if ( '' === $raw ) {
					return '';
				}

				$alias = '';
				$expr  = $raw;

				// Explicit alias form: "expression AS alias".
				if ( preg_match( '/^(.+?)\s+AS\s+([a-zA-Z_][a-zA-Z0-9_]*)$/i', $raw, $matches ) ) {
					$expr  = trim( $matches[1] );
					$alias = $matches[2];
				}

				// A sum, average, minimum or maximum of a stored setting can be one row's value, so its
				// credential rows are left out. A count shows no value and is left as asked.
				if ( preg_match( '/^(?:SUM|AVG|MIN|MAX)\(\s*(?:([a-zA-Z_][a-zA-Z0-9_]*)\.)?([a-zA-Z_][a-zA-Z0-9_]*)\s*\)$/i', $expr, $aggregate ) && null !== Database_Secrets::name_column_for( $aggregate[2] ) ) {
					$aggregated[] = array(
						'qualifier' => strtolower( $aggregate[1] ),
						'column'    => strtolower( $aggregate[2] ),
					);
				}
				$source = '';
				if ( preg_match( '/^(?:([a-zA-Z_][a-zA-Z0-9_]*)\.)?([a-zA-Z_][a-zA-Z0-9_]*)$/', $expr, $reference ) ) {
					$key    = '' !== $alias ? $alias : $reference[2];
					$source = $expr;
					// The owner redacted this column: select a constant, so its value never leaves the database.
					if ( ! Database_Secrets::is_secret_column( $reference[2] ) && $this->is_owner_redacted( $expr ) ) {
						$outputs[] = array(
							'name'   => $key,
							'source' => $source,
						);
						return "'" . Database_Secrets::MARKER . "' AS `" . $key . '`';
					}
					$selected[] = array(
						'qualifier' => strtolower( $reference[1] ),
						'column'    => strtolower( $reference[2] ),
						'key'       => $key,
					);
				}

				$this->in_select = true;
				$expr            = $this->sanitize_select_expression( $expr );
				$this->in_select = false;
				if ( '' === $expr ) {
					$refused[] = $raw;
					return '';
				}

				// MySQL names an unnamed function after its text, and a column after the column alone.
				$outputs[] = array(
					'name'   => '' !== $alias ? $alias : ( $reference[2] ?? $expr ),
					'source' => $source,
				);

				if ( '' !== $alias ) {
					$expr .= ' AS `' . $alias . '`';
				}

				return $expr;
			},
			self::split_columns( $columns )
		);

		$column_list = array_filter( $column_list );
		$supported   = 'List the table columns and select the names you need, or use COUNT(*) AS total to count matching rows. Named columns support AS, COUNT, SUM, AVG, MIN and MAX. Password hashes, post passwords, activation keys and WooCommerce API keys are never returned.';

		if ( empty( $column_list ) ) {
			return new \WP_Error( 'invalid_columns', trim( 'No valid SELECT columns were provided. ' . ( $refused ? 'Not supported: ' . implode( '; ', $refused ) . '. ' : '' ) . $supported ) );
		}

		// A column left out quietly reads as an empty value, so refuse the whole request.
		if ( $refused ) {
			return new \WP_Error( 'invalid_columns', sprintf( 'These SELECT columns are not supported: %s. %s', implode( '; ', $refused ), $supported ) );
		}

		$refusal = $this->output_name_refusal( array_column( $outputs, 'name' ) );
		if ( null !== $refusal ) {
			return $refusal;
		}
		$this->output_sources = array_change_key_case( array_column( $outputs, 'source', 'name' ), CASE_LOWER );

		// A stored setting is a credential or not by its name, so read the name with it. The name is
		// always read under a key of Automator's own: a column the caller selected can be renamed,
		// and an output name that reads like a credential column is dropped before the value is judged.
		foreach ( $selected as $value ) {
			$name_column = Database_Secrets::name_column_for( $value['column'] );
			$prefix      = null === $name_column ? null : $this->name_qualifier( $value['qualifier'], $value['column'], $name_column );
			if ( null === $name_column || null === $prefix ) {
				continue;
			}
			if ( $grouped ) {
				// A grouped row can stand for several settings, so leave the redacted ones out of it.
				$this->exclude_redacted_settings( $value['qualifier'], $name_column, $value['column'] );
				continue;
			}
			$hidden                = self::HIDDEN_SETTING_NAME . count( $this->setting_pairs );
			$column_list[]         = $this->sanitize_column_ref( ( '' !== $prefix ? $prefix . '.' : '' ) . $name_column ) . ' AS `' . $hidden . '`';
			$this->setting_pairs[] = array(
				'value'    => $value['key'],
				'name'     => $hidden,
				'prefixes' => $this->owner_setting_prefixes( '' !== $prefix ? strtolower( $prefix ) : $value['qualifier'] ),
			);
		}
		foreach ( $aggregated as $value ) {
			$this->exclude_redacted_settings( $value['qualifier'], (string) Database_Secrets::name_column_for( $value['column'] ), $value['column'] );
		}

		return implode( ', ', $column_list );
	}

	/**
	 * The refusal for a column list whose output names cannot each stand for one column.
	 *
	 * A row is keyed by output name, so of two columns with one name only the last
	 * reaches the response. A setting's value is judged by the name read under its
	 * name column's output name; a second column under that name would have the value
	 * judged by the wrong name and returned (#9697). MySQL compares these names
	 * without case, so they are compared that way here. A name that starts like the
	 * one Automator reads a setting's name under is removed from every row.
	 *
	 * @since 8.1.0
	 *
	 * @param string[] $outputs Output names in the order selected.
	 * @return \WP_Error|null
	 */
	private function output_name_refusal( array $outputs ): ?\WP_Error {
		$first    = array();
		$repeated = array();
		$reserved = array();
		foreach ( $outputs as $output ) {
			$key = strtolower( $output );
			if ( 0 === strpos( $key, self::HIDDEN_SETTING_NAME ) ) {
				$reserved[] = $output;
			}
			if ( isset( $first[ $key ] ) ) {
				$repeated[ $key ] = $first[ $key ];
				continue;
			}
			$first[ $key ] = $output;
		}

		if ( $reserved ) {
			return new \WP_Error( 'invalid_columns', sprintf( 'These output names are reserved by Automator: %s. Choose another name after AS.', implode( ', ', $reserved ) ) );
		}
		if ( $repeated ) {
			return new \WP_Error( 'invalid_columns', sprintf( 'These output names are used more than once: %s. Each selected column needs its own name in the response. Give one of the repeated columns another name with AS, such as "p.ID AS post_id".', implode( ', ', $repeated ) ) );
		}

		return null;
	}

	/**
	 * Split a column list at commas outside parentheses, so a function's arguments stay one column.
	 *
	 * @param string $columns Comma-separated columns.
	 * @return string[]
	 */
	private static function split_columns( string $columns ): array {
		$parts   = array();
		$current = '';
		$depth   = 0;
		foreach ( str_split( $columns ) as $char ) {
			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char && $depth > 0 ) {
				--$depth;
			} elseif ( ',' === $char && 0 === $depth ) {
				$parts[] = $current;
				$current = '';
				continue;
			}
			$current .= $char;
		}
		$parts[] = $current;
		return $parts;
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

		$column = $this->sanitize_column_ref( $expr );
		if ( '' !== $column ) {
			return $column;
		}

		if ( preg_match( '/^(COUNT|SUM|AVG|MIN|MAX)\(\s*(\*|[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?)\s*\)$/i', $expr, $matches ) ) {
			$function = strtoupper( $matches[1] );
			// A count shows no value, so it may count a redacted column.
			$this->allow_redacted = 'COUNT' === $function;
			$argument             = '*' === $matches[2] ? '*' : $this->sanitize_column_ref( $matches[2] );
			$this->allow_redacted = false;

			if ( '' === $argument || ( '*' === $argument && 'COUNT' !== $function ) ) {
				return '';
			}

			return "{$function}({$argument})";
		}

		return '';
	}

	/**
	 * Remove sensitive columns from query result rows.
	 *
	 * Output aliases receive the same authentication-field checks as selected columns.
	 *
	 * @param array $rows Query result rows.
	 *
	 * @return array Rows with sensitive columns stripped.
	 */
	private function redact_sensitive_columns( array $rows ): array {

		if ( empty( $rows ) ) {
			return $rows;
		}

		// An alias can name a column like a credential; drop it as the column would be.
		$columns_to_strip = array_filter(
			array_keys( $rows[0] ),
			static function ( $col ) {
				return Database_Secrets::is_secret_column( (string) $col );
			}
		);
		$setting_names    = array_column( $this->setting_pairs, 'name', 'value' );
		$setting_prefixes = array_column( $this->setting_pairs, 'prefixes', 'value' );

		foreach ( $rows as &$row ) {
			foreach ( $columns_to_strip as $col ) {
				unset( $row[ $col ] );
			}
			foreach ( $row as $key => $value ) {
				$name_key    = $setting_names[ $key ] ?? null;
				$row[ $key ] = Database_Secrets::redact_cell( $value, null !== $name_key ? (string) ( $row[ $name_key ] ?? '' ) : null, $setting_prefixes[ $key ] ?? array() );
			}
			foreach ( array_keys( $row ) as $key ) {
				if ( 0 === strpos( (string) $key, self::HIDDEN_SETTING_NAME ) ) {
					unset( $row[ $key ] );
				}
			}
		}
		unset( $row );

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
					// The value's characters in the preview, without the note: the read_cell offset to continue from.
					'returned_chars'  => $prefix_chars,
					'chars_remaining' => $chars_remaining,
					'next_offset'     => $prefix_chars,
				);
			}
		}

		return $rows;
	}

	/**
	 * Offer a continuation key only for a direct projection of one table.
	 *
	 * @since 8.1.0
	 * @param array $cells Truncated cell metadata.
	 * @param array $rows Returned, redacted row previews.
	 * @param string $table Validated base table name.
	 * @param string $columns Requested projection.
	 * @param mixed $where Requested predicate, or null when another row might exist.
	 */
	private function add_truncated_cell_row_keys( array &$cells, array $rows, string $table, string $columns, $where ): void {
		// Keep provenance for plain column references, including AS aliases.
		// Expressions can hide which source supplied a value. Output names are unique by now.
		$projection = array();
		foreach ( self::split_columns( $columns ) as $column ) {
			if ( ! preg_match( '/^(?:[a-zA-Z_][a-zA-Z0-9_]*\.)?([a-zA-Z_][a-zA-Z0-9_]*)(?:\s+AS\s+([a-zA-Z_][a-zA-Z0-9_]*))?$/iD', trim( $column ), $matches ) ) {
				return;
			}
			$projection[ strtolower( $matches[2] ?? $matches[1] ) ] = strtolower( $matches[1] );
		}

		$definitions = $this->database_boundary_service()->continuation_columns( $table );
		$primary     = array_keys(
			array_filter(
				$definitions,
				static function ( array $column ): bool {
					return (bool) $column['is_primary'];
				}
			)
		);
		if ( 1 !== count( $primary ) ) {
			return;
		}

		$key     = $primary[0];
		$integer = in_array( $definitions[ $key ]['data_type'], array( 'tinyint', 'smallint', 'mediumint', 'int', 'bigint' ), true );
		if ( ( ! $integer && empty( $definitions[ $key ]['charset'] ) )
			|| Database_Secrets::is_secret_column( $key ) || null !== Database_Secrets::name_column_for( $key ) || $this->is_owner_redacted( $key ) ) {
			return;
		}

		// Values already supplied in an exact primary-key predicate need no extra SELECT field.
		$predicate_key = 1 === count( $rows ) ? $this->exact_row_key( $where, $key ) : null;
		$key_output    = array_search( strtolower( $key ), $projection, true );
		$definitions   = array_change_key_case( $definitions, CASE_LOWER );
		foreach ( $cells as &$cell ) {
			$source = $projection[ strtolower( $cell['column'] ) ] ?? '';
			$target = $definitions[ $source ] ?? array();
			if ( empty( $target['charset'] ) && 'json' !== ( $target['data_type'] ?? '' ) ) {
				continue;
			}

			$row   = array_change_key_case( $rows[ $cell['row_index'] ], CASE_LOWER );
			$value = false !== $key_output && array_key_exists( $key_output, $row ) ? $row[ $key_output ] : $predicate_key;
			$value = $this->continuation_key_value( $value, $integer );
			if ( null !== $value ) {
				$cell['row_key'] = array(
					'key_column' => $key,
					'key_value'  => $value,
				);
			}
		}
		unset( $cell );
	}

	/**
	 * Read a key value supplied in an already validated equality predicate.
	 *
	 * @since 8.1.0
	 * @param mixed $where Approved WHERE input.
	 * @param string $key Actual primary key column.
	 * @return mixed Value passed to the equality predicate, when known.
	 */
	private function exact_row_key( $where, string $key ) {
		$predicate = is_string( $where ) ? $this->parse_legacy_where_string( $where ) : $where;
		if ( $this->is_condition_list( $predicate ) && 1 === count( $predicate ) ) {
			$predicate = $predicate[0];
		}
		if ( ! is_array( $predicate ) || isset( $predicate['conditions'] ) || '=' !== ( $predicate['operator'] ?? '=' ) ) {
			return null;
		}

		$reference = explode( '.', trim( (string) ( $predicate['column'] ?? '' ) ) );
		$value     = $predicate['value'] ?? null;
		if ( 0 !== strcasecmp( $key, end( $reference ) ) ) {
			return null;
		}
		// A legacy decimal outside PHP's integer range can be clamped when parsed.
		if ( is_string( $where ) && is_int( $value ) && ! preg_match( '/=\s*' . preg_quote( (string) $value, '/' ) . '\s*$/D', $where ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Preserve key identity across JSON and the database's parameter binding.
	 *
	 * @since 8.1.0
	 * @param mixed $value Key from the redacted row or the original predicate.
	 * @param bool  $integer Whether the column uses integer storage.
	 * @return int|string|null A key that read_cell can bind exactly.
	 */
	private function continuation_key_value( $value, bool $integer ) {
		if ( $integer ) {
			if ( ! is_int( $value ) && ( ! is_string( $value ) || (string) (int) $value !== $value ) ) {
				return null;
			}
			$key = (int) $value;
			// Stay within the exact integer range of JavaScript MCP clients.
			return $key >= -9007199254740991 && $key <= 9007199254740991 ? $key : null;
		}

		if ( is_string( $value ) && false === strpos( $value, Database_Secrets::MARKER )
			&& $this->char_length( $value ) < self::PREVIEW_MAX_CHARS && Database_Secrets::redact_cell( $value ) === $value ) {
			return $value;
		}

		return null;
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
