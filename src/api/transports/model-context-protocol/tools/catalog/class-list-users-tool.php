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
		return 'Query WordPress users using WP_User_Query. Search by name (first, last, or display name), email, username, or role. Supports meta_key/meta_value for custom user fields. Returns user ID, names, email, and roles.';
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
					'description' => 'Search term. Searches user_login, user_email, user_nicename, display_name, first_name, and last_name. Example: "Joseph" finds users with Joseph in any name field.',
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
					'description' => 'Filter by user meta value (requires meta_key).',
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
			'number'  => $limit,
			'orderby' => $orderby,
			'order'   => $order,
		);

		// Role filter.
		if ( ! empty( $params['role'] ) ) {
			$args['role'] = sanitize_key( $params['role'] );
		}

		// Search - searches across multiple fields.
		if ( ! empty( $params['search'] ) ) {
			$search                 = sanitize_text_field( $params['search'] );
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array(
				'user_login',
				'user_email',
				'user_nicename',
				'display_name',
			);

			// Also search first_name and last_name via meta query.
			$args['meta_query'] = array(
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
			);
		}

		// Include specific IDs.
		if ( ! empty( $params['include'] ) ) {
			$args['include'] = array_map( 'intval', explode( ',', $params['include'] ) );
		}

		// Exclude specific IDs.
		if ( ! empty( $params['exclude'] ) ) {
			$args['exclude'] = array_map( 'intval', explode( ',', $params['exclude'] ) );
		}

		// Meta query (only if search didn't already set it).
		if ( ! empty( $params['meta_key'] ) && empty( $params['search'] ) ) {
			$meta_query = array(
				'key' => sanitize_key( $params['meta_key'] ),
			);

			if ( ! empty( $params['meta_value'] ) ) {
				$meta_query['value']   = sanitize_text_field( $params['meta_value'] );
				$meta_query['compare'] = '=';
			}

			$args['meta_query'] = array( $meta_query );
		}

		$user_query = new \WP_User_Query( $args );
		$users      = $user_query->get_results();

		$items = array_map( array( $this, 'format_user' ), $users );

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Found %d users', count( $items ) ),
			array(
				'items'    => $items,
				'total'    => count( $items ),
				'has_more' => count( $items ) === $limit,
			)
		);
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
