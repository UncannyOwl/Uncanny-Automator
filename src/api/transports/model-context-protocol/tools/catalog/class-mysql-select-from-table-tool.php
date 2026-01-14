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

	/**
	 * Maximum rows to return.
	 */
	private const MAX_LIMIT = 100;

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
		return 'Query data from a database table. Read-only SELECT queries with WHERE filtering. Use mysql_get_table_columns first to understand available columns. Max 100 rows. Useful for finding option values, user data, or custom plugin data.';
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
					'description' => 'Full table name (e.g., wp_options, wp_usermeta).',
				),
				'columns' => array(
					'type'        => 'string',
					'description' => 'Comma-separated column names to select. Use "*" for all columns. Example: "ID,post_title,post_status"',
					'default'     => '*',
				),
				'where'   => array(
					'type'        => 'string',
					'description' => 'WHERE clause conditions WITHOUT the "WHERE" keyword. Example: "option_name LIKE \'%woocommerce%\'" or "meta_key = \'_subscription_status\'"',
				),
				'orderby' => array(
					'type'        => 'string',
					'description' => 'Column name to sort by.',
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
					'maximum'     => 100,
					'description' => 'Maximum rows to return (max 100).',
				),
			),
			'required'   => array( 'table' ),
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
		$where   = $params['where'] ?? '';
		$orderby = $params['orderby'] ?? '';
		$order   = strtoupper( $params['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
		$limit   = min( (int) ( $params['limit'] ?? 20 ), self::MAX_LIMIT );

		if ( empty( $table ) ) {
			return Json_Rpc_Response::create_error_response( 'Table name is required.' );
		}

		// Sanitize table name.
		$table = preg_replace( '/[^a-zA-Z0-9_]/', '', $table );

		// Verify table exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
				DB_NAME,
				$table
			)
		);

		if ( ! $exists ) {
			return Json_Rpc_Response::create_error_response( sprintf( 'Table "%s" does not exist.', $table ) );
		}

		// Build SELECT columns - sanitize each column name and wrap in backticks.
		if ( '*' !== $columns ) {
			$column_list = array_map(
				function ( $col ) {
					return preg_replace( '/[^a-zA-Z0-9_]/', '', trim( $col ) );
				},
				explode( ',', $columns )
			);
			$column_list = array_filter( $column_list );
			// Backticks protect reserved words like `order`, `select`, `key`.
			$select_cols = ! empty( $column_list ) ? '`' . implode( '`, `', $column_list ) . '`' : '*';
		} else {
			$select_cols = '*';
		}

		// Build query - table name already sanitized.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT {$select_cols} FROM `{$table}`";

		// Add WHERE clause if provided (user-provided, be careful).
		if ( ! empty( $where ) ) {
			// Basic SQL injection prevention - block dangerous keywords.
			// Use word boundaries to avoid false positives on column names like "last_created", "updated_at".
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
				// DoS vectors - no legitimate use in WHERE clause.
				'sleep',
				'benchmark',
				'wait_for_delay',
			);
			$blocked_symbols = array( '--', '/*', '*/', ';' );

			foreach ( $blocked_words as $keyword ) {
				if ( preg_match( '/\b' . $keyword . '\b/i', $where ) ) {
					return Json_Rpc_Response::create_error_response( 'WHERE clause contains blocked keyword: ' . $keyword );
				}
			}

			foreach ( $blocked_symbols as $symbol ) {
				if ( false !== strpos( $where, $symbol ) ) {
					return Json_Rpc_Response::create_error_response( 'WHERE clause contains blocked pattern: ' . $symbol );
				}
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query .= ' WHERE ' . $where;
		}

		// Add ORDER BY if provided.
		if ( ! empty( $orderby ) ) {
			$orderby_clean = preg_replace( '/[^a-zA-Z0-9_]/', '', $orderby );
			if ( ! empty( $orderby_clean ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$query .= " ORDER BY `{$orderby_clean}` {$order}";
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

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Retrieved %d rows from %s', count( $results ), $table ),
			array(
				'table'       => $table,
				'rows'        => $results,
				'row_count'   => count( $results ),
				'has_more'    => count( $results ) === $limit,
				'query_debug' => WP_DEBUG ? $query : null,
			)
		);
	}
}
