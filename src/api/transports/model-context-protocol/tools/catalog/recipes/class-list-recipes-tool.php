<?php
/**
 * MCP catalog tool that returns recipe listings with optional filters.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Service;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;

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
		return 'List recipes with optional filters for status, type, or title. Returns recipe IDs, titles, and status for selecting or presenting to users.';
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
					'description' => 'Filter recipes by publication status. "draft" = unpublished recipes, "publish" = live recipes. Defaults to "publish" if not specified.',
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
					'description' => 'Maximum number of recipes to return in the response. Use for pagination and performance optimization.',
					'minimum'     => 1,
					'maximum'     => 100,
					'default'     => 50,
				),
			),
			'required'   => array(),
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
		// Prepare filters, defaulting status to 'publish' as before
		$filters = array_filter(
			array(
				'status' => isset( $params['status'] ) ? strtolower( $params['status'] ) : null,
				'type'   => isset( $params['type'] ) ? strtolower( $params['type'] ) : null,
				'title'  => isset( $params['title'] ) ? trim( (string) $params['title'] ) : null,
				'limit'  => isset( $params['limit'] ) ? max( 1, (int) $params['limit'] ) : null,
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

		return Json_Rpc_Response::create_success_response( 'Recipes retrieved successfully', $result );
	}
}
