<?php
/**
 * Get Terms Tool.
 *
 * Lists WordPress terms using get_terms() parameters.
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
 * Get Terms Tool.
 */
class Get_Terms_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get_terms';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'Query WordPress taxonomy terms using get_terms(). Supports categories, tags, product categories, course categories, and any custom taxonomy. Filter by parent, search, slug, or get term hierarchy.';
	}

	/**
	 * Define input schema.
	 *
	 * @return array
	 */
	protected function schema_definition() {
		// Generate enums dynamically from WordPress - include all taxonomies.
		$taxonomies = array_values( get_taxonomies( array(), 'names' ) );

		return array(
			'type'       => 'object',
			'properties' => array(
				'taxonomy'   => array(
					'type'        => 'string',
					'enum'        => $taxonomies,
					'description' => 'Taxonomy to query. Common: category, post_tag, product_cat, product_tag, ld_course_category.',
				),
				'search'     => array(
					'type'        => 'string',
					'description' => 'Search terms by name.',
				),
				'slug'       => array(
					'type'        => 'string',
					'description' => 'Get term by exact slug.',
				),
				'parent'     => array(
					'type'        => 'integer',
					'description' => 'Parent term ID. Use 0 for top-level terms only.',
				),
				'child_of'   => array(
					'type'        => 'integer',
					'description' => 'Get all descendants of this term ID.',
				),
				'hide_empty' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Hide terms with no posts assigned.',
				),
				'include'    => array(
					'type'        => 'string',
					'description' => 'Comma-separated term IDs to include.',
				),
				'exclude'    => array(
					'type'        => 'string',
					'description' => 'Comma-separated term IDs to exclude.',
				),
				'orderby'    => array(
					'type'        => 'string',
					'enum'        => array( 'name', 'slug', 'term_id', 'count', 'parent', 'menu_order', 'meta_value', 'meta_value_num' ),
					'default'     => 'name',
					'description' => 'Order results by field.',
				),
				'order'      => array(
					'type'        => 'string',
					'enum'        => array( 'ASC', 'DESC' ),
					'default'     => 'ASC',
					'description' => 'Sort direction.',
				),
				'meta_key'   => array(
					'type'        => 'string',
					'description' => 'Filter by term meta key.',
				),
				'meta_value' => array(
					'type'        => 'string',
					'description' => 'Filter by term meta value (requires meta_key).',
				),
				'limit'      => array(
					'type'        => 'integer',
					'default'     => 20,
					'maximum'     => 100,
					'description' => 'Maximum results to return.',
				),
			),
			'required'   => array( 'taxonomy' ),
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
		$taxonomy   = sanitize_key( $params['taxonomy'] ?? 'category' );
		$limit      = min( (int) ( $params['limit'] ?? 20 ), 100 );
		$orderby    = sanitize_key( $params['orderby'] ?? 'name' );
		$order      = strtoupper( $params['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
		$hide_empty = ! empty( $params['hide_empty'] );

		$args = array(
			'taxonomy'   => $taxonomy,
			'number'     => $limit,
			'orderby'    => $orderby,
			'order'      => $order,
			'hide_empty' => $hide_empty,
		);

		// Search by name.
		if ( ! empty( $params['search'] ) ) {
			$args['search'] = sanitize_text_field( $params['search'] );
		}

		// Get by slug.
		if ( ! empty( $params['slug'] ) ) {
			$args['slug'] = sanitize_title( $params['slug'] );
		}

		// Parent filter (0 = top-level only).
		if ( isset( $params['parent'] ) ) {
			$args['parent'] = (int) $params['parent'];
		}

		// Get all descendants.
		if ( ! empty( $params['child_of'] ) ) {
			$args['child_of'] = (int) $params['child_of'];
		}

		// Include specific IDs.
		if ( ! empty( $params['include'] ) ) {
			$args['include'] = array_map( 'intval', explode( ',', $params['include'] ) );
		}

		// Exclude specific IDs.
		if ( ! empty( $params['exclude'] ) ) {
			$args['exclude'] = array_map( 'intval', explode( ',', $params['exclude'] ) );
		}

		// Meta query.
		if ( ! empty( $params['meta_key'] ) ) {
			$args['meta_key'] = sanitize_key( $params['meta_key'] );

			if ( ! empty( $params['meta_value'] ) ) {
				$args['meta_value'] = sanitize_text_field( $params['meta_value'] );
			}
		}

		$terms = get_terms( $args );

		// Handle WP_Error from invalid taxonomy.
		if ( is_wp_error( $terms ) ) {
			return Json_Rpc_Response::create_error_response( $terms->get_error_message() );
		}

		$items = array_map(
			function ( $term ) {
				return array(
					'id'          => $term->term_id,
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'parent'      => $term->parent,
					'count'       => $term->count,
				);
			},
			$terms
		);

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Found %d %s terms', count( $items ), $taxonomy ),
			array(
				'taxonomy' => $taxonomy,
				'items'    => $items,
				'total'    => count( $items ),
				'has_more' => count( $items ) === $limit,
			)
		);
	}
}
