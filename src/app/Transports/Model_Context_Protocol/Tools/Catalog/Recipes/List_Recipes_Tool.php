<?php
/**
 * MCP catalog tool that returns recipe listings with optional filters.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Service;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;

/**
 * List Recipes MCP Tool.
 *
 * MCP tool for listing Automator recipes with optional filtering.
 *
 * @since 7.0.0
 */
class List_Recipes_Tool extends Abstract_MCP_Tool {

	/**
	 * Recipe service.
	 *
	 * @var Recipe_Service
	 */
	private $recipe_service;

	/**
	 * Constructor.
	 *
	 * Allows for dependency injection of the recipe service.
	 */
	public function __construct( ?Recipe_Service $recipe_service = null ) {
		$this->recipe_service = $recipe_service ?? Recipe_Service::instance();
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 *
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'list_recipes';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 *
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'List recipes with optional filters for status, type, or title. Returns recipe IDs, titles, status, and site-wide published, drafted and trashed counts. For site-wide count questions, use the total_* fields (limit 1 is sufficient); do not paginate to count. Totals ignore filters.';
	}

	/**
	 * Define the input schema for the list recipes tool.
	 *
	 * Schema matches filtering options from Recipe Store.
	 *
	 * @since 7.0.0
	 *
	 * @return array JSON Schema for list recipes parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter recipes by publication status. "draft" = unpublished recipes, "publish" = live recipes. Omit to return both draft and publish.',
					'enum'        => array( Recipe_Status::DRAFT, Recipe_Status::PUBLISH ),
				),
				'type'   => array(
					'type'        => 'string',
					'description' => 'Filter recipes by execution context. "user" = requires logged-in users, "anonymous" = works for all visitors. Omit to return both types.',
					'enum'        => array( 'user', 'anonymous' ),
				),
				'title'  => array(
					'type'        => 'string',
					'description' => 'Search recipes by title using partial text matching. Case-insensitive search that matches any part of the recipe title.',
					'minLength'   => 1,
					'maxLength'   => 200,
				),
				'limit'  => array(
					'type'        => 'integer',
					'description' => 'Maximum recipes per page (default 50). Results are ordered by newest creation date, then ID. Continue with next_offset while has_more is true; keep filters unchanged.',
					'minimum'     => 1,
					'maximum'     => 100,
					'default'     => 50,
				),
				'offset' => array(
					'type'        => 'integer',
					'description' => 'Number of matching recipes to skip. Use next_offset from the previous response.',
					'minimum'     => 0,
					'default'     => 0,
				),
			),
			'required'   => array(),
		);
	}

	/**
	 * Define the output schema for the list recipes tool response data.
	 *
	 * @since 7.0.0
	 *
	 * @return array|null JSON Schema for list recipes response data.
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'success'      => array( 'type' => 'boolean' ),
				'message'      => array( 'type' => 'string' ),
				'recipe_count' => array(
					'type'        => 'integer',
					'description' => 'Number of recipes returned on this page, not a site-wide total.',
				),
				'total_recipes' => array(
					'type'        => 'integer',
					'description' => 'Site-wide published plus drafted recipes. Excludes trash and other statuses. Independent of filters and pagination.',
				),
				'total_published_recipes' => array(
					'type'        => 'integer',
					'description' => 'Site-wide published recipes, independent of filters and pagination.',
				),
				'total_drafted_recipes' => array(
					'type'        => 'integer',
					'description' => 'Site-wide drafted recipes, independent of filters and pagination. Excludes auto-drafts.',
				),
				'total_trashed_recipes' => array(
					'type'        => 'integer',
					'description' => 'Site-wide trashed recipes, reported separately from total_recipes. Independent of filters and pagination.',
				),
				'limit'        => array( 'type' => 'integer' ),
				'offset'       => array( 'type' => 'integer' ),
				'has_more'     => array( 'type' => 'boolean' ),
				'next_offset'  => array( 'type' => array( 'integer', 'null' ) ),
				'recipes'      => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'     => array( 'type' => 'integer' ),
							'title'  => array( 'type' => 'string' ),
							'status' => array( 'type' => 'string' ),
						),
					),
				),
			),
			'required'   => array( 'success', 'recipes', 'recipe_count', 'total_recipes', 'total_published_recipes', 'total_drafted_recipes', 'total_trashed_recipes' ),
		);
	}

	/**
	 * Execute the list recipes tool.
	 *
	 * @since 7.0.0
	 *
	 * @param array $params Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$limit  = isset( $params['limit'] ) ? min( 100, max( 1, (int) $params['limit'] ) ) : 50;
		$offset = isset( $params['offset'] ) ? max( 0, (int) $params['offset'] ) : 0;

		// Fetch one extra matching recipe to distinguish a full final page.
		$filters = array_filter(
			array(
				'status'  => isset( $params['status'] ) ? strtolower( $params['status'] ) : null,
				'type'    => isset( $params['type'] ) ? strtolower( $params['type'] ) : null,
				'title'   => isset( $params['title'] ) ? trim( (string) $params['title'] ) : null,
				'limit'   => $limit + 1,
				'offset'  => $offset,
				'orderby' => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
			),
			function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);

		// Use Recipe Service for business logic - domain handles validation automatically
		$result = $this->recipe_service->list_recipes( $filters );

		// Transform service result to MCP response
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$has_more               = count( $result['recipes'] ) > $limit;
		$result['recipes']      = array_slice( $result['recipes'], 0, $limit );
		$result['recipe_count'] = count( $result['recipes'] );
		$result['limit']        = $limit;
		$result['offset']       = $offset;
		$result['has_more']     = $has_more;
		$result['next_offset']  = $has_more ? $offset + $limit : null;

		// Counting must not require an AI to retrieve every page on a large site.
		// Keep recipe_count page-local for existing clients; totals are site-wide.
		$result = array_merge( $result, $this->recipe_service->get_recipe_totals() );

		return Json_Rpc_Response::create_success_response( 'Recipes retrieved successfully', $result );
	}
}
