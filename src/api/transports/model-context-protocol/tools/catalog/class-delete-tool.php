<?php
/**
 * Consolidated delete tool.
 *
 * Replaces: delete_recipe_component, remove_condition, loop_delete,
 *           loop_filter_delete, loop_filter_delete_all, delete_user_selector.
 *
 * Routes deletion by component type. Adds all_loop_filters support
 * beyond what delete_recipe_component handled.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_CRUD_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\Api\Services\User_Selector\User_Selector_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Delete Tool.
 *
 * Deletes any recipe component by type + ID.
 * component_id is string (post IDs as string for trigger/action/loop/loop_filter,
 * UUIDs for condition_group/condition_item).
 *
 * @since 7.1.0
 */
class Delete_Tool extends Abstract_MCP_Tool {

	private const SUPPORTED_TYPES = array(
		'trigger',
		'action',
		'loop',
		'loop_filter',
		'condition_group',
		'condition_item',
		'all_loop_filters',
		'user_selector',
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'delete';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Delete a component from a recipe. Supported types: '
			. 'trigger, action, loop (also deletes filters; actions reparented to recipe), '
			. 'loop_filter, condition_group (actions become unconditional), '
			. 'condition_item (requires group_id), all_loop_filters (deletes all filters from a loop), '
			. 'user_selector (clears user selector config). '
			. 'Use get_recipe to find component IDs.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'idempotentHint'  => true,
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
				'type'         => array(
					'type'        => 'string',
					'description' => 'Component type to delete.',
					'enum'        => self::SUPPORTED_TYPES,
				),
				'component_id' => array(
					'type'        => 'string',
					'description' => 'Component identifier. Required for all types except user_selector. Post ID (as string) for trigger/action/loop/loop_filter. UUID for condition_group/condition_item. Loop ID for all_loop_filters.',
				),
				'recipe_id'    => array(
					'type'        => 'integer',
					'description' => 'Parent recipe ID.',
					'minimum'     => 1,
				),
				'group_id'     => array(
					'type'        => 'string',
					'description' => 'Required when type=condition_item. The condition group UUID containing the condition.',
					'minLength'   => 1,
				),
				'confirm'      => array(
					'type'        => 'boolean',
					'description' => 'Required for destructive operations (all_loop_filters, loop). Must be true.',
					'default'     => false,
				),
			),
			'required'   => array( 'type', 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array( 'type' => 'integer' ),
				'loop_id'   => array( 'type' => 'integer' ),
				'group_id'  => array( 'type' => 'string' ),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$type         = $params['type'] ?? '';
		$component_id = $params['component_id'] ?? '';
		$recipe_id    = (int) ( $params['recipe_id'] ?? 0 );
		$group_id     = $params['group_id'] ?? '';
		$confirm      = ! empty( $params['confirm'] );

		if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Invalid type "%s". Must be one of: %s', $type, implode( ', ', self::SUPPORTED_TYPES ) )
			);
		}

		// Validate recipe exists.
		$recipe_post = get_post( $recipe_id );
		if ( ! $recipe_post || AUTOMATOR_POST_TYPE_RECIPE !== $recipe_post->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe not found with ID %d. Use list_recipes to find valid recipe IDs.', $recipe_id )
			);
		}

		// Validate group_id for condition_item.
		if ( 'condition_item' === $type && empty( $group_id ) ) {
			return Json_Rpc_Response::create_error_response( 'group_id is required when type=condition_item.' );
		}

		// component_id required for all types except user_selector.
		if ( 'user_selector' !== $type && empty( $component_id ) ) {
			return Json_Rpc_Response::create_error_response( 'component_id is required for type=' . $type . '.' );
		}

		// Validate confirm for destructive operations.
		if ( in_array( $type, array( 'all_loop_filters', 'loop', 'user_selector' ), true ) && ! $confirm ) {
			return Json_Rpc_Response::create_error_response( 'confirm=true is required for this destructive operation.' );
		}

		switch ( $type ) {
			case 'trigger':
				return $this->delete_trigger( (int) $component_id, $recipe_id );
			case 'action':
				return $this->delete_action( (int) $component_id, $recipe_id );
			case 'loop':
				return $this->delete_loop( (int) $component_id, $recipe_id );
			case 'loop_filter':
				return $this->delete_loop_filter( (int) $component_id, $recipe_id );
			case 'all_loop_filters':
				return $this->delete_all_loop_filters( (int) $component_id, $recipe_id );
			case 'condition_group':
				return $this->delete_condition_group( $component_id, $recipe_id );
			case 'condition_item':
				return $this->delete_condition_item( $component_id, $group_id, $recipe_id );
			case 'user_selector':
				return $this->delete_user_selector( $recipe_id );
			default:
				return Json_Rpc_Response::create_error_response( 'Unsupported component type.' );
		}
	}
	/**
	 * Delete trigger.
	 *
	 * @param int $trigger_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_trigger( int $trigger_id, int $recipe_id ): array {
		$ownership = $this->verify_component_ownership( $trigger_id, $recipe_id );
		if ( null !== $ownership ) {
			return $ownership;
		}
		$result = Trigger_CRUD_Service::instance()->remove_from_recipe( $recipe_id, $trigger_id );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		$demoted = Recipe_CRUD_Service::instance()->demote_if_empty( $recipe_id );
		$msg     = $demoted ? 'Trigger deleted. Recipe demoted to draft.' : 'Trigger deleted';
		return Json_Rpc_Response::create_success_response( $msg, array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete action.
	 *
	 * @param int $action_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_action( int $action_id, int $recipe_id ): array {
		$ownership = $this->verify_component_ownership( $action_id, $recipe_id );
		if ( null !== $ownership ) {
			return $ownership;
		}
		$result = Action_CRUD_Service::instance()->delete_action( $action_id, true );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		Recipe_Condition_Service::instance()->cleanup_groups_for_deleted_action( $action_id, $recipe_id );
		$demoted = Recipe_CRUD_Service::instance()->demote_if_empty( $recipe_id );
		$msg     = $demoted ? 'Action deleted. Recipe demoted to draft.' : 'Action deleted';
		return Json_Rpc_Response::create_success_response( $msg, array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete loop.
	 *
	 * @param int $loop_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_loop( int $loop_id, int $recipe_id ): array {
		$ownership = $this->verify_component_ownership( $loop_id, $recipe_id );
		if ( null !== $ownership ) {
			return $ownership;
		}
		$service = Loop_CRUD_Service::instance();
		$result  = $service->delete_loop( $loop_id, true );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response( 'Loop deleted. Actions reparented to recipe.', array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete loop filter.
	 *
	 * @param int $filter_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_loop_filter( int $filter_id, int $recipe_id ): array {
		$ownership = $this->verify_component_ownership( $filter_id, $recipe_id );
		if ( null !== $ownership ) {
			return $ownership;
		}
		$service = Filter_CRUD_Service::instance();
		$result  = $service->delete_filter( $filter_id, true );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response( 'Loop filter deleted', array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete all loop filters.
	 *
	 * @param int $loop_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_all_loop_filters( int $loop_id, int $recipe_id ): array {
		$service = Filter_CRUD_Service::instance();
		$result  = $service->delete_loop_filters( $loop_id, true );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response(
			'All loop filters deleted',
			array(
				'recipe_id' => $recipe_id,
				'loop_id' => $loop_id,
			)
		);
	}
	/**
	 * Delete condition group.
	 *
	 * @param string $group_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_condition_group( string $group_id, int $recipe_id ): array {
		$service = Recipe_Condition_Service::instance();
		$result  = $service->remove_condition_group( $recipe_id, $group_id );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response( 'Condition group deleted. Linked actions are now unconditional.', array( 'recipe_id' => $recipe_id ) );
	}
	/**
	 * Delete condition item.
	 *
	 * @param string $condition_id The ID.
	 * @param string $group_id The ID.
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_condition_item( string $condition_id, string $group_id, int $recipe_id ): array {
		$service = Recipe_Condition_Service::instance();
		$result  = $service->remove_condition_from_group( $condition_id, $group_id, $recipe_id );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response(
			'Condition removed from group',
			array(
				'recipe_id' => $recipe_id,
				'group_id' => $group_id,
			)
		);
	}
	/**
	 * Delete user selector.
	 *
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function delete_user_selector( int $recipe_id ): array {
		$service = User_Selector_Service::instance();
		$result  = $service->delete_user_selector( $recipe_id );
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}
		return Json_Rpc_Response::create_success_response( 'User selector cleared', array( 'recipe_id' => $recipe_id ) );
	}

	/**
	 * Verify a component (post) belongs to the specified recipe.
	 *
	 * Walks up the post_parent chain to confirm the component is a
	 * direct or indirect child of the recipe. Prevents cross-recipe
	 * modifications.
	 *
	 * @since 7.1.0
	 *
	 * @param int $component_id Component post ID.
	 * @param int $recipe_id    Expected parent recipe ID.
	 * @return array|null Error response if ownership fails, null if OK.
	 */
	private function verify_component_ownership( int $component_id, int $recipe_id ) {
		$post = get_post( $component_id );
		if ( ! $post ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Component %d not found.', $component_id )
			);
		}

		// Walk up parent chain (action -> loop -> recipe, or action -> recipe).
		$parent_id = (int) $post->post_parent;
		$parent    = $parent_id > 0 ? get_post( $parent_id ) : null;

		// If parent is a loop or filter container, go one more level up.
		if ( $parent && ! in_array( $parent->post_type, array( AUTOMATOR_POST_TYPE_RECIPE ), true ) ) {
			$parent_id = (int) $parent->post_parent;
		}

		if ( $parent_id !== $recipe_id ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Component %d does not belong to recipe %d.', $component_id, $recipe_id )
			);
		}

		return null;
	}
}
