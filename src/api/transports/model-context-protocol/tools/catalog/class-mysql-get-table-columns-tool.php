<?php
/**
 * MySQL Get Table Columns Tool.
 *
 * Retrieves column information for a specific database table.
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
 * MySQL Get Table Columns Tool.
 */
class Mysql_Get_Table_Columns_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'mysql_get_table_columns';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'Get column schema for one or more database tables in a single call. Accepts comma-separated table names for bulk introspection. Returns column names, types, keys, and defaults grouped by table. Essential for understanding table structure before building SELECT or JOIN queries.';
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
				'tables' => array(
					'type'        => 'string',
					'description' => 'Comma-separated table names (e.g., "wp_posts, wp_postmeta, wp_users"). Accepts one or many tables for bulk schema introspection in a single call.',
				),
			),
			'required'   => array( 'tables' ),
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

		$tables_input = $params['tables'] ?? '';

		if ( empty( $tables_input ) ) {
			return Json_Rpc_Response::create_error_response( 'At least one table name is required.' );
		}

		// Parse and sanitize table names.
		$table_names = array_filter(
			array_map(
				function ( $t ) {
					return preg_replace( '/[^a-zA-Z0-9_]/', '', trim( $t ) );
				},
				explode( ',', $tables_input )
			)
		);

		if ( empty( $table_names ) ) {
			return Json_Rpc_Response::create_error_response( 'No valid table names provided.' );
		}

		// Cap at 20 tables to prevent abuse.
		if ( count( $table_names ) > 20 ) {
			return Json_Rpc_Response::create_error_response( 'Maximum 20 tables per request.' );
		}

		// Verify all tables exist in one query.
		$placeholders = implode( ', ', array_fill( 0, count( $table_names ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$existing_tables = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( DB_NAME ), $table_names )
			)
		);

		$missing = array_diff( $table_names, $existing_tables );

		if ( ! empty( $missing ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Tables not found: %s', implode( ', ', $missing ) )
			);
		}

		// Get column information for all tables in one query.
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$columns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
				 FROM information_schema.COLUMNS
				 WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN ({$placeholders})
				 ORDER BY TABLE_NAME, ORDINAL_POSITION", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( DB_NAME ), $table_names )
			)
		);

		if ( null === $columns ) {
			return Json_Rpc_Response::create_error_response( 'Failed to retrieve columns: ' . $wpdb->last_error );
		}

		// Group columns by table.
		$tables = array();

		foreach ( $columns as $col ) {
			$tbl = $col->TABLE_NAME; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			if ( ! isset( $tables[ $tbl ] ) ) {
				$tables[ $tbl ] = array(
					'columns'      => array(),
					'primary_keys' => array(),
				);
			}

			$item = array(
				'name'      => $col->COLUMN_NAME, // phpcs:ignore
				'type'      => $col->DATA_TYPE, // phpcs:ignore
				'full_type' => $col->COLUMN_TYPE, // phpcs:ignore
				'nullable'  => 'YES' === $col->IS_NULLABLE, // phpcs:ignore
				'key'       => $col->COLUMN_KEY, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'default'   => $col->COLUMN_DEFAULT, // phpcs:ignore
				'extra'     => $col->EXTRA, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			);

			$tables[ $tbl ]['columns'][] = $item;

			if ( 'PRI' === $col->COLUMN_KEY ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$tables[ $tbl ]['primary_keys'][] = $col->COLUMN_NAME; // phpcs:ignore
			}
		}

		// Add column counts.
		foreach ( $tables as $tbl => &$data ) {
			$data['column_count'] = count( $data['columns'] );
		}
		unset( $data );

		$table_count  = count( $tables );
		$column_count = count( $columns );

		return Json_Rpc_Response::create_success_response(
			sprintf( '%d table(s), %d columns total', $table_count, $column_count ),
			array(
				'tables' => $tables,
			)
		);
	}
}
