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

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Get Posts Tool.
 */
class Get_Posts_Tool extends Abstract_MCP_Tool {

	use Id_List_Arguments;

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
		return 'Query WordPress posts. Use the listed fields to filter by parent, custom fields, published or updated date, and search text, and to order or paginate results.';
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
				'post_type'           => array(
					'type'        => 'string',
					'enum'        => $post_types,
					'description' => 'Post type to query.',
				),
				'post_parent'         => array(
					'type'        => 'integer',
					'description' => 'Parent post ID for hierarchical queries.',
				),
				'post_status'         => array(
					'type'        => 'string',
					'enum'        => array( 'publish', 'draft', 'pending', 'private', 'any' ),
					'description' => 'Post status filter. Omit to return both published and draft posts.',
				),
				'search'              => array(
					'type'        => 'string',
					'description' => 'Search posts by title/content.',
				),
				'meta_key'            => array(
					'type'        => 'string',
					'description' => 'Filter by meta key.',
				),
				'meta_value'          => array(
					'type'        => 'string',
					'description' => 'Filter by meta value (requires meta_key).',
				),
				'orderby'             => array(
					'type'        => 'string',
					'enum'        => array( 'date', 'modified', 'title', 'ID', 'menu_order', 'rand', 'meta_value', 'meta_value_num' ),
					'default'     => 'date',
					'description' => 'Order results by field. "date" = published date, "modified" = last updated date.',
				),
				'order'               => array(
					'type'        => 'string',
					'enum'        => array( 'DESC', 'ASC' ),
					'default'     => 'DESC',
					'description' => 'Sort direction. DESC = newest first (default), ASC = oldest first.',
				),
				'limit'               => array(
					'type'        => 'integer',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
					'description' => 'Maximum results.',
				),
				'offset'              => array(
					'type'        => 'integer',
					'default'     => 0,
					'minimum'     => 0,
					'description' => 'Number of posts to skip for pagination.',
				),
				'include'             => array(
					'type'        => 'string',
					'description' => 'Comma-separated post IDs to include.',
				),
				'exclude'             => array( // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- A tool input name in a schema, not a query argument.
					'type'        => 'string',
					'description' => 'Comma-separated post IDs to exclude.',
				),
				'date_published_from' => array(
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Filter posts published on or after this date. Use YYYY-MM-DD in the site timezone.',
				),
				'date_published_to'   => array(
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Filter posts published on or before this date. Use YYYY-MM-DD in the site timezone.',
				),
				'date_updated_from'   => array(
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Filter posts modified on or after this date. Use YYYY-MM-DD in the site timezone.',
				),
				'date_updated_to'     => array(
					'type'        => 'string',
					'format'      => 'date',
					'description' => 'Filter posts modified on or before this date. Use YYYY-MM-DD in the site timezone.',
				),
			),
			'required'   => array( 'post_type' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array( 'type' => 'string' ),
				'items'     => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'          => array( 'type' => 'integer' ),
							'title'       => array( 'type' => 'string' ),
							'slug'        => array( 'type' => 'string' ),
							'post_parent' => array( 'type' => 'integer' ),
							'status'      => array( 'type' => 'string' ),
						),
					),
				),
				'total'     => array( 'type' => 'integer' ),
				'offset'    => array( 'type' => 'integer' ),
				'limit'     => array( 'type' => 'integer' ),
				'has_more'  => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'post_type', 'items', 'total', 'offset', 'limit', 'has_more' ),
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
		// Intentional admin-context discovery default: include editable drafts unless caller narrows status.
		$post_type   = sanitize_key( $params['post_type'] ?? 'post' );
		$limit       = max( 1, min( (int) ( $params['limit'] ?? 20 ), 100 ) );
		$offset      = max( (int) ( $params['offset'] ?? 0 ), 0 );
		$post_status = isset( $params['post_status'] )
			? sanitize_key( $params['post_status'] )
			: array( 'publish', 'draft' );
		$orderby     = sanitize_key( $params['orderby'] ?? 'date' );
		// WP_Query's ID order key is case-sensitive; sanitize_key lowercases it.
		if ( 'id' === $orderby ) {
			$orderby = 'ID';
		}
		$order = strtoupper( $params['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		$args = array(
			'post_type'           => $post_type,
			'post_status'         => $post_status,
			'posts_per_page'      => $limit,
			'offset'              => $offset,
			'orderby'             => $orderby,
			'order'               => $order,
			// WordPress reads a query with no search or archive as the front page
			// and adds sticky posts to it, past every filter, limit and total here.
			'ignore_sticky_posts' => true,
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

		// Include and exclude specific IDs; an excluded ID is never returned.
		$id_args = $this->id_list_query_args( $params, 'post__in', 'post__not_in' );
		if ( null === $id_args ) {
			return $this->found_answer( $post_type, array(), 0, $offset, $limit );
		}
		$args = array_merge( $args, $id_args );

		// Build date query for published and modified dates.
		$date_query = $this->build_date_query( $params );

		if ( ! empty( $date_query ) ) {
			$args['date_query'] = $date_query;
		}

		$query = new \WP_Query( $args );
		$total = (int) $query->found_posts;

		// WP_Query skips found_posts when an offset returns no rows.
		// Probe the first matching ID to retain the total beyond the last page.
		if ( empty( $query->posts ) && $offset > 0 ) {
			$count_args                   = $args;
			$count_args['offset']         = 0;
			$count_args['posts_per_page'] = 1; // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- One row, only to read the total.
			$count_args['fields']         = 'ids';
			$count_query                  = new \WP_Query( $count_args );
			$total                        = (int) $count_query->found_posts;
		}

		$items = array_map(
			function ( $post ) {
				return array(
					'id'          => $post->ID,
					'title'       => $post->post_title,
					'slug'        => $post->post_name,
					'post_parent' => $post->post_parent,
					'status'      => $post->post_status,
				);
			},
			$query->posts
		);

		return $this->found_answer( $post_type, $items, $total, $offset, $limit );
	}

	/**
	 * The answer listing the posts found.
	 *
	 * @param string $post_type Post type queried.
	 * @param array  $items     Posts on this page.
	 * @param int    $total     Posts matching in all.
	 * @param int    $offset    Posts skipped.
	 * @param int    $limit     Page size.
	 * @return array
	 */
	private function found_answer( string $post_type, array $items, int $total, int $offset, int $limit ): array {
		return Json_Rpc_Response::create_success_response(
			sprintf( 'Found %d %s', count( $items ), $post_type ),
			array(
				'post_type' => $post_type,
				'items'     => $items,
				'total'     => $total,
				'offset'    => $offset,
				'limit'     => $limit,
				'has_more'  => ( $offset + count( $items ) ) < $total,
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
	 *
	 * A date without a timezone means that calendar day on the site, so it is
	 * parsed in the site timezone. Parsing it as UTC and converting afterwards
	 * shifted every date-only filter back one day on sites west of UTC.
	 * The tool's input schema accepts only a calendar date, without a time.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_date_query/
	 *
	 * @param string $date Date string (Y-m-d preferred).
	 * @return array|false Array with year, month, day keys or false on failure.
	 */
	private function parse_date_to_array( $date ) {
		$date = trim( (string) $date );

		if ( '' === $date ) {
			return false;
		}

		$timezone = wp_timezone();
		$parsed   = date_create( $date, $timezone );

		if ( false === $parsed ) {
			return false;
		}

		$parsed->setTimezone( $timezone );

		return array(
			'year'  => (int) $parsed->format( 'Y' ),
			'month' => (int) $parsed->format( 'n' ),
			'day'   => (int) $parsed->format( 'j' ),
		);
	}
}
