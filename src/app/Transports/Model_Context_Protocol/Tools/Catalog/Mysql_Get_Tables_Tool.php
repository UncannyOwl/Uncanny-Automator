<?php
/**
 * MySQL Get Tables Tool.
 *
 * Lists the database tables this site may read, with approximate row counts.
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
 * MySQL Get Tables Tool.
 */
class Mysql_Get_Tables_Tool extends Abstract_MCP_Tool {

	use Information_Schema_Query;

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
		return 'List database tables. Use to discover plugin data structures, custom tables, or understand the database schema. Returns table names and approximate_row_count, an InnoDB estimate that can be stale or zero for small tables; row_count is a deprecated alias for the same estimate. Use mysql_select_from_table with COUNT(*) for an exact count. On a single site, tables must start with the site prefix or be approved by the owner in Site tools settings. On a WordPress network only the current site\'s tables are listed; shared network tables also require a network administrator. total counts returned tables; has_more indicates additional matches. When has_more is true, pass next_offset as offset with the same search to continue.';
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
					'description' => 'Maximum tables to return. Use 1 to 200. Zero is refused. Negative values are treated as 1 for compatibility.',
				),
				'offset' => array(
					'type'        => 'integer',
					'default'     => 0,
					'minimum'     => 0,
					'description' => 'Pass next_offset from the previous page, using the same search.',
				),
			),
			'required'   => array(),
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
				'database'    => array( 'type' => 'string' ),
				'prefix'      => array( 'type' => 'string' ),
				'tables'      => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'                  => array( 'type' => 'string' ),
							'row_count'             => array(
								'type'        => 'integer',
								'description' => 'Deprecated alias of approximate_row_count, kept for existing consumers.',
							),
							'approximate_row_count' => array(
								'type'        => 'integer',
								'description' => 'Storage-engine estimate from information_schema, not an exact count.',
							),
							'is_wp_core'            => array( 'type' => 'boolean' ),
						),
					),
				),
				'total'       => array(
					'type'        => 'integer',
					'description' => 'Number of tables returned, not the total number of matching tables.',
				),
				'has_more'    => array( 'type' => 'boolean' ),
				'next_offset' => array(
					'type'        => 'integer',
					'description' => 'Offset for the next page. Present when more tables match.',
				),
			),
			'required'   => array( 'database', 'prefix', 'tables', 'total', 'has_more' ),
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

		if ( 0 === ( $params['limit'] ?? 50 ) ) {
			return Json_Rpc_Response::create_error_response( 'Limit must be between 1 and 200.' );
		}
		$search = $params['search'] ?? '';
		$limit  = max( 1, $params['limit'] ?? 50 );
		$offset = $params['offset'] ?? 0;
		$schema = $this->get_schema_name();

		if ( '' === $schema ) {
			return Json_Rpc_Response::create_error_response( 'The database name is not available, so tables cannot be listed.' );
		}
		if ( ! $this->has_expected_selected_database() ) {
			return Json_Rpc_Response::create_error_response( 'The WordPress database connection is pointing to an unexpected database. Ask your hosting provider to check the connection, then try again.' );
		}
		$known_mode   = $this->has_known_table_name_mode();
		$extra_tables = $this->get_extra_tables();
		$service      = $this->database_boundary_service( $extra_tables );
		$can_discover = $service->can_discover();
		if ( ! $can_discover && empty( $extra_tables ) ) {
			return Json_Rpc_Response::create_error_response( $service->discovery_failure_message() );
		}

		$site_prefix = $this->get_site_table_prefix();
		$ignore_case = $known_mode && ! $this->is_network_scoped() && $this->table_names_ignore_case();
		$tables      = $service->table_page(
			$can_discover ? $site_prefix : '',
			$this->get_restricted_network_tables( $user_context ),
			$this->is_network_scoped() && $site_prefix === $this->get_base_table_prefix(),
			$ignore_case,
			sanitize_text_field( $search ),
			$limit + 1,
			$offset
		);
		if ( null === $tables ) {
			return Json_Rpc_Response::create_error_response( 'Query failed: database table metadata is unavailable.' );
		}
		$tables = array_values(
			array_filter(
				$tables,
				function ( $table ) use ( $user_context, $service ) {
					return $this->is_table_available_on_this_site( $table['name'], $user_context )
						&& $service->permits_type( $table['type'], '0', $service->is_owner_approved_table( $table['name'] ) );
				}
			)
		);
		// A full page alone is not proof of truncation; only the extra match is.
		$has_more = count( $tables ) > $limit;
		$tables   = array_slice( $tables, 0, $limit );

		$items = array_map(
			function ( $table ) use ( $wpdb ) {
				$name = $table['name'];
				// Older clients read row_count directly. Keep it an alias so listing tables
				// stays a metadata query. Callers can request COUNT(*) for a specific table.
				return array(
					'name'                  => $name,
					'approximate_row_count' => $table['rows'],
					'row_count'             => $table['rows'],
					'is_wp_core'            => strpos( $name, $wpdb->prefix ) === 0 && in_array(
						str_replace( $wpdb->prefix, '', $name ),
						array( 'posts', 'postmeta', 'options', 'users', 'usermeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'comments', 'commentmeta', 'links' ),
						true
					),
				);
			},
			$tables
		);

		$message = $can_discover ? sprintf( 'Found %d tables.', count( $items ) ) : 'Only owner-approved tables and views are listed. ' . $service->schema_visibility_message();
		if ( $items ) {
			$message .= ' Both row_count and approximate_row_count are estimates. Use COUNT(*) for an exact count.';
		}
		return Json_Rpc_Response::create_success_response(
			$message,
			array(
				'database' => $schema,
				'prefix'   => $wpdb->prefix,
				'tables'   => $items,
				'total'    => count( $items ),
				'has_more' => $has_more,
			) + ( $has_more ? array( 'next_offset' => $offset + $limit ) : array() )
		);
	}
}
