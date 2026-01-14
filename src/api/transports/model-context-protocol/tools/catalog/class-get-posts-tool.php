<?php
/**
 * Get Posts Tool.
 *
 * Query WordPress posts using WP_Query parameters.
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
 * Get Posts Tool.
 */
class Get_Posts_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get_posts';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'Query WordPress posts using WP_Query. Supports post_parent for hierarchical content (variations, child pages), meta_key/meta_value for custom fields, date filtering (published/updated date ranges), search, and standard WP_Query parameters.';
	}

	/**
	 * Define input schema.
	 *
	 * @return array
	 */
	protected function schema_definition() {
		// Get all registered post types.
		$post_types = array_values( get_post_types( array(), 'names' ) );

		return array(
			'type'       => 'object',
			'properties' => array(
				'post_type'   => array(
					'type'        => 'string',
					'enum'        => $post_types,
					'description' => 'Post type to query.',
				),
				'post_parent' => array(
					'type'        => 'integer',
					'description' => 'Parent post ID for hierarchical queries.',
				),
				'post_status' => array(
					'type'        => 'string',
					'enum'        => array( 'publish', 'draft', 'pending', 'private', 'any' ),
					'default'     => 'publish',
					'description' => 'Post status filter.',
				),
				'search'      => array(
					'type'        => 'string',
					'description' => 'Search posts by title/content.',
				),
				'meta_key'    => array(
					'type'        => 'string',
					'description' => 'Filter by meta key.',
				),
				'meta_value'  => array(
					'type'        => 'string',
					'description' => 'Filter by meta value (requires meta_key).',
				),
				'orderby'     => array(
					'type'        => 'string',
					'enum'        => array( 'date', 'modified', 'title', 'ID', 'menu_order', 'rand', 'meta_value', 'meta_value_num' ),
					'default'     => 'date',
					'description' => 'Order results by field. "date" = published date, "modified" = last updated date.',
				),
				'order'       => array(
					'type'        => 'string',
					'enum'        => array( 'DESC', 'ASC' ),
					'default'     => 'DESC',
					'description' => 'Sort direction. DESC = newest first (default), ASC = oldest first.',
				),
				'limit'       => array(
					'type'        => 'integer',
					'default'     => 20,
					'maximum'     => 100,
					'description' => 'Maximum results.',
				),
				'include'     => array(
					'type'        => 'string',
					'description' => 'Comma-separated post IDs to include.',
				),
				'exclude'     => array(
					'type'        => 'string',
					'description' => 'Comma-separated post IDs to exclude.',
				),
				'date_published_from' => array(
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Filter posts published on or after this date. Accepts formats: Y-m-d, Y-m-d H:i:s, or ISO 8601.',
				),
				'date_published_to'   => array(
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Filter posts published on or before this date. Accepts formats: Y-m-d, Y-m-d H:i:s, or ISO 8601.',
				),
				'date_updated_from'   => array(
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Filter posts modified on or after this date. Accepts formats: Y-m-d, Y-m-d H:i:s, or ISO 8601.',
				),
				'date_updated_to'     => array(
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Filter posts modified on or before this date. Accepts formats: Y-m-d, Y-m-d H:i:s, or ISO 8601.',
				),
			),
			'required'   => array( 'post_type' ),
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
		$post_type   = sanitize_key( $params['post_type'] ?? 'post' );
		$limit       = min( (int) ( $params['limit'] ?? 20 ), 100 );
		$post_status = sanitize_key( $params['post_status'] ?? 'publish' );
		$orderby     = sanitize_key( $params['orderby'] ?? 'date' );
		$order       = strtoupper( $params['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $post_status,
			'posts_per_page' => $limit,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		// Post parent for hierarchical queries.
		if ( ! empty( $params['post_parent'] ) ) {
			$args['post_parent'] = (int) $params['post_parent'];
		}

		// Search.
		if ( ! empty( $params['search'] ) ) {
			$args['s'] = sanitize_text_field( $params['search'] );
		}

		// Meta query.
		if ( ! empty( $params['meta_key'] ) ) {
			$args['meta_key'] = sanitize_text_field( $params['meta_key'] );

			if ( ! empty( $params['meta_value'] ) ) {
				$args['meta_value'] = sanitize_text_field( $params['meta_value'] );
			}
		}

		// Include specific IDs.
		if ( ! empty( $params['include'] ) ) {
			$args['post__in'] = array_map( 'intval', explode( ',', $params['include'] ) );
		}

		// Exclude specific IDs.
		if ( ! empty( $params['exclude'] ) ) {
			$args['post__not_in'] = array_map( 'intval', explode( ',', $params['exclude'] ) );
		}

		// Build date query for published and modified dates.
		$date_query = $this->build_date_query( $params );

		if ( ! empty( $date_query ) ) {
			$args['date_query'] = $date_query;
		}

		$query = new \WP_Query( $args );

		$items = array_map(
			function ( $post ) {
				return array(
					'id'          => $post->ID,
					'title'       => $post->post_title,
					'slug'        => $post->post_name,
					'post_parent' => $post->post_parent,
				);
			},
			$query->posts
		);

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Found %d %s', count( $items ), $post_type ),
			array(
				'post_type' => $post_type,
				'items'     => $items,
				'total'     => $query->found_posts,
				'has_more'  => $query->found_posts > $limit,
			)
		);
	}

	/**
	 * Build date query array for WP_Query.
	 *
	 * @param array $params Tool parameters.
	 * @return array Date query array for WP_Query.
	 */
	private function build_date_query( array $params ) {
		$date_query = array();

		// Published date range (post_date column).
		$published_query = $this->build_single_date_query(
			$params['date_published_from'] ?? '',
			$params['date_published_to'] ?? '',
			'post_date'
		);

		if ( ! empty( $published_query ) ) {
			$date_query[] = $published_query;
		}

		// Modified date range (post_modified column).
		$modified_query = $this->build_single_date_query(
			$params['date_updated_from'] ?? '',
			$params['date_updated_to'] ?? '',
			'post_modified'
		);

		if ( ! empty( $modified_query ) ) {
			$date_query[] = $modified_query;
		}

		// Add relation if multiple date queries.
		if ( count( $date_query ) > 1 ) {
			$date_query['relation'] = 'AND';
		}

		return $date_query;
	}

	/**
	 * Build a single date query clause.
	 *
	 * @param string $date_from Start date (Y-m-d format).
	 * @param string $date_to   End date (Y-m-d format).
	 * @param string $column    Database column (post_date or post_modified).
	 * @return array Date query clause or empty array.
	 */
	private function build_single_date_query( $date_from, $date_to, $column ) {
		$date_from = sanitize_text_field( $date_from );
		$date_to   = sanitize_text_field( $date_to );

		if ( empty( $date_from ) && empty( $date_to ) ) {
			return array();
		}

		$query = array(
			'column'    => $column,
			'inclusive' => true,
		);

		if ( ! empty( $date_from ) ) {
			$parsed = $this->parse_date_to_array( $date_from );
			if ( $parsed ) {
				$query['after'] = $parsed;
			}
		}

		if ( ! empty( $date_to ) ) {
			$parsed = $this->parse_date_to_array( $date_to );
			if ( $parsed ) {
				$query['before'] = $parsed;
			}
		}

		return $query;
	}

	/**
	 * Parse date string to WordPress date_query array format.
	 *
	 * WordPress date_query works best with array format (year, month, day).
	 * Uses site timezone for consistency with WP_Date_Query.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_date_query/
	 *
	 * @param string $date Date string (Y-m-d preferred).
	 * @return array|false Array with year, month, day keys or false on failure.
	 */
	private function parse_date_to_array( $date ) {
		$timestamp = strtotime( $date );

		if ( false === $timestamp ) {
			return false;
		}

		// Use wp_date() for site timezone consistency with WP_Date_Query.
		return array(
			'year'  => (int) wp_date( 'Y', $timestamp ),
			'month' => (int) wp_date( 'n', $timestamp ),
			'day'   => (int) wp_date( 'j', $timestamp ),
		);
	}
}
