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

	private const SEARCH_FILTER_PRIORITY = 999;

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
				'search'       => array(
					'type'        => 'string',
					'description' => 'Search term. Searches user_login, user_email, user_nicename, display_name, first_name, last_name, and nickname. Example: "Joseph" finds users with Joseph in any name field.',
				),
				'role'         => array(
					'type'        => 'string',
					'enum'        => $roles,
					'description' => 'Filter by user role.',
				),
				'email'        => array(
					'type'        => 'string',
					'description' => 'Exact email address lookup. Returns single user if found.',
				),
				'include'      => array(
					'type'        => 'string',
					'description' => 'Comma-separated user IDs to include.',
				),
				'exclude'      => array(
					'type'        => 'string',
					'description' => 'Comma-separated user IDs to exclude.',
				),
				'meta_key'     => array(
					'type'        => 'string',
					'description' => 'Filter by user meta key (e.g., billing_country, first_name).',
				),
				'meta_value'   => array(
					'type'        => 'string',
					'description' => 'Filter by user meta value (requires meta_key). For IN/NOT IN, pass comma-separated values. For BETWEEN/NOT BETWEEN, pass two comma-separated values.',
				),
				'meta_compare' => array(
					'type'        => 'string',
					'enum'        => array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' ),
					'default'     => '=',
					'description' => 'Comparison operator for meta_value. Use EXISTS/NOT EXISTS to check if a meta key is set (no meta_value needed).',
				),
				'orderby'      => array(
					'type'        => 'string',
					'enum'        => array( 'ID', 'display_name', 'user_login', 'user_email', 'user_registered', 'meta_value' ),
					'default'     => 'display_name',
					'description' => 'Order results by field.',
				),
				'order'        => array(
					'type'        => 'string',
					'enum'        => array( 'ASC', 'DESC' ),
					'default'     => 'ASC',
					'description' => 'Sort direction.',
				),
				'offset'       => array(
					'type'        => 'integer',
					'default'     => 0,
					'minimum'     => 0,
					'description' => 'Number of users to skip (for pagination). Use with limit to page through results.',
				),
				'limit'        => array(
					'type'        => 'integer',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
					'description' => 'Maximum results to return.',
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
				'items'    => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'           => array( 'type' => 'integer' ),
							'user_login'   => array( 'type' => 'string' ),
							'display_name' => array( 'type' => 'string' ),
							'first_name'   => array( 'type' => 'string' ),
							'last_name'    => array( 'type' => 'string' ),
							'email'        => array( 'type' => 'string' ),
							'roles'        => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'registered'   => array( 'type' => 'string' ),
						),
					),
				),
				'total'    => array( 'type' => 'integer' ),
				'offset'   => array( 'type' => 'integer' ),
				'limit'    => array( 'type' => 'integer' ),
				'has_more' => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'items', 'total' ),
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
		$limit   = max( 1, min( (int) ( $params['limit'] ?? 20 ), 100 ) );
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

		$search_filter = null;
		if ( ! empty( $params['search'] ) ) {
			$search = trim( sanitize_text_field( $params['search'] ) );
			if ( '' !== $search ) {
				$search_scope                            = wp_generate_uuid4();
				$args['automator_mcp_user_search_scope'] = $search_scope;
				$search_filter                           = $this->build_search_query_filter( $search, $search_scope );
			}
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

		if ( null !== $search_filter ) {
			add_action( 'pre_user_query', $search_filter, self::SEARCH_FILTER_PRIORITY );
		}

		try {
			$user_query = new \WP_User_Query( $args );
		} finally {
			if ( null !== $search_filter ) {
				remove_action( 'pre_user_query', $search_filter, self::SEARCH_FILTER_PRIORITY );
			}
		}

		$users = $user_query->get_results();
		$total = (int) $user_query->get_total();

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
	 * Build a scoped search filter for WP_User_Query.
	 *
	 * @param string $search       Sanitized search term.
	 * @param string $search_scope Unique marker for the WP_User_Query instance this filter may mutate.
	 * @return callable
	 */
	private function build_search_query_filter( string $search, string $search_scope ): callable {
		return static function ( \WP_User_Query $query ) use ( $search, $search_scope ): void {
			global $wpdb;

			if ( ( $query->query_vars['automator_mcp_user_search_scope'] ?? null ) !== $search_scope ) {
				return;
			}

			// WordPress LIKE searches must escape the raw term, then pass the wildcarded value into prepare().
			$like       = '%' . $wpdb->esc_like( $search ) . '%';
			$conditions = array(
				$wpdb->prepare( "{$wpdb->users}.user_login LIKE %s", $like ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare( "{$wpdb->users}.user_email LIKE %s", $like ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare( "{$wpdb->users}.user_nicename LIKE %s", $like ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare( "{$wpdb->users}.display_name LIKE %s", $like ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"EXISTS (
							SELECT 1
							FROM {$wpdb->usermeta} automator_mcp_user_search_meta
							WHERE automator_mcp_user_search_meta.user_id = {$wpdb->users}.ID
							AND automator_mcp_user_search_meta.meta_key IN ( 'first_name', 'last_name', 'nickname' )
							AND automator_mcp_user_search_meta.meta_value LIKE %s
						)",
					$like
				), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			$query->query_where .= ' AND ( ' . implode( ' OR ', $conditions ) . ' )';
		};
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
