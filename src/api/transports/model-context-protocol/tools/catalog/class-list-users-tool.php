<?php
/**
 * List Users Tool.
 *
 * Lists WordPress users using WP_User_Query parameters.
 * Pure WordPress tool - no integration-specific code.
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
 * List Users Tool.
 */
class List_Users_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'list_users';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'Search and query WordPress users. Partial name search across first name, last name, display name, nickname, username, and email. Filter by role, custom user meta with full comparison operators (=, !=, >, <, LIKE, IN, BETWEEN, EXISTS, etc.), or combine search with meta filters. Paginated results with offset/limit. Returns user ID, names, email, roles, and registration date.';
	}

	/**
	 * Define input schema.
	 *
	 * @return array
	 */
	protected function schema_definition() {
		// Get all roles for enum.
		$roles = array_keys( wp_roles()->roles );

		return array(
			'type'       => 'object',
			'properties' => array(
				'search'      => array(
					'type'        => 'string',
					'description' => 'Search term. Searches user_login, user_email, user_nicename, display_name, first_name, last_name, and nickname. Example: "Joseph" finds users with Joseph in any name field.',
				),
				'role'        => array(
					'type'        => 'string',
					'enum'        => $roles,
					'description' => 'Filter by user role.',
				),
				'email'       => array(
					'type'        => 'string',
					'description' => 'Exact email address lookup. Returns single user if found.',
				),
				'include'     => array(
					'type'        => 'string',
					'description' => 'Comma-separated user IDs to include.',
				),
				'exclude'     => array(
					'type'        => 'string',
					'description' => 'Comma-separated user IDs to exclude.',
				),
				'meta_key'    => array(
					'type'        => 'string',
					'description' => 'Filter by user meta key (e.g., billing_country, first_name).',
				),
				'meta_value'  => array(
					'type'        => 'string',
					'description' => 'Filter by user meta value (requires meta_key). For IN/NOT IN, pass comma-separated values. For BETWEEN/NOT BETWEEN, pass two comma-separated values.',
				),
				'meta_compare' => array(
					'type'        => 'string',
					'enum'        => array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' ),
					'default'     => '=',
					'description' => 'Comparison operator for meta_value. Use EXISTS/NOT EXISTS to check if a meta key is set (no meta_value needed).',
				),
				'orderby'     => array(
					'type'        => 'string',
					'enum'        => array( 'ID', 'display_name', 'user_login', 'user_email', 'user_registered', 'meta_value' ),
					'default'     => 'display_name',
					'description' => 'Order results by field.',
				),
				'order'       => array(
					'type'        => 'string',
					'enum'        => array( 'ASC', 'DESC' ),
					'default'     => 'ASC',
					'description' => 'Sort direction.',
				),
				'offset'      => array(
					'type'        => 'integer',
					'default'     => 0,
					'minimum'     => 0,
					'description' => 'Number of users to skip (for pagination). Use with limit to page through results.',
				),
				'limit'       => array(
					'type'        => 'integer',
					'default'     => 20,
					'maximum'     => 100,
					'description' => 'Maximum results to return.',
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
		$limit   = min( (int) ( $params['limit'] ?? 20 ), 100 );
		$offset  = max( (int) ( $params['offset'] ?? 0 ), 0 );
		$orderby = sanitize_key( $params['orderby'] ?? 'display_name' );
		$order   = strtoupper( $params['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';

		// Email lookup - exact match, return single user.
		if ( ! empty( $params['email'] ) ) {
			$user = get_user_by( 'email', sanitize_email( $params['email'] ) );

			if ( ! $user ) {
				return Json_Rpc_Response::create_success_response(
					'No user found with that email',
					array(
						'items' => array(),
						'total' => 0,
					)
				);
			}

			return Json_Rpc_Response::create_success_response(
				'User found',
				array(
					'items' => array( $this->format_user( $user ) ),
					'total' => 1,
				)
			);
		}

		// Build WP_User_Query args.
		$args = array(
			'number'      => $limit,
			'offset'      => $offset,
			'orderby'     => $orderby,
			'order'       => $order,
			'count_total' => true,
		);

		// Role filter.
		if ( ! empty( $params['role'] ) ) {
			$args['role'] = sanitize_key( $params['role'] );
		}

		// Include specific IDs.
		if ( ! empty( $params['include'] ) ) {
			$args['include'] = array_map( 'intval', explode( ',', $params['include'] ) );
		}

		// Exclude specific IDs.
		if ( ! empty( $params['exclude'] ) ) {
			$args['exclude'] = array_map( 'intval', explode( ',', $params['exclude'] ) );
		}

		// Search - searches across multiple fields using OR logic.
		// Runs two ID-only queries (user table columns + usermeta) and merges results.
		if ( ! empty( $params['search'] ) ) {
			$search   = sanitize_text_field( $params['search'] );
			$user_ids = $this->search_users_by_name( $search, $limit + $offset );

			if ( empty( $user_ids ) ) {
				return Json_Rpc_Response::create_success_response(
					'No users found matching that search',
					array(
						'items' => array(),
						'total' => 0,
					)
				);
			}

			// If caller also passed 'include', intersect with search results.
			if ( ! empty( $args['include'] ) ) {
				$user_ids = array_values( array_intersect( $user_ids, $args['include'] ) );
			}

			$args['include'] = $user_ids;
		}

		// Meta query - works standalone or combined with search.
		if ( ! empty( $params['meta_key'] ) ) {
			$allowed_compares = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' );
			$compare          = strtoupper( $params['meta_compare'] ?? '=' );

			if ( ! in_array( $compare, $allowed_compares, true ) ) {
				$compare = '=';
			}

			$meta_query = array(
				'key'     => sanitize_key( $params['meta_key'] ),
				'compare' => $compare,
			);

			// EXISTS/NOT EXISTS don't need a value.
			if ( ! in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) && ! empty( $params['meta_value'] ) ) {
				$raw_value = $params['meta_value'];

				// IN, NOT IN, BETWEEN, NOT BETWEEN expect arrays.
				if ( in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ), true ) ) {
					$meta_query['value'] = array_map( 'sanitize_text_field', explode( ',', $raw_value ) );
				} else {
					$meta_query['value'] = sanitize_text_field( $raw_value );
				}
			}

			$args['meta_query'] = array( $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$user_query = new \WP_User_Query( $args );
		$users      = $user_query->get_results();
		$total      = (int) $user_query->get_total();

		$items = array_map( array( $this, 'format_user' ), $users );

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Found %d users (showing %d)', $total, count( $items ) ),
			array(
				'items'    => $items,
				'total'    => $total,
				'offset'   => $offset,
				'limit'    => $limit,
				'has_more' => ( $offset + count( $items ) ) < $total,
			)
		);
	}

	/**
	 * Search users across user table columns and usermeta fields.
	 *
	 * Runs two lightweight ID-only queries and merges with OR logic so that
	 * a partial match on first_name, last_name, nickname, display_name,
	 * user_login, user_email, or user_nicename will return the user.
	 *
	 * @param string $search Sanitized search term.
	 * @param int    $limit  Max results per sub-query.
	 * @return int[] Unique user IDs matching the search.
	 */
	private function search_users_by_name( string $search, int $limit ): array {
		// Query 1: user table columns (user_login, user_email, user_nicename, display_name).
		$table_query = new \WP_User_Query(
			array(
				'search'         => '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' ),
				'fields'         => 'ID',
				'number'         => $limit,
			)
		);

		// Query 2: usermeta fields (first_name, last_name, nickname).
		$meta_query = new \WP_User_Query(
			array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => 'first_name',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'last_name',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'nickname',
						'value'   => $search,
						'compare' => 'LIKE',
					),
				),
				'fields'     => 'ID',
				'number'     => $limit,
			)
		);

		$table_ids = array_map( 'intval', $table_query->get_results() );
		$meta_ids  = array_map( 'intval', $meta_query->get_results() );

		return array_values( array_unique( array_merge( $table_ids, $meta_ids ) ) );
	}

	/**
	 * Format user object for response.
	 *
	 * @param \WP_User $user User object.
	 * @return array
	 */
	private function format_user( \WP_User $user ): array {
		return array(
			'id'           => $user->ID,
			'user_login'   => $user->user_login,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'email'        => $user->user_email,
			'roles'        => $user->roles,
			'registered'   => $user->user_registered,
		);
	}
}
