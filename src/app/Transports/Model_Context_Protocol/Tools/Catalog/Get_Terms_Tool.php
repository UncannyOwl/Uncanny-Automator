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

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Get Terms Tool.
 */
class Get_Terms_Tool extends Abstract_MCP_Tool {

	use Id_List_Arguments;

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
		return 'Query terms from taxonomies currently registered on this WordPress site. Use the taxonomy choices in the input schema; available taxonomies depend on the active plugins and theme. Filter by parent, search, slug, or get term hierarchy.';
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
					'description' => 'Taxonomy to query. Choose one of the taxonomies currently registered on this site.',
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
					'minimum'     => 1,
					'maximum'     => 100,
					'description' => 'Maximum results to return.',
				),
				'offset'     => array(
					'type'        => 'integer',
					'default'     => 0,
					'minimum'     => 0,
					'description' => 'Number of terms to skip for pagination.',
				),
			),
			'required'   => array( 'taxonomy' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'taxonomy' => array( 'type' => 'string' ),
				'items'    => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'          => array( 'type' => 'integer' ),
							'name'        => array( 'type' => 'string' ),
							'slug'        => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'parent'      => array( 'type' => 'integer' ),
							'count'       => array( 'type' => 'integer' ),
						),
					),
				),
				'total'    => array( 'type' => 'integer' ),
				'offset'   => array( 'type' => 'integer' ),
				'limit'    => array( 'type' => 'integer' ),
				'has_more' => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'taxonomy', 'items', 'total', 'offset', 'limit', 'has_more' ),
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
		$limit      = max( 1, min( (int) ( $params['limit'] ?? 20 ), 100 ) );
		$offset     = max( (int) ( $params['offset'] ?? 0 ), 0 );
		$orderby    = sanitize_key( $params['orderby'] ?? 'name' );
		$order      = strtoupper( $params['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
		$hide_empty = ! empty( $params['hide_empty'] );

		$args = array(
			'taxonomy'   => $taxonomy,
			'number'     => $limit,
			'offset'     => $offset,
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

		// Include and exclude specific IDs; an excluded ID is never returned.
		$id_args = $this->id_list_query_args( $params, 'include', 'exclude' );
		if ( null === $id_args ) {
			return $this->found_answer( $taxonomy, array(), 0, $offset, $limit );
		}
		$args = array_merge( $args, $id_args );

		// Meta query.
		if ( ! empty( $params['meta_key'] ) ) {
			// Meta keys are case-sensitive; sanitize_key would lowercase them.
			$args['meta_key'] = sanitize_text_field( (string) $params['meta_key'] );

			if ( ! empty( $params['meta_value'] ) ) {
				$args['meta_value'] = sanitize_text_field( $params['meta_value'] );
			}
		}

		$terms = get_terms( $args );

		// Handle WP_Error from invalid taxonomy.
		if ( is_wp_error( $terms ) ) {
			return Json_Rpc_Response::create_error_response( $terms->get_error_message() );
		}

		$total = $this->count_terms( $args );
		if ( is_wp_error( $total ) ) {
			return Json_Rpc_Response::create_error_response( $total->get_error_message() );
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
			array_values( $terms )
		);

		return $this->found_answer( $taxonomy, $items, $total, $offset, $limit );
	}

	/**
	 * The answer listing the terms found.
	 *
	 * @param string $taxonomy Taxonomy queried.
	 * @param array  $items    Terms on this page.
	 * @param int    $total    Terms matching in all.
	 * @param int    $offset   Terms skipped.
	 * @param int    $limit    Page size.
	 * @return array
	 */
	private function found_answer( string $taxonomy, array $items, int $total, int $offset, int $limit ): array {
		return Json_Rpc_Response::create_success_response(
			sprintf( 'Found %d %s terms', count( $items ), $taxonomy ),
			array(
				'taxonomy' => $taxonomy,
				'items'    => $items,
				'total'    => $total,
				'offset'   => $offset,
				'limit'    => $limit,
				'has_more' => ( $offset + count( $items ) ) < $total,
			)
		);
	}

	/**
	 * Count terms using the same filters without pagination.
	 *
	 * @param array $args get_terms() arguments.
	 * @return int|\WP_Error
	 */
	private function count_terms( array $args ) {
		unset( $args['number'], $args['offset'], $args['orderby'], $args['order'] );

		$args['fields'] = 'count';

		$count = get_terms( $args );
		if ( is_wp_error( $count ) ) {
			return $count;
		}

		return (int) $count;
	}
}
