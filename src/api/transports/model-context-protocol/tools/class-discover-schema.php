<?php
/**
 * Discover Schema Tool.
 *
 * Discovers structural database elements like meta keys, option names,
 * or database tables. Useful for data discovery before querying values.
 *
 * @package Uncanny_Automator
 * @since   7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;

/**
 * Discover Schema Tool.
 *
 * MCP tool for discovering database schema elements.
 *
 * @since 7.0.0
 */
class Discover_Schema_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 *
	 * @return string Tool name.
	 */
	public function get_name(): string {
		return 'discover_schema';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 *
	 * @return string Tool description.
	 */
	public function get_description(): string {
		return 'Discover structural database elements: meta keys, option names, or tables. Use for data discovery BEFORE querying actual values.';
	}

	/**
	 * Define input schema.
	 *
	 * @since 7.0.0
	 *
	 * @return array JSON Schema for tool input parameters.
	 */
	protected function schema_definition(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'type'   => array(
					'type'        => 'string',
					'enum'        => array( 'post_meta', 'user_meta', 'options', 'tables' ),
					'description' => 'What to discover.',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Optional filter (e.g., "learndash", "woo"). Searches with LIKE %search%.',
				),
				'limit'  => array(
					'type'        => 'integer',
					'default'     => 100,
					'maximum'     => 500,
					'description' => 'Max results to return.',
				),
			),
			'required'   => array( 'type' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context User context.
	 * @param array        $params       Tool parameters.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		global $wpdb;

		$type   = sanitize_key( $params['type'] ?? '' );
		$search = sanitize_text_field( $params['search'] ?? '' );
		$limit  = min( absint( $params['limit'] ?? 100 ), 500 );

		// Route to appropriate query method based on type.
		switch ( $type ) {
			case 'post_meta':
				$results = $this->query_distinct( $wpdb->postmeta, 'meta_key', $search, $limit );
				break;

			case 'user_meta':
				$results = $this->query_distinct( $wpdb->usermeta, 'meta_key', $search, $limit );
				break;

			case 'options':
				$results = $this->query_distinct( $wpdb->options, 'option_name', $search, $limit );
				break;

			case 'tables':
				$results = $this->query_tables( $search, $limit );
				break;

			default:
				return Json_Rpc_Response::create_error_response(
					sprintf( 'Invalid type: %s', $type )
				);
		}

		// Check for database errors.
		if ( ! empty( $wpdb->last_error ) ) {
			return Json_Rpc_Response::create_error_response(
				'Database query failed: ' . $wpdb->last_error
			);
		}

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Found %d %s keys', count( $results ), $type ),
			array(
				'type'  => $type,
				'items' => $results,
				'total' => count( $results ),
			)
		);
	}

	/**
	 * Query distinct values from a table column.
	 *
	 * @since 7.0.0
	 *
	 * @param string $table  Table name (from $wpdb properties, already prefixed).
	 * @param string $column Column name to query (meta_key or option_name).
	 * @param string $search Optional search filter.
	 * @param int    $limit  Maximum results.
	 * @return array Array of distinct values.
	 */
	private function query_distinct( string $table, string $column, string $search, int $limit ): array {
		global $wpdb;

		// Column and table names come from $wpdb properties and are safe.
		// They cannot be parameterized with prepare(), so we use them directly.
		if ( ! empty( $search ) ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$results = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT {$column} FROM {$table} WHERE {$column} LIKE %s ORDER BY {$column} ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are validated $wpdb properties.
					$like,
					$limit
				)
			);
		} else {
			$results = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT {$column} FROM {$table} ORDER BY {$column} ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are validated $wpdb properties.
					$limit
				)
			);
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Query database tables.
	 *
	 * @since 7.0.0
	 *
	 * @param string $search Optional search filter.
	 * @param int    $limit  Maximum results.
	 * @return array Array of table names.
	 */
	private function query_tables( string $search, int $limit ): array {
		global $wpdb;

		if ( ! empty( $search ) ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$results = $wpdb->get_col(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $like )
			);

			// SHOW TABLES LIKE doesn't support LIMIT, so we slice.
			$results = array_slice( $results, 0, $limit );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_col( 'SHOW TABLES' );
			$results = array_slice( $results, 0, $limit );
		}

		return is_array( $results ) ? $results : array();
	}
}
