<?php
/**
 * MySQL Get Tables Tool.
 *
 * Lists all database tables with their row counts.
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
 * MySQL Get Tables Tool.
 */
class Mysql_Get_Tables_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'mysql_get_tables';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'List all database tables. Use to discover plugin data structures, custom tables, or understand the database schema. Returns table names and row counts. Use search parameter to filter by table name.';
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
				'search' => array(
					'type'        => 'string',
					'description' => 'Filter tables by name (e.g., "woocommerce", "users", "posts"). Partial match supported.',
				),
				'limit'  => array(
					'type'        => 'integer',
					'default'     => 50,
					'maximum'     => 200,
					'description' => 'Maximum tables to return.',
				),
			),
			'required'   => array(),
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

		$search = $params['search'] ?? '';
		$limit  = min( (int) ( $params['limit'] ?? 50 ), 200 );

		// Get all tables with row counts.
		$query = "SELECT TABLE_NAME, TABLE_ROWS
				  FROM information_schema.TABLES
				  WHERE TABLE_SCHEMA = %s";

		$query_params = array( DB_NAME );

		if ( ! empty( $search ) ) {
			$query         .= ' AND TABLE_NAME LIKE %s';
			$query_params[] = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
		}

		$query         .= ' ORDER BY TABLE_NAME ASC LIMIT %d';
		$query_params[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$tables = $wpdb->get_results( $wpdb->prepare( $query, $query_params ) );

		if ( null === $tables ) {
			return Json_Rpc_Response::create_error_response( 'Failed to retrieve tables: ' . $wpdb->last_error );
		}

		$items = array_map(
			function ( $table ) use ( $wpdb ) {
				$name = $table->TABLE_NAME; // phpcs:ignore
				return array(
					'name'       => $name,
					'row_count'  => (int) $table->TABLE_ROWS, // phpcs:ignore
					'is_wp_core' => strpos( $name, $wpdb->prefix ) === 0 && in_array(
						str_replace( $wpdb->prefix, '', $name ),
						array( 'posts', 'postmeta', 'options', 'users', 'usermeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'comments', 'commentmeta', 'links' ),
						true
					),
				);
			},
			$tables
		);

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Found %d tables', count( $items ) ),
			array(
				'database' => DB_NAME,
				'prefix'   => $wpdb->prefix,
				'tables'   => $items,
				'total'    => count( $items ),
			)
		);
	}
}
