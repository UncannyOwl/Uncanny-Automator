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
		return 'Get column schema for a database table. Use after mysql_get_tables to understand table structure. Returns column names, types, keys, and defaults. Essential for building correct SELECT queries.';
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
				'table' => array(
					'type'        => 'string',
					'description' => 'Full table name (e.g., wp_posts, wp_usermeta). Use mysql_get_tables to discover table names.',
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

		$table = $params['table'] ?? '';

		if ( empty( $table ) ) {
			return Json_Rpc_Response::create_error_response( 'Table name is required.' );
		}

		// Sanitize table name - allow only alphanumeric, underscore.
		$table = preg_replace( '/[^a-zA-Z0-9_]/', '', $table );

		// Verify table exists in current database.
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

		// Get column information.
		$columns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
				 FROM information_schema.COLUMNS
				 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
				 ORDER BY ORDINAL_POSITION",
				DB_NAME,
				$table
			)
		);

		if ( null === $columns ) {
			return Json_Rpc_Response::create_error_response( 'Failed to retrieve columns: ' . $wpdb->last_error );
		}

		$items = array_map(
			function ( $col ) {
				return array(
					'name'      => $col->COLUMN_NAME, // phpcs:ignore
					'type'      => $col->DATA_TYPE, // phpcs:ignore
					'full_type' => $col->COLUMN_TYPE, // phpcs:ignore
					'nullable'  => 'YES' === $col->IS_NULLABLE, // phpcs:ignore
					'key'       => $col->COLUMN_KEY, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PRI, UNI, MUL, or empty.
					'default'   => $col->COLUMN_DEFAULT, // phpcs:ignore
					'extra'     => $col->EXTRA, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- e.g., auto_increment.
				);
			},
			$columns
		);

		// Get primary key columns.
		$primary_keys = array_values(
			array_filter(
				array_column( $items, 'name' ),
				function ( $name ) use ( $items ) {
					foreach ( $items as $item ) {
						if ( $item['name'] === $name && 'PRI' === $item['key'] ) {
							return true;
						}
					}
					return false;
				}
			)
		);

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Table %s has %d columns', $table, count( $items ) ),
			array(
				'table'        => $table,
				'columns'      => $items,
				'primary_keys' => $primary_keys,
				'column_count' => count( $items ),
			)
		);
	}
}
