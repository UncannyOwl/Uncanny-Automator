<?php
/**
 * Block upsert tool (filter + delay_schedule).
 *
 * block_id absent = create, block_id present = update.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Blocks;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Save Block Tool — upsert for `uo-block` posts.
 *
 * The block-native write surface for the two wrappable block types:
 * `filter` (actions inside run only when its conditions pass — configure
 * via save_filter_condition) and `delay_schedule` (actions inside run
 * after the delay/at the scheduled time). Mirrors REST's block routes
 * over the same `Block_Crud_Service`; loops keep their dedicated tools.
 *
 * Create: omit block_id. Requires recipe_id + block_type; parent_id
 * defaults to the recipe root; path_code places the block inside a
 * specific branch of a parent block. Update: include block_id — only
 * fields are updated.
 */
class Save_Block_Tool extends Abstract_MCP_Tool {

	/**
	 * Block CRUD service dependency.
	 *
	 * @var Block_Crud_Service
	 */
	private Block_Crud_Service $block_service;

	/**
	 * Constructor.
	 *
	 * @param Block_Crud_Service|null $block_service Block CRUD service.
	 */
	public function __construct( ?Block_Crud_Service $block_service = null ) {
		$this->block_service = $block_service ?? Block_Crud_Service::instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'save_block';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Create or update a block. Omit block_id to create, include block_id to update (fields only). '
			. 'Use block_type=filter with save_filter_condition to make the actions inside it conditional; '
			. 'use block_type=delay_schedule (the Queue block in the builder) to delay or schedule the actions inside it '
			. '(fields: DELAY_MODE delay|schedule, DELAY_VALUE, DELAY_UNIT, SCHEDULE_DATE, SCHEDULE_TIME). '
			. 'Delay blocks require complete, valid timing on both create and update. '
			. 'Move actions into the block by saving them with parent_id set to the block ID. '
			. 'To delay or add conditions to a single action in one call, prefer the delay/conditions inputs on save_action.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'idempotentHint'  => false,
			'openWorldHint'   => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'  => array(
					'type'        => 'integer',
					'description' => 'Recipe ID. Required for both create and update.',
					'minimum'     => 1,
				),
				'block_type' => array(
					'type'        => 'string',
					'description' => 'Block type. Required. filter = conditional gate around its child actions; delay_schedule = the Queue block, which runs its child actions later while the rest of the recipe runs right away.',
					'enum'        => array( 'filter', 'delay_schedule' ),
				),
				'block_id'   => array(
					'type'        => 'integer',
					'description' => 'Existing block ID to update. Omit to create a new block.',
					'minimum'     => 1,
				),
				'parent_id'  => array(
					'type'        => 'integer',
					'description' => 'Create placement: recipe ID for root level (default), or a parent block/loop ID to nest.',
					'minimum'     => 1,
				),
				'path_code'  => array(
					'type'        => 'string',
					'description' => 'Create placement inside a parent block branch (e.g. FILTER-CONDITIONS-MET). Only valid when parent_id is a block.',
				),
				'fields'     => array(
					'type'                 => 'object',
					'description'          => 'Block configuration fields keyed by option code (delay_schedule: DELAY_MODE, DELAY_VALUE, DELAY_UNIT, SCHEDULE_DATE, SCHEDULE_TIME).',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'recipe_id', 'block_type' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'block_id'   => array( 'type' => 'integer' ),
				'block_type' => array( 'type' => 'string' ),
				'recipe_id'  => array( 'type' => 'integer' ),
				'parent_id'  => array( 'type' => 'integer' ),
				'fields'     => array( 'type' => 'object' ),
			),
			'required'   => array( 'block_id', 'block_type', 'recipe_id', 'parent_id', 'fields' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$recipe_id  = (int) ( $params['recipe_id'] ?? 0 );
		$block_type = (string) ( $params['block_type'] ?? '' );
		$block_id   = isset( $params['block_id'] ) ? (int) $params['block_id'] : 0;
		$fields     = $this->parse_json_param( $params['fields'] ?? array() );

		if ( $block_id > 0 ) {
			$block = $this->block_service->get_block( $block_id );
			if ( is_wp_error( $block ) ) {
				return Json_Rpc_Response::create_error_response( sprintf( 'Block not found: %d.', $block_id ) );
			}

			$owner_recipe_id = (int) ( $block['recipe_id'] ?? 0 );
			if ( $owner_recipe_id !== $recipe_id ) {
				return Json_Rpc_Response::create_error_response(
					sprintf( 'Block %d belongs to recipe %d, not recipe %d.', $block_id, $owner_recipe_id, $recipe_id )
				);
			}
			$block_type = (string) $block['block_type'];
		}

		if ( Block_Type::DELAY_SCHEDULE === $block_type ) {
			$valid = $this->block_service->validate_delay_fields( $fields );
			if ( is_wp_error( $valid ) ) {
				return Json_Rpc_Response::create_error_response( $valid->get_error_message() );
			}
		}

		if ( $block_id > 0 ) {
			$result = $this->block_service->update_block( $block_id, array( 'fields' => $fields ) );
		} else {
			$parent_id = isset( $params['parent_id'] ) ? (int) $params['parent_id'] : $recipe_id;
			$path_code = isset( $params['path_code'] ) && '' !== $params['path_code'] ? (string) $params['path_code'] : null;
			$result    = $this->block_service->add_block( $recipe_id, $block_type, $parent_id, $fields, $path_code );
		}

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			$block_id > 0 ? 'Block updated.' : 'Block created.',
			array(
				'block_id'   => (int) $result['id'],
				'block_type' => (string) $result['block_type'],
				'recipe_id'  => (int) $result['recipe_id'],
				'parent_id'  => (int) $result['parent_id'],
				'fields'     => $this->ensure_object( $result['fields'] ),
			)
		);
	}
}
